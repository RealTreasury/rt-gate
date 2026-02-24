<?php
/**
 * Install-path assertions for rtg_mappings unique (form_id, asset_id) key.
 */

$test_wp_root = sys_get_temp_dir() . '/rtg-wp-stub-' . uniqid( '', true );
$upgrade_path = $test_wp_root . '/wp-admin/includes';

if ( ! mkdir( $upgrade_path, 0777, true ) && ! is_dir( $upgrade_path ) ) {
	fwrite( STDERR, "Assertion failed: unable to create temporary WordPress stub directory.\n" );
	exit( 1 );
}

file_put_contents(
	$upgrade_path . '/upgrade.php',
	"<?php\nfunction dbDelta( \$sql ) {\n\tglobal \$rtg_dbdelta_sql;\n\t\$rtg_dbdelta_sql[] = \$sql;\n}\n"
);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $test_wp_root . '/' );
}

require_once __DIR__ . '/../includes/class-db.php';

class RTG_Test_DB_WPDB {
	public $prefix = 'wp_';

	public function get_charset_collate() {
		return 'DEFAULT CHARSET=utf8mb4';
	}
}

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "Assertion failed: {$message}\n" );
		exit( 1 );
	}
};

global $wpdb, $rtg_dbdelta_sql;
$wpdb            = new RTG_Test_DB_WPDB();
$rtg_dbdelta_sql = array();

RTG_DB::install();

$mappings_sql = null;
foreach ( $rtg_dbdelta_sql as $sql ) {
	if ( false !== strpos( $sql, 'CREATE TABLE wp_rtg_mappings' ) ) {
		$mappings_sql = $sql;
		break;
	}
}

$assert( is_string( $mappings_sql ), 'Mappings table SQL should be sent to dbDelta().' );
$assert(
	false !== strpos( $mappings_sql, 'UNIQUE KEY form_asset (form_id,asset_id)' ),
	'Mappings DDL should include a unique key for form_id + asset_id.'
);

echo "Mappings unique-key install assertions passed.\n";
