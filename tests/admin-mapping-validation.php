<?php
/**
 * Lightweight assertions for RTG_Admin mapping validation.
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

class RTG_Test_WPDB {
	public $prefix = 'wp_';

	/**
	 * @var int[]
	 */
	private $forms;

	/**
	 * @var int[]
	 */
	private $assets;

	/**
	 * @param int[] $forms  Existing form IDs.
	 * @param int[] $assets Existing asset IDs.
	 */
	public function __construct( $forms, $assets ) {
		$this->forms  = $forms;
		$this->assets = $assets;
	}

	/**
	 * Mimic $wpdb->prepare by returning structured query args.
	 *
	 * @param string $query SQL query string.
	 * @param int    $id    Record ID.
	 * @return array{query: string, id: int}
	 */
	public function prepare( $query, $id ) {
		return array(
			'query' => $query,
			'id'    => (int) $id,
		);
	}

	/**
	 * Mimic $wpdb->get_var for form/asset existence checks.
	 *
	 * @param array{query: string, id: int} $prepared Prepared query data.
	 * @return int|null
	 */
	public function get_var( $prepared ) {
		$query = $prepared['query'];
		$id    = (int) $prepared['id'];

		if ( false !== strpos( $query, 'rtg_forms' ) ) {
			return in_array( $id, $this->forms, true ) ? $id : null;
		}

		if ( false !== strpos( $query, 'rtg_assets' ) ) {
			return in_array( $id, $this->assets, true ) ? $id : null;
		}

		return null;
	}
}

$method = new ReflectionMethod( 'RTG_Admin', 'validate_mapping_data' );
$method->setAccessible( true );

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Assertion failed: {$message}\n" );
		exit( 1 );
	}
};

global $wpdb;
$wpdb = new RTG_Test_WPDB( array( 1 ), array( 10 ) );

$invalid_form = $method->invoke( null, 999, 10, '' );
$assert( false === $invalid_form['valid'], 'Unknown form ID should be rejected.' );
$assert( 'Please select a valid form.' === $invalid_form['message'], 'Unknown form ID should return specific message.' );

$invalid_asset = $method->invoke( null, 1, 999, '' );
$assert( false === $invalid_asset['valid'], 'Unknown asset ID should be rejected.' );
$assert( 'Please select a valid asset.' === $invalid_asset['message'], 'Unknown asset ID should return specific message.' );

$invalid_template = $method->invoke( null, 1, 10, 'https://gate.example.com/view' );
$assert( false === $invalid_template['valid'], 'Template without required placeholders should be rejected.' );
$assert(
	'Iframe source template must include {asset_slug} or {token} when provided.' === $invalid_template['message'],
	'Template without placeholders should return specific message.'
);

$valid_asset_slug_template = $method->invoke( null, 1, 10, 'https://gate.example.com/{asset_slug}' );
$assert( true === $valid_asset_slug_template['valid'], 'Template with {asset_slug} should be accepted.' );

$valid_token_template = $method->invoke( null, 1, 10, 'https://gate.example.com/?t={token}' );
$assert( true === $valid_token_template['valid'], 'Template with {token} should be accepted.' );

echo "All mapping validation assertions passed.\n";
