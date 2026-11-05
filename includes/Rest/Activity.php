<?php
/**
 * Builds the data for the Activity Log screen and its CSV export. No request handling here.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Rest;

use Twinspot_Lite\Core\Csv;
use Twinspot_Lite\Core\Rings;
use Twinspot_Lite\Data\BlockLog;
use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Events;

defined( 'ABSPATH' ) || exit;

class Activity {
	const RANGES = array(
		'24h' => DAY_IN_SECONDS,
		'7d'  => 7 * DAY_IN_SECONDS,
		'30d' => 30 * DAY_IN_SECONDS,
	);

	/** @var Events */
	private $events;
	/** @var Edges */
	private $edges;
	/** @var BlockLog */
	private $log;

	public function __construct( Events $events, Edges $edges, BlockLog $log ) {
		$this->events = $events;
		$this->edges  = $edges;
		$this->log    = $log;
	}

	/** @param array $args range, type, severity, search, page, per_page. */
	public function build( array $args ): array {
		$since = time() - self::RANGES[ $args['range'] ];
		$res   = $this->events->query( array( 'since' => $since ) + $args );
		$s     = $this->events->stats( $since );
		$n     = $s['counts'];

		$evaluated = ( $n['blocked'] ?? 0 ) + ( $n['clean'] ?? 0 ) + ( $n['held'] ?? 0 );
		$rings     = $this->ring_lookup();
		return array(
			'events'      => array_map( fn( $r ) => $this->describe( $r, $rings ), $res['rows'] ),
			'total'       => $res['total'],
			'total_pages' => (int) ceil( $res['total'] / $args['per_page'] ),
			'stats'       => array(
				'total'     => array_sum( $n ),
				'evaluated' => $evaluated,
				'coupons'   => $s['coupons'],
				'blocked'   => $n['blocked'] ?? 0,
				'saved'     => $s['blocked_amount'] > 0 ? $this->money( $s['blocked_amount'] ) : '',
				'cleared'   => $n['clean'] ?? 0,
			),
		);
	}

	/** @return array{filename:string,csv:string} Up to 5000 events matching the filters. */
	public function export( array $args ): array {
		$res = $this->build( array( 'page' => 1, 'per_page' => 5000 ) + $args );
		$csv = Csv::row( array( 'Time (UTC)', 'Event', 'Coupon', 'Order', 'Linked orders', 'Matched on', 'Ring', 'Ring orders', 'Amount' ) );
		foreach ( $res['events'] as $e ) {
			$csv .= Csv::row(
				array(
					$e['created'],
					$e['type'],
					$e['coupon'],
					$e['order'] ? '#' . $e['order']['id'] : '',
					implode( ' ', array_map( fn( $o ) => '#' . $o['id'], $e['linked'] ) ),
					implode( ' ', $e['matched'] ),
					$e['ring'] ? 'R-' . ( $e['ring']['key'] % Edges::SAMPLE_BASE ) : '',
					$e['ring'] ? $e['ring']['size'] : '',
					$e['amount'],
				)
			);
		}
		return array(
			'filename' => 'twinspot-activity-' . gmdate( 'Y-m-d' ) . '.csv',
			'csv'      => $csv,
		);
	}

	/** order id => ring (key, size, allowed, blocked), for orders that are in a ring right now. */
	private function ring_lookup(): array {
		$rows   = $this->edges->all();
		$hashes = array();
		foreach ( $rows as $r ) {
			$hashes[ (int) $r['order_id'] ] = array( $r['email_h'], $r['phone_h'], $r['addr_h'] );
		}
		$out = array();
		foreach ( Rings::find( $rows ) as $ids ) {
			$ring = array(
				'key'  => max( $ids ),
				'size' => count( $ids ),
			) + $this->log->ring_state( array_merge( ...array_map( fn( $id ) => $hashes[ $id ], $ids ) ) );
			foreach ( $ids as $id ) {
				$out[ $id ] = $ring;
			}
		}
		return $out;
	}

	private function describe( array $r, array $rings ): array {
		$linked = array_values( array_filter( array_map( 'intval', explode( ',', $r['linked'] ) ) ) );
		$order  = (int) $r['order_id'];
		$ring   = null;
		foreach ( array_merge( array( $order ), $linked ) as $id ) {
			if ( isset( $rings[ $id ] ) ) {
				$ring = $rings[ $id ];
				break;
			}
		}
		$amount = (float) $r['amount'];
		return array(
			'id'      => (int) $r['id'],
			'type'    => $r['type'],
			'created' => gmdate( 'c', (int) strtotime( $r['created'] . ' UTC' ) ),
			'coupon'  => $r['coupon'],
			'order'   => $order ? $this->order_ref( $order ) : null,
			'linked'  => array_map( array( $this, 'order_ref' ), $linked ),
			'matched' => array_values( array_filter( explode( ',', $r['matched'] ) ) ),
			'amount'  => $amount > 0 ? $this->money( $amount ) : '',
			'ring'    => $ring,
			'meta'    => json_decode( $r['meta'] ?: '{}', true ) ?: new \stdClass(),
		);
	}

	private function order_ref( int $id ): array {
		$o       = wc_get_order( $id );
		$created = $o ? $o->get_date_created() : null;
		return array(
			'id'   => $id,
			'url'  => $o ? $o->get_edit_order_url() : '',
			'date' => $created ? gmdate( 'c', $created->getTimestamp() ) : null,
		);
	}

	private function money( float $amount ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}
}
