<?php
/**
 * Ring detection. Pure PHP, no WordPress calls.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Core;

defined( 'ABSPATH' ) || exit;

class Rings {
	/** Columns that link orders. IP is not one (shared networks). */
	const LINKS = array(
		'email_h' => 'email',
		'phone_h' => 'phone',
		'addr_h'  => 'address',
	);

	/**
	 * Connected components of orders that share any hash.
	 * $rows: [ ['order_id'=>1,'email_h'=>..,'phone_h'=>..,'addr_h'=>..], ... ]
	 * Returns list of order-id lists, size >= 2.
	 */
	public static function find( array $rows ): array {
		$parent = array();
		$find   = function ( $x ) use ( &$parent, &$find ) {
			if ( ! isset( $parent[ $x ] ) ) {
				$parent[ $x ] = $x;
			}
			return $parent[ $x ] === $x ? $x : ( $parent[ $x ] = $find( $parent[ $x ] ) );
		};
		foreach ( $rows as $r ) {
			$o = 'o' . $r['order_id'];
			$find( $o );
			foreach ( array_keys( self::LINKS ) as $k ) {
				if ( ! empty( $r[ $k ] ) ) {
					$parent[ $find( $o ) ] = $find( $k . $r[ $k ] );
				}
			}
		}
		$groups = array();
		foreach ( $rows as $r ) {
			$groups[ $find( 'o' . $r['order_id'] ) ][] = (int) $r['order_id'];
		}
		return array_values(
			array_filter(
				$groups,
				function ( $g ) {
					return count( $g ) > 1;
				}
			)
		);
	}

	/**
	 * Which identifiers are shared by at least two orders in one ring.
	 * $rows: edge rows of the ring's orders. Returns a subset of ['email','phone','address'].
	 */
	public static function links( array $rows ): array {
		$links = array();
		foreach ( self::LINKS as $col => $name ) {
			$counts = array_count_values( array_filter( array_column( $rows, $col ) ) );
			if ( $counts && max( $counts ) > 1 ) {
				$links[] = $name;
			}
		}
		return $links;
	}

	/**
	 * Which identifiers a shopper (hashes $h) shares with the given earlier order rows.
	 * Returns a subset of ['email','phone','address'].
	 */
	public static function shared( array $h, array $rows ): array {
		$out = array();
		foreach ( self::LINKS as $col => $name ) {
			if ( ! empty( $h[ $col ] ) && in_array( $h[ $col ], array_column( $rows, $col ), true ) ) {
				$out[] = $name;
			}
		}
		return $out;
	}
}
