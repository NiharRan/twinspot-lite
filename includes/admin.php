<?php
/**
 * Admin screen: mounts the React app (source in src/, built to build/).
 *
 * @package Twinspot_Lite
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'woocommerce', 'Twinspot', 'Twinspot', 'manage_woocommerce', 'twinspot-lite', 'twinspot_lite_admin_page' );
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'woocommerce_page_twinspot-lite' !== $hook ) {
			return;
		}
		$asset_file = plugin_dir_path( __DIR__ ) . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		$url   = plugins_url( 'build/', __DIR__ );
		wp_enqueue_script( 'twinspot-lite-admin', $url . 'index.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'twinspot-lite-admin', $url . 'style-index.css', array(), $asset['version'] );
		wp_set_script_translations( 'twinspot-lite-admin', 'twinspot-lite' );
	}
);

function twinspot_lite_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	if ( ! file_exists( plugin_dir_path( __DIR__ ) . 'build/index.asset.php' ) ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Twinspot Lite admin files are missing. Reinstall the plugin.', 'twinspot-lite' ) . '</p></div></div>';
		return;
	}
	echo '<div class="wrap"><div id="twinspot-root"></div></div>';
}
