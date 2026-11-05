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
		woocommerce_wp_checkbox(
			array(
				'id'          => self::META,
				'label'       => __( 'New customers only (Twinspot)', 'twinspot-lite' ),
				'description' => __( 'Block if the shopper’s email, phone or address matches any earlier order, even under another account or Gmail alias.', 'twinspot-lite' ),
			)
		);
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
			$this->log->add( $ids, $fixed ? (float) $coupon->get_amount() : 0.0 );
			throw new \Exception( esc_html__( 'Sorry, this coupon is only for first-time customers.', 'twinspot-lite' ) );
		}
		return $valid;
	}
}
