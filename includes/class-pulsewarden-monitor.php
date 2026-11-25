<?php
/**
 * Listeners, health checks and alert emails.
 *
 * @package PulseWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Watches the store and sends one email when a problem starts and one when it is over.
 */
class PulseWarden_Monitor {

	const STATE = 'pulsewarden_state';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_classic_order' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_store_api_order' ), 20, 1 );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'on_failed' ), 20, 1 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'on_classic_validation' ), 99999, 2 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'on_rest_response' ), 99999, 3 );
	}

	/* ------------------------------------------------------------------
	 * Listeners
	 * ---------------------------------------------------------------- */

	/**
	 * Classic checkout order.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function on_classic_order( $order_id ) {
		self::record_order( (int) $order_id );
	}

	/**
	 * Block checkout order.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function on_store_api_order( $order ) {
		if ( $order instanceof WC_Order ) {
			self::record_order( (int) $order->get_id() );
		}
	}

	/**
	 * Remember an order once.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private static function record_order( $order_id ) {
		if ( $order_id > 0 && ! PulseWarden_Store::has( 'order', $order_id ) ) {
			PulseWarden_Store::log( 'order', $order_id );
		}
	}

	/**
	 * Failed payment.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function on_failed( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id > 0 && ! PulseWarden_Store::has( 'failed', $order_id ) ) {
			PulseWarden_Store::log( 'failed', $order_id );
		}
	}

	/**
	 * Error codes that come from our own protection plugins. A blocked bot is not a broken checkout.
	 *
	 * @param string $code Error code.
	 * @return bool
	 */
	private static function is_guard_code( $code ) {
		return (bool) preg_match( '/^(orderwarden|codwarden|blockwarden)/', (string) $code );
	}

	/**
	 * Classic checkout validation errors.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 * @return void
	 */
	public static function on_classic_validation( $data, $errors ) {
		unset( $data );
		if ( ! $errors instanceof WP_Error || ! $errors->has_errors() ) {
			return;
		}
		$codes = array_values( array_filter( $errors->get_error_codes(), array( __CLASS__, 'is_not_guard_code' ) ) );
		if ( ! empty( $codes ) ) {
			PulseWarden_Store::log( 'error', 0, 'classic:' . sanitize_key( (string) $codes[0] ) );
		}
	}

	/**
	 * Filter helper.
	 *
	 * @param string $code Code.
	 * @return bool
	 */
	public static function is_not_guard_code( $code ) {
		return ! self::is_guard_code( $code );
	}

	/**
	 * Block checkout errors (Store API).
	 *
	 * @param WP_HTTP_Response $response Response.
	 * @param WP_REST_Server   $server   Server.
	 * @param WP_REST_Request  $request  Request.
	 * @return WP_HTTP_Response
	 */
	public static function on_rest_response( $response, $server, $request ) {
		unset( $server );
		if ( ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
			return $response;
		}
		if ( ! preg_match( '#^/wc/store(/v\d+)?/checkout/?$#', (string) $request->get_route() ) ) {
			return $response;
		}
		if ( ! $response instanceof WP_HTTP_Response || $response->get_status() < 400 ) {
			return $response;
		}
		$data = $response->get_data();
		$code = is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : '';
		if ( ! self::is_guard_code( $code ) && ! did_action( 'orderwarden_blocked' ) ) {
			PulseWarden_Store::log( 'error', 0, 'store_api:' . (int) $response->get_status() . ':' . sanitize_key( $code ) );
		}
		return $response;
	}

	/* ------------------------------------------------------------------
	 * Baseline
	 * ---------------------------------------------------------------- */

	/**
	 * Hour of the week (0 = Monday 00:00) in the store's time zone.
	 *
	 * @param int $time Unix time.
	 * @return int
	 */
	private static function hour_of_week( $time ) {
		$dt = new DateTime( '@' . (int) $time );
		$dt->setTimezone( wp_timezone() );
		return ( (int) $dt->format( 'N' ) - 1 ) * 24 + (int) $dt->format( 'G' );
	}

	/**
	 * Usual orders per hour of the week, learned from the last 4 weeks.
	 *
	 * @return array{ready:bool,days:float,orders:int,rates:float[]}
	 */
	public static function baseline() {
		$cached = get_transient( 'pulsewarden_baseline' );
		if ( is_array( $cached ) && isset( $cached['rates'] ) ) {
			return $cached;
		}
		$since = (int) get_option( 'pulsewarden_since', time() );
		$days  = max( 0, min( 28, ( time() - $since ) / DAY_IN_SECONDS ) );
		$times = PulseWarden_Store::times_since( 'order', 28 * DAY_IN_SECONDS );
		$count = array_fill( 0, 168, 0 );
		foreach ( $times as $t ) {
			++$count[ self::hour_of_week( $t ) ];
		}
		$weeks = max( $days / 7, 1 / 7 );
		$rates = array();
		foreach ( $count as $how => $n ) {
			$rates[ $how ] = $n / $weeks;
		}
		$base = array(
			'ready'  => $days >= 7 && count( $times ) >= 10,
			'days'   => round( $days, 1 ),
			'orders' => count( $times ),
			'rates'  => $rates,
		);
		set_transient( 'pulsewarden_baseline', $base, 3 * HOUR_IN_SECONDS );
		return $base;
	}

	/**
	 * Orders you would normally get between two times.
	 *
	 * @param int $from Unix time.
	 * @param int $to   Unix time.
	 * @return float
	 */
	public static function expected_between( $from, $to ) {
		$base  = self::baseline();
		$from  = max( (int) $from, (int) $to - 7 * DAY_IN_SECONDS );
		$total = 0.0;
		$t     = $from;
		while ( $t < $to ) {
			$next   = min( $to, ( (int) floor( $t / HOUR_IN_SECONDS ) + 1 ) * HOUR_IN_SECONDS );
			$total += $base['rates'][ self::hour_of_week( $t ) ] * ( ( $next - $t ) / HOUR_IN_SECONDS );
			$t      = $next;
		}
		return $total;
	}

	/**
	 * Start of today in the store's time zone.
	 *
	 * @return int
	 */
	public static function today_start() {
		$dt = new DateTime( 'today', wp_timezone() );
		return $dt->getTimestamp();
	}

	/* ------------------------------------------------------------------
	 * Checks
	 * ---------------------------------------------------------------- */

	/**
	 * Check names.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'heartbeat' => __( 'Order heartbeat', 'pulsewarden' ),
			'failed'    => __( 'Failed payments', 'pulsewarden' ),
			'errors'    => __( 'Checkout errors', 'pulsewarden' ),
			'tasks'     => __( 'Scheduled tasks', 'pulsewarden' ),
		);
	}

	/**
	 * Run every check and return the result of each.
	 *
	 * @return array
	 */
	public static function evaluate() {
		$s   = 'PulseWarden_Settings';
		$out = array();

		// Heartbeat.
		if ( ! $s::on( 'heartbeat' ) ) {
			$out['heartbeat'] = array( 'state' => 'off', 'text' => __( 'Turned off in Settings.', 'pulsewarden' ) );
		} else {
			$base = self::baseline();
			$last = PulseWarden_Store::last( 'order' );
			if ( ! $base['ready'] || ! $last ) {
				$out['heartbeat'] = array(
					'state' => 'learning',
					'text'  => sprintf(
						/* translators: 1: number of orders seen, 2: days of history */
						__( 'Learning your normal order rhythm: %1$s orders over %2$s days so far. Alerts start after 7 days and 10 orders.', 'pulsewarden' ),
						number_format_i18n( $base['orders'] ),
						number_format_i18n( $base['days'], 1 )
					),
				);
			} else {
				$quiet    = time() - $last;
				$expected = self::expected_between( $last, time() );
				$hours    = round( $quiet / HOUR_IN_SECONDS, 1 );
				if ( $quiet >= (int) $s::get( 'quiet_hours' ) * HOUR_IN_SECONDS && $expected >= (int) $s::get( 'expected_orders' ) ) {
					$out['heartbeat'] = array(
						'state' => 'issue',
						'text'  => sprintf(
							/* translators: 1: hours, 2: number of orders */
							__( 'No new order for %1$s hours. On a normal day you would have about %2$s orders in this time.', 'pulsewarden' ),
							number_format_i18n( $hours, 1 ),
							number_format_i18n( round( $expected ) )
						),
					);
				} else {
					$out['heartbeat'] = array(
						'state' => 'ok',
						'text'  => sprintf(
							/* translators: %s: time difference */
							__( 'Last order %s ago. Orders are coming in as usual.', 'pulsewarden' ),
							human_time_diff( $last )
						),
					);
				}
			}
		}

		// Failed payments.
		if ( ! $s::on( 'failed_watch' ) ) {
			$out['failed'] = array( 'state' => 'off', 'text' => __( 'Turned off in Settings.', 'pulsewarden' ) );
		} else {
			$failed  = PulseWarden_Store::count_since( 'failed', HOUR_IN_SECONDS );
			$orders  = PulseWarden_Store::count_since( 'order', HOUR_IN_SECONDS );
			$percent = (int) round( 100 * $failed / max( 1, max( $orders, $failed ) ) );
			if ( $failed >= (int) $s::get( 'failed_min' ) && $percent >= (int) $s::get( 'failed_percent' ) ) {
				$out['failed'] = array(
					'state' => 'issue',
					'text'  => sprintf(
						/* translators: 1: failed payments, 2: percent */
						_n( '%1$s payment failed in the last hour (%2$s%% of orders). Check your payment gateway, or a card testing attack may be running.', '%1$s payments failed in the last hour (%2$s%% of orders). Check your payment gateway, or a card testing attack may be running.', $failed, 'pulsewarden' ),
						number_format_i18n( $failed ),
						number_format_i18n( $percent )
					),
				);
			} else {
				$out['failed'] = array(
					'state' => 'ok',
					'text'  => sprintf(
						/* translators: %s: failed payments */
						_n( '%s failed payment in the last hour. Normal.', '%s failed payments in the last hour. Normal.', $failed, 'pulsewarden' ),
						number_format_i18n( $failed )
					),
				);
			}
		}

		// Checkout errors.
		if ( ! $s::on( 'errors_watch' ) ) {
			$out['errors'] = array( 'state' => 'off', 'text' => __( 'Turned off in Settings.', 'pulsewarden' ) );
		} else {
			$errors = PulseWarden_Store::count_since( 'error', HOUR_IN_SECONDS );
			$usual  = PulseWarden_Store::count_since( 'error', 7 * DAY_IN_SECONDS ) / 168;
			if ( $errors >= (int) $s::get( 'errors_min' ) && $errors >= 3 * $usual ) {
				$out['errors'] = array(
					'state' => 'issue',
					'text'  => $usual < 0.1
						? sprintf(
							/* translators: %s: errors */
							_n( '%s checkout error in the last hour, usually none. Customers may not be able to finish their order.', '%s checkout errors in the last hour, usually none. Customers may not be able to finish their order.', $errors, 'pulsewarden' ),
							number_format_i18n( $errors )
						)
						: sprintf(
							/* translators: 1: errors, 2: usual errors per hour */
							_n( '%1$s checkout error in the last hour, usually about %2$s. Customers may not be able to finish their order.', '%1$s checkout errors in the last hour, usually about %2$s. Customers may not be able to finish their order.', $errors, 'pulsewarden' ),
							number_format_i18n( $errors ),
							number_format_i18n( $usual, 1 )
						),
				);
			} else {
				$out['errors'] = array(
					'state' => 'ok',
					'text'  => sprintf(
						/* translators: %s: errors */
						_n( '%s checkout error in the last hour. Normal.', '%s checkout errors in the last hour. Normal.', $errors, 'pulsewarden' ),
						number_format_i18n( $errors )
					),
				);
			}
		}

		// Scheduled tasks.
		if ( ! $s::on( 'tasks_watch' ) ) {
			$out['tasks'] = array( 'state' => 'off', 'text' => __( 'Turned off in Settings.', 'pulsewarden' ) );
		} else {
			$stuck = self::stuck_tasks();
			if ( $stuck['actions'] >= (int) $s::get( 'tasks_min' ) || $stuck['cron'] >= 10 ) {
				$out['tasks'] = array(
					'state' => 'issue',
					'text'  => sprintf(
						/* translators: 1: background actions, 2: cron events */
						__( '%1$s WooCommerce background actions and %2$s WordPress cron events are more than an hour late. Emails, stock updates and renewals may be waiting.', 'pulsewarden' ),
						number_format_i18n( $stuck['actions'] ),
						number_format_i18n( $stuck['cron'] )
					),
				);
			} else {
				$out['tasks'] = array( 'state' => 'ok', 'text' => __( 'Background tasks are running on time.', 'pulsewarden' ) );
			}
		}

		return $out;
	}

	/**
	 * Late background work.
	 *
	 * @return array{actions:int,cron:int}
	 */
	public static function stuck_tasks() {
		$actions = 0;
		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			$ids     = as_get_scheduled_actions(
				array(
					'status'       => 'pending',
					'date'         => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
					'date_compare' => '<=',
					'per_page'     => 1000,
				),
				'ids'
			);
			$actions = is_array( $ids ) ? count( $ids ) : 0;
		}
		$cron = 0;
		$jobs = function_exists( 'wp_get_ready_cron_jobs' ) ? wp_get_ready_cron_jobs() : array();
		foreach ( (array) $jobs as $time => $hooks ) {
			if ( (int) $time < time() - HOUR_IN_SECONDS ) {
				foreach ( (array) $hooks as $events ) {
					$cron += count( (array) $events );
				}
			}
		}
		return array(
			'actions' => $actions,
			'cron'    => $cron,
		);
	}

	/**
	 * Run the checks, open and close alerts, send emails.
	 *
	 * @return array Results.
	 */
	public static function run() {
		update_option( 'pulsewarden_last_check', time(), false );
		$results = self::evaluate();
		if ( ! PulseWarden_Settings::on( 'enabled' ) ) {
			return $results;
		}
		$state  = get_option( self::STATE, array() );
		$state  = is_array( $state ) ? $state : array();
		$labels = self::labels();
		foreach ( $results as $key => $res ) {
			$open = ! empty( $state[ $key ]['open'] );
			if ( 'issue' === $res['state'] && ! $open ) {
				$state[ $key ] = array(
					'open'  => true,
					'since' => time(),
				);
				PulseWarden_Store::log( 'alert', 0, $key . '|open|' . $res['text'] );
				/**
				 * A health problem started.
				 *
				 * @param string $key  Check key: heartbeat, failed, errors or tasks.
				 * @param string $text What is wrong.
				 */
				do_action( 'pulsewarden_alert_opened', $key, $res['text'] );
				/* translators: 1: check name, 2: site name */
				self::email( sprintf( __( '%1$s problem on %2$s', 'pulsewarden' ), $labels[ $key ], wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), $res['text'], 'issue' );
			} elseif ( 'issue' !== $res['state'] && $open ) {
				$since         = (int) $state[ $key ]['since'];
				$state[ $key ] = array( 'open' => false );
				$text          = sprintf(
					/* translators: 1: what is normal now, 2: duration */
					__( '%1$s The problem lasted %2$s.', 'pulsewarden' ),
					$res['text'],
					human_time_diff( $since )
				);
				PulseWarden_Store::log( 'alert', 0, $key . '|resolved|' . $text );
				/**
				 * A health problem is over.
				 *
				 * @param string $key  Check key.
				 * @param string $text What is normal again.
				 */
				do_action( 'pulsewarden_alert_resolved', $key, $text );
				if ( PulseWarden_Settings::on( 'resolved_email' ) ) {
					/* translators: 1: check name, 2: site name */
					self::email( sprintf( __( '%1$s back to normal on %2$s', 'pulsewarden' ), $labels[ $key ], wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), $text, 'ok' );
				}
			}
		}
		update_option( self::STATE, $state, false );
		return $results;
	}

	/**
	 * Open problems.
	 *
	 * @return array Keys of open checks.
	 */
	public static function open_issues() {
		$state = get_option( self::STATE, array() );
		$open  = array();
		foreach ( (array) $state as $key => $row ) {
			if ( ! empty( $row['open'] ) ) {
				$open[] = $key;
			}
		}
		return $open;
	}

	/**
	 * Send an alert email.
	 *
	 * @param string $subject Subject.
	 * @param string $text    Main sentence.
	 * @param string $tone    issue|ok|test.
	 * @return bool
	 */
	public static function email( $subject, $text, $tone = 'issue' ) {
		$colour = 'issue' === $tone ? '#b42318' : '#12704c';
		$link   = admin_url( 'admin.php?page=pulsewarden' );
		$site   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$body   = '<div style="background:#f6f8f7;padding:24px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
			. '<div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e2e8e5;border-radius:14px;overflow:hidden;">'
			. '<div style="background:#0b4f35;color:#ffffff;padding:20px 24px;font-size:18px;font-weight:700;">PulseWarden &middot; ' . esc_html( $site ) . '</div>'
			. '<div style="padding:24px;">'
			. '<p style="margin:0 0 8px;font-size:20px;font-weight:700;color:' . esc_attr( $colour ) . ';">' . esc_html( $subject ) . '</p>'
			. '<p style="margin:0 0 20px;font-size:16px;line-height:1.6;color:#33433c;">' . esc_html( $text ) . '</p>'
			. '<a href="' . esc_url( $link ) . '" style="display:inline-block;background:#12704c;color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;padding:12px 20px;border-radius:10px;">' . esc_html__( 'Open the dashboard', 'pulsewarden' ) . '</a>'
			. '<p style="margin:20px 0 0;font-size:16px;color:#5f6f68;">' . esc_html__( 'You get one email when a problem starts and one when it is over.', 'pulsewarden' ) . '</p>'
			. '</div></div></div>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		// Send from the store's own address. Many hosts reject the default wordpress@ sender.
		$from = get_option( 'woocommerce_email_from_address' );
		if ( is_email( $from ) ) {
			$name      = get_option( 'woocommerce_email_from_name' );
			$headers[] = 'From: ' . str_replace( array( "\r", "\n", '<', '>' ), '', $name ? $name : $site ) . ' <' . $from . '>';
		}
		return wp_mail( PulseWarden_Settings::recipients(), $subject, $body, $headers );
	}

	/* ------------------------------------------------------------------
	 * History import
	 * ---------------------------------------------------------------- */

	/**
	 * Import up to 4 weeks of past orders, 100 per run, so the heartbeat can start right away on a busy store.
	 *
	 * @param int $page Page.
	 * @return void
	 */
	public static function backfill( $page = 1 ) {
		$page  = max( 1, (int) $page );
		$until = (int) get_option( 'pulsewarden_backfill', 0 );
		if ( ! $until ) {
			return;
		}
		$statuses = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) );
		$orders   = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => $statuses,
				'date_created' => ( $until - 28 * DAY_IN_SECONDS ) . '...' . $until,
				'limit'        => 100,
				'page'         => $page,
				'orderby'      => 'date',
				'order'        => 'ASC',
			)
		);
		$first = 0;
		foreach ( $orders as $order ) {
			$created = $order->get_date_created();
			if ( ! $created || PulseWarden_Store::has( 'order', $order->get_id() ) ) {
				continue;
			}
			$ts = $created->getTimestamp();
			PulseWarden_Store::log( 'order', $order->get_id(), 'import', $ts );
			if ( 'failed' === $order->get_status() ) {
				PulseWarden_Store::log( 'failed', $order->get_id(), 'import', $ts );
			}
			if ( ! $first ) {
				$first = $ts;
			}
		}
		if ( 1 === $page && $first ) {
			update_option( 'pulsewarden_since', min( (int) get_option( 'pulsewarden_since', time() ), $first ), false );
		}
		delete_transient( 'pulsewarden_baseline' );
		if ( count( $orders ) === 100 && $page < 200 && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'pulsewarden_backfill', array( $page + 1 ), 'pulsewarden' );
		} else {
			delete_option( 'pulsewarden_backfill' );
		}
	}
}
