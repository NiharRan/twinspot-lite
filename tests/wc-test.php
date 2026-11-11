<?php
/**
 * Dev only. Integration test against a real WooCommerce site. Not shipped (see .distignore).
 *
 * Run from the WordPress root:  wp eval-file wp-content/plugins/twinspot-lite/tests/wc-test.php
 * Creates products, coupons and orders (orders tagged _twinspot_test) and deletes them at the end. Existing orders, rules and the two shield settings are left as they were. Still best run on a dev site.
 * Exit code is 1 if any check fails.
 */

defined( 'ABSPATH' ) || exit;

use Twinspot_Lite\Data\BlockLog;
use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Fingerprinter;

$GLOBALS['ts_fail'] = 0;

function ts_check( $ok, $label ) {
	echo ( $ok ? "  ok   " : "  FAIL " ) . $label . "\n";
	if ( ! $ok ) {
		++$GLOBALS['ts_fail'];
	}
}

function ts_order( $email, $phone, $line1, $postcode, $coupon = '', $status = 'pending' ) {
	global $ts_product;
	$o = wc_create_order();
	$o->add_product( $ts_product, 1 );
	$o->set_billing_email( $email );
	$o->set_billing_phone( $phone );
	$o->set_billing_address_1( $line1 );
	$o->set_billing_postcode( $postcode );
	$o->update_meta_data( '_twinspot_test', '1' );
	if ( $coupon ) {
		$o->apply_coupon( $coupon );
	}
	$o->calculate_totals();
	$o->save();
	do_action( 'woocommerce_checkout_order_created', $o ); // What classic checkout fires.
	$o = wc_get_order( $o->get_id() );
	if ( 'pending' !== $status ) {
		$o->set_status( $status );
		$o->save();
	}
	return wc_get_order( $o->get_id() );
}

/** Apply a coupon in a fresh cart as the given shopper. Returns array( bool applied, string notices ). */
function ts_apply( $code, $email, $phone = '', $line1 = '', $postcode = '' ) {
	global $ts_product;
	wc_load_cart();
	WC()->cart->empty_cart();
	WC()->cart->remove_coupons();
	wc_clear_notices();
	WC()->customer->set_billing_email( $email );
	WC()->customer->set_billing_phone( $phone );
	WC()->customer->set_billing_address_1( $line1 );
	WC()->customer->set_billing_postcode( $postcode );
	WC()->cart->add_to_cart( $ts_product->get_id() );
	$ok = WC()->cart->apply_coupon( $code );
	$n  = wp_strip_all_tags( implode( ' ', array_column( wc_get_notices( 'error' ), 'notice' ) ) );
	wc_clear_notices();
	return array( $ok, $n );
}

/** Delete only what this script created. Deleting an order also drops its index row (woocommerce_delete_order), so real data is untouched. */
function ts_clean() {
	foreach ( wc_get_orders( array( 'limit' => -1, 'status' => array_keys( wc_get_order_statuses() ), 'meta_key' => '_twinspot_test', 'meta_value' => '1' ) ) as $o ) { // phpcs:ignore WordPress.DB.SlowDBQuery -- dev test.
		$o->delete( true );
	}
	update_option( 'twinspot_lite_enabled', 'yes' );
	update_option( 'twinspot_lite_hold_orders', 'no' );
}

// ---------------------------------------------------------------- setup
echo "Setup\n";
wp_set_current_user( 0 );
ts_check( class_exists( 'WooCommerce' ) && class_exists( 'Twinspot_Lite\Plugin' ), 'WooCommerce and Twinspot Lite are active' );
global $wpdb;
$edges = new Edges();
ts_check( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $edges->table() ) ) === $edges->table(), 'edges table exists' );
ts_check( (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'twinspot_lite_events' ) ), 'events table exists' );

$ts_saved = array( get_option( 'twinspot_lite_enabled' ), get_option( 'twinspot_lite_hold_orders' ) ); // Restored at the end.
ts_clean();
$GLOBALS['ts_product'] = $ts_product = new WC_Product_Simple();
$ts_product->set_name( 'TS Test Product' );
$ts_product->set_regular_price( '50' );
$ts_product->set_status( 'publish' );
$ts_product->save();

foreach ( array( 'TSFIRST' => 'yes', 'TSOPEN' => 'no' ) as $code => $first ) {
	$c = new WC_Coupon( wc_get_coupon_id_by_code( $code ) );
	$c->set_code( $code );
	$c->set_discount_type( 'fixed_cart' );
	$c->set_amount( 10 );
	$c->update_meta_data( '_twinspot_lite_first_order_only', $first );
	$c->save();
}

// ---------------------------------------------------------------- tracking
echo "Tracking and rings\n";
$a = ts_order( 'jane.doe@gmail.com', '555-100-2000', '1 Main St', '10001' );
ts_check( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $edges->table() . ' WHERE order_id = %d', $a->get_id() ) ), 'order indexed on checkout hook' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$b = ts_order( 'janedoe+promo@gmail.com', '', '', '' );
$notes = wc_get_order_notes( array( 'order_id' => $b->get_id() ) );
ts_check( (bool) array_filter( $notes, function ( $n ) use ( $a ) { return false !== strpos( $n->content, '#' . $a->get_id() ); } ), 'gmail dot and +tag alias links to earlier order (note added)' );
$lone = ts_order( 'someone.else@example.com', '555-999-0000', '9 Other Rd', '99999' );
ts_check( ! array_filter( wc_get_order_notes( array( 'order_id' => $lone->get_id() ) ), function ( $n ) { return false !== strpos( $n->content, 'Twinspot' ); } ), 'unrelated shopper gets no ring note' );

// ---------------------------------------------------------------- block mode
echo "Block mode\n";
list( $ok, $msg ) = ts_apply( 'TSFIRST', 'jane.doe+x@gmail.com' );
ts_check( ! $ok && false !== stripos( $msg, 'first-time' ), 'repeat shopper (alias) refused on restricted coupon' );
list( $ok ) = ts_apply( 'TSFIRST', 'brand.new@example.com', '555-000-1111', '5 New Ave', '12345' );
ts_check( $ok, 'new shopper accepted' );
list( $ok ) = ts_apply( 'TSOPEN', 'jane.doe@gmail.com' );
ts_check( $ok, 'unrestricted coupon accepted for repeat shopper' );
list( $ok ) = ts_apply( 'TSFIRST', '' );
ts_check( $ok, 'fails open with no billing details' );
list( $ok ) = ts_apply( 'TSFIRST', 'x@example.com', '555-100-2000' ); // Same phone as first order.
ts_check( ! $ok, 'phone match refused' );
update_option( 'twinspot_lite_enabled', 'no' );
list( $ok ) = ts_apply( 'TSFIRST', 'jane.doe@gmail.com' );
ts_check( $ok, 'shield off: repeat shopper accepted' );
update_option( 'twinspot_lite_enabled', 'yes' );

// Whitelist.
$fp = new Fingerprinter();
$h  = $fp->hashes( 'jane.doe@gmail.com', '', '', '' );
( new BlockLog() )->set_ring_allowed( 1, $h, true );
list( $ok ) = ts_apply( 'TSFIRST', 'jane.doe@gmail.com' );
ts_check( $ok, 'allowed ring skips the guard' );
( new BlockLog() )->set_ring_allowed( 1, $h, false );

// ---------------------------------------------------------------- hold mode
echo "Hold mode\n";
update_option( 'twinspot_lite_enabled', 'no' );
update_option( 'twinspot_lite_hold_orders', 'yes' );
list( $ok ) = ts_apply( 'TSFIRST', 'jane.doe@gmail.com' );
ts_check( $ok, 'hold mode does not refuse the coupon' );

$h1 = ts_order( 'jane.doe@gmail.com', '', '', '', 'TSFIRST' );
ts_check( 'on-hold' === $h1->get_status() && $h1->get_meta( '_twinspot_lite_held' ), 'repeat shopper order lands on-hold and flagged' );
$h1->payment_complete();
$h1 = wc_get_order( $h1->get_id() );
ts_check( 'on-hold' === $h1->get_status(), 'payment_complete() keeps it on-hold' );
$h1->update_status( 'processing' );
$h1 = wc_get_order( $h1->get_id() );
ts_check( 'on-hold' === $h1->get_status(), 'direct update_status(processing) with no user is reverted' );
add_filter( 'twinspot_lite_keep_held', '__return_false' );
$h1->update_status( 'processing' );
ts_check( 'processing' === wc_get_order( $h1->get_id() )->get_status(), 'twinspot_lite_keep_held filter lets it through' );
remove_filter( 'twinspot_lite_keep_held', '__return_false' );

$h2 = ts_order( 'janedoe@gmail.com', '', '', '', 'TSFIRST' );
ts_check( 'on-hold' === $h2->get_status(), 'second held order' );
$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
wp_set_current_user( $admin->ID );
$h2->update_status( 'processing' );
$h2 = wc_get_order( $h2->get_id() );
ts_check( 'processing' === $h2->get_status() && ! $h2->get_meta( '_twinspot_lite_held' ), 'admin release works and clears the flag' );
wp_set_current_user( 0 );

$ok1 = ts_order( 'fresh.person@example.com', '555-777-8888', '3 Fresh Ln', '33333', 'TSFIRST' );
ts_check( 'on-hold' !== $ok1->get_status(), 'new shopper is not held' );
$ok2 = ts_order( 'jane.doe@gmail.com', '', '', '', 'TSOPEN' );
ts_check( 'on-hold' !== $ok2->get_status(), 'repeat shopper with unrestricted coupon is not held' );
add_filter( 'twinspot_lite_hold_order', '__return_false' );
$ok3 = ts_order( 'jane.doe@gmail.com', '', '', '', 'TSFIRST' );
remove_filter( 'twinspot_lite_hold_order', '__return_false' );
ts_check( 'on-hold' !== $ok3->get_status(), 'twinspot_lite_hold_order filter skips the hold' );

// ---------------------------------------------------------------- cleanup of index
echo "Index upkeep\n";
$c1 = ts_order( 'cancel.me@example.com', '555-123-0000', '8 Gone St', '88888' );
$cnt = function ( $id ) use ( $wpdb, $edges ) {
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $edges->table() . ' WHERE order_id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};
$c1->update_status( 'cancelled' );
ts_check( 0 === $cnt( $c1->get_id() ), 'cancelled order removed from index' );
$c1 = wc_get_order( $c1->get_id() );
$c1->update_status( 'processing' );
ts_check( 1 === $cnt( $c1->get_id() ), 'order re-indexed when it reaches processing' );
$id = $c1->get_id();
$c1->delete( true );
ts_check( 0 === $cnt( $id ), 'deleted order removed from index' );

// ---------------------------------------------------------------- api facade
echo "Api facade\n";
ts_clean();
$ra = ts_order( 'ra@example.com', '555-300-4000', '', '' );
$rb = ts_order( 'rb@example.net', '555-300-4000', '7 Ring Rd', '30003' );
$rc = ts_order( 'rc@example.org', '555-300-9999', '7 Ring Rd', '30003' );
$rd = ts_order( 'rd@example.com', '555-777-8888', '88 Lone Ave', '88888' );
$want = array( $ra->get_id(), $rb->get_id(), $rc->get_id() );
sort( $want );
$got = \Twinspot_Lite\Api::ring_orders_for_order( $ra->get_id() );
sort( $got );
ts_check( $want === $got, 'Api: ring_orders_for_order returns the multi-hop ring' );
$got = \Twinspot_Lite\Api::ring_orders_for_details( 'rc@example.org', '555-300-9999', '7 Ring Rd', '30003' );
sort( $got );
ts_check( $want === $got, 'Api: ring_orders_for_details matches the same ring' );
ts_check( array() === \Twinspot_Lite\Api::ring_orders_for_details( 'nobody@example.com', '555-000-1111', '1 Nowhere Ln', '00001' ), 'Api: lone order has no ring (details)' );
ts_check( array() === \Twinspot_Lite\Api::ring_orders_for_order( $rd->get_id() ), 'Api: lone order has no ring (order)' );
ts_check( array() === \Twinspot_Lite\Api::ring_orders_for_details( '', '', '', '' ), 'Api: empty details match nothing' );
ts_check( 2 === count( \Twinspot_Lite\Api::ring_orders_for_order( $ra->get_id(), 2 ) ), 'Api: limit caps the ring' );
ts_check( array() === \Twinspot_Lite\Api::ring_orders_for_order( 1999999999 ), 'Api: unknown order has no ring' );
ts_check( ! \Twinspot_Lite\Api::is_ring_allowed_for_order( $ra->get_id() ) && ! \Twinspot_Lite\Api::is_ring_allowed_for_details( 'rc@example.org', '555-300-9999', '7 Ring Rd', '30003' ), 'Api: ring not allowed by default' );
$rh = array();
foreach ( $want as $rid ) { // Like the Rings screen: store every hash of the ring.
	$rh = array_merge( $rh, array( $edges->row( $rid )['email_h'], $edges->row( $rid )['phone_h'], $edges->row( $rid )['addr_h'] ) );
}
( new BlockLog() )->set_ring_allowed( 1, array_values( array_filter( $rh ) ), true );
ts_check( \Twinspot_Lite\Api::is_ring_allowed_for_order( $ra->get_id() ) && \Twinspot_Lite\Api::is_ring_allowed_for_details( 'rc@example.org', '555-300-9999', '7 Ring Rd', '30003' ), 'Api: allowed ring reported for order and details' );
( new BlockLog() )->set_ring_allowed( 1, array_values( array_filter( $rh ) ), false );
\Twinspot_Lite\Api::hold_order( $rd, 'Test hold' );
$rd = wc_get_order( $rd->get_id() );
ts_check( 'on-hold' === $rd->get_status() && '1' === (string) $rd->get_meta( '_twinspot_lite_held' ), 'Api: hold_order puts the order on hold with the meta' );
ts_clean();

// ---------------------------------------------------------------- privacy and REST
echo "Privacy and REST\n";
$ex = apply_filters( 'wp_privacy_personal_data_exporters', array() );
$er = apply_filters( 'wp_privacy_personal_data_erasers', array() );
ts_check( isset( $ex['twinspot-lite'] ) || (bool) array_filter( array_keys( $ex ), function ( $k ) { return 0 === strpos( $k, 'twinspot' ); } ), 'privacy exporter registered' );
ts_check( (bool) array_filter( array_keys( $er ), function ( $k ) { return 0 === strpos( $k, 'twinspot' ); } ), 'privacy eraser registered' );
wp_set_current_user( $admin->ID );
foreach ( array( 'overview', 'activity' ) as $route ) {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/twinspot-lite/v1/' . $route ) );
	ts_check( 200 === $res->get_status(), "REST /$route returns 200 for admin" );
}
wp_set_current_user( 0 );
$res = rest_do_request( new WP_REST_Request( 'GET', '/twinspot-lite/v1/overview' ) );
ts_check( in_array( $res->get_status(), array( 401, 403 ), true ), 'REST refuses anonymous users' );

// ---------------------------------------------------------------- done
ts_clean();
update_option( 'twinspot_lite_enabled', $ts_saved[0] ? $ts_saved[0] : 'yes' );
update_option( 'twinspot_lite_hold_orders', $ts_saved[1] ? $ts_saved[1] : 'no' );
$ts_product->delete( true );
foreach ( array( 'TSFIRST', 'TSOPEN' ) as $code ) {
	wp_delete_post( wc_get_coupon_id_by_code( $code ), true );
}
echo "\n" . ( $GLOBALS['ts_fail'] ? $GLOBALS['ts_fail'] . " FAILED\n" : "All passed\n" );
exit( $GLOBALS['ts_fail'] ? 1 : 0 );
