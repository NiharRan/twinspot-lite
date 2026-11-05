<?php
/**
 * Coupon setting "New customers only (Twinspot)" and its checkout check.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Features;

use Twinspot_Lite\Data\BlockLog;
use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Fingerprinter;
use Twinspot_Lite\Module;

defined( 'ABSPATH' ) || exit;

class CouponGuard implements Module {
	const META = '_twinspot_lite_first_order_only';

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

	public function register(): void {
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'render_field' ) );
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save_field' ), 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate' ), 20, 2 );
	}

	public function render_field(): void {
		global $post;
		$c  = new \WC_Coupon( $post->ID );
		$on = 'yes' === $c->get_meta( self::META );
		$chips = array( __( 'Normalized addresses', 'twinspot-lite' ), __( 'Alias detection (+tag)', 'twinspot-lite' ), __( 'Phone hashing', 'twinspot-lite' ) );
		echo '<div class="options_group"><div style="margin:12px;padding:12px 14px;border:1px solid #c3dcd8;border-radius:10px;background:#f0f7f6">';
		echo '<label style="font-weight:600"><input type="checkbox" name="' . esc_attr( self::META ) . '" value="yes" ' . checked( $on, true, false ) . ' /> ' . esc_html__( 'New customers only (Twinspot)', 'twinspot-lite' ) . '</label> ';
		echo '<span style="background:#9cf2e8;color:#00504a;border-radius:99px;padding:1px 8px;font-size:11px;font-weight:600">' . esc_html__( 'Twinspot Lite Guard', 'twinspot-lite' ) . '</span>';
		echo '<p style="margin:6px 0 8px;padding:0">' . esc_html__( 'Block if the shopper’s email, phone or address matches any earlier order, even under another account or Gmail alias.', 'twinspot-lite' ) . '</p>';
		foreach ( $chips as $chip ) {
			echo '<span style="display:inline-block;margin:0 6px 0 0;background:#e7eeff;border-radius:6px;padding:2px 8px;font-size:11px">' . esc_html( $chip ) . '</span>';
		}
		echo '</div></div>';
	}

	public function save_field( $post_id, $coupon ): void {
		$coupon->update_meta_data( self::META, isset( $_POST[ self::META ] ) ? 'yes' : 'no' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the nonce before this action.
		$coupon->save();
	}

	public function validate( $valid, $coupon ) {
		$block = 'no' !== get_option( 'twinspot_lite_enabled' );
		$hold  = 'yes' === get_option( 'twinspot_lite_hold_orders' );
		if ( ! $valid || ( ! $block && ! $hold ) || ! WC()->customer ) {
			return $valid;
		}
		$c       = WC()->customer;
		$h       = $this->fingerprinter->hashes( $c->get_billing_email(), $c->get_billing_phone(), $c->get_billing_address_1(), $c->get_billing_postcode() );
		$blocked = $this->log->is_blocked( $h ); // Admin blocked this ring from every coupon.
		// Hold mode never refuses on the "new customers only" rule (OrderTracker holds the order), but admin-blocked rings stay refused.
		if ( ! $blocked && ( ! $block || 'yes' !== $coupon->get_meta( self::META ) ) ) {
			return $valid;
		}
		$ids = $this->edges->matches( $h ); // Empty details match nothing, so this fails open.
		if ( $blocked || $ids ) {
			$fixed = in_array( $coupon->get_discount_type(), array( 'fixed_cart', 'fixed_product' ), true );
			if ( WC()->session ) {
				WC()->session->set( 'twinspot_lite_blocked_coupon', array( 'code' => $coupon->get_code(), 'ids' => array_map( 'intval', $ids ) ) ); // Read by OrderTracker to note the order.
			}
			$this->log->add( $ids, $fixed ? (float) $coupon->get_amount() : 0.0 );
			throw new \Exception( esc_html__( 'Sorry, this coupon is only for first-time customers.', 'twinspot-lite' ) );
		}
		return $valid;
	}
}
