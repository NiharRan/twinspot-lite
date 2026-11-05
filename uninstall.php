<?php
/**
 * Remove plugin data on delete.
 *
 * @package Twinspot_Lite
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require __DIR__ . '/includes/autoload.php';

( new \Twinspot_Lite\Data\Edges() )->drop();
( new \Twinspot_Lite\Data\Fingerprinter() )->uninstall();
delete_option( 'twinspot_lite_blocked' );
delete_option( 'twinspot_lite_enabled' );
// Order and coupon meta (_twinspot_lite_noted, _twinspot_lite_first_order_only) is tiny and harmless; left in place.
