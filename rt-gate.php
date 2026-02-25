<?php
/**
 * Plugin Name:       Real Treasury Gate
 * Plugin URI:        https://realtreasury.com
 * Description:       Gated content plugin with form-to-asset mapping, tokenized access, and event tracking.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Real Treasury
 * Author URI:        https://realtreasury.com
 * Text Domain:       rt-gate
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RTG_PLUGIN_FILE', __FILE__ );
define( 'RTG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RTG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RTG_PLUGIN_VERSION', '0.1.0' );
define( 'RTG_SCHEMA_VERSION', RTG_PLUGIN_VERSION );

$rtg_includes = array(
	'includes/class-db.php',
	'includes/class-admin.php',
	'includes/class-rest.php',
	'includes/class-token.php',
	'includes/class-events.php',
	'includes/class-webhook.php',
	'includes/class-email.php',
	'includes/class-salesforce.php',
	'includes/class-utils.php',
);

foreach ( $rtg_includes as $rtg_include ) {
	$rtg_path = RTG_PLUGIN_DIR . $rtg_include;

	if ( file_exists( $rtg_path ) ) {
		require_once $rtg_path;
	}
}

register_activation_hook( RTG_PLUGIN_FILE, array( 'RTG_DB', 'install' ) );
register_activation_hook( RTG_PLUGIN_FILE, function () {
	update_option( 'rtg_schema_version', RTG_SCHEMA_VERSION );
} );

/**
 * Run schema installation on normal loads when plugin version changes.
 *
 * Keeps existing active installs in sync after plugin updates.
 */
function rtg_maybe_install_schema() {
	$stored_schema_version = get_option( 'rtg_schema_version' );

	if ( is_string( $stored_schema_version ) && version_compare( $stored_schema_version, RTG_SCHEMA_VERSION, '>=' ) ) {
		return;
	}

	RTG_DB::install();
	update_option( 'rtg_schema_version', RTG_SCHEMA_VERSION );
}
add_action( 'plugins_loaded', 'rtg_maybe_install_schema' );

/**
 * Schedule daily expired token cleanup on activation.
 */
register_activation_hook( RTG_PLUGIN_FILE, function () {
	if ( ! wp_next_scheduled( 'rtg_cleanup_expired_tokens' ) ) {
		wp_schedule_event( time(), 'daily', 'rtg_cleanup_expired_tokens' );
	}
} );

/**
 * Clear scheduled cleanup on deactivation.
 */
register_deactivation_hook( RTG_PLUGIN_FILE, function () {
	wp_clear_scheduled_hook( 'rtg_cleanup_expired_tokens' );
} );

add_action( 'rtg_cleanup_expired_tokens', array( 'RTG_Token', 'cleanup_expired' ) );

/**
 * Enqueue the no-download script on the public front-end.
 *
 * Prevents visitors from downloading embedded media unless the element
 * (or a parent) has the CSS class "Download".
 */
function rtg_enqueue_no_download_script() {
	if ( is_admin() ) {
		return;
	}

	$script_path = RTG_PLUGIN_DIR . 'assets/js/rtg-no-download.js';

	wp_enqueue_script(
		'rtg-no-download',
		RTG_PLUGIN_URL . 'assets/js/rtg-no-download.js',
		array(),
		file_exists( $script_path ) ? (string) filemtime( $script_path ) : RTG_PLUGIN_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'rtg_enqueue_no_download_script' );
