<?php
/**
 * The edges table: one row of hashes per order. All SQL lives here.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Data;

use Twinspot_Lite\Core\Rings;

defined( 'ABSPATH' ) || exit;

class Edges {
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'twinspot_lite_edges';
	}

	public function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = $this->table();
		dbDelta(
			"CREATE TABLE $table (
			order_id bigint(20) unsigned NOT NULL,
			email_h varchar(64) NOT NULL DEFAULT '',
			phone_h varchar(64) NOT NULL DEFAULT '',
			addr_h varchar(64) NOT NULL DEFAULT '',
			ip_h varchar(64) NOT NULL DEFAULT '',
			PRIMARY KEY  (order_id),
			KEY email_h (email_h), KEY phone_h (phone_h), KEY addr_h (addr_h)
		) {$wpdb->get_charset_collate()};"
		);
	}

	public function drop(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table()}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	public function save( int $order_id, array $hashes ): void {
		global $wpdb;
		$wpdb->replace( $this->table(), array( 'order_id' => $order_id ) + $hashes ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function delete( int $order_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table(), array( 'order_id' => $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function all(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM {$this->table()}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function by_email_hash( string $hash ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE email_h = %s", $hash ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Ids of other orders sharing email, phone or address (IP is too weak to match on).
	 */
	public function matches( array $h, int $exclude = 0 ): array {
		global $wpdb;
		$where = array();
		foreach ( array_keys( Rings::LINKS ) as $col ) {
			if ( ! empty( $h[ $col ] ) ) {
				$where[] = $wpdb->prepare( "$col = %s", $h[ $col ] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column is from a fixed list.
			}
		}
		if ( ! $where ) {
			return array();
		}
		$sql = $wpdb->prepare( "SELECT order_id FROM {$this->table()} WHERE order_id <> %d AND (" . implode( ' OR ', $where ) . ') LIMIT 20', $exclude ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- parts prepared above.
		return array_map( 'intval', $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}
}
