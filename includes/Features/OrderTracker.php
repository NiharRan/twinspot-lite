<?php
/**
 * Records order hashes at checkout, notes matches on the order, forgets dead orders.
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

class OrderTracker implements Module {
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
		add_action( 'woocommerce_checkout_order_created', array( $this, 'created' ) );             // Classic checkout.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'created' ) );  // Block checkout.
		// Paid gateways call payment_complete(), which would otherwise move a held order to processing.
		add_filter(
			'woocommerce_payment_complete_order_status',
			function ( $status, $order_id ) {
				$order = wc_get_order( $order_id );
				return $order && $order->get_meta( '_twinspot_lite_held' ) ? 'on-hold' : $status;
			},
			10,
			2
		);
		// A shop manager leaving on-hold releases the order; gateway or webhook changes (no logged-in user) keep the flag, so a late payment_complete still holds it.
		add_action(
			'woocommerce_order_status_changed',
			function ( $order_id, $from, $to ) {
				$order = 'on-hold' === $from && 'on-hold' !== $to && current_user_can( 'edit_shop_orders' ) ? wc_get_order( $order_id ) : null;
				if ( $order && $order->get_meta( '_twinspot_lite_held' ) ) {
					$order->delete_meta_data( '_twinspot_lite_held' );
					$order->save();
				}
			},
			10,
			3
		);
		// A failed or cancelled order can later succeed on retry, so index it again once it reaches an active status.
		foreach ( array( 'processing', 'completed', 'on-hold' ) as $status ) {
			add_action(
				'woocommerce_order_status_' . $status,
				function ( $order_id ) {
					$this->record( wc_get_order( $order_id ) );
				}
			);
		}
		foreach ( array( 'woocommerce_order_status_cancelled', 'woocommerce_order_status_failed', 'woocommerce_delete_order' ) as $hook ) {
			add_action( $hook, array( $this, 'forget' ) );
		}
	}

	/** New checkout order: index it, log each coupon decision, and hold the order if hold mode is on and a "new customers only" coupon was used by a repeat shopper. */
	public function created( $order ): void {
		$this->record( $order );
		$this->note_blocked_coupon( $order );
		if ( ! $order instanceof \WC_Order || ! $order->get_coupon_codes() ) {
			return;
		}
		$h       = $this->fingerprinter->hashes( $order->get_billing_email(), $order->get_billing_phone(), $order->get_billing_address_1(), $order->get_billing_postcode() );
		$m       = $this->edges->matches( $h, $order->get_id() );
		$match   = $m ? array(
			'linked'  => $m,
			'matched' => $this->edges->match_types( $h, $m ),
		) : array();
		$allowed = $this->log->is_allowed( $h );
		$hold    = 'yes' === get_option( 'twinspot_lite_hold_orders' ) && 'no' === get_option( 'twinspot_lite_enabled' ); // With blocking on, the coupon was already refused at checkout.
		$guard   = $hold || 'no' !== get_option( 'twinspot_lite_enabled' );
		$saved   = array();
		/** @var \WC_Order_Item_Coupon $item */
		foreach ( $order->get_items( 'coupon' ) as $item ) {
			$saved[ $item->get_code() ] = (float) $item->get_discount();
		}
		$held = false;
		foreach ( $order->get_coupon_codes() as $code ) {
			$first = 'yes' === ( new \WC_Coupon( $code ) )->get_meta( CouponGuard::META );
			$base  = array(
				'coupon'   => $code,
				'order_id' => $order->get_id(),
				'amount'   => $saved[ $code ] ?? 0,
			);
			if ( $first && $hold && $m && ! $allowed && apply_filters( 'twinspot_lite_hold_order', true, $order, $m ) ) {
				$this->events->add( 'held', $base + $match );
				$held = true;
				continue;
			}
			// Why the coupon went through: not restricted, guard paused, ring allowed, nothing matched, or a match found only now (billing details were missing at coupon check).
			$why = ! $first ? 'open' : ( ! $guard ? 'paused' : ( ! $m ? 'verified' : ( $allowed ? 'allowed' : 'late' ) ) );
			$this->events->add( 'clean', $base + ( $first ? $match : array() ) + array( 'meta' => array( 'why' => $why ) ) );
		}
		if ( $held ) {
			$order->update_meta_data( '_twinspot_lite_held', '1' );
			do_action( 'twinspot_lite_order_held', $order, $m );
			$order->update_status( 'on-hold', __( 'Twinspot: held for review, a new-customers-only coupon was used by a returning shopper.', 'twinspot-lite' ) );
		}
	}

	/** Order note for a coupon the guard refused earlier in this checkout session. */
	private function note_blocked_coupon( $order ): void {
		$b = WC()->session ? WC()->session->get( 'twinspot_lite_blocked_coupon' ) : null;
		if ( ! $order instanceof \WC_Order || ! $b ) {
			return;
		}
		WC()->session->set( 'twinspot_lite_blocked_coupon', null );
		$ids = array_values( array_diff( (array) $b['ids'], array( $order->get_id() ) ) );
		$order->add_order_note(
			sprintf(
				/* translators: 1: Twinspot label, 2: linked order numbers, 3: coupon code, 4: ring link */
				__( '%1$s shares email, phone or address with order(s) %2$s. Blocked coupon “%3$s” at checkout. %4$s', 'twinspot-lite' ),
				'<strong>' . esc_html__( 'Twinspot System Alert', 'twinspot-lite' ) . '</strong> <span style="background:#ffdad6;color:#dc2626;border-radius:99px;padding:1px 8px;font-size:11px;font-weight:600">' . esc_html__( 'Coupon Blocked', 'twinspot-lite' ) . '</span><br>',
				$ids ? $this->links( $ids ) : esc_html__( '(none left)', 'twinspot-lite' ),
				esc_html( $b['code'] ),
				$this->ring_link( $order->get_id(), count( $ids ) + 1 )
			)
		);
	}

	/** @param int[] $ids */
	private function links( array $ids ): string {
		$out = array();
		foreach ( $ids as $id ) {
			$o     = wc_get_order( $id );
			$out[] = $o ? '<a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . (int) $id . '</a>' : '#' . (int) $id;
		}
		return implode( ', ', $out );
	}

	private function ring_link( int $order_id, int $orders ): string {
		$url = add_query_arg( array( 'page' => 'twinspot-lite', 'ring' => $order_id ), admin_url( 'admin.php' ) );
		$chip = 'background:#e7eeff;border-radius:6px;padding:1px 8px;font-size:11px';
		/* translators: %d: number of orders in the ring */
		$count = _n( '%d order', '%d orders', $orders, 'twinspot-lite' );
		return '<br><span style="' . $chip . '">' . esc_html__( 'Linked ring', 'twinspot-lite' ) . '</span> <span style="' . $chip . '">' . esc_html( sprintf( $count, $orders ) ) . '</span> <a href="' . esc_url( $url ) . '">' . esc_html__( 'Inspect tree →', 'twinspot-lite' ) . '</a>';
	}

	/** @param bool $log Log a "ring" event when the order links to earlier ones (off for the bulk import). */
	public function record( $order, bool $log = true ): void {
		if ( ! $order instanceof \WC_Order || ! apply_filters( 'twinspot_lite_track_order', true, $order ) ) {
			return;
		}
		$h = $this->fingerprinter->hashes( $order->get_billing_email(), $order->get_billing_phone(), $order->get_billing_address_1(), $order->get_billing_postcode(), $order->get_customer_ip_address() );
		$this->edges->save( $order->get_id(), $h );
		$m = $this->edges->matches( $h, $order->get_id() );
		if ( $m && ! $order->get_meta( '_twinspot_lite_noted' ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: list of order numbers, e.g. "#12, #15" */
					__( 'Twinspot: shares email, phone or address with order(s) %s', 'twinspot-lite' ),
					$this->links( $m )
				) . ' ' . $this->ring_link( $order->get_id(), count( $m ) + 1 )
			);
			$order->update_meta_data( '_twinspot_lite_noted', '1' );
			$order->save();
			do_action( 'twinspot_lite_ring_detected', $order, $m );
			if ( $log ) {
				$this->events->add(
					'ring',
					array(
						'order_id' => $order->get_id(),
						'linked'   => $m,
						'matched'  => $this->edges->match_types( $h, $m ),
					)
				);
			}
		}
	}

	public function forget( $order_id ): void {
		$this->edges->delete( (int) $order_id );
	}
}
