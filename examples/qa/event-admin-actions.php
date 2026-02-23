<?php
/**
 * Events admin action wiring QA check.
 *
 * Usage:
 * php examples/qa/event-admin-actions.php
 */

$target = dirname( __DIR__, 2 ) . '/includes/class-events.php';
if ( ! file_exists( $target ) ) {
	fwrite( STDERR, "class-events.php not found.\n" );
	exit( 1 );
}

$source = file_get_contents( $target );
if ( false === $source ) {
	fwrite( STDERR, "Unable to read class-events.php.\n" );
	exit( 1 );
}

$checks = array(
	"manage_options gate" => "current_user_can( 'manage_options' )",
	"delete_event action" => "case 'delete_event':",
	"delete_event nonce check" => "check_admin_referer( 'rtg_delete_event_' .",
	"events export nonce check" => "check_admin_referer( 'rtg_export_events_csv' );",
	"row delete nonce url" => "wp_nonce_url(",
	"soft delete update field" => "'is_deleted' => 1",
	"default query hides deleted" => "\$where_sql[] = 'e.is_deleted = 0';",
	"include deleted request filter" => "'include_deleted' => isset( \$_GET['include_deleted'] )",
	"include deleted table filter" => "<input type=\"checkbox\" name=\"include_deleted\" value=\"1\"",
);

$failed = 0;
foreach ( $checks as $label => $needle ) {
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "[FAIL] {$label}\n" );
		$failed++;
	} else {
		echo "[PASS] {$label}\n";
	}
}

if ( $failed > 0 ) {
	fwrite( STDERR, "\nEvent admin action QA failed with {$failed} issue(s).\n" );
	exit( 1 );
}

echo "\nEvent admin action QA passed.\n";
