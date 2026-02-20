<?php
/**
 * Event logging and admin table UI.
 */
class RTG_Events {
	/**
	 * Initialize event admin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_actions' ) );
	}

	/**
	 * Insert an event record with hashed request metadata.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param int    $form_id Form ID.
	 * @param int    $asset_id Asset ID.
	 * @param string $event_type Event type.
	 * @param array  $meta Event metadata.
	 * @return bool
	 */
	public static function log_event( $lead_id, $form_id, $asset_id, $event_type, $meta = array() ) {
		global $wpdb;

		$events_table = $wpdb->prefix . 'rtg_events';
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$meta['request_ip_hash'] = RTG_Utils::hash_ip( self::get_request_ip() );
		$meta['request_ua_hash'] = RTG_Utils::hash_user_agent( self::get_user_agent() );

		$inserted = $wpdb->insert(
			$events_table,
			array(
				'lead_id'    => max( 0, absint( $lead_id ) ),
				'form_id'    => max( 0, absint( $form_id ) ),
				'asset_id'   => max( 0, absint( $asset_id ) ),
				'event_type' => sanitize_key( $event_type ),
				'meta'       => wp_json_encode( $meta ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		return false !== $inserted;
	}

	/**
	 * Register the events admin submenu.
	 *
	 * @return void
	 */
	public static function register_submenu() {
		add_submenu_page(
			'rtg-forms',
			esc_html__( 'Events', 'rt-gate' ),
			esc_html__( 'Events', 'rt-gate' ),
			'manage_options',
			'rtg-events',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Process CSV export requests.
	 *
	 * @return void
	 */
	public static function handle_admin_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$action = isset( $_GET['rtg_action'] ) ? sanitize_key( wp_unslash( $_GET['rtg_action'] ) ) : '';

		if ( 'rtg-events' !== $page || 'export_events_csv' !== $action ) {
			return;
		}

		check_admin_referer( 'rtg_export_events_csv' );
		self::export_csv();
		exit;
	}

	/**
	 * Render the events admin page.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

		$table = new RTG_Events_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Events', 'rt-gate' ); ?></h1>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-events&rtg_action=export_events_csv' ), 'rtg_export_events_csv' ) ); ?>">
					<?php echo esc_html__( 'Export CSV', 'rt-gate' ); ?>
				</a>
			</p>
			<form method="get">
				<input type="hidden" name="page" value="rtg-events" />
				<?php $table->search_box( esc_html__( 'Search Email', 'rt-gate' ), 'rtg-events' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Export filtered events as CSV.
	 *
	 * @return void
	 */
	private static function export_csv() {
		$rows = self::query_events( -1, 0 );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=rtg-events-' . gmdate( 'Ymd-His' ) . '.csv' );

		$fp = fopen( 'php://output', 'w' );
		if ( false === $fp ) {
			return;
		}

		fputcsv( $fp, array( 'id', 'email', 'form_id', 'asset_id', 'event_type', 'meta', 'created_at' ) );
		foreach ( $rows as $row ) {
			fputcsv(
				$fp,
				array(
					(int) $row->id,
					(string) $row->email,
					(int) $row->form_id,
					(int) $row->asset_id,
					(string) $row->event_type,
					(string) $row->meta,
					(string) $row->created_at,
				)
			);
		}

		fclose( $fp );
	}

	/**
	 * Query events with current filters.
	 *
	 * @param int $limit Row limit.
	 * @param int $offset Row offset.
	 * @return array
	 */
	public static function query_events( $limit = 20, $offset = 0 ) {
		global $wpdb;

		$events_table = $wpdb->prefix . 'rtg_events';
		$leads_table  = $wpdb->prefix . 'rtg_leads';
		$where_sql    = array( '1=1' );
		$params       = array();

		$form_id = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
		if ( $form_id > 0 ) {
			$where_sql[] = 'e.form_id = %d';
			$params[]    = $form_id;
		}

		$asset_id = isset( $_GET['asset_id'] ) ? absint( wp_unslash( $_GET['asset_id'] ) ) : 0;
		if ( $asset_id > 0 ) {
			$where_sql[] = 'e.asset_id = %d';
			$params[]    = $asset_id;
		}

		$event_type = isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : '';
		if ( ! empty( $event_type ) ) {
			$where_sql[] = 'e.event_type = %s';
			$params[]    = $event_type;
		}

		$email = isset( $_REQUEST['s'] ) ? sanitize_email( wp_unslash( $_REQUEST['s'] ) ) : '';
		if ( ! empty( $email ) ) {
			$where_sql[] = 'l.email LIKE %s';
			$params[]    = '%' . $wpdb->esc_like( $email ) . '%';
		}

		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		if ( ! empty( $date_from ) ) {
			$where_sql[] = 'e.created_at >= %s';
			$params[]    = gmdate( 'Y-m-d 00:00:00', strtotime( $date_from ) );
		}

		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		if ( ! empty( $date_to ) ) {
			$where_sql[] = 'e.created_at <= %s';
			$params[]    = gmdate( 'Y-m-d 23:59:59', strtotime( $date_to ) );
		}

		$sql = "SELECT e.id, e.form_id, e.asset_id, e.event_type, e.meta, e.created_at, COALESCE(l.email, '') AS email
			FROM {$events_table} e
			LEFT JOIN {$leads_table} l ON l.id = e.lead_id
			WHERE " . implode( ' AND ', $where_sql ) . ' ORDER BY e.id DESC';

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', absint( $limit ), absint( $offset ) );
		}

		if ( empty( $params ) ) {
			return $wpdb->get_results( $sql );
		}

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Count events with current filters.
	 *
	 * @return int
	 */
	public static function count_events() {
		return count( self::query_events( -1, 0 ) );
	}

	/**
	 * Get requester IP.
	 *
	 * @return string
	 */
	private static function get_request_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return '';
	}

	/**
	 * Get requester user agent.
	 *
	 * @return string
	 */
	private static function get_user_agent() {
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		return '';
	}
}

/**
 * Events list table.
 */
class RTG_Events_List_Table extends WP_List_Table {
	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'id'         => esc_html__( 'ID', 'rt-gate' ),
			'email'      => esc_html__( 'Email', 'rt-gate' ),
			'form_id'    => esc_html__( 'Form', 'rt-gate' ),
			'asset_id'   => esc_html__( 'Asset', 'rt-gate' ),
			'event_type' => esc_html__( 'Event Type', 'rt-gate' ),
			'meta'       => esc_html__( 'Meta', 'rt-gate' ),
			'created_at' => esc_html__( 'Created', 'rt-gate' ),
		);
	}

	/**
	 * Prepare table items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$this->items = RTG_Events::query_events( $per_page, $offset );
		$total_items = RTG_Events::count_events();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Render default columns.
	 *
	 * @param array  $item Item data.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
			case 'form_id':
			case 'asset_id':
				return (string) absint( $item->$column_name );
			case 'email':
			case 'event_type':
				return esc_html( (string) $item->$column_name );
			case 'meta':
				return esc_html( wp_json_encode( json_decode( (string) $item->meta, true ) ) );
			case 'created_at':
				return esc_html( (string) $item->created_at );
		}

		return '';
	}
}

RTG_Events::init();
