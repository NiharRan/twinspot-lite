<?php
/**
 * Admin screen: mounts the React app (source in src/, built to build/).
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Admin;

use Twinspot_Lite\Module;

defined( 'ABSPATH' ) || exit;

class Page implements Module {
	const HOOK = 'woocommerce_page_twinspot-lite';

	/** @var string Plugin root, with trailing slash. */
	private $dir;

	public function __construct( string $dir ) {
		$this->dir = trailingslashit( $dir );
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_menu(): void {
		add_submenu_page( 'woocommerce', 'Twinspot', 'Twinspot', 'manage_woocommerce', 'twinspot-lite', array( $this, 'render' ) );
	}

	public function enqueue( $hook ): void {
		$asset_file = $this->dir . 'build/index.asset.php';
		if ( self::HOOK !== $hook || ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		$url   = plugins_url( 'build/', $this->dir . 'twinspot-lite.php' );
		wp_enqueue_script( 'twinspot-lite-admin', $url . 'index.js', apply_filters( 'twinspot_lite_admin_script_deps', $asset['dependencies'] ), $asset['version'], true );
		wp_enqueue_style( 'twinspot-lite-admin', $url . 'style-index.css', array(), $asset['version'] );
		wp_set_script_translations( 'twinspot-lite-admin', 'twinspot-lite' );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! file_exists( $this->dir . 'build/index.asset.php' ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Twinspot Lite admin files are missing. Reinstall the plugin.', 'twinspot-lite' ) . '</p></div></div>';
			return;
		}
		echo '<div class="wrap"><div id="twinspot-root"></div></div>';
	}
}
