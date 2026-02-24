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
	 * @var array<int, array{form_id:int, asset_id:int}>
	 */
	private $mappings;

	/**
	 * @param int[]                                      $forms    Existing form IDs.
	 * @param int[]                                      $assets   Existing asset IDs.
	 * @param array<int, array{form_id:int, asset_id:int}> $mappings Existing mapping rows keyed by ID.
	 */
	public function __construct( $forms, $assets, $mappings = array() ) {
		$this->forms    = $forms;
		$this->assets   = $assets;
		$this->mappings = $mappings;
	}

	/**
	 * Mimic $wpdb->prepare by returning structured query args.
	 *
	 * @param string $query SQL query string.
	 * @param mixed  ...$args Query args.
	 * @return array{query: string, args: array<int, mixed>}
	 */
	public function prepare( $query, ...$args ) {
		return array(
			'query' => $query,
			'args'  => $args,
		);
	}

	/**
	 * Mimic $wpdb->get_var for form/asset existence checks.
	 *
	 * @param array{query: string, args: array<int, mixed>} $prepared Prepared query data.
	 * @return int|null
	 */
	public function get_var( $prepared ) {
		$query = $prepared['query'];
		$args  = $prepared['args'];

		if ( false !== strpos( $query, 'rtg_forms' ) ) {
			$id = (int) $args[0];
			return in_array( $id, $this->forms, true ) ? $id : null;
		}

		if ( false !== strpos( $query, 'rtg_assets' ) ) {
			$id = (int) $args[0];
			return in_array( $id, $this->assets, true ) ? $id : null;
		}

		if ( false !== strpos( $query, 'rtg_mappings' ) ) {
			$form_id    = (int) $args[0];
			$asset_id   = (int) $args[1];
			$mapping_id = (int) $args[2];

			foreach ( $this->mappings as $id => $mapping ) {
				if ( $mapping['form_id'] === $form_id && $mapping['asset_id'] === $asset_id && $id !== $mapping_id ) {
					return $id;
				}
			}
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
$wpdb = new RTG_Test_WPDB(
	array( 1 ),
	array( 10 ),
	array(
		22 => array(
			'form_id'  => 1,
			'asset_id' => 10,
		),
	)
);

$invalid_form = $method->invoke( null, 999, 10, '', 0 );
$assert( false === $invalid_form['valid'], 'Unknown form ID should be rejected.' );
$assert( 'Please select a valid form.' === $invalid_form['message'], 'Unknown form ID should return specific message.' );

$invalid_asset = $method->invoke( null, 1, 999, '', 0 );
$assert( false === $invalid_asset['valid'], 'Unknown asset ID should be rejected.' );
$assert( 'Please select a valid asset.' === $invalid_asset['message'], 'Unknown asset ID should return specific message.' );

$duplicate_create = $method->invoke( null, 1, 10, '', 0 );
$assert( false === $duplicate_create['valid'], 'Duplicate mapping should be rejected during create.' );
$assert(
	'A mapping already exists for the selected form and asset.' === $duplicate_create['message'],
	'Duplicate mapping should return specific error message.'
);

$duplicate_edit_same_row = $method->invoke( null, 1, 10, '', 22 );
$assert( true === $duplicate_edit_same_row['valid'], 'Editing the same mapping row should not trigger duplicate failure.' );

$invalid_template = $method->invoke( null, 1, 10, 'https://gate.example.com/view', 22 );
$assert( false === $invalid_template['valid'], 'Template without required placeholders should be rejected.' );
$assert(
	'Iframe source template must include {asset_slug} or {token} when provided.' === $invalid_template['message'],
	'Template without placeholders should return specific message.'
);

$valid_asset_slug_template = $method->invoke( null, 1, 10, 'https://gate.example.com/{asset_slug}', 22 );
$assert( true === $valid_asset_slug_template['valid'], 'Template with {asset_slug} should be accepted.' );

$valid_token_template = $method->invoke( null, 1, 10, 'https://gate.example.com/?t={token}', 22 );
$assert( true === $valid_token_template['valid'], 'Template with {token} should be accepted.' );

echo "All mapping validation assertions passed.\n";
