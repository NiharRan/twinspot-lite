<?php
/**
 * Dev only. Seeds a local WooCommerce site with products, "new customers only" coupons
 * and orders that form rings, then checks the coupon guard. Not shipped (see .distignore).
 *
 * Run from the WordPress root:  wp eval-file wp-content/plugins/twinspot-lite/scripts/seed-test-data.php
 * Re-running replaces the earlier seed (orders carry meta _twinspot_seed).
 */

defined( 'ABSPATH' ) || exit;

foreach ( wc_get_orders( array( 'limit' => -1, 'meta_key' => '_twinspot_seed', 'meta_value' => 1, 'status' => array_keys( wc_get_order_statuses() ) ) ) as $old ) {
	$old->delete( true );
}

$products = array();
foreach ( array( 'Seed Tee' => 20, 'Seed Mug' => 12, 'Seed Cap' => 15 ) as $name => $price ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_regular_price( (string) $price );
	$p->set_status( 'publish' );
	$p->save();
	$products[] = $p;
}

foreach ( array( 'WELCOME15' => array( 'percent', 15, 'yes' ), 'WELCOME10' => array( 'fixed_cart', 10, 'yes' ), 'SAVE5' => array( 'fixed_cart', 5, 'no' ) ) as $code => list( $type, $amount, $first ) ) {
	$id = wc_get_coupon_id_by_code( $code );
	$c  = new WC_Coupon( $id );
	$c->set_code( $code );
	$c->set_discount_type( $type );
	$c->set_amount( $amount );
	$c->update_meta_data( '_twinspot_lite_first_order_only', $first );
	$c->save();
}

// [email, phone, line 1, postcode, status, coupon]
$orders = array(
	// Ring 1: chain through Gmail alias, then phone, then address.
	array( 'alice.smith@gmail.com', '555-111-2222', '12 Oak Street', '90210', 'completed', '' ),
	array( 'alicesmith+promo@gmail.com', '555-999-0001', '99 Elm Road', '10001', 'processing', '' ),
	array( 'alice.new@example.com', '(555) 111-2222', '5 Pine Ave', '60601', 'processing', '' ),
	array( 'third.person@example.com', '555-777-0000', '5 pine avenue', '60601', 'processing', 'WELCOME15' ),
	// Ring 2: shared address only.
	array( 'bob@example.com', '555-222-3333', '7 River Lane', '30301', 'completed', '' ),
	array( 'robert@example.org', '555-222-4444', '7 River Lane', '30301', 'processing', '' ),
	// Clean customer.
	array( 'carol@example.com', '555-333-4444', '1 Hill Court', '80202', 'completed', '' ),
	// Cancelled order must not link anything.
	array( 'dave@example.com', '555-444-5555', '3 Lake Drive', '98101', 'completed', '' ),
	array( 'dave.again@example.com', '555-444-5555', '8 Bay Street', '94016', 'cancelled', '' ),
);

foreach ( $orders as $i => list( $email, $phone, $line1, $post, $status, $coupon ) ) {
	$o = wc_create_order();
	$o->add_product( $products[ $i % 3 ], 1 );
	$o->set_billing_email( $email );
	$o->set_billing_phone( $phone );
	$o->set_billing_address_1( $line1 );
	$o->set_billing_postcode( $post );
	$o->set_billing_first_name( 'Seed' );
	$o->set_billing_last_name( 'Customer ' . ( $i + 1 ) );
	$o->set_customer_ip_address( '203.0.113.' . ( $i + 1 ) );
	if ( $coupon ) {
		$o->apply_coupon( $coupon );
	}
	$o->calculate_totals();
	$o->update_meta_data( '_twinspot_seed', 1 );
	$o->save();
	$o->update_status( $status );
}
WP_CLI::success( count( $orders ) . ' orders, 3 products, 3 coupons seeded.' );

// Coupon guard checks: apply each coupon as a given shopper in a fresh cart.
wc_load_cart();
$check = function ( $label, $email, $phone, $line1, $post, $code, $expect_ok ) use ( $products ) {
	WC()->cart->empty_cart();
	WC()->cart->remove_coupons();
	WC()->customer->set_billing_email( $email );
	WC()->customer->set_billing_phone( $phone );
	WC()->customer->set_billing_address_1( $line1 );
	WC()->customer->set_billing_postcode( $post );
	WC()->cart->add_to_cart( $products[0]->get_id() );
	wc_clear_notices();
	$ok = WC()->cart->apply_coupon( $code );
	wc_clear_notices();
	WP_CLI::line( sprintf( '%s %s: %s', ( $ok === $expect_ok ) ? 'PASS' : 'FAIL', $label, $ok ? 'allowed' : 'refused' ) );
};
$check( 'brand new shopper, WELCOME15', 'new.person@example.com', '555-000-1111', '1 New Road', '11111', 'WELCOME15', true );
$check( 'Gmail dot alias of Alice', 'a.l.i.c.e.smith@gmail.com', '555-000-2222', '2 New Road', '11112', 'WELCOME15', false );
$check( 'same phone as Alice, other email', 'x@example.com', '+1 555 111 2222', '3 New Road', '11113', 'WELCOME10', false );
$check( 'same address as Bob', 'y@example.com', '555-000-3333', '7 river lane', '30301', 'WELCOME15', false );
$check( 'Alice, coupon without the rule', 'alice.smith@gmail.com', '555-111-2222', '12 Oak Street', '90210', 'SAVE5', true );
$check( 'matches only the cancelled order', 'z@example.com', '555-444-5555', '4 Other St', '22222', 'WELCOME15', false ); // Dave's completed order shares this phone, so refused.
$check( 'missing details fail open', '', '', '', '', 'WELCOME15', true );
