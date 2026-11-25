<?php
/**
 * Data: a small event log.
 *
 * @package PulseWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One table: orders placed, failed payments, checkout errors and alerts.
 * No customer data is stored, only the order ID and a short note.
 */
class PulseWarden_Store {

	/**
	 * Log table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'pulsewarden_log';
	}

	/**
	 * Create or update the table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			type varchar(20) NOT NULL,
			ref bigint(20) unsigned NOT NULL DEFAULT 0,
			detail varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY type_created (type, created_at),
			KEY type_ref (type, ref)
		) {$charset};"
		);
		update_option( 'pulsewarden_db_version', PULSEWARDEN_DB_VERSION, false );
	}

	/**
	 * Add a row.
	 *
	 * @param string   $type   order|failed|error|alert.
	 * @param int      $ref    Order ID or 0.
	 * @param string   $detail Short note.
	 * @param int|null $time   Unix time, default now.
	 * @return void
	 */
	public static function log( $type, $ref = 0, $detail = '', $time = null ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- own table, write.
		$wpdb->insert(
			self::table(),
			array(
				'created_at' => gmdate( 'Y-m-d H:i:s', null === $time ? time() : (int) $time ),
				'type'       => substr( sanitize_key( $type ), 0, 20 ),
				'ref'        => absint( $ref ),
				'detail'     => substr( (string) $detail, 0, 255 ),
			),
			array( '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Whether a row for this type and order exists.
	 *
	 * @param string $type Type.
	 * @param int    $ref  Order ID.
	 * @return bool
	 */
	public static function has( $type, $ref ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE type = %s AND ref = %d LIMIT 1', self::table(), $type, $ref ) );
	}

	/**
	 * Rows of a type in the last N seconds.
	 *
	 * @param string $type    Type.
	 * @param int    $seconds Window.
	 * @return int
	 */
	public static function count_since( $type, $seconds ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE type = %s AND created_at >= %s', self::table(), $type, gmdate( 'Y-m-d H:i:s', time() - (int) $seconds ) ) );
	}

	/**
	 * Rows of a type between two times.
	 *
	 * @param string $type Type.
	 * @param int    $from Unix time.
	 * @param int    $to   Unix time.
	 * @return int
	 */
	public static function count_between( $type, $from, $to ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, report.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE type = %s AND created_at >= %s AND created_at < %s', self::table(), $type, gmdate( 'Y-m-d H:i:s', (int) $from ), gmdate( 'Y-m-d H:i:s', (int) $to ) ) );
	}

	/**
	 * Time of the newest row of a type.
	 *
	 * @param string $type Type.
	 * @return int Unix time or 0.
	 */
	public static function last( $type ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		$val = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(created_at) FROM %i WHERE type = %s', self::table(), $type ) );
		return $val ? (int) strtotime( $val . ' UTC' ) : 0;
	}

	/**
	 * Times of all rows of a type in the last N seconds.
	 *
	 * @param string $type    Type.
	 * @param int    $seconds Window.
	 * @return int[]
	 */
	public static function times_since( $type, $seconds ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, cached by the caller.
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT created_at FROM %i WHERE type = %s AND created_at >= %s ORDER BY created_at ASC LIMIT 50000', self::table(), $type, gmdate( 'Y-m-d H:i:s', time() - (int) $seconds ) ) );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = (int) strtotime( $row . ' UTC' );
		}
		return $out;
	}

	/**
	 * Recent alerts.
	 *
	 * @param int $limit Rows.
	 * @return array
	 */
	public static function alerts( $limit = 30 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, live data.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT created_at, detail FROM %i WHERE type = %s ORDER BY id DESC LIMIT %d', self::table(), 'alert', (int) $limit ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$parts = explode( '|', $row['detail'], 3 );
			$out[] = array(
				'time'  => (int) strtotime( $row['created_at'] . ' UTC' ),
				'check' => isset( $parts[0] ) ? $parts[0] : '',
				'state' => isset( $parts[1] ) ? $parts[1] : '',
				'text'  => isset( $parts[2] ) ? $parts[2] : '',
			);
		}
		return $out;
	}

	/**
	 * Delete rows older than the retention setting. Order rows are kept at least 35 days so the baseline stays intact.
	 *
	 * @return void
	 */
	public static function purge() {
		global $wpdb;
		$days = max( 35, (int) PulseWarden_Settings::get( 'retention_days' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, cleanup.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT 5000', self::table(), gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
	}
}
