<?php
/**
 * QA script to verify admin table and CSV query parity.
 *
 * Run with:
 * wp eval-file examples/qa/query-parity.php
 */

if ( ! class_exists( 'RTG_Admin' ) || ! class_exists( 'RTG_Events' ) ) {
	echo "RTG classes are not loaded. Activate the plugin first.\n";
	return;
}

$assertions = array();

$original_get     = $_GET;
$original_request = $_REQUEST;

$run_assertion = static function ( $label, $condition, $details = '' ) use ( &$assertions ) {
	$assertions[] = array(
		'label'     => $label,
		'condition' => (bool) $condition,
		'details'   => $details,
	);
};

$reset_filters = static function () {
	$_GET     = array();
	$_REQUEST = array();
};

$reset_filters();

$lead_ui_rows  = RTG_Admin::query_leads( 20, 0 );
$lead_csv_rows = RTG_Admin::query_leads( -1, 0 );
$lead_count    = RTG_Admin::count_leads();

$run_assertion(
	'Leads: count matches CSV row count with no filters',
	$lead_count === count( $lead_csv_rows ),
	'count_leads=' . $lead_count . ', csv_rows=' . count( $lead_csv_rows )
);

$run_assertion(
	'Leads: no-filter UI has rows when CSV has rows',
	count( $lead_csv_rows ) === 0 || count( $lead_ui_rows ) > 0,
	'ui_rows=' . count( $lead_ui_rows ) . ', csv_rows=' . count( $lead_csv_rows )
);

$sample_lead_email = '';
if ( ! empty( $lead_csv_rows ) ) {
	$sample_lead_email = (string) $lead_csv_rows[0]->email;
}

if ( '' !== $sample_lead_email ) {
	$_REQUEST = array( 's' => $sample_lead_email );
	$_GET     = array( 's' => $sample_lead_email );

	$filtered_lead_rows  = RTG_Admin::query_leads( -1, 0 );
	$filtered_lead_count = RTG_Admin::count_leads();

	$run_assertion(
		'Leads: email filter parity between count and CSV query',
		$filtered_lead_count === count( $filtered_lead_rows ),
		'email=' . $sample_lead_email . ', count=' . $filtered_lead_count . ', csv_rows=' . count( $filtered_lead_rows )
	);
} else {
	$run_assertion( 'Leads: email filter parity between count and CSV query', true, 'skipped (no leads available)' );
}

$reset_filters();

$event_ui_rows  = RTG_Events::query_events( 20, 0 );
$event_csv_rows = RTG_Events::query_events( -1, 0 );
$event_count    = RTG_Events::count_events();

$run_assertion(
	'Events: count matches CSV row count with no filters',
	$event_count === count( $event_csv_rows ),
	'count_events=' . $event_count . ', csv_rows=' . count( $event_csv_rows )
);

$run_assertion(
	'Events: no-filter UI has rows when CSV has rows',
	count( $event_csv_rows ) === 0 || count( $event_ui_rows ) > 0,
	'ui_rows=' . count( $event_ui_rows ) . ', csv_rows=' . count( $event_csv_rows )
);

$sample_event_email = '';
if ( ! empty( $event_csv_rows ) ) {
	$sample_event_email = (string) $event_csv_rows[0]->email;
}

if ( '' !== $sample_event_email ) {
	$_REQUEST = array( 's' => $sample_event_email );
	$_GET     = array( 's' => $sample_event_email );

	$filtered_event_rows  = RTG_Events::query_events( -1, 0 );
	$filtered_event_count = RTG_Events::count_events();

	$run_assertion(
		'Events: email filter parity between count and CSV query',
		$filtered_event_count === count( $filtered_event_rows ),
		'email=' . $sample_event_email . ', count=' . $filtered_event_count . ', csv_rows=' . count( $filtered_event_rows )
	);
} else {
	$run_assertion( 'Events: email filter parity between count and CSV query', true, 'skipped (no events available)' );
}

$_GET     = $original_get;
$_REQUEST = $original_request;

$failed = false;
foreach ( $assertions as $assertion ) {
	$status = $assertion['condition'] ? '[PASS]' : '[FAIL]';
	echo $status . ' ' . $assertion['label'] . ' - ' . $assertion['details'] . "\n";
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

echo "All QA assertions passed.\n";
