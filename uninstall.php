<?php
/**
 * Remove plugin data on delete.
 *
 * @package Twinspot_Lite
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}twinspot_lite_edges" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
delete_option( 'twinspot_lite_secret' );
// Order and coupon meta (_twinspot_lite_noted, _twinspot_lite_first_order_only) is tiny and harmless; left in place.
