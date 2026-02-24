<?php
/**
 * Install-path assertions for revision tables.
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

class RTG_Test_DB_Revisions_WPDB {
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
$wpdb            = new RTG_Test_DB_Revisions_WPDB();
$rtg_dbdelta_sql = array();

RTG_DB::install();

$form_sql    = null;
$mapping_sql = null;
foreach ( $rtg_dbdelta_sql as $sql ) {
	if ( false !== strpos( $sql, 'CREATE TABLE wp_rtg_form_revisions' ) ) {
		$form_sql = $sql;
	}
	if ( false !== strpos( $sql, 'CREATE TABLE wp_rtg_mapping_revisions' ) ) {
		$mapping_sql = $sql;
	}
}

$assert( is_string( $form_sql ), 'Form revisions table SQL should be sent to dbDelta().' );
$assert( false !== strpos( $form_sql, 'restored_from_revision_id' ), 'Form revisions table should track restored_from_revision_id.' );
$assert( is_string( $mapping_sql ), 'Mapping revisions table SQL should be sent to dbDelta().' );
$assert( false !== strpos( $mapping_sql, 'restored_from_revision_id' ), 'Mapping revisions table should track restored_from_revision_id.' );

echo "Revision table install assertions passed.\n";
