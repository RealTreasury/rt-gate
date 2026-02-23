<?php
/**
 * Lead admin action wiring QA check.
 *
 * Usage:
 * php examples/qa/lead-admin-actions.php
 */

$target = dirname( __DIR__, 2 ) . '/includes/class-admin.php';
if ( ! file_exists( $target ) ) {
	fwrite( STDERR, "class-admin.php not found.\n" );
	exit( 1 );
}

$source = file_get_contents( $target );
if ( false === $source ) {
	fwrite( STDERR, "Unable to read class-admin.php.\n" );
	exit( 1 );
}

$checks = array(
	"manage_options gate" => "current_user_can( 'manage_options' )",
	"save_lead action" => "case 'save_lead':",
	"save_lead nonce" => "check_admin_referer( 'rtg_save_lead' );",
	"delete_lead action" => "case 'delete_lead':",
	"delete_lead nonce" => "check_admin_referer( 'rtg_delete_lead' );",
	"lead edit form action" => 'name="rtg_action" value="save_lead"',
	"lead delete form action" => 'name="rtg_action" value="delete_lead"',
	"row delete nonce url" => 'wp_nonce_url( admin_url( \'admin.php?page=rtg-leads&lead_id=\' . $lead_id . \'&rtg_action=delete_lead\' ), \'rtg_delete_lead\' )',
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
	fwrite( STDERR, "\nLead admin action QA failed with {$failed} issue(s).\n" );
	exit( 1 );
}

echo "\nLead admin action QA passed.\n";
