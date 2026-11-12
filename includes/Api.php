<?php
/**
 * Public PHP facade for add-ons (Twinspot Pro). Static, stable surface over the internal services.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite;

use Twinspot_Lite\Data\BlockLog;
use Twinspot_Lite\Data\Edges;
use Twinspot_Lite\Data\Events;
use Twinspot_Lite\Data\Fingerprinter;
use Twinspot_Lite\Rest\Overview;

defined( 'ABSPATH' ) || exit;

class Api {
	/**
	 * Ids of the earlier orders in the ring the checkout details match (multi-hop).
	 * A single match returns that one id (the shopper plus it forms a ring, as the new order is not indexed yet).
	 * Unlike ring_orders_for_order(), which returns [] for an order with no other linked orders.
	 *
	 * @return int[] Empty only when nothing matches or details are missing.
	 */
	public static function ring_orders_for_details( string $email, string $phone, string $line1, string $postcode, int $limit = 200 ): array {
		$edges = new Edges();
		$seed  = $edges->matches( ( new Fingerprinter() )->hashes( $email, $phone, $line1, $postcode ) );
		return $seed ? $edges->component( $seed, $limit ) : array();
	}

	/**
	 * Ring ids including $order_id; empty if the order has no edge row or no ring.
	 *
	 * @return int[]
	 */
	public static function ring_orders_for_order( int $order_id, int $limit = 200 ): array {
		$edges = new Edges();
		$ids   = $edges->component( array( $order_id ), $limit );
		return $edges->row( $order_id ) && count( $ids ) > 1 ? $ids : array();
	}

	/**
	 * Lite's ring for several seed orders at once (multi-hop). Seeds are always included.
	 *
	 * @param int[] $order_ids Seed order ids.
	 * @return int[] Sorted order ids.
	 */
	public static function component( array $order_ids, int $limit = 200 ): array {
		$order_ids = array_values( array_filter( array_map( 'intval', $order_ids ) ) );
		return $order_ids ? ( new Edges() )->component( $order_ids, $limit ) : array();
	}

	/**
	 * Stored hashes per order, without the IP hash: [ order_id => [ email_h, phone_h, addr_h ] ]. Unknown orders are omitted.
	 *
	 * @param int[] $order_ids Order ids.
	 * @return array<int, array<string, string>>
	 */
	public static function edge_rows( array $order_ids ): array {
		$edges = new Edges();
		$out   = array();
		foreach ( array_unique( array_filter( array_map( 'intval', $order_ids ) ) ) as $id ) {
			$row = $edges->row( $id );
			if ( $row ) {
				$out[ $id ] = array(
					'email_h' => (string) $row['email_h'],
					'phone_h' => (string) $row['phone_h'],
					'addr_h'  => (string) $row['addr_h'],
				);
			}
		}
		return $out;
	}

	public static function is_ring_allowed_for_details( string $email, string $phone, string $line1, string $postcode ): bool {
		return ( new BlockLog() )->is_allowed( ( new Fingerprinter() )->hashes( $email, $phone, $line1, $postcode ) );
	}

	public static function is_ring_allowed_for_order( int $order_id ): bool {
		$row = ( new Edges() )->row( $order_id );
		return $row && ( new BlockLog() )->is_allowed( $row );
	}

	/** Ring state for an order: allowed, blocked or none (also none when the order is in no ring). */
	public static function ring_state_for_order( int $order_id ): string {
		$ring = self::overview()->detail( $order_id );
		return $ring ? ( $ring['allowed'] ? 'allowed' : ( $ring['blocked'] ? 'blocked' : 'none' ) ) : 'none';
	}

	/**
	 * Allow, block or clear the whole ring of an order.
	 *
	 * @param string $state allow, block or clear.
	 * @return string|null The new state (allowed, blocked, none); null when the order is in no ring or $state is unknown.
	 */
	public static function set_ring_state( int $order_id, string $state ): ?string {
		if ( ! in_array( $state, array( 'allow', 'block', 'clear' ), true ) ) {
			return null;
		}
		$overview = self::overview();
		if ( null === $overview->set_allowed( $order_id, 'allow' === $state ) ) {
			return null;
		}
		$overview->set_blocked( $order_id, 'block' === $state );
		return self::ring_state_for_order( $order_id );
	}

	private static function overview(): Overview {
		return new Overview( new Edges(), new Fingerprinter(), new BlockLog(), new Events() );
	}

	/** Hold an order for review: flag, notify, move to on-hold. */
	public static function hold_order( \WC_Order $order, string $note ): void {
		$order->update_meta_data( '_twinspot_lite_held', '1' );
		do_action( 'twinspot_lite_order_held', $order, array() );
		$order->update_status( 'on-hold', $note );
	}
}
