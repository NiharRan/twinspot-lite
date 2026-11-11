<?php
/**
 * Plugin Name:       Twinspot Lite
 * Description:       Spot rings of linked accounts and block repeat welcome coupons at WooCommerce checkout. Runs on your store; no data leaves your site.
 * Version:           0.3.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * Author:            Nihar Ranjan Das
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       twinspot-lite
 *
 * @package Twinspot_Lite
 */

defined( 'ABSPATH' ) || exit;

const TWINSPOT_LITE_VERSION = '0.3.0';

require __DIR__ . '/includes/autoload.php';

register_activation_hook( __FILE__, array( \Twinspot_Lite\Plugin::class, 'activate' ) );
// On plugins_loaded so add-ons loaded after Lite can hook twinspot_lite_modules.
add_action(
	'plugins_loaded',
	function () {
		( new \Twinspot_Lite\Plugin( __FILE__ ) )->boot();
	}
);
