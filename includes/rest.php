<?php
/**
 * REST API used by the React admin screen.
 *
 * @package Twinspot_Lite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Index the newest orders from the last 12 months. Returns how many were processed.
 */
function twinspot_lite_import_orders() {
	// shortcut: newest 1000 orders in one request; upgrade to batches for bigger stores.
	$orders = wc_get_orders(
		array(
			'limit'        => 1000,
			'date_created' => '>' . strtotime( '-12 months' ),
			'status'       => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-refunded' ),
		)
	);
	foreach ( $orders as $order ) {
		twinspot_lite_record( $order );
	}
	return count( $orders );
}

/**
 * Remember a blocked coupon attempt (timestamps, last 30 days only).
 */
function twinspot_lite_log_block() {
	$log   = get_option( 'twinspot_lite_blocked', array() );
	$log   = array_filter( (array) $log, fn( $t ) => $t > time() - MONTH_IN_SECONDS );
	$log[] = time();
	update_option( 'twinspot_lite_blocked', array_values( $log ), false );
}

add_action(
	'rest_api_init',
	function () {
		$permission = fn() => current_user_can( 'manage_woocommerce' );
		register_rest_route(
			'twinspot-lite/v1',
			'/overview',
			array(
				'methods'             => 'GET',
				'permission_callback' => $permission,
				'callback'            => 'twinspot_lite_rest_overview',
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 8,
						'minimum'           => 1,
						'maximum'           => 50,
						'sanitize_callback' => 'absint',
					),
					'min_size' => array(
						'type'              => 'integer',
						'default'           => 2,
						'minimum'           => 2,
						'sanitize_callback' => 'absint',
					),
					'link'     => array(
						'type'    => 'string',
						'default' => '',
						'enum'    => array( '', 'email', 'phone', 'address' ),
					),
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			'twinspot-lite/v1',
			'/import',
			array(
				'methods'             => 'POST',
				'permission_callback' => $permission,
				'callback'            => fn() => array( 'imported' => twinspot_lite_import_orders() ),
			)
		);
	}
);

function twinspot_lite_rest_overview( WP_REST_Request $req ) {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}twinspot_lite_edges", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$by_id = array();
	foreach ( $rows as $r ) {
		$by_id[ (int) $r['order_id'] ] = $r;
	}
	$all_rings = twinspot_lite_rings( $rows );

	$stats = array(
		'indexed'         => count( $rows ),
		'rings'           => count( $all_rings ),
		'orders_in_rings' => array_sum( array_map( 'count', $all_rings ) ),
		'blocked_30d'     => count( array_filter( (array) get_option( 'twinspot_lite_blocked', array() ), fn( $t ) => $t > time() - MONTH_IN_SECONDS ) ),
	);

	// Search: order number, or an email (matched through its hash).
	$search = $req['search'];
	$ids    = null;
	if ( '' !== $search ) {
		if ( ctype_digit( ltrim( $search, '#' ) ) ) {
			$ids = array( (int) ltrim( $search, '#' ) );
		} else {
			$h   = twinspot_lite_hash( twinspot_lite_norm_email( $search ), get_option( 'twinspot_lite_secret' ) );
			$ids = $h ? array_column( array_filter( $rows, fn( $r ) => $r['email_h'] === $h ), 'order_id' ) : array();
			$ids = array_map( 'intval', $ids );
		}
	}

	$list = array();
	foreach ( $all_rings as $ring ) {
		$ring_rows = array_map( fn( $id ) => $by_id[ $id ], $ring );
		$links     = twinspot_lite_ring_links( $ring_rows );
		if ( count( $ring ) < $req['min_size'] ) {
			continue;
		}
		if ( $req['link'] && ! in_array( $req['link'], $links, true ) ) {
			continue;
		}
		if ( null !== $ids && ! array_intersect( $ids, $ring ) ) {
			continue;
		}
		rsort( $ring );
		$list[] = array(
			'ids'   => $ring,
			'links' => $links,
		);
	}
	usort( $list, fn( $a, $b ) => count( $b['ids'] ) - count( $a['ids'] ) ?: $b['ids'][0] - $a['ids'][0] );

	$total    = count( $list );
	$per_page = $req['per_page'];
	$list     = array_slice( $list, ( $req['page'] - 1 ) * $per_page, $per_page );

	foreach ( $list as &$ring ) {
		$orders = array();
		$spent  = 0.0;
		$last   = null;
		foreach ( $ring['ids'] as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}
			$orders[] = array(
				'id'  => $id,
				'url' => $order->get_edit_order_url(),
			);
			$spent   += (float) $order->get_total();
			$created  = $order->get_date_created();
			if ( $created && ( ! $last || $created->getTimestamp() > $last ) ) {
				$last = $created->getTimestamp();
			}
		}
		$ring = array(
			'size'       => count( $ring['ids'] ),
			'links'      => $ring['links'],
			'orders'     => $orders,
			'total'      => html_entity_decode( wp_strip_all_tags( wc_price( $spent ) ), ENT_QUOTES, 'UTF-8' ),
			'last_order' => $last ? gmdate( 'c', $last ) : null,
		);
	}
	unset( $ring );

	return array(
		'stats'       => $stats,
		'rings'       => $list,
		'total'       => $total,
		'total_pages' => (int) ceil( $total / $per_page ),
	);
}
