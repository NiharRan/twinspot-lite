<?php
/**
 * Blocked coupon attempts (daily count for 30 days, last 200 events) and manually blocked rings.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Data;

defined( 'ABSPATH' ) || exit;

class BlockLog {
	const OPTION = 'twinspot_lite_blocked';
	const EVENTS = 'twinspot_lite_block_events';
	const RINGS  = 'twinspot_lite_blocked_rings';

	/**
	 * @param int[] $order_ids Earlier orders the shopper matched.
	 * @param float $amount    Discount kept, when known (fixed coupons only).
	 */
	public function add( array $order_ids = array(), float $amount = 0.0 ): void {
		$events   = (array) get_option( self::EVENTS, array() );
		$events[] = array( 'ids' => $order_ids, 'amount' => $amount );
		update_option( self::EVENTS, array_slice( $events, -200 ), false );
		$log         = (array) get_option( self::OPTION, array() );
		$day         = gmdate( 'Y-m-d' );
		$log[ $day ] = ( $log[ $day ] ?? 0 ) + 1;
		ksort( $log );
		update_option( self::OPTION, array_slice( $log, -30, null, true ), false );
	}

	/** @return array<string,int> Blocked count per day, newest first. */
	public function days(): array {
		return array_reverse( array_map( 'intval', (array) get_option( self::OPTION, array() ) ), true );
	}

	public function total(): int {
		return (int) array_sum( (array) get_option( self::OPTION, array() ) );
	}

	/** @return array{count:int,amount:float} Blocked attempts that matched any of these orders. */
	public function for_orders( array $order_ids ): array {
		$count  = 0;
		$amount = 0.0;
		foreach ( (array) get_option( self::EVENTS, array() ) as $e ) {
			if ( array_intersect( $e['ids'], $order_ids ) ) {
				++$count;
				$amount += $e['amount'];
			}
		}
		return array( 'count' => $count, 'amount' => $amount );
	}

	/** Rings an admin blocked from all coupons: ring key => identifier hashes. */
	public function blocked_rings(): array {
		return (array) get_option( self::RINGS, array() );
	}

	public function set_ring_blocked( int $key, array $hashes, bool $on ): void {
		$rings = $this->blocked_rings();
		if ( $on ) {
			$rings[ $key ] = array_values( array_unique( array_filter( $hashes ) ) );
		} else {
			unset( $rings[ $key ] );
		}
		update_option( self::RINGS, $rings, false );
	}

	/** True if any of the shopper's hashes belongs to a blocked ring. */
	public function is_blocked( array $h ): bool {
		$mine = array_filter( array( $h['email_h'] ?? '', $h['phone_h'] ?? '', $h['addr_h'] ?? '' ) );
		foreach ( $this->blocked_rings() as $hashes ) {
			if ( array_intersect( $mine, $hashes ) ) {
				return true;
			}
		}
		return false;
	}
}
