<?php
/**
 * Builds the data for the admin Overview screen. No request handling here.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Rest;

use Twinspot_Lite\Core\Rings;
use Twinspot_Lite\Data\BlockLog;
use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Fingerprinter;
use Twinspot_Lite\Features\CouponGuard;

defined( 'ABSPATH' ) || exit;

class Overview {
	/** @var Edges */
	private $edges;
	/** @var Fingerprinter */
	private $fingerprinter;
	/** @var BlockLog */
	private $log;

	public function __construct( Edges $edges, Fingerprinter $fingerprinter, BlockLog $log ) {
		$this->edges         = $edges;
		$this->fingerprinter = $fingerprinter;
		$this->log           = $log;
	}

	/**
	 * @param array $args page, per_page, min_size, link, search.
	 */
	public function build( array $args ): array {
		$rows  = $this->edges->all();
		$by_id = array();
		foreach ( $rows as $r ) {
			$by_id[ (int) $r['order_id'] ] = $r;
		}
		$all_rings = Rings::find( $rows );

		$in_rings = array_merge( array(), ...$all_rings );
		$stats    = array(
			'ring_customers'  => count( array_unique( array_filter( array_map( fn( $id ) => $by_id[ $id ]['email_h'], $in_rings ) ) ) ),
			'indexed'         => count( $rows ),
			'rings'           => count( $all_rings ),
			'orders_in_rings' => array_sum( array_map( 'count', $all_rings ) ),
			'blocked_30d'     => $this->log->total(),
			'customers'       => $this->edges->customers(),
			'coupons'         => $this->coupon_count(),
			'sample'          => (bool) array_filter( array_keys( $by_id ), fn( $id ) => $id >= Edges::SAMPLE_BASE ),
			'version'         => TWINSPOT_LITE_VERSION,
		);

		// Search: order number, or an email (matched through its hash).
		$search = $args['search'];
		$ids    = null;
		if ( '' !== $search ) {
			if ( ctype_digit( ltrim( $search, '#' ) ) ) {
				$ids = array( (int) ltrim( $search, '#' ) );
			} else {
				$h   = $this->fingerprinter->email_hash( $search );
				$ids = $h ? array_map( 'intval', array_column( array_filter( $rows, fn( $r ) => $r['email_h'] === $h ), 'order_id' ) ) : array();
			}
		}

		$list = array();
		foreach ( $all_rings as $ring ) {
			$links = Rings::links( array_map( fn( $id ) => $by_id[ $id ], $ring ) );
			if ( count( $ring ) < $args['min_size'] ) {
				continue;
			}
			if ( $args['link'] && ! in_array( $args['link'], $links, true ) ) {
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
		$per_page = $args['per_page'];
		$list     = array_map( array( $this, 'describe' ), array_slice( $list, ( $args['page'] - 1 ) * $per_page, $per_page ) );

		return array(
			'stats'       => $stats,
			'rings'       => $list,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/** Coupons with the "New customers only" box ticked. */
	private function coupon_count(): int {
		return count(
			get_posts(
				array(
					'post_type'   => 'shop_coupon',
					'post_status' => 'publish',
					'fields'      => 'ids',
					'numberposts' => -1,
					'meta_key'    => CouponGuard::META, // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'  => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			)
		);
	}

	/**
	 * Adds order links, spend and last order date to one ring.
	 */
	private function describe( array $ring ): array {
		$orders = array();
		$spent  = 0.0;
		$last   = null;
		foreach ( $ring['ids'] as $id ) {
			if ( $id >= Edges::SAMPLE_BASE ) {
				$orders[] = array(
					'id'  => $id - Edges::SAMPLE_BASE,
					'url' => '',
				);
				$spent   += 49;
				$last     = $last ?: time();
				continue;
			}
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
		return array(
			'size'       => count( $ring['ids'] ),
			'sample'     => $ring['ids'][0] >= Edges::SAMPLE_BASE,
			'links'      => $ring['links'],
			'orders'     => $orders,
			'total'      => html_entity_decode( wp_strip_all_tags( wc_price( $spent ) ), ENT_QUOTES, 'UTF-8' ),
			'last_order' => $last ? gmdate( 'c', $last ) : null,
		);
	}
}
