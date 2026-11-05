<?php
/**
 * Builds the data for the admin Overview screen. No request handling here.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Rest;

use Twinspot_Lite\Core\Rings;
use Twinspot_Lite\Data\BlockLog;
use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Fingerprinter;
use Twinspot_Lite\Features\CouponGuard;

defined( 'ABSPATH' ) || exit;

class Overview {
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

	/**
	 * @param array $args page, per_page, min_size, link, search.
	 */
	public function build( array $args ): array {
		$rows  = $this->edges->all();
		$by_id = array();
		foreach ( $rows as $r ) {
			$by_id[ (int) $r['order_id'] ] = $r;
		}
		$all_rings = Rings::find( $rows );

		$in_rings = array_merge( array(), ...$all_rings );
		$stats    = array(
			'ring_customers'  => count( array_unique( array_filter( array_map( fn( $id ) => $by_id[ $id ]['email_h'], $in_rings ) ) ) ),
			'indexed'         => count( $rows ),
			'rings'           => count( $all_rings ),
			'orders_in_rings' => array_sum( array_map( 'count', $all_rings ) ),
			'blocked_30d'     => $this->log->total(),
			'customers'       => $this->edges->customers(),
			'coupons'         => $this->coupon_count(),
			'sample'          => (bool) array_filter( array_keys( $by_id ), fn( $id ) => $id >= Edges::SAMPLE_BASE ),
			'version'         => TWINSPOT_LITE_VERSION,
		);

		// Search: order number, or an email (matched through its hash).
		$search = $args['search'];
		$ids    = null;
		if ( '' !== $search ) {
			if ( ctype_digit( ltrim( $search, '#' ) ) ) {
				$ids = array( (int) ltrim( $search, '#' ) );
			} else {
				$h   = $this->fingerprinter->email_hash( $search );
				$ids = $h ? array_map( 'intval', array_column( array_filter( $rows, fn( $r ) => $r['email_h'] === $h ), 'order_id' ) ) : array();
			}
		}

		$list = array();
		foreach ( $all_rings as $ring ) {
			$links = Rings::links( array_map( fn( $id ) => $by_id[ $id ], $ring ) );
			if ( count( $ring ) < $args['min_size'] ) {
				continue;
			}
			if ( $args['link'] && ! in_array( $args['link'], $links, true ) ) {
				continue;
			}
			if ( null !== $ids && ! array_intersect( $ids, $ring ) ) {
				continue;
			}
			rsort( $ring );
			$list[] = array(
				'ids'   => $ring,
				'links' => $links,
			);
		}
		usort( $list, fn( $a, $b ) => count( $b['ids'] ) - count( $a['ids'] ) ?: $b['ids'][0] - $a['ids'][0] );

		$total    = count( $list );
		$per_page = $args['per_page'];
		$list     = array_map( array( $this, 'describe' ), array_slice( $list, ( $args['page'] - 1 ) * $per_page, $per_page ) );

		return array(
			'stats'       => $stats,
			'rings'       => $list,
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/** Coupons with the "New customers only" box ticked. */
	private function coupon_count(): int {
		return count(
			get_posts(
				array(
					'post_type'   => 'shop_coupon',
					'post_status' => 'publish',
					'fields'      => 'ids',
					'numberposts' => -1,
					'meta_key'    => CouponGuard::META, // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'  => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			)
		);
	}

	/**
	 * Adds order links, spend and last order date to one ring.
	 */
	private function describe( array $ring ): array {
		$orders = array();
		$spent  = 0.0;
		$last   = null;
		foreach ( $ring['ids'] as $id ) {
			if ( $id >= Edges::SAMPLE_BASE ) {
				$orders[] = array(
					'id'  => $id - Edges::SAMPLE_BASE,
					'url' => '',
				);
				$spent   += 49;
				$last     = $last ?: time();
				continue;
			}
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}
			$orders[] = array(
				'id'  => $id,
				'url' => $order->get_edit_order_url(),
			);
			$spent   += (float) $order->get_total();
			$created  = $order->get_date_created();
			if ( $created && ( ! $last || $created->getTimestamp() > $last ) ) {
				$last = $created->getTimestamp();
			}
		}
		return array(
			'key'        => $ring['ids'][0],
			'size'       => count( $ring['ids'] ),
			'sample'     => $ring['ids'][0] >= Edges::SAMPLE_BASE,
			'links'      => $ring['links'],
			'orders'     => $orders,
			'total'      => $this->money( $spent ),
			'last_order' => $last ? gmdate( 'c', $last ) : null,
		);
	}

	private function money( float $amount ): string {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * One ring as a graph: order nodes, a hub node per identifier shared by 2+ orders, and an edge per use.
	 * Hubs carry only a type and a count, never the hash.
	 *
	 * @param int $order_id Any order in the ring.
	 * @return array|null Null when the order is in no ring.
	 */
	public function detail( int $order_id ): ?array {
		$rows = $this->edges->all();
		$ring = null;
		foreach ( Rings::find( $rows ) as $ids ) {
			if ( in_array( $order_id, $ids, true ) ) {
				$ring = $ids;
				break;
			}
		}
		if ( ! $ring ) {
			return null;
		}
		$rows = array_values( array_filter( $rows, fn( $r ) => in_array( (int) $r['order_id'], $ring, true ) ) );
		rsort( $ring );

		$hubs  = array();
		$edges = array();
		foreach ( Rings::LINKS as $col => $type ) {
			$groups = array();
			foreach ( $rows as $r ) {
				if ( ! empty( $r[ $col ] ) ) {
					$groups[ $r[ $col ] ][] = (int) $r['order_id'];
				}
			}
			foreach ( array_values( array_filter( $groups, fn( $g ) => count( $g ) > 1 ) ) as $n => $g ) {
				$hubs[] = array(
					'id'    => $type . $n,
					'type'  => $type,
					'count' => count( $g ),
				);
				foreach ( $g as $oid ) {
					$edges[] = array( $oid, $type . $n );
				}
			}
		}
		$top = $hubs ? max( array_column( $hubs, 'count' ) ) : 0;
		foreach ( $hubs as &$h ) {
			$h['top'] = $h['count'] === $top;
		}
		unset( $h );

		$orders = array();
		$spent  = 0.0;
		$first  = null;
		foreach ( $ring as $id ) {
			if ( $id >= Edges::SAMPLE_BASE ) {
				$orders[] = array(
					'id'      => $id - Edges::SAMPLE_BASE,
					'edge_id' => $id,
					'url'     => '',
					'total'   => $this->money( 49 ),
					'date'    => gmdate( 'c' ),
					'status'  => '',
					'coupons' => array(),
				);
				$spent   += 49;
				continue;
			}
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}
			$created = $order->get_date_created();
			$spent  += (float) $order->get_total();
			if ( $created && ( ! $first || $created->getTimestamp() < $first ) ) {
				$first = $created->getTimestamp();
			}
			$orders[] = array(
				'id'      => $id,
				'edge_id' => $id,
				'url'     => $order->get_edit_order_url(),
				'total'   => $this->money( (float) $order->get_total() ),
				'date'    => $created ? gmdate( 'c', $created->getTimestamp() ) : null,
				'status'  => wc_get_order_status_name( $order->get_status() ),
				'coupons' => $order->get_coupon_codes(),
			);
		}

		$blocks = $this->log->for_orders( $ring );
		return array(
			'key'        => $ring[0],
			'cluster'    => 'R-' . ( $ring[0] % Edges::SAMPLE_BASE ),
			'blocked'    => isset( $this->log->blocked_rings()[ $ring[0] ] ),
			'attempts'   => $blocks['count'],
			'prevented'  => $blocks['amount'] > 0 ? $this->money( $blocks['amount'] ) : '',
			'ids'        => $ring,
			'size'       => count( $ring ),
			'sample'     => $ring[0] >= Edges::SAMPLE_BASE,
			'links'      => Rings::links( $rows ),
			'personas'   => count( array_unique( array_filter( array_column( $rows, 'email_h' ) ) ) ),
			'total'      => $this->money( $spent ),
			'first'      => $first ? gmdate( 'c', $first ) : null,
			'orders'     => $orders,
			'hubs'       => $hubs,
			'edges'      => $edges,
		);
	}

	/** Block or unblock a whole ring from coupons. Returns the new state. */
	public function set_blocked( int $order_id, bool $on ): ?bool {
		$ring = $this->detail( $order_id );
		if ( ! $ring ) {
			return null;
		}
		$hashes = array();
		foreach ( $this->edges->all() as $r ) {
			if ( in_array( (int) $r['order_id'], $ring['ids'], true ) ) {
				$hashes = array_merge( $hashes, array( $r['email_h'], $r['phone_h'], $r['addr_h'] ) );
			}
		}
		$this->log->set_ring_blocked( $ring['key'], $hashes, $on );
		return $on;
	}
}
