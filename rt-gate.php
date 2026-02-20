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

$rtg_includes = array(
	'includes/class-db.php',
	'includes/class-admin.php',
	'includes/class-events-table.php',
	'includes/class-rest.php',
	'includes/class-token.php',
	'includes/class-events.php',
	'includes/class-webhook.php',
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
