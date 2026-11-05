<?php
/**
 * Audit log: one row per coupon decision, ring change and background task. Keeps 30 days.
 * Rows hold order ids and coupon codes only, never customer details or hashes. All SQL for the log lives here.
 *
 * @package Twinspot_Lite
 */

namespace Twinspot_Lite\Data;

defined( 'ABSPATH' ) || exit;

class Events {
	const DB_VERSION = '1';
	const OPTION     = 'twinspot_lite_events_db';
	const KEEP_DAYS  = 30;

	/** Filter values (event type or severity) to the event types they cover. */
	const GROUPS = array(
		'blocked' => array( 'blocked' ),
		'clean'   => array( 'clean' ),
		'ring'    => array( 'ring' ),
		'held'    => array( 'held' ),
		'system'  => array( 'import', 'rule' ),
		'flagged' => array( 'held', 'ring' ),
		'allowed' => array( 'clean' ),
	);

	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'twinspot_lite_events';
	}

	public function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = $this->table();
		dbDelta(
			"CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created datetime NOT NULL,
			type varchar(12) NOT NULL DEFAULT '',
			coupon varchar(100) NOT NULL DEFAULT '',
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			linked varchar(120) NOT NULL DEFAULT '',
			matched varchar(40) NOT NULL DEFAULT '',
			amount decimal(12,4) NOT NULL DEFAULT 0,
			meta varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created (created), KEY type (type)
		) {$wpdb->get_charset_collate()};"
		);
		update_option( self::OPTION, self::DB_VERSION, false );
	}

	/** Existing sites get the table on the first request after the update. */
	public function maybe_install(): void {
		if ( get_option( self::OPTION ) !== self::DB_VERSION ) {
			$this->install();
		}
	}

	public function drop(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table()}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		delete_option( self::OPTION );
		delete_transient( 'twinspot_lite_pruned' );
	}

	/**
	 * @param string $type  blocked, clean, ring, held, import or rule.
	 * @param array  $f     coupon, order_id, linked (order ids, 5 kept), matched (email/phone/address), amount, meta (small array).
	 */
	public function add( string $type, array $f = array() ): void {
		global $wpdb;
		$linked = array_slice( array_unique( array_map( 'intval', (array) ( $f['linked'] ?? array() ) ) ), 0, 5 );
		$row    = array(
			'created'  => gmdate( 'Y-m-d H:i:s' ),
			'type'     => $type,
			'coupon'   => substr( (string) ( $f['coupon'] ?? '' ), 0, 100 ),
			'order_id' => (int) ( $f['order_id'] ?? 0 ),
			'linked'   => $linked ? ',' . implode( ',', $linked ) . ',' : '',
			'matched'  => implode( ',', (array) ( $f['matched'] ?? array() ) ),
			'amount'   => (float) ( $f['amount'] ?? 0 ),
			'meta'     => empty( $f['meta'] ) ? '' : substr( (string) wp_json_encode( $f['meta'] ), 0, 255 ),
		);
		// WooCommerce re-validates coupons on every cart refresh; log one refusal per 10 minutes.
		if ( 'blocked' === $type && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table()} WHERE type = 'blocked' AND coupon = %s AND linked = %s AND matched = %s AND created >= %s LIMIT 1", $row['coupon'], $row['linked'], $row['matched'], gmdate( 'Y-m-d H:i:s', time() - 600 ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return;
		}
		$wpdb->insert( $this->table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! get_transient( 'twinspot_lite_pruned' ) ) {
			set_transient( 'twinspot_lite_pruned', 1, HOUR_IN_SECONDS );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table()} WHERE created < %s", gmdate( 'Y-m-d H:i:s', time() - self::KEEP_DAYS * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * @param array $a since (unix time), type, severity, search, page, per_page.
	 * @return array{rows:array,total:int}
	 */
	public function query( array $a ): array {
		global $wpdb;
		$where = $this->where( $a );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table()} WHERE $where" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows  = $wpdb->get_results( "SELECT * FROM {$this->table()} WHERE $where ORDER BY id DESC LIMIT " . (int) $a['per_page'] . ' OFFSET ' . (int) ( ( $a['page'] - 1 ) * $a['per_page'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		return array( 'rows' => $rows, 'total' => $total );
	}

	/** @return array{counts:array<string,int>,blocked_amount:float,coupons:int} Totals per event type since a time. */
	public function stats( int $since ): array {
		global $wpdb;
		$from   = gmdate( 'Y-m-d H:i:s', $since );
		$counts = array();
		$amount = 0.0;
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT type, COUNT(*) AS n, SUM(amount) AS amt FROM {$this->table()} WHERE created >= %s GROUP BY type", $from ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$counts[ $r['type'] ] = (int) $r['n'];
			$amount              += 'blocked' === $r['type'] ? (float) $r['amt'] : 0.0;
		}
		return array(
			'counts'         => $counts,
			'blocked_amount' => $amount,
			'coupons'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT coupon) FROM {$this->table()} WHERE created >= %s AND coupon <> ''", $from ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/** Erase support: drop events about these orders and unlink them from other events. */
	public function forget( array $order_ids ): void {
		global $wpdb;
		foreach ( array_map( 'intval', $order_ids ) as $id ) {
			$wpdb->delete( $this->table(), array( 'order_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( "UPDATE {$this->table()} SET linked = REPLACE(linked, %s, ',') WHERE linked LIKE %s", ",$id,", '%,' . $id . ',%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$wpdb->query( "UPDATE {$this->table()} SET linked = '' WHERE linked = ','" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	private function where( array $a ): string {
		global $wpdb;
		$w = array( $wpdb->prepare( 'created >= %s', gmdate( 'Y-m-d H:i:s', $a['since'] ) ) );
		foreach ( array( 'type', 'severity' ) as $key ) {
			$types = self::GROUPS[ $a[ $key ] ?? '' ] ?? array();
			if ( $types ) {
				$w[] = $wpdb->prepare( 'type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')', $types ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			}
		}
		$s = ltrim( trim( (string) ( $a['search'] ?? '' ) ), '#' );
		if ( '' !== $s ) {
			$like = $wpdb->prepare( 'coupon LIKE %s', '%' . $wpdb->esc_like( $s ) . '%' );
			if ( ctype_digit( $s ) ) {
				$like .= $wpdb->prepare( ' OR order_id = %d OR linked LIKE %s', (int) $s, '%,' . $s . ',%' );
			}
			$w[] = "($like)";
		}
		return implode( ' AND ', $w );
	}
}
