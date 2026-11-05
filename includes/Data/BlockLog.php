<?php
/**
 * Count of blocked coupon attempts per day, last 30 days only.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Data;

defined( 'ABSPATH' ) || exit;

class BlockLog {
	const OPTION = 'twinspot_lite_blocked';

	public function add(): void {
		$log         = (array) get_option( self::OPTION, array() );
		$day         = gmdate( 'Y-m-d' );
		$log[ $day ] = ( $log[ $day ] ?? 0 ) + 1;
		ksort( $log );
		update_option( self::OPTION, array_slice( $log, -30, null, true ), false );
	}

	public function total(): int {
		return (int) array_sum( (array) get_option( self::OPTION, array() ) );
	}
}
