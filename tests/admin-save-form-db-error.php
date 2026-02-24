<?php
/**
 * Regression test for RTG_Admin::save_form() DB failure handling.
 */

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return null;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'get_privacy_policy_url' ) ) {
	function get_privacy_policy_url() {
		return 'https://example.test/privacy-policy/';
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $url ) {
		global $rtg_redirect_url;
		$rtg_redirect_url = $url;
		return true;
	}
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

require_once __DIR__ . '/../includes/class-admin.php';

class RTG_Test_Save_Form_DB_Error_WPDB {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $last_error = 'Table schema mismatch';

	public function insert() {
		return false;
	}

	public function update() {
		return false;
	}

	public function get_row() {
		return null;
	}
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Assertion failed: {$message}\n" );
		exit( 1 );
	}
};

global $wpdb;
$wpdb = new RTG_Test_Save_Form_DB_Error_WPDB();

$_POST = array(
	'id'                  => 0,
	'name'                => 'Broken Insert Form',
	'fields_schema'       => '[{"key":"email","label":"Email","type":"email"}]',
	'lead_email_mode'     => 'none',
	'internal_notify'     => '',
	'internal_recipients' => '',
);

register_shutdown_function(
	static function () use ( $assert ) {
		global $rtg_redirect_url;

		$assert( is_string( $rtg_redirect_url ), 'save_form should redirect after DB write attempt.' );
		$assert( false !== strpos( $rtg_redirect_url, 'rtg_notice_type=error' ), 'DB failure should use an error notice type.' );
		$assert( false !== strpos( $rtg_redirect_url, rawurlencode( 'Form could not be saved. Check database schema.' ) ), 'DB failure should include user-safe error notice.' );
		$assert( false === strpos( $rtg_redirect_url, rawurlencode( 'Form saved.' ) ), 'DB failure should not return a success notice.' );

		echo "save_form DB failure assertions passed.\n";
	}
);

$method = new ReflectionMethod( 'RTG_Admin', 'save_form' );
$method->setAccessible( true );
$method->invoke( null );
