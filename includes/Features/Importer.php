<?php
/**
 * Indexes past orders. Not a hook module: used by the REST import route.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Features;

defined( 'ABSPATH' ) || exit;

class Importer {
	/** @var OrderTracker */
	private $tracker;

	public function __construct( OrderTracker $tracker ) {
		$this->tracker = $tracker;
	}

	/**
	 * Index the newest orders from the last 12 months. Returns how many were processed.
	 */
	public function run(): int {
		// shortcut: newest 1000 orders in one request; upgrade to batches for bigger stores.
		$orders = wc_get_orders(
			array(
				'limit'        => 1000,
				'date_created' => '>' . strtotime( '-12 months' ),
				'status'       => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-refunded' ),
			)
		);
		foreach ( $orders as $order ) {
			$this->tracker->record( $order );
		}
		return count( $orders );
	}
}
