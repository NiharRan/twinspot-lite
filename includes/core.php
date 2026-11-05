<?php
/**
 * Pure logic, no WordPress calls, so tests/core-test.php runs with plain php.
 *
 * @package Twinspot_Lite
 */

defined( 'ABSPATH' ) || exit;

function twinspot_lite_norm_email( $email ) {
	$email = strtolower( trim( $email ) );
	if ( ! strpos( $email, '@' ) ) {
		return '';
	}
	list( $local, $domain ) = explode( '@', $email, 2 );
	$local = explode( '+', $local )[0];
	if ( in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
		$local  = str_replace( '.', '', $local );
		$domain = 'gmail.com';
	}
	return $local . '@' . $domain;
}

function twinspot_lite_norm_phone( $phone ) {
	$d = preg_replace( '/\D+/', '', (string) $phone );
	// shortcut: last 10 digits ignores the country code; upgrade when selling outside one region.
	return strlen( $d ) >= 7 ? substr( $d, -10 ) : '';
}

function twinspot_lite_norm_addr( $line1, $postcode ) {
	$a = preg_replace( '/[^a-z0-9]/', '', strtolower( $line1 ) );
	$p = preg_replace( '/[^a-z0-9]/', '', strtolower( $postcode ) );
	return ( $a && $p ) ? $a . '|' . $p : '';
}

function twinspot_lite_hash( $value, $secret ) {
	return '' === $value ? '' : hash_hmac( 'sha256', $value, $secret );
}

/**
 * Connected components of orders that share any hash.
 * $rows: [ ['order_id'=>1,'email_h'=>..,'phone_h'=>..,'addr_h'=>..], ... ]
 * Returns list of order-id lists, size >= 2. IP is not an edge (shared networks).
 */
function twinspot_lite_rings( array $rows ) {
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
		foreach ( array( 'email_h', 'phone_h', 'addr_h' ) as $k ) {
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
function twinspot_lite_ring_links( array $rows ) {
	$map   = array(
		'email_h' => 'email',
		'phone_h' => 'phone',
		'addr_h'  => 'address',
	);
	$links = array();
	foreach ( $map as $col => $name ) {
		$counts = array_count_values( array_filter( array_column( $rows, $col ) ) );
		if ( $counts && max( $counts ) > 1 ) {
			$links[] = $name;
		}
	}
	return $links;
}
