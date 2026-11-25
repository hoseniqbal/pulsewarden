<?php
/**
 * Settings.
 *
 * @package PulseWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All settings in one option.
 */
class PulseWarden_Settings {

	const OPTION = 'pulsewarden_settings';

	/**
	 * Cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'         => 'yes',
			'recipients'      => '',
			'heartbeat'       => 'yes',
			'quiet_hours'     => 2,
			'expected_orders' => 3,
			'failed_watch'    => 'yes',
			'failed_min'      => 5,
			'failed_percent'  => 50,
			'errors_watch'    => 'yes',
			'errors_min'      => 10,
			'tasks_watch'     => 'yes',
			'tasks_min'       => 20,
			'resolved_email'  => 'yes',
			'retention_days'  => 90,
			'delete_data'     => 'no',
		);
	}

	/**
	 * All values.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One value.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Yes/no check.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	public static function on( $key ) {
		return 'yes' === self::get( $key );
	}

	/**
	 * Email addresses that get alerts.
	 *
	 * @return array
	 */
	public static function recipients() {
		$list = array_filter( array_map( 'trim', explode( ',', (string) self::get( 'recipients' ) ) ), 'is_email' );
		if ( empty( $list ) ) {
			$list = array( get_option( 'admin_email' ) );
		}
		return array_values( array_unique( $list ) );
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $in Raw.
	 * @return array
	 */
	public static function sanitize( $in ) {
		$d   = self::defaults();
		$in  = is_array( $in ) ? $in : array();
		$out = array();
		foreach ( array( 'enabled', 'heartbeat', 'failed_watch', 'errors_watch', 'tasks_watch', 'resolved_email', 'delete_data' ) as $key ) {
			$out[ $key ] = ( isset( $in[ $key ] ) && 'yes' === $in[ $key ] ) ? 'yes' : 'no';
		}
		$ints = array(
			'quiet_hours'     => array( 1, 72 ),
			'expected_orders' => array( 1, 100 ),
			'failed_min'      => array( 1, 500 ),
			'failed_percent'  => array( 10, 100 ),
			'errors_min'      => array( 1, 1000 ),
			'tasks_min'       => array( 1, 10000 ),
			'retention_days'  => array( 35, 730 ),
		);
		foreach ( $ints as $key => $range ) {
			$val         = isset( $in[ $key ] ) ? absint( $in[ $key ] ) : $d[ $key ];
			$out[ $key ] = min( $range[1], max( $range[0], $val ) );
		}
		$emails            = isset( $in['recipients'] ) ? explode( ',', sanitize_text_field( wp_unslash( $in['recipients'] ) ) ) : array();
		$emails            = array_filter( array_map( 'sanitize_email', array_map( 'trim', $emails ) ), 'is_email' );
		$out['recipients'] = implode( ', ', array_slice( array_unique( $emails ), 0, 10 ) );
		self::$cache       = null;
		return $out;
	}
}
