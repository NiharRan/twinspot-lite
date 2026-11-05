<?php
/**
 * Remove plugin data on delete.
 *
 * @package Twinspot_Lite
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require __DIR__ . '/includes/autoload.php';

( new \Twinspot_Lite\Data\Edges() )->drop();
( new \Twinspot_Lite\Data\Events() )->drop();
( new \Twinspot_Lite\Data\Fingerprinter() )->uninstall();
delete_option( 'twinspot_lite_blocked' );
delete_option( 'twinspot_lite_block_events' );
delete_option( 'twinspot_lite_blocked_rings' );
delete_option( 'twinspot_lite_allowed_rings' );
delete_option( 'twinspot_lite_enabled' );
delete_option( 'twinspot_lite_hold_orders' );
// Order and coupon meta (_twinspot_lite_noted, _twinspot_lite_first_order_only) is tiny and harmless; left in place.
