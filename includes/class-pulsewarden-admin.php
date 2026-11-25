<?php
/**
 * Admin screens.
 *
 * @package PulseWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Overview, Alerts and Settings.
 */
class PulseWarden_Admin {

	const PAGE = 'pulsewarden';
	const CAP  = 'manage_woocommerce';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 61 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_pulsewarden_action', array( __CLASS__, 'handle_action' ) );
		add_action( 'update_option_' . PulseWarden_Settings::OPTION, array( __CLASS__, 'after_save' ) );
		add_filter( 'option_page_capability_pulsewarden', array( __CLASS__, 'capability' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PULSEWARDEN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Capability for saving settings.
	 *
	 * @return string
	 */
	public static function capability() {
		return self::CAP;
	}

	/**
	 * Forget the learned baseline after a save so new numbers apply at once.
	 *
	 * @return void
	 */
	public static function after_save() {
		delete_transient( 'pulsewarden_baseline' );
	}

	/**
	 * Tabs.
	 *
	 * @return array
	 */
	private static function tabs() {
		$tabs = array(
			'overview' => __( 'Overview', 'pulsewarden' ),
			'alerts'   => __( 'Alerts', 'pulsewarden' ),
			'settings' => __( 'Settings', 'pulsewarden' ),
		);
		if ( ! class_exists( 'PWP_Plugin' ) ) {
			$tabs['pro'] = __( 'Pro', 'pulsewarden' );
		}
		return $tabs;
	}

	/**
	 * Page URL.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function url( $tab = 'overview', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Plugins screen link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Dashboard', 'pulsewarden' ) . '</a>' );
		return $links;
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon.
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1.5 2.8 4.2v5.2c0 4.4 3 7.7 7.2 8.9 4.2-1.2 7.2-4.5 7.2-8.9V4.2L10 1.5Zm-4.6 8.3h2.1l1.2-2.6 1.8 4.6 1.3-2h2.8v1.3h-2.1l-2.2 3.2-1.6-4.2-.4.9H5.4V9.8Z"/></svg>'
		);
		add_menu_page( __( 'PulseWarden', 'pulsewarden' ), __( 'PulseWarden', 'pulsewarden' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ), $icon, 57 );
		add_submenu_page( self::PAGE, __( 'Overview', 'pulsewarden' ), __( 'Overview', 'pulsewarden' ), self::CAP, self::PAGE, array( __CLASS__, 'render' ) );
		$tabs = self::tabs();
		foreach ( array_intersect( array_keys( self::tabs() ), array( 'alerts', 'settings', 'pro' ) ) as $tab ) {
			add_submenu_page( self::PAGE, $tabs[ $tab ], $tabs[ $tab ], self::CAP, 'admin.php?page=' . self::PAGE . '&tab=' . $tab );
		}
	}

	/**
	 * Highlight the open tab in the menu.
	 *
	 * @param string|null $file Submenu file.
	 * @return string|null
	 */
	public static function submenu_file( $file ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- menu highlight.
		if ( self::PAGE !== $page ) {
			return $file;
		}
		$tab = self::current_tab();
		return 'overview' === $tab ? self::PAGE : 'admin.php?page=' . self::PAGE . '&tab=' . $tab;
	}

	/**
	 * Styles.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		$file = PULSEWARDEN_PATH . 'assets/css/admin.css';
		wp_enqueue_style( 'pulsewarden-admin', PULSEWARDEN_URL . 'assets/css/admin.css', array(), PULSEWARDEN_VERSION . '.' . (int) filemtime( $file ) );
	}

	/**
	 * Settings registration.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'pulsewarden',
			PulseWarden_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'PulseWarden_Settings', 'sanitize' ),
				'default'           => PulseWarden_Settings::defaults(),
			)
		);
	}

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private static function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch.
		return isset( self::tabs()[ $tab ] ) ? $tab : 'overview';
	}

	/**
	 * Logo.
	 *
	 * @return void
	 */
	private static function logo() {
		?>
		<svg width="34" height="34" viewBox="0 0 32 32" aria-hidden="true" focusable="false"><path d="M16 2.5 4.5 6.8v8.4c0 7.1 4.9 12.4 11.5 14.3 6.6-1.9 11.5-7.2 11.5-14.3V6.8L16 2.5Z" fill="#12704c"/><path d="M8.5 16h3.2l1.9-4 2.8 7.5 2-3.5h5.1" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="23.5" cy="21.5" r="4" fill="#e0457b" stroke="#fff" stroke-width="1.5"/></svg>
		<?php
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tab = self::current_tab();
		?>
		<div class="wrap pulsewarden">
			<div class="ow-hero">
				<div class="pulsewarden-header">
					<span class="ow-logo"><?php self::logo(); ?></span>
					<div class="ow-title">
						<h1><?php esc_html_e( 'PulseWarden', 'pulsewarden' ); ?></h1>
						<p class="ow-tagline"><?php esc_html_e( 'Knows when your store stops selling, and tells you first.', 'pulsewarden' ); ?></p>
					</div>
					<span class="pulsewarden-version"><?php echo esc_html( 'v' . PULSEWARDEN_VERSION ); ?></span>
				</div>
				<nav class="pulsewarden-tabs" aria-label="<?php esc_attr_e( 'PulseWarden sections', 'pulsewarden' ); ?>">
					<?php foreach ( self::tabs() as $key => $label ) : ?>
						<a href="<?php echo esc_url( self::url( $key ) ); ?>"<?php echo 'pro' === $key ? ' class="ow-tab-pro"' : ''; ?><?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
			<hr class="wp-header-end">
			<?php
			self::notices();
			if ( 'settings' === $tab ) {
				self::render_settings();
			} elseif ( 'alerts' === $tab ) {
				self::render_alerts();
			} elseif ( 'pro' === $tab ) {
				self::render_pro();
			} else {
				self::render_overview();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Result notices after an action.
	 *
	 * @return void
	 */
	private static function notices() {
		$msg  = isset( $_GET['pw_msg'] ) ? sanitize_key( wp_unslash( $_GET['pw_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$msgs = array(
			'checked'   => array( 'success', __( 'All checks ran just now.', 'pulsewarden' ) ),
			'sent'      => array( 'success', __( 'Test email sent. If it does not arrive in a few minutes, your site cannot send email and alerts will not reach you either.', 'pulsewarden' ) ),
			'notsent'   => array( 'error', __( 'WordPress could not send the test email. Install or check an SMTP plugin so alerts can reach you.', 'pulsewarden' ) ),
		);
		if ( isset( $msgs[ $msg ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $msgs[ $msg ][0] ), esc_html( $msgs[ $msg ][1] ) );
		}
	}

	/**
	 * Action buttons.
	 *
	 * @param string $do    Action.
	 * @param string $label Label.
	 * @param string $class Button class.
	 * @return void
	 */
	private static function action_button( $do, $label, $class ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pulsewarden_action">
			<input type="hidden" name="do" value="<?php echo esc_attr( $do ); ?>">
			<?php wp_nonce_field( 'pulsewarden_action' ); ?>
			<?php submit_button( $label, $class, 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Overview.
	 *
	 * @return void
	 */
	private static function render_overview() {
		$on      = PulseWarden_Settings::on( 'enabled' );
		$results = PulseWarden_Monitor::evaluate();
		$open    = PulseWarden_Monitor::open_issues();
		$issues  = array();
		$labels  = PulseWarden_Monitor::labels();
		foreach ( $results as $key => $res ) {
			if ( 'issue' === $res['state'] ) {
				$issues[] = $labels[ $key ];
			}
		}
		$state = ! $on ? 'is-off' : ( $issues ? 'is-off' : ( 'learning' === $results['heartbeat']['state'] ? 'is-learning' : 'is-on' ) );
		$last  = PulseWarden_Store::last( 'order' );
		$today = PulseWarden_Monitor::today_start();
		$base  = PulseWarden_Monitor::baseline();
		$usual = $base['ready'] ? round( PulseWarden_Monitor::expected_between( $today, time() ) ) : null;
		$check = (int) get_option( 'pulsewarden_last_check', 0 );
		?>
		<section class="pulsewarden-banner <?php echo esc_attr( $state ); ?>">
			<div>
				<?php if ( ! $on ) : ?>
					<h2><?php esc_html_e( 'Alerts are off', 'pulsewarden' ); ?></h2>
					<p><?php esc_html_e( 'PulseWarden still watches, but sends no email. Turn alerts on in Settings.', 'pulsewarden' ); ?></p>
				<?php elseif ( $issues ) : ?>
					<h2><?php esc_html_e( 'Something needs your attention', 'pulsewarden' ); ?></h2>
					<p>
						<?php
						/* translators: %s: list of checks */
						echo esc_html( sprintf( __( 'Problem found in: %s. Details are below.', 'pulsewarden' ), implode( ', ', $issues ) ) );
						?>
					</p>
				<?php elseif ( 'is-learning' === $state ) : ?>
					<h2><?php esc_html_e( 'Watching and learning', 'pulsewarden' ); ?></h2>
					<p><?php esc_html_e( 'Payment, checkout and task checks are already on. The order heartbeat starts once it knows how your store normally sells.', 'pulsewarden' ); ?></p>
				<?php else : ?>
					<h2><?php esc_html_e( 'Your store is healthy', 'pulsewarden' ); ?></h2>
					<p><?php esc_html_e( 'Orders, payments, checkout and background tasks all look normal. You will get an email if that changes.', 'pulsewarden' ); ?></p>
				<?php endif; ?>
			</div>
		</section>

		<section class="pulsewarden-panel">
			<dl class="pulsewarden-figures">
				<div><dt><?php esc_html_e( 'Last order', 'pulsewarden' ); ?></dt><dd>
					<?php
					/* translators: %s: time difference */
					echo esc_html( $last ? sprintf( __( '%s ago', 'pulsewarden' ), human_time_diff( $last ) ) : __( 'None yet', 'pulsewarden' ) );
					?>
				</dd></div>
				<div><dt>
					<?php
					echo esc_html(
						null === $usual
							? __( 'Orders today', 'pulsewarden' )
							/* translators: %s: usual number of orders */
							: sprintf( __( 'Orders today (usually %s by now)', 'pulsewarden' ), number_format_i18n( $usual ) )
					);
					?>
				</dt><dd><?php echo esc_html( number_format_i18n( PulseWarden_Store::count_since( 'order', time() - $today ) ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Failed payments, last hour', 'pulsewarden' ); ?></dt><dd><?php echo esc_html( number_format_i18n( PulseWarden_Store::count_since( 'failed', HOUR_IN_SECONDS ) ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Checkout errors, last hour', 'pulsewarden' ); ?></dt><dd><?php echo esc_html( number_format_i18n( PulseWarden_Store::count_since( 'error', HOUR_IN_SECONDS ) ) ); ?></dd></div>
			</dl>
		</section>

		<section class="pulsewarden-panel">
			<div class="pulsewarden-panel-head">
				<h2><?php esc_html_e( 'Health checks', 'pulsewarden' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						$check
							/* translators: %s: time difference */
							? sprintf( __( 'Checked automatically every 15 minutes. Last run %s ago.', 'pulsewarden' ), human_time_diff( $check ) )
							: __( 'Checked automatically every 15 minutes.', 'pulsewarden' )
					);
					?>
				</p>
			</div>
			<ul class="pw-checks">
				<?php
				foreach ( $results as $key => $res ) :
					$dot = array(
						'ok'       => 'is-on',
						'issue'    => 'is-off',
						'learning' => '',
						'off'      => 'is-wait',
					);
					$tag = array(
						'ok'       => array( 'is-ok', __( 'Normal', 'pulsewarden' ) ),
						'issue'    => array( 'is-blocked', in_array( $key, $open, true ) ? __( 'Alert sent', 'pulsewarden' ) : __( 'Problem', 'pulsewarden' ) ),
						'learning' => array( 'is-would', __( 'Learning', 'pulsewarden' ) ),
						'off'      => array( '', __( 'Off', 'pulsewarden' ) ),
					);
					?>
					<li>
						<span class="pulsewarden-dot <?php echo esc_attr( $dot[ $res['state'] ] ); ?>"></span>
						<div><strong><?php echo esc_html( $labels[ $key ] ); ?></strong><p><?php echo esc_html( $res['text'] ); ?></p></div>
						<span class="pulsewarden-tag <?php echo esc_attr( $tag[ $res['state'] ][0] ); ?>"><?php echo esc_html( $tag[ $res['state'] ][1] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<?php if ( $check && $check < time() - HOUR_IN_SECONDS ) : ?>
			<div class="pulsewarden-callout">
				<p><?php esc_html_e( 'The automatic checks have not run for over an hour. WordPress cron only runs when someone visits the site. Ask your host to run wp-cron.php every 5 minutes so alerts arrive on time.', 'pulsewarden' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="pulsewarden-actions pw-inline">
			<?php self::action_button( 'check', __( 'Run checks now', 'pulsewarden' ), 'pulsewarden-btn' ); ?>
			<?php self::action_button( 'test', __( 'Send a test email', 'pulsewarden' ), 'pulsewarden-btn-secondary' ); ?>
		</div>
		<?php
	}

	/**
	 * Alerts history.
	 *
	 * @return void
	 */
	private static function render_alerts() {
		$rows   = PulseWarden_Store::alerts( 50 );
		$labels = PulseWarden_Monitor::labels();
		?>
		<section class="pulsewarden-panel">
			<div class="pulsewarden-panel-head">
				<h2><?php esc_html_e( 'Alert history', 'pulsewarden' ); ?></h2>
				<p>
					<?php
					/* translators: %s: email addresses */
					echo esc_html( sprintf( __( 'Emails go to %s.', 'pulsewarden' ), implode( ', ', PulseWarden_Settings::recipients() ) ) );
					?>
				</p>
			</div>
			<?php if ( empty( $rows ) ) : ?>
				<div class="pulsewarden-empty">
					<strong><?php esc_html_e( 'No alerts so far', 'pulsewarden' ); ?></strong>
					<?php esc_html_e( 'When a check finds a problem, the alert and the all-clear show up here.', 'pulsewarden' ); ?>
				</div>
			<?php else : ?>
				<table class="pulsewarden-table">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'When', 'pulsewarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Check', 'pulsewarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'pulsewarden' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What happened', 'pulsewarden' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td class="is-time">
								<?php
								/* translators: %s: time difference */
								echo esc_html( sprintf( __( '%s ago', 'pulsewarden' ), human_time_diff( $row['time'] ) ) );
								?>
							</td>
							<td><?php echo esc_html( isset( $labels[ $row['check'] ] ) ? $labels[ $row['check'] ] : $row['check'] ); ?></td>
							<td>
								<?php if ( 'open' === $row['state'] ) : ?>
									<span class="pulsewarden-tag is-blocked"><?php esc_html_e( 'Problem', 'pulsewarden' ); ?></span>
								<?php else : ?>
									<span class="pulsewarden-tag is-ok"><?php esc_html_e( 'Resolved', 'pulsewarden' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['text'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Switch.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @return void
	 */
	private static function field_switch( $key, $label ) {
		?>
		<label class="pulsewarden-switch">
			<input type="checkbox" name="<?php echo esc_attr( PulseWarden_Settings::OPTION . '[' . $key . ']' ); ?>" value="yes" <?php checked( PulseWarden_Settings::get( $key ), 'yes' ); ?>>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Number input inside a sentence.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @return string
	 */
	private static function field_number( $key, $label ) {
		return sprintf(
			'<input type="number" min="0" step="1" name="%1$s" value="%2$s" aria-label="%3$s">',
			esc_attr( PulseWarden_Settings::OPTION . '[' . $key . ']' ),
			esc_attr( PulseWarden_Settings::get( $key ) ),
			esc_attr( $label )
		);
	}

	/**
	 * A sentence with inputs.
	 *
	 * @param string $html Escaped HTML.
	 * @return void
	 */
	private static function sentence( $html ) {
		echo '<p>' . wp_kses(
			$html,
			array(
				'input' => array(
					'type'       => true,
					'min'        => true,
					'step'       => true,
					'name'       => true,
					'value'      => true,
					'aria-label' => true,
				),
			)
		) . '</p>';
	}

	/**
	 * Settings.
	 *
	 * @return void
	 */
	private static function render_settings() {
		settings_errors();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'pulsewarden' ); ?>
			<section class="pulsewarden-panel">
				<div class="pulsewarden-panel-head"><h2><?php esc_html_e( 'Alerts', 'pulsewarden' ); ?></h2></div>
				<div class="pulsewarden-row">
					<div class="pulsewarden-row-label"><?php esc_html_e( 'Email alerts', 'pulsewarden' ); ?><p><?php esc_html_e( 'One email when a problem starts, one when it is over.', 'pulsewarden' ); ?></p></div>
					<div class="pulsewarden-row-control">
						<?php self::field_switch( 'enabled', __( 'Send alert emails', 'pulsewarden' ) ); ?>
						<?php self::field_switch( 'resolved_email', __( 'Also email me when things are back to normal', 'pulsewarden' ) ); ?>
					</div>
				</div>
				<div class="pulsewarden-row">
					<div class="pulsewarden-row-label"><label for="pw-to"><?php esc_html_e( 'Send to', 'pulsewarden' ); ?></label><p><?php esc_html_e( 'Separate addresses with commas.', 'pulsewarden' ); ?></p></div>
					<div class="pulsewarden-row-control">
						<input id="pw-to" type="text" class="pw-wide" name="<?php echo esc_attr( PulseWarden_Settings::OPTION ); ?>[recipients]" value="<?php echo esc_attr( PulseWarden_Settings::get( 'recipients' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<p class="pulsewarden-help"><?php esc_html_e( 'Leave empty to use the site admin email.', 'pulsewarden' ); ?></p>
					</div>
				</div>
			</section>

			<section class="pulsewarden-panel">
				<div class="pulsewarden-panel-head"><h2><?php esc_html_e( 'Checks', 'pulsewarden' ); ?></h2></div>
				<div class="pulsewarden-row">
					<div class="pulsewarden-row-label"><?php esc_html_e( 'Order heartbeat', 'pulsewarden' ); ?><p><?php esc_html_e( 'Learns when your store usually sells, so a quiet night never triggers it.', 'pulsewarden' ); ?></p></div>
					<div class="pulsewarden-row-control">
						<?php self::field_switch( 'heartbeat', __( 'Tell me when orders stop', 'pulsewarden' ) ); ?>
						<?php
						self::sentence(
							sprintf(
								/* translators: 1: hours, 2: number of orders */
								esc_html__( 'Alert after %1$s hours without an order, but only when you would normally have had at least %2$s orders in that time.', 'pulsewarden' ),
								self::field_number( 'quiet_hours', __( 'Hours', 'pulsewarden' ) ),
								self::field_number( 'expected_orders', __( 'Orders', 'pulsewarden' ) )
							)
						);
						?>
					</div>
				</div>
				<div class="pulsewarden-row">
					<div class="pulsewarden-row-label"><?php esc_html_e( 'Failed payments', 'pulsewarden' ); ?><p><?php esc_html_e( 'A broken gateway or a card testing attack.', 'pulsewarden' ); ?></p></div>
					<div class="pulsewarden-row-control">
						<?php self::field_switch( 'failed_watch', __( 'Tell me when payments start failing', 'pulsewarden' ) ); ?>
						<?php
						self::sentence(
							sprintf(
								/* translators: 1: number, 2: percent */
								esc_html__( 'Alert when at least %1$s payments fail within an hour and they are %2$s%% or more of all orders.', 'pulsewarden' ),
								self::field_number( 'failed_min', __( 'Failed payments', 'pulsewarden' ) ),
								self::field_number( 'failed_percent', __( 'Percent', 'pulsewarden' ) )
							)
						);
						?>
					</div>
				</div>
				<div class="pulsewarden-row">
					<div class="pulsewarden-row-label"><?php esc_html_e( 'Checkout errors', 'pulsewarden' ); ?><p><?php esc_html_e( 'A plugin update or a theme change that breaks checkout.', 'pulsewarden' ); ?></p></div>
					<div class="pulsewarden-row-control">
						<?php self::field_switch( 'errors_watch', __( 'Tell me when checkout errors jump', 'pulsewarden' ) ); ?>
						<?php
						self::sentence(
							sprintf(
								/* translators: %s: number */
								esc_html__( 'Alert when there are at least %s errors within an hour and three times more than usual.', 'pulsewarden' ),
								self::field_number( 'errors_min', __( 'Errors', 'pulsewarden' ) )
							)
						);
						?>
						<p class="pulsewarden-help"><?php esc_html_e( 'Bots stopped by OrderWarden, CODWarden or BlockWarden are not counted as errors.', 'pulsewarden' ); ?></p>
					</div>
				</div>
				<div class="pulsewarden-row">
					<div class="pulsewarden-row-label"><?php esc_html_e( 'Scheduled tasks', 'pulsewarden' ); ?><p><?php esc_html_e( 'Order emails, stock updates and renewals run in the background.', 'pulsewarden' ); ?></p></div>
					<div class="pulsewarden-row-control">
						<?php self::field_switch( 'tasks_watch', __( 'Tell me when background tasks get stuck', 'pulsewarden' ) ); ?>
						<?php
						self::sentence(
							sprintf(
								/* translators: %s: number */
								esc_html__( 'Alert when %s or more WooCommerce background actions are over an hour late.', 'pulsewarden' ),
								self::field_number( 'tasks_min', __( 'Actions', 'pulsewarden' ) )
							)
						);
						?>
					</div>
				</div>
			</section>

			<section class="pulsewarden-panel">
				<div class="pulsewarden-panel-head"><h2><?php esc_html_e( 'Data', 'pulsewarden' ); ?></h2></div>
				<div class="pulsewarden-row">
					<div class="pulsewarden-row-label"><?php esc_html_e( 'History', 'pulsewarden' ); ?><p><?php esc_html_e( 'Only order numbers and times are kept, no customer details.', 'pulsewarden' ); ?></p></div>
					<div class="pulsewarden-row-control">
						<?php
						self::sentence(
							sprintf(
								/* translators: %s: days */
								esc_html__( 'Keep the history for %s days (at least 35).', 'pulsewarden' ),
								self::field_number( 'retention_days', __( 'Days', 'pulsewarden' ) )
							)
						);
						?>
					</div>
				</div>
				<div class="pulsewarden-row">
					<div class="pulsewarden-row-label"><?php esc_html_e( 'When you delete the plugin', 'pulsewarden' ); ?></div>
					<div class="pulsewarden-row-control"><?php self::field_switch( 'delete_data', __( 'Also delete its settings and history', 'pulsewarden' ) ); ?></div>
				</div>
			</section>

			<div class="pulsewarden-actions">
				<?php submit_button( __( 'Save settings', 'pulsewarden' ), 'pulsewarden-btn', 'submit', false ); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Run checks or send a test email.
	 *
	 * @return void
	 */
	public static function handle_action() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'pulsewarden' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'pulsewarden_action' );
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'test' === $do ) {
			$ok = PulseWarden_Monitor::email(
				/* translators: %s: site name */
				sprintf( __( 'Test alert from %s', 'pulsewarden' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
				__( 'This is a test. When PulseWarden finds a real problem, the email looks like this.', 'pulsewarden' ),
				'test'
			);
			wp_safe_redirect( self::url( 'overview', array( 'pw_msg' => $ok ? 'sent' : 'notsent' ) ) );
			exit;
		}
		delete_transient( 'pulsewarden_baseline' );
		PulseWarden_Monitor::run();
		wp_safe_redirect( self::url( 'overview', array( 'pw_msg' => 'checked' ) ) );
		exit;
	}
	/**
	 * Pro page: what the add-on adds. Shows "Coming soon" until a store link is set.
	 *
	 * @return void
	 */
	private static function render_pro() {
		$points = array(
			__( 'Instant alerts on Slack, Discord, Telegram or any webhook', 'pulsewarden' ),
			__( 'A log of plugin, theme and WordPress updates and activations', 'pulsewarden' ),
			__( 'Every alert lists the changes from the 6 hours before', 'pulsewarden' ),
			__( 'Weekly store health report against the week before', 'pulsewarden' ),
		);
		/**
		 * Where to buy PulseWarden Pro. Empty shows "Coming soon".
		 *
		 * @param string $url URL.
		 */
		$buy = (string) apply_filters( 'pulsewarden_pro_url', '' );
		?>
		<section class="ow-sale">
			<div class="ow-sale-main">
				<h2><?php esc_html_e( 'PulseWarden Pro', 'pulsewarden' ); ?></h2>
				<p><?php esc_html_e( 'Hear about problems on your phone within minutes, and see what changed on the site right before.', 'pulsewarden' ); ?></p>
				<ul class="ow-points">
					<?php foreach ( $points as $point ) : ?>
						<li><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="ow-sale-aside">
				<span class="ow-pro-badge"><?php esc_html_e( 'Pro', 'pulsewarden' ); ?></span>
				<h3 style="margin-top:14px"><?php esc_html_e( 'A separate add-on', 'pulsewarden' ); ?></h3>
				<p><?php esc_html_e( 'Everything you use today stays free, with no limits. Pro adds the features on the left.', 'pulsewarden' ); ?></p>
				<?php if ( '' === $buy ) : ?>
					<p><strong><?php esc_html_e( 'Coming soon.', 'pulsewarden' ); ?></strong></p>
				<?php else : ?>
					<a class="button ow-btn-gold" href="<?php echo esc_url( $buy ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See plans', 'pulsewarden' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
