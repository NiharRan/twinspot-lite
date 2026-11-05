<?php
/**
 * Records order hashes at checkout, notes matches on the order, forgets dead orders.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Features;

use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Fingerprinter;
use Twinspot_Lite\Module;

defined( 'ABSPATH' ) || exit;

class OrderTracker implements Module {
	/** @var Edges */
	private $edges;
	/** @var Fingerprinter */
	private $fingerprinter;

	public function __construct( Edges $edges, Fingerprinter $fingerprinter ) {
		$this->edges         = $edges;
		$this->fingerprinter = $fingerprinter;
	}

	public function register(): void {
		add_action( 'woocommerce_checkout_order_created', array( $this, 'record' ) );             // Classic checkout.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'record' ) );  // Block checkout.
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

	public function record( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
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
					'#' . implode( ', #', $m )
				)
			);
			$order->update_meta_data( '_twinspot_lite_noted', 1 );
			$order->save();
		}
	}

	public function forget( $order_id ): void {
		$this->edges->delete( (int) $order_id );
	}
}
