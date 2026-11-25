<?php
/**
 * Plugin Name:       PulseWarden: Store Health Alerts for WooCommerce
 * Plugin URI:        https://github.com/hoseniqbal/pulsewarden
 * Description:       Emails you when orders stop, payments start failing, checkout throws errors or scheduled tasks get stuck, and again when things are back to normal.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Iqbal Hossen
 * Author URI:        https://profiles.wordpress.org/iqbal511138/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pulsewarden
 * WC requires at least: 7.6
 * WC tested up to:   11.3
 *
 * @package PulseWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PULSEWARDEN_VERSION', '1.0.0' );
define( 'PULSEWARDEN_DB_VERSION', '1' );
define( 'PULSEWARDEN_FILE', __FILE__ );
define( 'PULSEWARDEN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PULSEWARDEN_URL', plugin_dir_url( __FILE__ ) );

require_once PULSEWARDEN_PATH . 'includes/class-pulsewarden-settings.php';
require_once PULSEWARDEN_PATH . 'includes/class-pulsewarden-store.php';
require_once PULSEWARDEN_PATH . 'includes/class-pulsewarden-monitor.php';
require_once PULSEWARDEN_PATH . 'includes/class-pulsewarden-admin.php';
require_once PULSEWARDEN_PATH . 'includes/class-pulsewarden-plugin.php';

register_activation_hook( __FILE__, array( 'PulseWarden_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PulseWarden_Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'PulseWarden_Plugin', 'init' ) );
