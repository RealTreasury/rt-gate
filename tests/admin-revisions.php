<?php
/**
 * Lightweight assertions for revision create/list/restore behavior.
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

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 99;
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

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'admin.php' . ( $path ? '?' . $path : '' );
	}
}

if ( ! function_exists( 'rawurlencode' ) ) {
	// Core function exists in PHP.
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
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

require_once __DIR__ . '/../includes/class-admin.php';

class RTG_Test_Revisions_WPDB {
	public $prefix = 'wp_';
	public $forms = array();
	public $mappings = array();
	public $form_revisions = array();
	public $mapping_revisions = array();
	public $insert_id = 0;

	public function __construct() {
		$this->forms = array(
			1 => array(
				'id' => 1,
				'name' => 'Original Form',
				'fields_schema' => '[]',
				'consent_text' => 'consent',
				'email_settings' => '{}',
			),
		);
		$this->mappings = array(
			7 => array(
				'id' => 7,
				'form_id' => 1,
				'asset_id' => 10,
				'iframe_src_template' => 'https://gate/{asset_slug}?t={token}',
			),
		);
		$this->mapping_revisions = array(
			3 => array(
				'id' => 3,
				'mapping_id' => 7,
				'snapshot' => '{"id":7,"form_id":1,"asset_id":20,"iframe_src_template":"https://new/{asset_slug}"}',
				'edited_by' => 11,
				'restored_from_revision_id' => 0,
				'created_at' => '2026-02-24 00:00:00',
			),
		);
	}

	public function prepare( $query, ...$args ) {
		return array( 'query' => $query, 'args' => $args );
	}

	public function insert( $table, $data ) {
		if ( false !== strpos( $table, 'form_revisions' ) ) {
			$id = count( $this->form_revisions ) + 1;
			$data['id'] = $id;
			$this->form_revisions[ $id ] = $data;
			$this->insert_id = $id;
			return 1;
		}
		if ( false !== strpos( $table, 'mapping_revisions' ) ) {
			$id = max( array_merge( array( 0 ), array_keys( $this->mapping_revisions ) ) ) + 1;
			$data['id'] = $id;
			$this->mapping_revisions[ $id ] = $data;
			$this->insert_id = $id;
			return 1;
		}
		return 0;
	}

	public function update( $table, $data, $where ) {
		$id = (int) $where['id'];
		if ( false !== strpos( $table, 'rtg_mappings' ) && isset( $this->mappings[ $id ] ) ) {
			$this->mappings[ $id ] = array_merge( $this->mappings[ $id ], $data );
			return 1;
		}
		if ( false !== strpos( $table, 'rtg_forms' ) && isset( $this->forms[ $id ] ) ) {
			$this->forms[ $id ] = array_merge( $this->forms[ $id ], $data );
			return 1;
		}
		return 0;
	}

	public function get_row( $prepared, $output = OBJECT ) {
		$query = $prepared['query'];
		$args  = $prepared['args'];
		$id    = (int) $args[0];

		if ( false !== strpos( $query, 'rtg_mapping_revisions' ) ) {
			$row = isset( $this->mapping_revisions[ $id ] ) ? $this->mapping_revisions[ $id ] : null;
		} elseif ( false !== strpos( $query, 'rtg_mappings' ) ) {
			$row = isset( $this->mappings[ $id ] ) ? $this->mappings[ $id ] : null;
		} elseif ( false !== strpos( $query, 'rtg_forms' ) ) {
			$row = isset( $this->forms[ $id ] ) ? $this->forms[ $id ] : null;
		} else {
			$row = null;
		}

		if ( ! is_array( $row ) ) {
			return null;
		}
		return ARRAY_A === $output ? $row : (object) $row;
	}

	public function get_var( $prepared ) {
		$query = $prepared['query'];
		$args  = $prepared['args'];
		if ( false !== strpos( $query, 'rtg_forms' ) ) {
			$id = (int) $args[0];
			return isset( $this->forms[ $id ] ) ? $id : null;
		}
		if ( false !== strpos( $query, 'rtg_assets' ) ) {
			$id = (int) $args[0];
			return in_array( $id, array( 10, 20 ), true ) ? $id : null;
		}
		if ( false !== strpos( $query, 'rtg_mappings' ) ) {
			return null;
		}
		return null;
	}

	public function get_results() {
		$rows = array();
		foreach ( $this->mapping_revisions as $row ) {
			$rows[] = (object) $row;
		}
		return $rows;
	}
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Assertion failed: {$message}\n" );
		exit( 1 );
	}
};

global $wpdb;
$wpdb = new RTG_Test_Revisions_WPDB();

$insert_form_method = new ReflectionMethod( 'RTG_Admin', 'insert_form_revision' );
$insert_form_method->setAccessible( true );
$insert_form_method->invoke( null, 1, $wpdb->forms[1], 0 );
$assert( 1 === count( $wpdb->form_revisions ), 'Create form revision should insert a revision row.' );

$insert_mapping_method = new ReflectionMethod( 'RTG_Admin', 'insert_mapping_revision' );
$insert_mapping_method->setAccessible( true );
$insert_mapping_method->invoke( null, 7, $wpdb->mappings[7], 0 );
$assert( 2 === count( $wpdb->mapping_revisions ), 'Create mapping revision should insert a revision row.' );

$list = $wpdb->get_results();
$assert( count( $list ) >= 1, 'List revisions should return inserted revisions.' );

$_POST = array(
	'id' => 7,
	'revision_id' => 3,
);

register_shutdown_function(
	static function () use ( $assert, $wpdb ) {
		$restored_mapping = $wpdb->mappings[7];
		$assert( 20 === (int) $restored_mapping['asset_id'], 'Restore flow should apply selected revision snapshot.' );
		$latest_id = max( array_keys( $wpdb->mapping_revisions ) );
		$assert( 3 === (int) $wpdb->mapping_revisions[ $latest_id ]['restored_from_revision_id'], 'Restore flow should append a new revision linked to source revision.' );
		echo "Revision create/list/restore assertions passed.\n";
	}
);

$restore_method = new ReflectionMethod( 'RTG_Admin', 'restore_mapping_revision' );
$restore_method->setAccessible( true );
$restore_method->invoke( null );
