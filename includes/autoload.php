<?php
/**
 * Class autoloader: Twinspot_Lite\Sub\Name loads includes/Sub/Name.php.
 *
 * @package Twinspot_Lite
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'Twinspot_Lite\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
