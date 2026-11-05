<?php
/**
 * Indexes past orders. Not a hook module: used by the REST import route.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Features;

use Twinspot_Lite\Core\Rings;
use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Events;
use Twinspot_Lite\Data\Fingerprinter;

defined( 'ABSPATH' ) || exit;

class Importer {
	/** @var OrderTracker */
	private $tracker;

	/** @var Edges */
	private $edges;
	/** @var Fingerprinter */
	private $fingerprinter;
	/** @var Events */
	private $events;

	public function __construct( OrderTracker $tracker, Edges $edges, Fingerprinter $fingerprinter, Events $events ) {
		$this->tracker       = $tracker;
		$this->edges         = $edges;
		$this->fingerprinter = $fingerprinter;
		$this->events        = $events;
	}

	/**
	 * Adds three fake linked orders (never real WooCommerce orders) so a new store can see a ring.
	 */
	public function add_sample(): void {
		$rows = array(
			array( 'jane.doe@gmail.com', '555 010 1111', '12 Oak Street', 'AB1 2CD' ),
			array( 'janedoe+deal@gmail.com', '555 010 2222', '12 Oak St', 'AB1 2CD' ),
			array( 'j.shop@example.com', '555-010-1111', '12 Oak Street', 'AB1 2CD' ),
		);
		foreach ( $rows as $i => $r ) {
			$this->edges->save( Edges::SAMPLE_BASE + $i + 1, $this->fingerprinter->hashes( ...$r ) );
		}
	}

	public function clear_sample(): void {
		$this->edges->delete_samples();
	}

	/**
	 * Index one batch (100 orders) of the last 12 months, newest first. The screen calls this page by page to show progress.
	 *
	 * @return array{processed:int,total:int,pages:int}
	 */
	public function run( int $page = 1 ): array {
		$res = wc_get_orders(
			array(
				'limit'        => 100,
				'paged'        => $page,
				'paginate'     => true,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>' . strtotime( '-12 months' ),
				'status'       => array( 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-refunded' ),
			)
		);
		foreach ( $res->orders as $order ) {
			$this->tracker->record( $order, false );
		}
		if ( $res->total && $page >= $res->max_num_pages ) { // Last batch: one log entry for the whole import.
			$this->events->add(
				'import',
				array(
					'meta' => array(
						'orders' => (int) $res->total,
						'rings'  => count( Rings::find( $this->edges->all() ) ),
					),
				)
			);
		}
		return array(
			'processed' => count( $res->orders ),
			'total'     => (int) $res->total,
			'pages'     => (int) $res->max_num_pages,
		);
	}
}
