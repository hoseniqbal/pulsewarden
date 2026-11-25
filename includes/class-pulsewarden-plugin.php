<?php
/**
 * Bootstrap.
 *
 * @package PulseWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires everything together.
 */
class PulseWarden_Plugin {

	const CHECK = 'pulsewarden_check';
	const DAILY = 'pulsewarden_daily';

	/**
	 * Runs on plugins_loaded.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 15 minutes.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( get_option( 'pulsewarden_db_version' ) !== PULSEWARDEN_DB_VERSION ) {
			PulseWarden_Store::install();
		}
		add_action( self::CHECK, array( 'PulseWarden_Monitor', 'run' ) );
		add_action( self::DAILY, array( __CLASS__, 'daily' ) );
		add_action( 'pulsewarden_backfill', array( 'PulseWarden_Monitor', 'backfill' ) );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
		PulseWarden_Monitor::init();
		if ( is_admin() ) {
			PulseWarden_Admin::init();
		}
	}

	/**
	 * Fifteen minute schedule.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules['pulsewarden_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes (PulseWarden)', 'pulsewarden' ),
		);
		return $schedules;
	}

	/**
	 * Make sure the checks are scheduled.
	 *
	 * @return void
	 */
	public static function ensure_schedule() {
		if ( ! wp_next_scheduled( self::CHECK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'pulsewarden_15min', self::CHECK );
		}
		if ( ! wp_next_scheduled( self::DAILY ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY );
		}
	}

	/**
	 * Daily housekeeping.
	 *
	 * @return void
	 */
	public static function daily() {
		PulseWarden_Store::purge();
		delete_transient( 'pulsewarden_baseline' );
	}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {
		PulseWarden_Store::install();
		if ( ! get_option( 'pulsewarden_since' ) ) {
			update_option( 'pulsewarden_since', time(), false );
			update_option( 'pulsewarden_backfill', time(), false );
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( 'pulsewarden_backfill', array( 1 ), 'pulsewarden' );
			}
		}
	}

	/**
	 * Deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CHECK );
		wp_clear_scheduled_hook( self::DAILY );
	}
}
