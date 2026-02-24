<?php
/**
 * Lightweight assertions for RTG_Admin form schema validation.
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

require_once __DIR__ . '/../includes/class-admin.php';

$method = new ReflectionMethod( 'RTG_Admin', 'validate_fields_schema' );
$method->setAccessible( true );

$normalize_method = new ReflectionMethod( 'RTG_Admin', 'normalize_fields_schema' );
$normalize_method->setAccessible( true );

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Assertion failed: {$message}\n" );
		exit( 1 );
	}
};

$missing_email = array(
	array(
		'key'   => 'first_name',
		'label' => 'First Name',
	),
);
$result_missing_email = $method->invoke( null, $missing_email );
$assert( false === $result_missing_email['valid'], 'Schema missing email should be rejected.' );
$assert(
	'Fields schema must include an email field key.' === $result_missing_email['message'],
	'Schema missing email should return specific error message.'
);

$duplicate_keys = array(
	array(
		'key' => 'email',
	),
	array(
		'key' => 'email',
	),
);
$result_duplicate_keys = $method->invoke( null, $duplicate_keys );
$assert( false === $result_duplicate_keys['valid'], 'Schema with duplicate keys should be rejected.' );
$assert(
	'Field keys must be unique.' === $result_duplicate_keys['message'],
	'Schema with duplicate keys should return specific error message.'
);

$valid_schema = array(
	array(
		'key'   => 'email',
		'label' => 'Email',
		'type'  => 'email',
	),
	array(
		'key'   => 'company',
		'label' => 'Company',
		'type'  => 'text',
	),
);
$result_valid_schema = $method->invoke( null, $valid_schema );
$assert( true === $result_valid_schema['valid'], 'Valid schema should be accepted.' );
$assert( '' === $result_valid_schema['message'], 'Valid schema should return empty message.' );

$legacy_schema = array(
	array(
		'name'        => 'email',
		'title'       => 'Email Address',
		'field_type'  => 'email',
		'is_required' => 'true',
	),
	array(
		'name'       => 'company',
		'title'      => 'Company',
		'field_type' => 'text',
	),
);

$normalized_legacy_schema = $normalize_method->invoke( null, $legacy_schema );
$result_legacy_schema     = $method->invoke( null, $normalized_legacy_schema );
$assert( true === $result_legacy_schema['valid'], 'Legacy schema should be normalized and accepted.' );
$assert( 'email' === $normalized_legacy_schema[0]['key'], 'Legacy name should map to key.' );
$assert( 'Email Address' === $normalized_legacy_schema[0]['label'], 'Legacy title should map to label.' );
$assert( 'email' === $normalized_legacy_schema[0]['type'], 'Legacy field_type should map to type.' );
$assert( true === $normalized_legacy_schema[0]['required'], 'Legacy is_required should map to required.' );

echo "All schema validation assertions passed.\n";
