<?php
/**
 * Pure logic, no WordPress calls, so tests/core-test.php runs with plain php.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Core;

defined( 'ABSPATH' ) || exit;

class Normalize {
	public static function email( $email ): string {
		$email = strtolower( trim( (string) $email ) );
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

	public static function phone( $phone ): string {
		$d = preg_replace( '/\D+/', '', (string) $phone );
		// shortcut: last 10 digits ignores the country code; upgrade when selling outside one region.
		return strlen( $d ) >= 7 ? substr( $d, -10 ) : '';
	}

	public static function address( $line1, $postcode ): string {
		$a = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $line1 ) );
		$p = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $postcode ) );
		return ( $a && $p ) ? $a . '|' . $p : '';
	}

	public static function hash( string $value, string $secret ): string {
		return '' === $value ? '' : hash_hmac( 'sha256', $value, $secret );
	}
}
