<?php
/**
 * Plugin Name:       Twinspot Lite
 * Description:       Spot rings of linked accounts and block repeat welcome coupons at WooCommerce checkout. Runs on your store; no data leaves your site.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * Author:            Nihar Ranjan Das
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       twinspot-lite
 *
 * @package Twinspot_Lite
 */

defined( 'ABSPATH' ) || exit;

const TWINSPOT_LITE_VERSION = '0.1.0';

require __DIR__ . '/includes/core.php';
require __DIR__ . '/includes/privacy.php';
require __DIR__ . '/includes/rest.php';
require __DIR__ . '/includes/admin.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook(
	__FILE__,
	function () {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = $wpdb->prefix . 'twinspot_lite_edges';
		dbDelta(
			"CREATE TABLE $table (
			order_id bigint(20) unsigned NOT NULL,
			email_h varchar(64) NOT NULL DEFAULT '',
			phone_h varchar(64) NOT NULL DEFAULT '',
			addr_h varchar(64) NOT NULL DEFAULT '',
			ip_h varchar(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (order_id),
			KEY email_h (email_h), KEY phone_h (phone_h), KEY addr_h (addr_h)
		) {$wpdb->get_charset_collate()};"
		);
		if ( ! get_option( 'twinspot_lite_secret' ) ) {
			add_option( 'twinspot_lite_secret', wp_generate_password( 64, true, true ), '', false );
		}
	}
);

function twinspot_lite_hashes( $email, $phone, $line1, $postcode, $ip = '' ) {
	$s = get_option( 'twinspot_lite_secret' );
	return array(
		'email_h' => twinspot_lite_hash( twinspot_lite_norm_email( $email ), $s ),
		'phone_h' => twinspot_lite_hash( twinspot_lite_norm_phone( $phone ), $s ),
		'addr_h'  => twinspot_lite_hash( twinspot_lite_norm_addr( $line1, $postcode ), $s ),
		'ip_h'    => twinspot_lite_hash( $ip, $s ),
	);
}

/**
 * Ids of other orders sharing email, phone or address (IP is too weak to match on).
 */
function twinspot_lite_matches( array $h, $exclude = 0 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'twinspot_lite_edges';
	$where = array();
	foreach ( array( 'email_h', 'phone_h', 'addr_h' ) as $col ) {
		if ( '' !== $h[ $col ] ) {
			$where[] = $wpdb->prepare( "$col = %s", $h[ $col ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column is from a fixed list.
		}
	}
	if ( ! $where ) {
		return array();
	}
	$sql = $wpdb->prepare( "SELECT order_id FROM $table WHERE order_id <> %d AND (" . implode( ' OR ', $where ) . ') LIMIT 20', $exclude ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- parts prepared above.
	return array_map( 'intval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
}

function twinspot_lite_record( $order ) {
	global $wpdb;
	if ( ! $order instanceof WC_Order ) {
		return;
	}
	$h = twinspot_lite_hashes( $order->get_billing_email(), $order->get_billing_phone(), $order->get_billing_address_1(), $order->get_billing_postcode(), $order->get_customer_ip_address() );
	$wpdb->replace( $wpdb->prefix . 'twinspot_lite_edges', array( 'order_id' => $order->get_id() ) + $h ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$m = twinspot_lite_matches( $h, $order->get_id() );
	if ( $m && ! $order->get_meta( '_twinspot_lite_noted' ) ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s: list of order numbers, e.g. "#12, #15" */
				__( 'Twinspot: shares email, phone or address with order(s) %s', 'twinspot-lite' ),
				'#' . implode( ', #', $m )
			)
		);
		$order->update_meta_data( '_twinspot_lite_noted', 1 );
		$order->save();
	}
}
add_action( 'woocommerce_checkout_order_created', 'twinspot_lite_record' );             // Classic checkout.
add_action( 'woocommerce_store_api_checkout_order_processed', 'twinspot_lite_record' );  // Block checkout.
// A failed or cancelled order can later succeed on retry, so index it again once it reaches an active status.
foreach ( array( 'processing', 'completed', 'on-hold' ) as $twinspot_lite_status ) {
	add_action(
		'woocommerce_order_status_' . $twinspot_lite_status,
		function ( $order_id ) {
			twinspot_lite_record( wc_get_order( $order_id ) );
		}
	);
}
add_action( 'woocommerce_order_status_cancelled', 'twinspot_lite_forget' );
add_action( 'woocommerce_order_status_failed', 'twinspot_lite_forget' );
add_action( 'woocommerce_delete_order', 'twinspot_lite_forget' );

function twinspot_lite_forget( $order_id ) {
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'twinspot_lite_edges', array( 'order_id' => $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// Coupon setting: "New customers only (Twinspot)".
add_action(
	'woocommerce_coupon_options_usage_restriction',
	function () {
		woocommerce_wp_checkbox(
			array(
				'id'          => '_twinspot_lite_first_order_only',
				'label'       => __( 'New customers only (Twinspot)', 'twinspot-lite' ),
				'description' => __( 'Block if the shopper’s email, phone or address matches any earlier order, even under another account or Gmail alias.', 'twinspot-lite' ),
			)
		);
	}
);
add_action(
	'woocommerce_coupon_options_save',
	function ( $post_id, $coupon ) {
		$coupon->update_meta_data( '_twinspot_lite_first_order_only', isset( $_POST['_twinspot_lite_first_order_only'] ) ? 'yes' : 'no' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the nonce before this action.
		$coupon->save();
	},
	10,
	2
);

add_filter(
	'woocommerce_coupon_is_valid',
	function ( $valid, $coupon ) {
		if ( ! $valid || 'yes' !== $coupon->get_meta( '_twinspot_lite_first_order_only' ) || ! WC()->customer ) {
			return $valid;
		}
		$c = WC()->customer;
		$h = twinspot_lite_hashes( $c->get_billing_email(), $c->get_billing_phone(), $c->get_billing_address_1(), $c->get_billing_postcode() );
		if ( twinspot_lite_matches( $h ) ) { // Empty details match nothing, so this fails open.
			twinspot_lite_log_block();
			throw new Exception( esc_html__( 'Sorry, this coupon is only for first-time customers.', 'twinspot-lite' ) );
		}
		return $valid;
	},
	20,
	2
);
