<?php
/**
 * Blocked coupon attempts (daily count for 30 days, last 200 events), manually blocked rings and allowed (whitelisted) rings.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Data;

defined( 'ABSPATH' ) || exit;

class BlockLog {
	const OPTION  = 'twinspot_lite_blocked';
	const EVENTS  = 'twinspot_lite_block_events';
	const RINGS   = 'twinspot_lite_blocked_rings';
	const ALLOWED = 'twinspot_lite_allowed_rings';

	/**
	 * @param int[] $order_ids Earlier orders the shopper matched.
	 * @param float $amount    Discount kept, when known (fixed coupons only).
	 */
	public function add( array $order_ids = array(), float $amount = 0.0 ): void {
		$events   = (array) get_option( self::EVENTS, array() );
		$events[] = array(
			'ids'    => $order_ids,
			'amount' => $amount,
		);
		update_option( self::EVENTS, array_slice( $events, -200 ), false );
		$log         = (array) get_option( self::OPTION, array() );
		$day         = gmdate( 'Y-m-d' );
		$log[ $day ] = ( $log[ $day ] ?? 0 ) + 1;
		ksort( $log );
		update_option( self::OPTION, array_slice( $log, -30, null, true ), false );
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
		return array(
			'count'  => $count,
			'amount' => $amount,
		);
	}

	public function set_ring_blocked( int $key, array $hashes, bool $on ): void {
		$this->set_ring( self::RINGS, self::ALLOWED, $key, $hashes, $on );
	}

	public function set_ring_allowed( int $key, array $hashes, bool $on ): void {
		$this->set_ring( self::ALLOWED, self::RINGS, $key, $hashes, $on );
	}

	/** Store or clear one ring in a list. Matching by hash (not key) survives ring keys changing as rings grow; a ring is never in both lists. */
	private function set_ring( string $option, string $other, int $key, array $hashes, bool $on ): void {
		$hashes = array_values( array_unique( array_filter( $hashes ) ) );
		$rings  = $this->without( $option, $hashes );
		if ( $on ) {
			$rings[ $key ] = $hashes;
			$this->without( $other, $hashes );
		}
		update_option( $option, $rings, false );
	}

	/** Saves and returns the list minus every ring that shares a hash with $hashes. */
	private function without( string $option, array $hashes ): array {
		$rings = array_filter( (array) get_option( $option, array() ), fn( $ring ) => ! array_intersect( $ring, $hashes ) );
		update_option( $option, $rings, false );
		return $rings;
	}

	/** @return array{blocked:bool,allowed:bool} Whether a ring (its hashes) is on either list. */
	public function ring_state( array $hashes ): array {
		$hashes = array_filter( $hashes );
		return array(
			'blocked' => $this->hits( self::RINGS, $hashes ),
			'allowed' => $this->hits( self::ALLOWED, $hashes ),
		);
	}

	private function hits( string $option, array $hashes ): bool {
		foreach ( (array) get_option( $option, array() ) as $list ) {
			if ( array_intersect( $hashes, $list ) ) {
				return true;
			}
		}
		return false;
	}

	/** Erase support: drop these hashes from blocked rings and these order IDs from the event log. */
	public function forget( array $hashes, array $order_ids ): void {
		$hashes = array_filter( $hashes );
		foreach ( array( self::RINGS, self::ALLOWED ) as $option ) {
			$rings = array();
			foreach ( (array) get_option( $option, array() ) as $key => $list ) {
				$list = array_values( array_diff( $list, $hashes ) );
				if ( $list ) {
					$rings[ $key ] = $list;
				}
			}
			update_option( $option, $rings, false );
		}
		$events = (array) get_option( self::EVENTS, array() );
		foreach ( $events as &$e ) {
			$e['ids'] = array_values( array_diff( $e['ids'], $order_ids ) );
		}
		unset( $e );
		update_option( self::EVENTS, $events, false );
	}

	/** True if any of the shopper's hashes belongs to a blocked ring. */
	public function is_blocked( array $h ): bool {
		return $this->hits( self::RINGS, $this->mine( $h ) );
	}

	/** True if any of the shopper's hashes belongs to an allowed (whitelisted) ring. */
	public function is_allowed( array $h ): bool {
		return $this->hits( self::ALLOWED, $this->mine( $h ) );
	}

	private function mine( array $h ): array {
		return array_filter( array( $h['email_h'] ?? '', $h['phone_h'] ?? '', $h['addr_h'] ?? '' ) );
	}
}
