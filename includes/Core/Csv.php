<?php
/**
 * CSV line builder. Pure PHP, no WordPress calls.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Core;

defined( 'ABSPATH' ) || exit;

class Csv {
	/** One quoted CSV line. Cells starting with = + - @ are prefixed with a quote so spreadsheets do not run them as formulas. */
	public static function row( array $cells ): string {
		$out = array();
		foreach ( $cells as $c ) {
			$c     = (string) $c;
			$c     = preg_match( '/^[=+\-@\t\r]/', $c ) ? "'" . $c : $c;
			$out[] = '"' . str_replace( '"', '""', $c ) . '"';
		}
		return implode( ',', $out ) . "\r\n";
	}
}
