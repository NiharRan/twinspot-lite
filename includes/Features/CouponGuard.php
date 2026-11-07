<?php
/**
 * Coupon setting "New customers only (Twinspot)" and its checkout check.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Features;

use Twinspot_Lite\Data\BlockLog;
use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Events;
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
	/** @var Events */
	private $events;

	public function __construct( Edges $edges, Fingerprinter $fingerprinter, BlockLog $log, Events $events ) {
		$this->edges         = $edges;
		$this->fingerprinter = $fingerprinter;
		$this->log           = $log;
		$this->events        = $events;
	}

	public function register(): void {
		add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'render_field' ) );
		add_action( 'woocommerce_coupon_options_save', array( $this, 'save_field' ), 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate' ), 20, 2 );
	}

	public function render_field(): void {
		global $post;
		if ( ! $post ) {
			return;
		}
		$c  = new \WC_Coupon( $post->ID );
		$on = 'yes' === $c->get_meta( self::META );
		$chips = array( __( 'Normalized addresses', 'twinspot-lite' ), __( 'Alias detection (+tag)', 'twinspot-lite' ), __( 'Phone hashing', 'twinspot-lite' ) );
		echo '<div class="options_group"><div style="overflow:hidden;margin:12px;padding:12px 14px;border:1px solid #c3dcd8;border-radius:10px;background:#f0f7f6">';
		echo '<label style="float:none;width:auto;margin:0;padding:0;font-weight:600"><input type="checkbox" style="float:none;margin:0 6px 0 0" name="' . esc_attr( self::META ) . '" value="yes" ' . checked( $on, true, false ) . ' /> ' . esc_html__( 'New customers only (Twinspot)', 'twinspot-lite' ) . '</label> ';
		echo '<span style="background:#9cf2e8;color:#00504a;border-radius:99px;padding:1px 8px;font-size:11px;font-weight:600">' . esc_html__( 'Twinspot Lite Guard', 'twinspot-lite' ) . '</span>';
		echo '<p style="float:none;margin:6px 0 8px;padding:0">' . esc_html__( 'Block if the shopper’s email, phone or address matches any earlier order, even under another account or Gmail alias.', 'twinspot-lite' ) . '</p>';
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
		if ( ! $blocked && $this->log->is_allowed( $h ) ) {
			return $valid; // Admin allowed this ring (whitelist rule). A block always wins over an allow.
		}
		// Hold mode never refuses on the "new customers only" rule (OrderTracker holds the order), but admin-blocked rings stay refused.
		if ( ! $blocked && ( ! $block || 'yes' !== $coupon->get_meta( self::META ) ) ) {
			return $valid;
		}
		$ids = $this->edges->matches( $h ); // Empty details match nothing, so this fails open.
		/**
		 * Filters whether a repeat shopper's coupon is refused. Return false to let it through.
		 *
		 * @param bool       $refuse Whether to refuse the coupon.
		 * @param int[]      $ids    Earlier order ids linked to this shopper.
		 * @param \WC_Coupon $coupon Coupon being validated.
		 */
		if ( ( $blocked || $ids ) && apply_filters( 'twinspot_lite_refuse_coupon', true, $ids, $coupon ) ) {
			$fixed = in_array( $coupon->get_discount_type(), array( 'fixed_cart', 'fixed_product' ), true );
			if ( WC()->session ) {
				WC()->session->set( 'twinspot_lite_blocked_coupon', array( 'code' => $coupon->get_code(), 'ids' => array_map( 'intval', $ids ) ) ); // Read by OrderTracker to note the order.
			}
			$amount = $fixed ? (float) $coupon->get_amount() : 0.0;
			$this->log->add( $ids, $amount );
			$this->events->add(
				'blocked',
				array(
					'coupon'  => $coupon->get_code(),
					'linked'  => $ids,
					'matched' => $this->edges->match_types( $h, $ids ),
					'amount'  => $amount,
					'meta'    => $blocked ? array( 'why' => 'ring' ) : array(),
				)
			);
			do_action( 'twinspot_lite_coupon_blocked', $coupon->get_code(), $ids );
			throw new \Exception( esc_html( apply_filters( 'twinspot_lite_refusal_message', __( 'Sorry, this coupon is only for first-time customers.', 'twinspot-lite' ), $coupon ) ) );
		}
		return $valid;
	}
}
