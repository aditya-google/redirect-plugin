<?php
/**
 * Plugin Name:       Redirect Me
 * Plugin URI:        https://wordpress.org/plugins/redirect-me/
 * Description:       A simple plugin to handle your redirects.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            LowQ
 * Author URI:        https://wordpress.org/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       redirect-me
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'WP_STARTER_PLUGIN_VERSION', '1.0.0' );
define( 'WP_STARTER_PLUGIN_FILE', __FILE__ );
define( 'WP_STARTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_STARTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_STARTER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload includes.
require_once WP_STARTER_PLUGIN_DIR . 'includes/class-wp-starter-plugin.php';

/**
 * Initialize the plugin.
 *
 * @return WP_Starter_Plugin
 */
function wp_starter_plugin() {
    return WP_Starter_Plugin::instance();
}

// Kick things off.
add_action( 'plugins_loaded', 'wp_starter_plugin' );

// Activation / deactivation hooks.
register_activation_hook( __FILE__, array( 'WP_Starter_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_Starter_Plugin', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'WP_Starter_Plugin', 'uninstall' ) );
