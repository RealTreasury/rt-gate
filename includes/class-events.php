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
			'rtg-dashboard',
			esc_html__( 'Events', 'rt-gate' ),
			esc_html__( 'Events', 'rt-gate' ),
			'manage_options',
			'rtg-events',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Process event admin actions.
	 *
	 * @return void
	 */
	public static function handle_admin_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$action = isset( $_GET['rtg_action'] ) ? sanitize_key( wp_unslash( $_GET['rtg_action'] ) ) : '';

		if ( 'rtg-events' !== $page ) {
			return;
		}

		switch ( $action ) {
			case 'export_events_csv':
				check_admin_referer( 'rtg_export_events_csv' );
				self::export_csv();
				exit;
			case 'delete_event':
				$event_id = isset( $_GET['event_id'] ) ? absint( wp_unslash( $_GET['event_id'] ) ) : 0;
				check_admin_referer( 'rtg_delete_event_' . $event_id );
				self::delete_event( $event_id );
				break;
		}
	}

	/**
	 * Soft-delete an event record.
	 *
	 * @param int $event_id Event ID.
	 * @return void
	 */
	private static function delete_event( $event_id ) {
		global $wpdb;

		$event_id = absint( $event_id );
		if ( $event_id <= 0 ) {
			self::redirect_with_notice( esc_html__( 'Invalid event ID.', 'rt-gate' ), true );
		}

		$events_table = $wpdb->prefix . 'rtg_events';
		$updated      = $wpdb->update(
			$events_table,
			array(
				'is_deleted' => 1,
				'deleted_at' => current_time( 'mysql', true ),
				'deleted_by' => get_current_user_id(),
			),
			array(
				'id'         => $event_id,
				'is_deleted' => 0,
			),
			array( '%d', '%s', '%d' ),
			array( '%d', '%d' )
		);

		if ( false === $updated || 0 === $updated ) {
			self::redirect_with_notice( esc_html__( 'Unable to delete event.', 'rt-gate' ), true );
		}

		self::redirect_with_notice( esc_html__( 'Event deleted.', 'rt-gate' ) );
	}

	/**
	 * Redirect events page with admin notice.
	 *
	 * @param string $message Notice message.
	 * @param bool   $is_error Whether the notice is an error.
	 * @return void
	 */
	private static function redirect_with_notice( $message, $is_error = false ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'rtg-events',
					'rtg_msg'   => rawurlencode( $message ),
					'rtg_error' => $is_error ? '1' : '0',
				),
				admin_url( 'admin.php' )
			)
		);
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

		if ( ! class_exists( 'RTG_Events_List_Table' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Events', 'rt-gate' ) . '</h1>';
			echo '<p>' . esc_html__( 'Unable to load the events table. Please deactivate and reactivate the plugin.', 'rt-gate' ) . '</p></div>';
			return;
		}

		$table = new RTG_Events_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Events', 'rt-gate' ); ?></h1>
			<?php self::render_admin_notice(); ?>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-events&rtg_action=export_events_csv' ), 'rtg_export_events_csv' ) ); ?>">
					<?php echo esc_html__( 'Export CSV', 'rt-gate' ); ?>
				</a>
			</p>
			<form method="get">
				<input type="hidden" name="page" value="rtg-events" />
				<?php $table->search_box( esc_html__( 'Search Email', 'rt-gate' ), 'rtg-events' ); ?>
				<?php $table->render_query_notice(); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render events admin notices.
	 *
	 * @return void
	 */
	private static function render_admin_notice() {
		if ( ! isset( $_GET['rtg_msg'] ) ) {
			return;
		}

		$message  = sanitize_text_field( wp_unslash( $_GET['rtg_msg'] ) );
		$is_error = isset( $_GET['rtg_error'] ) && '1' === sanitize_key( wp_unslash( $_GET['rtg_error'] ) );
		$class    = $is_error ? 'notice notice-error' : 'notice notice-success';
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible"><p><?php echo esc_html( rawurldecode( $message ) ); ?></p></div>
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

		$query_parts = self::build_event_query_parts();
		$where_sql   = $query_parts['where_sql'];
		$params      = $query_parts['params'];

		$forms_table  = $wpdb->prefix . 'rtg_forms';
		$assets_table = $wpdb->prefix . 'rtg_assets';

		$sql = "SELECT e.id, e.lead_id, e.form_id, e.asset_id, e.event_type, e.meta, e.created_at,
				COALESCE(l.email, '') AS email,
				COALESCE(f.name, '') AS form_name,
				COALESCE(a.name, '') AS asset_name
			FROM {$events_table} e
			LEFT JOIN {$leads_table} l ON l.id = e.lead_id
			LEFT JOIN {$forms_table} f ON f.id = e.form_id
			LEFT JOIN {$assets_table} a ON a.id = e.asset_id
			WHERE " . implode( ' AND ', $where_sql ) . ' ORDER BY e.id DESC';

		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = absint( $limit );
			$params[] = absint( $offset );
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
		global $wpdb;

		$events_table = $wpdb->prefix . 'rtg_events';
		$leads_table  = $wpdb->prefix . 'rtg_leads';
		$query_parts  = self::build_event_query_parts();
		$where_sql    = $query_parts['where_sql'];
		$params       = $query_parts['params'];

		$sql = "SELECT COUNT(*) FROM {$events_table} e
			LEFT JOIN {$leads_table} l ON l.id = e.lead_id
			WHERE " . implode( ' AND ', $where_sql );

		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Get requester IP via centralized utility.
	 *
	 * @return string
	 */
	private static function get_request_ip() {
		return RTG_Utils::get_request_ip();
	}

	/**
	 * Get requester user agent via centralized utility.
	 *
	 * @return string
	 */
	private static function get_user_agent() {
		return RTG_Utils::get_user_agent();
	}

	/**
	 * Build normalized event filters from request parameters.
	 *
	 * @return array
	 */
	private static function build_event_filters_from_request() {
		return array(
			'form_id'         => isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0,
			'asset_id'        => isset( $_GET['asset_id'] ) ? absint( wp_unslash( $_GET['asset_id'] ) ) : 0,
			'event_type'      => isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : '',
			'email'           => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			'date_from'       => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'         => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'include_deleted' => isset( $_GET['include_deleted'] ) ? absint( wp_unslash( $_GET['include_deleted'] ) ) : 0,
		);
	}

	/**
	 * Build normalized WHERE SQL fragments for event queries.
	 *
	 * @return array
	 */
	private static function build_event_query_parts() {
		global $wpdb;

		$filters   = self::build_event_filters_from_request();
		$where_sql = array( '1=1' );
		$params    = array();

		if ( 1 !== $filters['include_deleted'] ) {
			$where_sql[] = 'e.is_deleted = 0';
		}

		if ( $filters['form_id'] > 0 ) {
			$where_sql[] = 'e.form_id = %d';
			$params[]    = $filters['form_id'];
		}

		if ( $filters['asset_id'] > 0 ) {
			$where_sql[] = 'e.asset_id = %d';
			$params[]    = $filters['asset_id'];
		}

		if ( ! empty( $filters['event_type'] ) ) {
			$where_sql[] = 'e.event_type = %s';
			$params[]    = $filters['event_type'];
		}

		if ( ! empty( $filters['email'] ) ) {
			$where_sql[] = 'l.email LIKE %s';
			$params[]    = '%' . $wpdb->esc_like( $filters['email'] ) . '%';
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_sql[] = 'e.created_at >= %s';
			$params[]    = gmdate( 'Y-m-d 00:00:00', strtotime( $filters['date_from'] ) );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_sql[] = 'e.created_at <= %s';
			$params[]    = gmdate( 'Y-m-d 23:59:59', strtotime( $filters['date_to'] ) );
		}

		return array(
			'where_sql' => $where_sql,
			'params'    => $params,
		);
	}
}

if ( is_admin() ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( class_exists( 'WP_List_Table' ) ) {
	/**
	 * Events list table.
	 */
	class RTG_Events_List_Table extends WP_List_Table {
		/**
		 * Whether the latest query failed.
		 *
		 * @var bool
		 */
		protected $query_error = false;

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
			global $wpdb;

			$per_page     = 20;
			$current_page = $this->get_pagenum();
			$offset       = ( $current_page - 1 ) * $per_page;

			$results           = RTG_Events::query_events( $per_page, $offset );
			$this->query_error = ! is_array( $results ) || ! empty( $wpdb->last_error );

			if ( $this->query_error && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'RTG Events list query failed: %s', (string) $wpdb->last_error ) );
			}

			$this->items = is_array( $results ) ? $results : array();
			$total_items = RTG_Events::count_events();

			$columns  = $this->get_columns();
			$hidden   = is_object( $this->screen ) ? get_hidden_columns( $this->screen ) : array();
			$sortable = method_exists( $this, 'get_sortable_columns' ) ? $this->get_sortable_columns() : array();
			$primary  = 'email';

			$this->_column_headers = array( $columns, $hidden, $sortable, $primary );

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
				)
			);
		}

		/**
		 * Render a generic query failure notice.
		 *
		 * @return void
		 */
		public function render_query_notice() {
			if ( ! $this->query_error ) {
				return;
			}
			?>
			<div class="notice notice-error inline">
				<p><?php echo esc_html__( 'We could not load the events list right now. Please try again, or check your debug logs for details.', 'rt-gate' ); ?></p>
			</div>
			<?php
		}

		/**
		 * Get the primary column name used by WP_List_Table internals.
		 *
		 * @return string
		 */
		protected function get_default_primary_column_name() {
			return 'email';
		}

		/**
		 * Render explicit empty-state messaging.
		 *
		 * @return void
		 */
		public function no_items() {
			echo esc_html__( 'No events found.', 'rt-gate' );
		}

		/**
		 * Render the primary column with row actions.
		 *
		 * @param object $item Event row.
		 * @return string
		 */
		public function column_email( $item ) {
			$event_id = absint( $item->id );
			$lead_id  = isset( $item->lead_id ) ? absint( $item->lead_id ) : 0;
			$email    = esc_html( (string) $item->email );

			$delete_url = wp_nonce_url(
				admin_url( 'admin.php?page=rtg-events&event_id=' . $event_id . '&rtg_action=delete_event' ),
				'rtg_delete_event_' . $event_id
			);

			$actions = array();

			if ( $lead_id > 0 ) {
				$lead_url          = admin_url( 'admin.php?page=rtg-leads&lead_id=' . $lead_id );
				$form_data_lead_url = $lead_url . '#rtg-latest-form-data';

				$actions['view_lead']      = '<a href="' . esc_url( $lead_url ) . '">' . esc_html__( 'View Lead', 'rt-gate' ) . '</a>';
				$actions['view_form_data'] = '<a href="' . esc_url( $form_data_lead_url ) . '">' . esc_html__( 'View Form Data', 'rt-gate' ) . '</a>';
			} else {
				$actions['no_lead_linked'] = '<span aria-disabled="true">' . esc_html__( 'No lead linked', 'rt-gate' ) . '</span>';
			}

			$actions['delete'] = '<a href="' . esc_url( $delete_url ) . '" class="submitdelete" onclick="return confirm(\'' . esc_js( __( 'Soft-delete this event from default views?', 'rt-gate' ) ) . '\');">' . esc_html__( 'Delete', 'rt-gate' ) . '</a>';

			return $email . $this->row_actions( $actions );
		}

		/**
		 * Render dropdown filters above the table.
		 *
		 * @param string $which Top or bottom.
		 * @return void
		 */
		protected function extra_tablenav( $which ) {
			if ( 'top' !== $which ) {
				return;
			}

			global $wpdb;
			$forms  = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}rtg_forms ORDER BY name ASC" );
			$assets = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}rtg_assets ORDER BY name ASC" );

			$current_form_id      = isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0;
			$current_asset_id     = isset( $_GET['asset_id'] ) ? absint( wp_unslash( $_GET['asset_id'] ) ) : 0;
			$current_event_type   = isset( $_GET['event_type'] ) ? sanitize_key( wp_unslash( $_GET['event_type'] ) ) : '';
			$current_show_deleted = isset( $_GET['include_deleted'] ) ? absint( wp_unslash( $_GET['include_deleted'] ) ) : 0;

			$event_types = array( 'form_submit', 'page_view', 'download_click', 'video_play', 'video_progress' );
			?>
			<div class="rtg-events-filters">
				<select name="form_id">
					<option value=""><?php echo esc_html__( 'All Forms', 'rt-gate' ); ?></option>
					<?php foreach ( $forms as $form ) : ?>
						<option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $current_form_id, (int) $form->id ); ?>><?php echo esc_html( $form->name ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="asset_id">
					<option value=""><?php echo esc_html__( 'All Assets', 'rt-gate' ); ?></option>
					<?php foreach ( $assets as $asset ) : ?>
						<option value="<?php echo esc_attr( $asset->id ); ?>" <?php selected( $current_asset_id, (int) $asset->id ); ?>><?php echo esc_html( $asset->name ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="event_type">
					<option value=""><?php echo esc_html__( 'All Event Types', 'rt-gate' ); ?></option>
					<?php foreach ( $event_types as $etype ) : ?>
						<option value="<?php echo esc_attr( $etype ); ?>" <?php selected( $current_event_type, $etype ); ?>><?php echo esc_html( $etype ); ?></option>
					<?php endforeach; ?>
				</select>

				<label>
					<input type="checkbox" name="include_deleted" value="1" <?php checked( 1, $current_show_deleted ); ?> />
					<?php echo esc_html__( 'Include deleted', 'rt-gate' ); ?>
				</label>

				<?php submit_button( esc_html__( 'Filter', 'rt-gate' ), '', '', false ); ?>
			</div>
			<?php
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
					return (string) absint( $item->id );
				case 'form_id':
					$name = ! empty( $item->form_name ) ? $item->form_name : '#' . absint( $item->form_id );
					return esc_html( $name );
				case 'asset_id':
					$name = ! empty( $item->asset_name ) ? $item->asset_name : '#' . absint( $item->asset_id );
					return esc_html( $name );
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
}

RTG_Events::init();
