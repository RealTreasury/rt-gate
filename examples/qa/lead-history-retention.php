<?php
/**
 * QA script for lead history retention and multi-form submissions.
 *
 * Run with:
 * wp eval-file examples/qa/lead-history-retention.php
 */

if ( ! class_exists( 'RTG_REST' ) || ! class_exists( 'RTG_Admin' ) ) {
	echo "Required RTG classes are not loaded. Activate the plugin first.\n";
	return;
}

global $wpdb;

$forms_table    = $wpdb->prefix . 'rtg_forms';
$assets_table   = $wpdb->prefix . 'rtg_assets';
$mappings_table = $wpdb->prefix . 'rtg_mappings';
$leads_table    = $wpdb->prefix . 'rtg_leads';
$tokens_table   = $wpdb->prefix . 'rtg_tokens';
$events_table   = $wpdb->prefix . 'rtg_events';

$uid   = 'qa_' . wp_generate_password( 8, false, false );
$email = "{$uid}@example.com";

$assertions = array();
$record_assertion = static function ( $label, $condition, $details = '' ) use ( &$assertions ) {
	$assertions[] = array(
		'label'     => $label,
		'condition' => (bool) $condition,
		'details'   => $details,
	);
};

$form_one_name = 'QA Form One ' . $uid;
$form_two_name = 'QA Form Two ' . $uid;

$wpdb->insert(
	$forms_table,
	array(
		'name'         => $form_one_name,
		'fields_schema'=> wp_json_encode( array( 'email', 'first_name', 'user_type' ) ),
		'consent_text' => 'QA consent',
	),
	array( '%s', '%s', '%s' )
);
$form_one_id = (int) $wpdb->insert_id;

$wpdb->insert(
	$forms_table,
	array(
		'name'         => $form_two_name,
		'fields_schema'=> wp_json_encode( array( 'email', 'full_name', 'company', 'user_type' ) ),
		'consent_text' => 'QA consent',
	),
	array( '%s', '%s', '%s' )
);
$form_two_id = (int) $wpdb->insert_id;

$wpdb->insert(
	$assets_table,
	array(
		'name'   => 'QA Asset One ' . $uid,
		'slug'   => sanitize_title( 'qa-asset-one-' . $uid ),
		'type'   => 'link',
		'config' => wp_json_encode( array( 'target_url' => 'https://example.com/one' ) ),
	),
	array( '%s', '%s', '%s', '%s' )
);
$asset_one_id = (int) $wpdb->insert_id;

$wpdb->insert(
	$assets_table,
	array(
		'name'   => 'QA Asset Two ' . $uid,
		'slug'   => sanitize_title( 'qa-asset-two-' . $uid ),
		'type'   => 'link',
		'config' => wp_json_encode( array( 'target_url' => 'https://example.com/two' ) ),
	),
	array( '%s', '%s', '%s', '%s' )
);
$asset_two_id = (int) $wpdb->insert_id;

$wpdb->insert(
	$mappings_table,
	array(
		'form_id'             => $form_one_id,
		'asset_id'            => $asset_one_id,
		'iframe_src_template' => 'https://example.com/gate/{asset_slug}?t={token}',
	),
	array( '%d', '%d', '%s' )
);
$wpdb->insert(
	$mappings_table,
	array(
		'form_id'             => $form_two_id,
		'asset_id'            => $asset_one_id,
		'iframe_src_template' => 'https://example.com/gate/{asset_slug}?t={token}',
	),
	array( '%d', '%d', '%s' )
);
$wpdb->insert(
	$mappings_table,
	array(
		'form_id'             => $form_two_id,
		'asset_id'            => $asset_two_id,
		'iframe_src_template' => 'https://example.com/gate/{asset_slug}?t={token}',
	),
	array( '%d', '%d', '%s' )
);

$request_one = new WP_REST_Request( 'POST', '/rtg/v1/submit' );
$request_one->set_param( 'form_id', $form_one_id );
$request_one->set_param( 'consent', true );
$request_one->set_param(
	'fields',
	array(
		'email'      => $email,
		'first_name' => 'Avery',
		'user_type'  => 'Corporate treasury professional (current system in place)',
	)
);
$response_one = RTG_REST::handle_submit( $request_one );

$request_two = new WP_REST_Request( 'POST', '/rtg/v1/submit' );
$request_two->set_param( 'form_id', $form_two_id );
$request_two->set_param( 'consent', true );
$request_two->set_param(
	'fields',
	array(
		'email'     => $email,
		'full_name' => 'Avery Jordan',
		'company'   => 'Treasury QA Labs',
		'user_type' => 'Treasury or finance consultant',
	)
);
$response_two = RTG_REST::handle_submit( $request_two );

$record_assertion(
	'Submit response one is successful',
	$response_one instanceof WP_REST_Response,
	is_wp_error( $response_one ) ? $response_one->get_error_message() : 'ok'
);
$record_assertion(
	'Submit response two is successful',
	$response_two instanceof WP_REST_Response,
	is_wp_error( $response_two ) ? $response_two->get_error_message() : 'ok'
);

$lead = $wpdb->get_row( $wpdb->prepare( "SELECT id, form_data FROM {$leads_table} WHERE email = %s", $email ) );
$lead_id = $lead ? (int) $lead->id : 0;
$form_payload = $lead ? RTG_Admin::normalize_form_data_payload( $lead->form_data ) : array();
$latest       = RTG_Admin::get_latest_form_fields( $form_payload );

$record_assertion( 'Lead upserted once for same email', $lead_id > 0, 'lead_id=' . $lead_id );
$record_assertion( 'Latest payload reflects second submission', isset( $latest['full_name'] ) && 'Avery Jordan' === $latest['full_name'] );
$record_assertion( 'History contains first form entries', isset( $form_payload['history'][ (string) $form_one_id ] ) && count( (array) $form_payload['history'][ (string) $form_one_id ] ) >= 1 );
$record_assertion( 'History contains second form entries', isset( $form_payload['history'][ (string) $form_two_id ] ) && count( (array) $form_payload['history'][ (string) $form_two_id ] ) >= 1 );

$token_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tokens_table} WHERE lead_id = %d", $lead_id ) );
$asset_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT asset_id) FROM {$tokens_table} WHERE lead_id = %d", $lead_id ) );
$record_assertion( 'Multiple tokens issued across submissions', $token_count >= 3, 'token_count=' . $token_count );
$record_assertion( 'Multiple assets issued for same lead', $asset_count >= 2, 'asset_count=' . $asset_count );

$lead_rows = RTG_Admin::query_leads( -1, 0 );
$matched = array_values(
	array_filter(
		$lead_rows,
		static function ( $row ) use ( $email ) {
			return isset( $row->email ) && $email === $row->email;
		}
	)
);
$record_assertion( 'Lead appears in lead query', ! empty( $matched ) );

// Cleanup created QA data.
if ( $lead_id > 0 ) {
	$wpdb->delete( $tokens_table, array( 'lead_id' => $lead_id ), array( '%d' ) );
	$wpdb->delete( $events_table, array( 'lead_id' => $lead_id ), array( '%d' ) );
	$wpdb->delete( $leads_table, array( 'id' => $lead_id ), array( '%d' ) );
}
$wpdb->query( $wpdb->prepare( "DELETE FROM {$mappings_table} WHERE form_id IN (%d, %d)", $form_one_id, $form_two_id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$assets_table} WHERE id IN (%d, %d)", $asset_one_id, $asset_two_id ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$forms_table} WHERE id IN (%d, %d)", $form_one_id, $form_two_id ) );

$failed = false;
foreach ( $assertions as $assertion ) {
	$status = $assertion['condition'] ? '[PASS]' : '[FAIL]';
	echo $status . ' ' . $assertion['label'];
	if ( '' !== $assertion['details'] ) {
		echo ' - ' . $assertion['details'];
	}
	echo "\n";
	if ( ! $assertion['condition'] ) {
		$failed = true;
	}
}

if ( $failed ) {
	echo "QA assertions failed.\n";
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::halt( 1 );
	}
}

echo "Lead history QA assertions passed.\n";
