<?php
/**
 * Turns raw customer details into keyed hashes. Owns the per-site secret.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Data;

use Twinspot_Lite\Core\Normalize;

defined( 'ABSPATH' ) || exit;

class Fingerprinter {
	const OPTION = 'twinspot_lite_secret';

	public function install(): void {
		if ( ! get_option( self::OPTION ) ) {
			add_option( self::OPTION, wp_generate_password( 64, true, true ), '', false );
		}
	}

	public function uninstall(): void {
		delete_option( self::OPTION );
	}

	public function email_hash( $email ): string {
		return Normalize::hash( Normalize::email( $email ), (string) get_option( self::OPTION ) );
	}

	public function hashes( $email, $phone, $line1, $postcode, $ip = '' ): array {
		$s = (string) get_option( self::OPTION );
		return array(
			'email_h' => Normalize::hash( Normalize::email( $email ), $s ),
			'phone_h' => Normalize::hash( Normalize::phone( $phone ), $s ),
			'addr_h'  => Normalize::hash( Normalize::address( $line1, $postcode ), $s ),
			'ip_h'    => Normalize::hash( (string) $ip, $s ),
		);
	}
}
