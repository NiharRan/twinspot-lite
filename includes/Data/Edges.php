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
	/** Sample orders use ids from here up; real order ids never get this high. */
	const SAMPLE_BASE = 4000000000;

	/** Bump when the table columns or keys change; maybe_install() then runs dbDelta once. */
	const DB_VERSION = '1';
	const OPTION     = 'twinspot_lite_edges_db';

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
		update_option( self::OPTION, self::DB_VERSION, false );
	}

	/** Existing sites get schema changes on the first request after an update. */
	public function maybe_install(): void {
		if ( get_option( self::OPTION ) !== self::DB_VERSION ) {
			$this->install();
		}
	}

	public function drop(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table()}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		delete_option( self::OPTION );
	}

	public function save( int $order_id, array $hashes ): void {
		global $wpdb;
		$wpdb->replace( $this->table(), array( 'order_id' => $order_id ) + $hashes ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function delete( int $order_id ): void {
		global $wpdb;
		$wpdb->delete( $this->table(), array( 'order_id' => $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function delete_samples(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table()} WHERE order_id >= %d", self::SAMPLE_BASE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Distinct shoppers by email hash, sample rows excluded. */
	public function customers(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT email_h) FROM {$this->table()} WHERE email_h <> '' AND order_id < %d", self::SAMPLE_BASE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * Ids of other real orders sharing email, phone or address (IP is too weak to match on). Sample rows never match.
	 */
	public function matches( array $h, int $exclude = 0 ): array {
		global $wpdb;
		$where = array();
		$args  = array( $exclude, self::SAMPLE_BASE );
		foreach ( array_keys( Rings::LINKS ) as $col ) {
			if ( ! empty( $h[ $col ] ) ) {
				$where[] = "$col = %s"; // Column is from a fixed list.
				$args[]  = $h[ $col ];
			}
		}
		if ( ! $where ) {
			return array();
		}
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$this->table()} WHERE order_id <> %d AND order_id < %d AND (" . implode( ' OR ', $where ) . ') LIMIT 20', $args ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders built above.
	}

	/** Which of email, phone, address a shopper shares with the given earlier orders. */
	public function match_types( array $h, array $ids ): array {
		global $wpdb;
		$ids = array_map( 'intval', $ids );
		if ( ! $ids ) {
			return array();
		}
		return Rings::shared( $h, $wpdb->get_results( "SELECT * FROM {$this->table()} WHERE order_id IN (" . implode( ',', $ids ) . ')', ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- ids are cast to int.
	}
}
