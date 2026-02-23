<?php
/**
 * Admin UI manager for Real Treasury Gate.
 */
class RTG_Admin {
	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_form_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin scripts for plugin pages.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		$plugin_pages = array(
			'toplevel_page_rtg-dashboard',
			'real-treasury-gate_page_rtg-forms',
			'real-treasury-gate_page_rtg-assets',
			'real-treasury-gate_page_rtg-mappings',
			'real-treasury-gate_page_rtg-leads',
			'real-treasury-gate_page_rtg-events',
			'real-treasury-gate_page_rtg-settings',
		);

		if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
			return;
		}

		$css_path = RTG_PLUGIN_DIR . 'assets/css/admin.css';
		wp_enqueue_style(
			'rtg-admin-styles',
			RTG_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : null
		);

		if ( 'real-treasury-gate_page_rtg-assets' === $hook_suffix ) {
			wp_enqueue_media();
			$script_path = RTG_PLUGIN_DIR . 'assets/js/admin-assets.js';

			wp_enqueue_script(
				'rtg-admin-assets',
				RTG_PLUGIN_URL . 'assets/js/admin-assets.js',
				array( 'jquery' ),
				file_exists( $script_path ) ? (string) filemtime( $script_path ) : null,
				true
			);
		}
	}

	/**
	 * Register plugin admin menus.
	 *
	 * @return void
	 */
	public static function register_admin_menu() {
		add_menu_page(
			esc_html__( 'Real Treasury Gate', 'rt-gate' ),
			esc_html__( 'Real Treasury Gate', 'rt-gate' ),
			'manage_options',
			'rtg-dashboard',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-lock',
			56
		);

		add_submenu_page(
			'rtg-dashboard',
			esc_html__( 'Dashboard', 'rt-gate' ),
			esc_html__( 'Dashboard', 'rt-gate' ),
			'manage_options',
			'rtg-dashboard',
			array( __CLASS__, 'render_dashboard_page' )
		);

		add_submenu_page(
			'rtg-dashboard',
			esc_html__( 'Forms', 'rt-gate' ),
			esc_html__( 'Forms', 'rt-gate' ),
			'manage_options',
			'rtg-forms',
			array( __CLASS__, 'render_forms_page' )
		);

		add_submenu_page(
			'rtg-dashboard',
			esc_html__( 'Assets', 'rt-gate' ),
			esc_html__( 'Assets', 'rt-gate' ),
			'manage_options',
			'rtg-assets',
			array( __CLASS__, 'render_assets_page' )
		);

		add_submenu_page(
			'rtg-dashboard',
			esc_html__( 'Mappings', 'rt-gate' ),
			esc_html__( 'Mappings', 'rt-gate' ),
			'manage_options',
			'rtg-mappings',
			array( __CLASS__, 'render_mappings_page' )
		);

		add_submenu_page(
			'rtg-dashboard',
			esc_html__( 'Leads', 'rt-gate' ),
			esc_html__( 'Leads', 'rt-gate' ),
			'manage_options',
			'rtg-leads',
			array( __CLASS__, 'render_leads_page' )
		);

		if ( class_exists( 'RTG_Events' ) ) {
			RTG_Events::register_submenu();
		}

		add_submenu_page(
			'rtg-dashboard',
			esc_html__( 'Settings', 'rt-gate' ),
			esc_html__( 'Settings', 'rt-gate' ),
			'manage_options',
			'rtg-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Handle admin postbacks.
	 *
	 * @return void
	 */
	public static function handle_form_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle GET-based export actions.
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$action = isset( $_GET['rtg_action'] ) ? sanitize_key( wp_unslash( $_GET['rtg_action'] ) ) : '';
		if ( 'rtg-leads' === $page && 'export_leads_csv' === $action ) {
			check_admin_referer( 'rtg_export_leads_csv' );
			self::export_leads_csv();
			exit;
		}

		if ( ! isset( $_POST['rtg_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['rtg_action'] ) );

		switch ( $action ) {
			case 'save_form':
				check_admin_referer( 'rtg_save_form' );
				self::save_form();
				break;
			case 'save_asset':
				check_admin_referer( 'rtg_save_asset' );
				self::save_asset();
				break;
			case 'save_mapping':
				check_admin_referer( 'rtg_save_mapping' );
				self::save_mapping();
				break;
			case 'delete_form':
				check_admin_referer( 'rtg_delete_form' );
				self::delete_record( 'rtg_forms', 'rtg-forms', 'Form deleted.' );
				break;
			case 'delete_asset':
				check_admin_referer( 'rtg_delete_asset' );
				self::delete_record( 'rtg_assets', 'rtg-assets', 'Asset deleted.' );
				break;
			case 'delete_mapping':
				check_admin_referer( 'rtg_delete_mapping' );
				self::delete_record( 'rtg_mappings', 'rtg-mappings', 'Mapping deleted.' );
				break;
			case 'save_settings':
				check_admin_referer( 'rtg_save_settings' );
				self::save_settings();
				break;
		}
	}

	/**
	 * Save form record.
	 *
	 * @return void
	 */
	private static function save_form() {
		global $wpdb;

		$table = $wpdb->prefix . 'rtg_forms';
		$id    = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		$privacy_policy_url = self::get_privacy_policy_url();
		$consent_text       = sprintf(
			/* translators: %s: Privacy policy URL. */
			__( 'I agree to receive updates and accept the privacy policy: %s', 'rt-gate' ),
			$privacy_policy_url
		);

		$raw_schema    = isset( $_POST['fields_schema'] ) ? wp_unslash( $_POST['fields_schema'] ) : '';
		$decoded_schema = json_decode( $raw_schema, true );
		$safe_schema    = is_array( $decoded_schema ) ? wp_json_encode( $decoded_schema ) : '[]';

		$data = array(
			'name'          => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'fields_schema' => $safe_schema,
			'consent_text'  => sanitize_text_field( $consent_text ),
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), array( '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $table, $data, array( '%s', '%s', '%s' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rtg-forms&rtg_notice=' . rawurlencode( 'Form saved.' ) ) );
		exit;
	}

	/**
	 * Resolve the site privacy policy URL with a sensible fallback.
	 *
	 * @return string
	 */
	private static function get_privacy_policy_url() {
		$privacy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

		if ( empty( $privacy_url ) ) {
			$privacy_url = home_url( '/privacy-policy/' );
		}

		return esc_url_raw( $privacy_url );
	}

	/**
	 * Save asset record.
	 *
	 * @return void
	 */
	private static function save_asset() {
		global $wpdb;

		$table = $wpdb->prefix . 'rtg_assets';
		$id    = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		$asset_type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$config     = isset( $_POST['config'] ) ? sanitize_textarea_field( wp_unslash( $_POST['config'] ) ) : '';
		$asset_url  = isset( $_POST['asset_url'] ) ? esc_url_raw( wp_unslash( $_POST['asset_url'] ) ) : '';

		if ( ! empty( $asset_url ) ) {
			$config = self::merge_asset_url_into_config( $config, $asset_type, $asset_url );
		}

		$data = array(
			'name'   => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'slug'   => isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '',
			'type'   => $asset_type,
			'config' => $config,
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $table, $data, array( '%s', '%s', '%s', '%s' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rtg-assets&rtg_notice=' . rawurlencode( 'Asset saved.' ) ) );
		exit;
	}

	/**
	 * Merge a simple asset URL into the JSON config based on asset type.
	 *
	 * @param string $config_json Existing config JSON.
	 * @param string $asset_type  Asset type.
	 * @param string $asset_url   Asset URL selected in admin.
	 * @return string
	 */
	private static function merge_asset_url_into_config( $config_json, $asset_type, $asset_url ) {
		$config = array();

		if ( ! empty( $config_json ) ) {
			$decoded = json_decode( $config_json, true );
			if ( is_array( $decoded ) ) {
				$config = $decoded;
			}
		}

		switch ( $asset_type ) {
			case 'download':
				$config['file_url'] = $asset_url;
				break;
			case 'video':
				$config['embed_url'] = $asset_url;
				break;
			case 'link':
				$config['target_url'] = $asset_url;
				break;
		}

		return wp_json_encode( $config );
	}

	/**
	 * Save mapping record.
	 *
	 * @return void
	 */
	private static function save_mapping() {
		global $wpdb;

		$table = $wpdb->prefix . 'rtg_mappings';
		$id    = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		$data = array(
			'form_id'             => isset( $_POST['form_id'] ) ? absint( wp_unslash( $_POST['form_id'] ) ) : 0,
			'asset_id'            => isset( $_POST['asset_id'] ) ? absint( wp_unslash( $_POST['asset_id'] ) ) : 0,
			'iframe_src_template' => isset( $_POST['iframe_src_template'] ) ? sanitize_text_field( wp_unslash( $_POST['iframe_src_template'] ) ) : '',
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ), array( '%d', '%d', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert( $table, $data, array( '%d', '%d', '%s' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rtg-mappings&rtg_notice=' . rawurlencode( 'Mapping saved.' ) ) );
		exit;
	}

	/**
	 * Delete a record from a plugin table by ID.
	 *
	 * @param string $table_suffix Table name suffix (e.g. 'rtg_forms').
	 * @param string $redirect_page Admin page slug to redirect to.
	 * @param string $notice Success message.
	 * @return void
	 */
	private static function delete_record( $table_suffix, $redirect_page, $notice ) {
		global $wpdb;

		$id = isset( $_POST['delete_id'] ) ? absint( wp_unslash( $_POST['delete_id'] ) ) : 0;
		if ( $id > 0 ) {
			$table = $wpdb->prefix . $table_suffix;
			$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_page . '&rtg_notice=' . rawurlencode( $notice ) ) );
		exit;
	}

	/**
	 * Save plugin settings.
	 *
	 * @return void
	 */
	private static function save_settings() {
		$settings = array(
			'token_ttl_minutes' => isset( $_POST['token_ttl_minutes'] ) ? max( 1, absint( wp_unslash( $_POST['token_ttl_minutes'] ) ) ) : 60,
			'allowed_origins'   => isset( $_POST['allowed_origins'] ) ? sanitize_textarea_field( wp_unslash( $_POST['allowed_origins'] ) ) : '',
			'webhook_url'       => isset( $_POST['webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['webhook_url'] ) ) : '',
			'webhook_secret'    => isset( $_POST['webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) : '',
			'webhook_events'    => isset( $_POST['webhook_events'] ) && is_array( $_POST['webhook_events'] )
				? array_map( 'sanitize_key', wp_unslash( $_POST['webhook_events'] ) )
				: array(),
		);

		update_option( 'rtg_settings', $settings );

		wp_safe_redirect( admin_url( 'admin.php?page=rtg-settings&rtg_notice=' . rawurlencode( 'Settings saved.' ) ) );
		exit;
	}

	/**
	 * Render the dashboard overview page.
	 *
	 * @return void
	 */
	public static function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rt-gate' ) );
		}

		global $wpdb;
		$total_forms   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rtg_forms" );
		$total_assets  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rtg_assets" );
		$total_leads   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rtg_leads" );
		$total_events  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rtg_events" );
		$total_mappings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}rtg_mappings" );
		$active_tokens = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}rtg_tokens WHERE expires_at > %s",
			gmdate( 'Y-m-d H:i:s' )
		) );

		$recent_events = $wpdb->get_results(
			"SELECT e.id, e.event_type, e.created_at,
				COALESCE(l.email, '') AS email,
				COALESCE(f.name, '') AS form_name,
				COALESCE(a.name, '') AS asset_name
			FROM {$wpdb->prefix}rtg_events e
			LEFT JOIN {$wpdb->prefix}rtg_leads l ON l.id = e.lead_id
			LEFT JOIN {$wpdb->prefix}rtg_forms f ON f.id = e.form_id
			LEFT JOIN {$wpdb->prefix}rtg_assets a ON a.id = e.asset_id
			ORDER BY e.id DESC LIMIT 10"
		);

		$recent_leads = $wpdb->get_results(
			"SELECT l.id, l.email, l.form_data, l.created_at
			FROM {$wpdb->prefix}rtg_leads l
			ORDER BY l.id DESC LIMIT 5"
		);
		?>
		<div class="wrap rtg-dashboard-wrap">
			<h1><?php echo esc_html__( 'Real Treasury Gate', 'rt-gate' ); ?></h1>
			<p class="rtg-dashboard-subtitle"><?php echo esc_html__( 'Manage your gated content, track leads, and monitor engagement.', 'rt-gate' ); ?></p>

			<div class="rtg-stats-row">
				<div class="rtg-stat-card">
					<span class="rtg-stat-number"><?php echo esc_html( $total_leads ); ?></span>
					<span class="rtg-stat-label"><?php echo esc_html__( 'Total Leads', 'rt-gate' ); ?></span>
				</div>
				<div class="rtg-stat-card">
					<span class="rtg-stat-number"><?php echo esc_html( $total_events ); ?></span>
					<span class="rtg-stat-label"><?php echo esc_html__( 'Total Events', 'rt-gate' ); ?></span>
				</div>
				<div class="rtg-stat-card">
					<span class="rtg-stat-number"><?php echo esc_html( $active_tokens ); ?></span>
					<span class="rtg-stat-label"><?php echo esc_html__( 'Active Tokens', 'rt-gate' ); ?></span>
				</div>
				<div class="rtg-stat-card">
					<span class="rtg-stat-number"><?php echo esc_html( $total_forms ); ?></span>
					<span class="rtg-stat-label"><?php echo esc_html__( 'Forms', 'rt-gate' ); ?></span>
				</div>
				<div class="rtg-stat-card">
					<span class="rtg-stat-number"><?php echo esc_html( $total_assets ); ?></span>
					<span class="rtg-stat-label"><?php echo esc_html__( 'Assets', 'rt-gate' ); ?></span>
				</div>
			</div>

			<h2 class="rtg-section-title"><?php echo esc_html__( 'Quick Access', 'rt-gate' ); ?></h2>
			<div class="rtg-nav-grid">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-leads' ) ); ?>" class="rtg-nav-card">
					<span class="dashicons dashicons-groups rtg-nav-icon"></span>
					<div class="rtg-nav-content">
						<strong class="rtg-nav-title"><?php echo esc_html__( 'Leads', 'rt-gate' ); ?></strong>
						<span class="rtg-nav-desc"><?php echo esc_html__( 'View all captured leads, filter by form or user type, and export to CSV.', 'rt-gate' ); ?></span>
						<span class="rtg-nav-count">
							<?php
							printf(
								/* translators: %d: number of leads */
								esc_html( _n( '%d lead', '%d leads', $total_leads, 'rt-gate' ) ),
								$total_leads
							);
							?>
						</span>
					</div>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-events' ) ); ?>" class="rtg-nav-card">
					<span class="dashicons dashicons-chart-line rtg-nav-icon"></span>
					<div class="rtg-nav-content">
						<strong class="rtg-nav-title"><?php echo esc_html__( 'Events', 'rt-gate' ); ?></strong>
						<span class="rtg-nav-desc"><?php echo esc_html__( 'Track form submissions, downloads, video plays, and page views.', 'rt-gate' ); ?></span>
						<span class="rtg-nav-count">
							<?php
							printf(
								/* translators: %d: number of events */
								esc_html( _n( '%d event', '%d events', $total_events, 'rt-gate' ) ),
								$total_events
							);
							?>
						</span>
					</div>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-forms' ) ); ?>" class="rtg-nav-card">
					<span class="dashicons dashicons-feedback rtg-nav-icon"></span>
					<div class="rtg-nav-content">
						<strong class="rtg-nav-title"><?php echo esc_html__( 'Forms', 'rt-gate' ); ?></strong>
						<span class="rtg-nav-desc"><?php echo esc_html__( 'Create and manage gated content forms with the no-code builder.', 'rt-gate' ); ?></span>
						<span class="rtg-nav-count">
							<?php
							printf(
								/* translators: %d: number of forms */
								esc_html( _n( '%d form', '%d forms', $total_forms, 'rt-gate' ) ),
								$total_forms
							);
							?>
						</span>
					</div>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-assets' ) ); ?>" class="rtg-nav-card">
					<span class="dashicons dashicons-media-document rtg-nav-icon"></span>
					<div class="rtg-nav-content">
						<strong class="rtg-nav-title"><?php echo esc_html__( 'Assets', 'rt-gate' ); ?></strong>
						<span class="rtg-nav-desc"><?php echo esc_html__( 'Manage downloads, videos, and links that are gated behind forms.', 'rt-gate' ); ?></span>
						<span class="rtg-nav-count">
							<?php
							printf(
								/* translators: %d: number of assets */
								esc_html( _n( '%d asset', '%d assets', $total_assets, 'rt-gate' ) ),
								$total_assets
							);
							?>
						</span>
					</div>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-mappings' ) ); ?>" class="rtg-nav-card">
					<span class="dashicons dashicons-randomize rtg-nav-icon"></span>
					<div class="rtg-nav-content">
						<strong class="rtg-nav-title"><?php echo esc_html__( 'Mappings', 'rt-gate' ); ?></strong>
						<span class="rtg-nav-desc"><?php echo esc_html__( 'Connect forms to assets and configure iframe templates.', 'rt-gate' ); ?></span>
						<span class="rtg-nav-count">
							<?php
							printf(
								/* translators: %d: number of mappings */
								esc_html( _n( '%d mapping', '%d mappings', $total_mappings, 'rt-gate' ) ),
								$total_mappings
							);
							?>
						</span>
					</div>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-settings' ) ); ?>" class="rtg-nav-card">
					<span class="dashicons dashicons-admin-settings rtg-nav-icon"></span>
					<div class="rtg-nav-content">
						<strong class="rtg-nav-title"><?php echo esc_html__( 'Settings', 'rt-gate' ); ?></strong>
						<span class="rtg-nav-desc"><?php echo esc_html__( 'Configure token TTL, CORS origins, and webhook integrations.', 'rt-gate' ); ?></span>
					</div>
				</a>
			</div>

			<div class="rtg-dashboard-panels">
				<div class="rtg-card">
					<div class="rtg-card-header">
						<h2><?php echo esc_html__( 'Recent Events', 'rt-gate' ); ?></h2>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-events' ) ); ?>" class="button button-small"><?php echo esc_html__( 'View All Events', 'rt-gate' ); ?></a>
					</div>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Email', 'rt-gate' ); ?></th>
								<th><?php echo esc_html__( 'Form', 'rt-gate' ); ?></th>
								<th><?php echo esc_html__( 'Asset', 'rt-gate' ); ?></th>
								<th><?php echo esc_html__( 'Event Type', 'rt-gate' ); ?></th>
								<th><?php echo esc_html__( 'Date', 'rt-gate' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $recent_events ) ) : ?>
								<?php foreach ( $recent_events as $event ) : ?>
									<tr>
										<td><?php echo esc_html( $event->email ); ?></td>
										<td><?php echo esc_html( $event->form_name ); ?></td>
										<td><?php echo esc_html( $event->asset_name ); ?></td>
										<td><span class="rtg-event-badge rtg-event-<?php echo esc_attr( $event->event_type ); ?>"><?php echo esc_html( $event->event_type ); ?></span></td>
										<td><?php echo esc_html( $event->created_at ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="5"><?php echo esc_html__( 'No events recorded yet.', 'rt-gate' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<div class="rtg-card">
					<div class="rtg-card-header">
						<h2><?php echo esc_html__( 'Recent Leads', 'rt-gate' ); ?></h2>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-leads' ) ); ?>" class="button button-small"><?php echo esc_html__( 'View All Leads', 'rt-gate' ); ?></a>
					</div>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Email', 'rt-gate' ); ?></th>
								<th><?php echo esc_html__( 'Name', 'rt-gate' ); ?></th>
								<th><?php echo esc_html__( 'Date', 'rt-gate' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $recent_leads ) ) : ?>
								<?php foreach ( $recent_leads as $lead ) : ?>
									<?php
									$lead_form_data = json_decode( (string) $lead->form_data, true );
									$lead_name      = '';
									if ( is_array( $lead_form_data ) ) {
										if ( ! empty( $lead_form_data['full_name'] ) ) {
											$lead_name = $lead_form_data['full_name'];
										} elseif ( ! empty( $lead_form_data['first_name'] ) ) {
											$lead_name = $lead_form_data['first_name'];
											if ( ! empty( $lead_form_data['last_name'] ) ) {
												$lead_name .= ' ' . $lead_form_data['last_name'];
											}
										}
									}
									?>
									<tr>
										<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-leads&lead_id=' . absint( $lead->id ) ) ); ?>"><?php echo esc_html( $lead->email ); ?></a></td>
										<td><?php echo esc_html( $lead_name ); ?></td>
										<td><?php echo esc_html( $lead->created_at ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="3"><?php echo esc_html__( 'No leads captured yet.', 'rt-gate' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render forms page.
	 *
	 * @return void
	 */
	public static function render_forms_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rt-gate' ) );
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'rtg_forms';
		$forms   = $wpdb->get_results( "SELECT id, name, created_at FROM {$table} ORDER BY id DESC" );
		$edit_id = isset( $_GET['edit_id'] ) ? absint( wp_unslash( $_GET['edit_id'] ) ) : 0;
		$record    = null;
		$asset_url = '';

		if ( $edit_id > 0 ) {
			$record = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, fields_schema, consent_text FROM {$table} WHERE id = %d", $edit_id ) );
		}

		$nonce = wp_create_nonce( 'rtg_save_form' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Forms', 'rt-gate' ); ?></h1>
			<?php self::render_notice(); ?>

			<div class="rtg-card">
				<h2><?php echo esc_html__( 'No-code form builder guide', 'rt-gate' ); ?></h2>
				<ol class="rtg-helper-list">
					<li><?php echo esc_html__( 'Enter a form name. Standard consent text is applied automatically.', 'rt-gate' ); ?></li>
					<li><?php echo esc_html__( 'Use the Field Builder to add your form fields.', 'rt-gate' ); ?></li>
					<li><?php echo esc_html__( 'Click “Apply Fields to JSON” to generate a valid schema automatically.', 'rt-gate' ); ?></li>
					<li><?php echo esc_html__( 'Save the form, then map it to one or more assets on the Mappings tab.', 'rt-gate' ); ?></li>
				</ol>
			</div>
			<?php
			$privacy_policy_url = self::get_privacy_policy_url();
			$standard_consent   = sprintf(
				/* translators: %s: Privacy policy URL. */
				esc_html__( 'I agree to receive updates and accept the privacy policy: %s', 'rt-gate' ),
				esc_url( $privacy_policy_url )
			);
			?>

			<form method="post">
				<input type="hidden" name="rtg_action" value="save_form" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $record ? $record->id : 0 ); ?>" />
				<div class="rtg-form-builder-grid">
					<div class="rtg-card">
						<h3><?php echo esc_html__( 'Form details', 'rt-gate' ); ?></h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="rtg_form_name"><?php echo esc_html__( 'Name', 'rt-gate' ); ?></label></th>
								<td><input id="rtg_form_name" name="name" type="text" class="regular-text" value="<?php echo esc_attr( $record ? $record->name : '' ); ?>" required /></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Consent', 'rt-gate' ); ?></th>
								<td>
									<p><?php echo esc_html( $standard_consent ); ?></p>
									<p class="description">
										<?php echo esc_html__( 'This text is automatically used for every form.', 'rt-gate' ); ?>
										<a href="<?php echo esc_url( $privacy_policy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Review Privacy Policy', 'rt-gate' ); ?></a>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rtg_fields_schema"><?php echo esc_html__( 'Fields Schema (JSON)', 'rt-gate' ); ?></label></th>
								<td>
									<textarea id="rtg_fields_schema" name="fields_schema" rows="8" class="large-text" placeholder="<?php echo esc_attr__( 'Use the Field Builder to generate this automatically.', 'rt-gate' ); ?>"><?php echo esc_html( $record ? $record->fields_schema : '' ); ?></textarea>
									<p class="description"><?php echo esc_html__( 'Advanced users can edit this JSON directly.', 'rt-gate' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<div class="rtg-card">
						<h3><?php echo esc_html__( 'Field Builder', 'rt-gate' ); ?></h3>
						<p><?php echo esc_html__( 'Add fields below, use templates for common forms, then generate the schema automatically.', 'rt-gate' ); ?></p>
						<div class="rtg-form-builder-presets">
							<select id="rtg_form_template_select">
								<option value=""><?php echo esc_html__( 'Choose a form template…', 'rt-gate' ); ?></option>
								<option value="lead_gen"><?php echo esc_html__( 'Lead capture (name, company, role)', 'rt-gate' ); ?></option>
								<option value="webinar"><?php echo esc_html__( 'Webinar registration', 'rt-gate' ); ?></option>
								<option value="download"><?php echo esc_html__( 'Download request', 'rt-gate' ); ?></option>
							</select>
							<button type="button" class="button" id="rtg_insert_template"><?php echo esc_html__( 'Insert Template', 'rt-gate' ); ?></button>
							<button type="button" class="button" id="rtg_add_email_field"><?php echo esc_html__( 'Quick Add Email', 'rt-gate' ); ?></button>
							<button type="button" class="button" id="rtg_add_name_field"><?php echo esc_html__( 'Quick Add Name', 'rt-gate' ); ?></button>
							<button type="button" class="button" id="rtg_add_phone_field"><?php echo esc_html__( 'Quick Add Phone', 'rt-gate' ); ?></button>
							<button type="button" class="button" id="rtg_add_user_type_field"><?php echo esc_html__( 'Quick Add User Type', 'rt-gate' ); ?></button>
						</div>
						<div class="rtg-form-builder-scroller">
							<table class="widefat striped rtg-form-builder-table" id="rtg_field_builder_table">
								<thead>
								<tr>
									<th><?php echo esc_html__( 'Label', 'rt-gate' ); ?></th>
									<th><?php echo esc_html__( 'Key', 'rt-gate' ); ?></th>
									<th><?php echo esc_html__( 'Type', 'rt-gate' ); ?></th>
									<th><?php echo esc_html__( 'Required', 'rt-gate' ); ?></th>
									<th><?php echo esc_html__( 'Autocomplete', 'rt-gate' ); ?></th>
									<th><?php echo esc_html__( 'Placeholder / Options', 'rt-gate' ); ?></th>
									<th><?php echo esc_html__( 'Reorder', 'rt-gate' ); ?></th>
									<th><?php echo esc_html__( 'Remove', 'rt-gate' ); ?></th>
								</tr>
								</thead>
								<tbody id="rtg_field_builder_rows"></tbody>
							</table>
						</div>
						<div class="rtg-form-builder-actions">
							<button type="button" class="button" id="rtg_add_field_row"><?php echo esc_html__( 'Add Field', 'rt-gate' ); ?></button>
							<button type="button" class="button button-secondary" id="rtg_load_from_json"><?php echo esc_html__( 'Load From JSON', 'rt-gate' ); ?></button>
							<button type="button" class="button button-primary" id="rtg_apply_to_json"><?php echo esc_html__( 'Apply Fields to JSON', 'rt-gate' ); ?></button>
						</div>
						<p class="description"><?php echo esc_html__( 'Available field types: text, email, tel, company, textarea, select, radio, checkbox, url, number, date.', 'rt-gate' ); ?></p>
						<p class="description"><?php echo esc_html__( 'For select/radio/checkbox fields, enter options as comma-separated values (example: Investor, Advisor, Other).', 'rt-gate' ); ?></p>
						<p class="description"><?php echo esc_html__( 'Autocomplete controls browser autofill behavior for each field.', 'rt-gate' ); ?></p>
						<p class="rtg-builder-status" id="rtg_builder_status" aria-live="polite"></p>
					</div>
				</div>
				<?php submit_button( $record ? esc_html__( 'Update Form', 'rt-gate' ) : esc_html__( 'Create Form', 'rt-gate' ) ); ?>
			</form>
			<script>
				(function () {
					var rowsContainer = document.getElementById('rtg_field_builder_rows');
					var jsonTextarea = document.getElementById('rtg_fields_schema');
					var formElement = jsonTextarea ? jsonTextarea.closest('form') : null;
					var addButton = document.getElementById('rtg_add_field_row');
					var loadButton = document.getElementById('rtg_load_from_json');
					var applyButton = document.getElementById('rtg_apply_to_json');
					var statusNode = document.getElementById('rtg_builder_status');
					var templateSelect = document.getElementById('rtg_form_template_select');
					var templateButton = document.getElementById('rtg_insert_template');
					var quickEmailButton = document.getElementById('rtg_add_email_field');
					var quickNameButton = document.getElementById('rtg_add_name_field');
					var quickPhoneButton = document.getElementById('rtg_add_phone_field');
					var quickUserTypeButton = document.getElementById('rtg_add_user_type_field');
					var userTypeOptions = [
						'Corporate treasury professional (current system in place)',
						'Corporate treasury professional (planning a system change)',
						'Corporate treasury professional (early research / alignment)',
						'Treasury technology provider',
						'Treasury or finance consultant'
					];

					if (!rowsContainer || !jsonTextarea || !formElement || !addButton || !loadButton || !applyButton) {
						return;
					}

					var fieldTypes = ['text', 'email', 'tel', 'company', 'textarea', 'select', 'radio', 'checkbox', 'url', 'number', 'date'];
					var fieldTypeHelp = {
						text: 'Great for plain short text.',
						email: 'Use for email addresses.',
						tel: 'Use for phone numbers.',
						company: 'Use when collecting organization names.',
						textarea: 'Best for longer responses.',
						select: 'Comma-separated options required.',
						radio: 'Comma-separated options required.',
						checkbox: 'Comma-separated options required.',
						url: 'Use for website/profile links.',
						number: 'Use for numeric values.',
						date: 'Use for date values.'
					};
					var formTemplates = {
						lead_gen: [
							{ type: 'text', label: 'Full Name', key: 'full_name', required: true, placeholder: 'Jane Smith' },
							{ type: 'email', label: 'Email Address', key: 'email', required: true, placeholder: 'you@company.com' },
							{ type: 'company', label: 'Company', key: 'company', required: true, placeholder: 'Real Treasury' },
							{ type: 'select', label: 'Role', key: 'role', required: true, options: ['CFO', 'Finance Lead', 'Operations', 'Other'] }
						],
						webinar: [
							{ type: 'text', label: 'Full Name', key: 'full_name', required: true },
							{ type: 'email', label: 'Email Address', key: 'email', required: true },
							{ type: 'company', label: 'Company', key: 'company', required: false },
							{ type: 'select', label: 'Session Preference', key: 'session_preference', required: true, options: ['Morning', 'Afternoon', 'On-demand'] },
							{ type: 'checkbox', label: 'Topics of Interest', key: 'topics', required: false, options: ['Treasury', 'Reporting', 'Automation'] }
						],
						download: [
							{ type: 'email', label: 'Work Email', key: 'email', required: true },
							{ type: 'text', label: 'First Name', key: 'first_name', required: true },
							{ type: 'text', label: 'Last Name', key: 'last_name', required: true },
							{ type: 'company', label: 'Company', key: 'company', required: false },
							{ type: 'radio', label: 'I am evaluating this for', key: 'use_case', required: true, options: ['Myself', 'My team', 'My clients'] }
						]
					};

					function setStatus(message) {
						if (statusNode) {
							statusNode.textContent = message;
						}
					}

					function createFieldRow(field) {
						var defaults = field || {};
						var selectedType = defaults.type || 'text';
						var extraValue = (defaults.placeholder || (defaults.options ? defaults.options.join(', ') : '')) || '';
						var autocompleteEnabled = defaults.autocomplete !== false;
						var row = document.createElement('tr');
						row.innerHTML =
							'<td><input type="text" class="rtg-f-label" value="' + (defaults.label || '') + '" placeholder="Full Name" /></td>' +
							'<td><input type="text" class="rtg-f-key" value="' + (defaults.key || '') + '" placeholder="full_name" /></td>' +
							'<td>' +
								'<select class="rtg-f-type">' + fieldTypes.map(function (type) {
									var selected = selectedType === type ? ' selected' : '';
									return '<option value="' + type + '"' + selected + '>' + type + '</option>';
								}).join('') +
								'</select>' +
							'</td>' +
							'<td><label><input type="checkbox" class="rtg-f-required"' + (defaults.required ? ' checked' : '') + ' /> <?php echo esc_js( __( 'Yes', 'rt-gate' ) ); ?></label></td>' +
							'<td><label><input type="checkbox" class="rtg-f-autocomplete"' + (autocompleteEnabled ? ' checked' : '') + ' /> <?php echo esc_js( __( 'Allow', 'rt-gate' ) ); ?></label></td>' +
							'<td><input type="text" class="rtg-f-extra" value="' + extraValue + '" placeholder="Enter placeholder or options" /><small class="rtg-f-extra-help">' + (fieldTypeHelp[selectedType] || '') + '</small></td>' +
							'<td><button type="button" class="button-link rtg-move-up">↑</button> <button type="button" class="button-link rtg-move-down">↓</button></td>' +
							'<td><button type="button" class="button-link-delete rtg-remove-row"><?php echo esc_js( __( 'Remove', 'rt-gate' ) ); ?></button></td>';

						rowsContainer.appendChild(row);
					}

					function sanitizeKey(value) {
						return value.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '');
					}

					function collectRows() {
						var schema = [];
						var usedKeys = {};
						rowsContainer.querySelectorAll('tr').forEach(function (row) {
							var label = row.querySelector('.rtg-f-label').value.trim();
							var keyInput = row.querySelector('.rtg-f-key');
							var type = row.querySelector('.rtg-f-type').value;
							var required = row.querySelector('.rtg-f-required').checked;
							var autocomplete = row.querySelector('.rtg-f-autocomplete').checked;
							var extra = row.querySelector('.rtg-f-extra').value.trim();

							if (!label) {
								return;
							}

							var key = sanitizeKey(keyInput.value || label);
							if (!key) {
								return;
							}

							if (usedKeys[key]) {
								var suffix = 2;
								while (usedKeys[key + '_' + suffix]) {
									suffix++;
								}
								key = key + '_' + suffix;
							}

							usedKeys[key] = true;
							keyInput.value = key;

							var field = {
								key: key,
								label: label,
								type: type,
								required: required,
								autocomplete: autocomplete
							};

							if (['select', 'radio', 'checkbox'].indexOf(type) !== -1 && extra) {
								field.options = extra.split(',').map(function (value) {
									return value.trim();
								}).filter(Boolean);
							} else if (extra) {
								field.placeholder = extra;
							}

							schema.push(field);
						});

						return schema;
					}

					addButton.addEventListener('click', function () {
						createFieldRow();
						setStatus('Field added.');
					});

					rowsContainer.addEventListener('input', function (event) {
						if (event.target.classList.contains('rtg-f-label')) {
							var row = event.target.closest('tr');
							var keyInput = row.querySelector('.rtg-f-key');
							if (!keyInput.value.trim()) {
								keyInput.value = sanitizeKey(event.target.value);
							}
						}
					});

					rowsContainer.addEventListener('change', function (event) {
						if (!event.target.classList.contains('rtg-f-type')) {
							return;
						}

						var row = event.target.closest('tr');
						var help = row.querySelector('.rtg-f-extra-help');
						if (help) {
							help.textContent = fieldTypeHelp[event.target.value] || '';
						}
					});

					rowsContainer.addEventListener('click', function (event) {
						var row = event.target.closest('tr');
						if (!row) {
							return;
						}

						if (event.target.classList.contains('rtg-remove-row')) {
							event.preventDefault();
							row.remove();
							setStatus('Field removed.');
							return;
						}

						if (event.target.classList.contains('rtg-move-up') && row.previousElementSibling) {
							event.preventDefault();
							rowsContainer.insertBefore(row, row.previousElementSibling);
							setStatus('Field moved up.');
							return;
						}

						if (event.target.classList.contains('rtg-move-down') && row.nextElementSibling) {
							event.preventDefault();
							rowsContainer.insertBefore(row.nextElementSibling, row);
							setStatus('Field moved down.');
						}
					});

					applyButton.addEventListener('click', function () {
						var schema = collectRows();
						jsonTextarea.value = JSON.stringify(schema, null, 2);
						setStatus('Schema updated from builder.');
					});

					formElement.addEventListener('submit', function () {
						var schema = collectRows();
						jsonTextarea.value = JSON.stringify(schema, null, 2);
					});

					loadButton.addEventListener('click', function () {
						var parsed = [];
						var source = jsonTextarea.value.trim();

						if (source) {
							try {
								parsed = JSON.parse(source);
							} catch (error) {
								window.alert('<?php echo esc_js( __( 'JSON is invalid. Please fix it first, then try loading again.', 'rt-gate' ) ); ?>');
								return;
							}
						}

						rowsContainer.innerHTML = '';
						if (Array.isArray(parsed) && parsed.length) {
							parsed.forEach(function (field) {
								createFieldRow(field);
							});
							setStatus('Loaded fields from JSON.');
						} else {
							createFieldRow({ type: 'email', label: 'Email Address', key: 'email', required: true });
							setStatus('Added a starter email field.');
						}
					});

					if (templateButton && templateSelect) {
						templateButton.addEventListener('click', function () {
							var templateKey = templateSelect.value;
							if (!templateKey || !formTemplates[templateKey]) {
								window.alert('<?php echo esc_js( __( 'Please choose a template first.', 'rt-gate' ) ); ?>');
								return;
							}

							rowsContainer.innerHTML = '';
							formTemplates[templateKey].forEach(function (field) {
								createFieldRow(field);
							});
							setStatus('Template inserted. Click "Apply Fields to JSON" to save it into schema JSON.');
						});
					}

					if (quickEmailButton) {
						quickEmailButton.addEventListener('click', function () {
							createFieldRow({ type: 'email', label: 'Email Address', key: 'email', required: true });
							setStatus('Quick email field added.');
						});
					}

					if (quickNameButton) {
						quickNameButton.addEventListener('click', function () {
							createFieldRow({ type: 'text', label: 'Full Name', key: 'full_name', required: true });
							setStatus('Quick name field added.');
						});
					}

					if (quickPhoneButton) {
						quickPhoneButton.addEventListener('click', function () {
							createFieldRow({ type: 'tel', label: 'Phone Number', key: 'phone', required: false });
							setStatus('Quick phone field added.');
						});
					}

					if (quickUserTypeButton) {
						quickUserTypeButton.addEventListener('click', function () {
							createFieldRow({
								type: 'select',
								label: 'Type of User',
								key: 'user_type',
								required: true,
								options: userTypeOptions
							});
							setStatus('Quick user type dropdown added.');
						});
					}

					loadButton.click();
				})();
			</script>

			<h2><?php echo esc_html__( 'Existing Forms', 'rt-gate' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'ID', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Name', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Created', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Actions', 'rt-gate' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $forms ) ) : ?>
						<?php foreach ( $forms as $form ) : ?>
							<tr>
								<td><?php echo esc_html( $form->id ); ?></td>
								<td><?php echo esc_html( $form->name ); ?></td>
								<td><?php echo esc_html( $form->created_at ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-forms&edit_id=' . absint( $form->id ) ) ); ?>"><?php echo esc_html__( 'Edit', 'rt-gate' ); ?></a>
									&nbsp;|&nbsp;
									<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this form? This cannot be undone.', 'rt-gate' ) ); ?>');">
										<?php wp_nonce_field( 'rtg_delete_form' ); ?>
										<input type="hidden" name="rtg_action" value="delete_form" />
										<input type="hidden" name="delete_id" value="<?php echo esc_attr( $form->id ); ?>" />
										<button type="submit" class="button-link button-link-delete"><?php echo esc_html__( 'Delete', 'rt-gate' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="4"><?php echo esc_html__( 'No forms found.', 'rt-gate' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render assets page.
	 *
	 * @return void
	 */
	public static function render_assets_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rt-gate' ) );
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'rtg_assets';
		$assets  = $wpdb->get_results( "SELECT id, name, slug, type, created_at FROM {$table} ORDER BY id DESC" );
		$edit_id   = isset( $_GET['edit_id'] ) ? absint( wp_unslash( $_GET['edit_id'] ) ) : 0;
		$record    = null;
		$asset_url = '';

		if ( $edit_id > 0 ) {
			$record = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, slug, type, config FROM {$table} WHERE id = %d", $edit_id ) );

			if ( $record && ! empty( $record->config ) ) {
				$config = json_decode( $record->config, true );
				if ( is_array( $config ) ) {
					if ( 'download' === $record->type && ! empty( $config['file_url'] ) ) {
						$asset_url = $config['file_url'];
					} elseif ( 'video' === $record->type && ! empty( $config['embed_url'] ) ) {
						$asset_url = $config['embed_url'];
					} elseif ( 'link' === $record->type && ! empty( $config['target_url'] ) ) {
						$asset_url = $config['target_url'];
					}
				}
			}
		}

		$nonce = wp_create_nonce( 'rtg_save_asset' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Assets', 'rt-gate' ); ?></h1>
			<?php self::render_notice(); ?>
			<form method="post">
				<input type="hidden" name="rtg_action" value="save_asset" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $record ? $record->id : 0 ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rtg_asset_name"><?php echo esc_html__( 'Name', 'rt-gate' ); ?></label></th>
						<td><input id="rtg_asset_name" name="name" type="text" class="regular-text" value="<?php echo esc_attr( $record ? $record->name : '' ); ?>" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rtg_asset_slug"><?php echo esc_html__( 'Slug', 'rt-gate' ); ?></label></th>
						<td><input id="rtg_asset_slug" name="slug" type="text" class="regular-text" value="<?php echo esc_attr( $record ? $record->slug : '' ); ?>" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rtg_asset_type"><?php echo esc_html__( 'Type', 'rt-gate' ); ?></label></th>
						<td>
							<select id="rtg_asset_type" name="type">
								<?php
								$types = array( 'download', 'video', 'link' );
								$current_type = $record ? $record->type : '';
								foreach ( $types as $type ) :
									?>
									<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $current_type, $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rtg_asset_url"><?php echo esc_html__( 'Asset URL', 'rt-gate' ); ?></label></th>
						<td>
							<input id="rtg_asset_url" name="asset_url" type="url" class="large-text" value="<?php echo esc_attr( $asset_url ); ?>" placeholder="https://" />
							<p>
								<button type="button" class="button" id="rtg_select_media"><?php echo esc_html__( 'Choose from Media Library', 'rt-gate' ); ?></button>
							</p>
							<p class="description"><?php echo esc_html__( 'Optional helper: selecting a URL here auto-populates config as file_url (download), embed_url (video), or target_url (link).', 'rt-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rtg_asset_config"><?php echo esc_html__( 'Config (JSON)', 'rt-gate' ); ?></label></th>
						<td><textarea id="rtg_asset_config" name="config" rows="8" class="large-text"><?php echo esc_html( $record ? $record->config : '' ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button( $record ? esc_html__( 'Update Asset', 'rt-gate' ) : esc_html__( 'Create Asset', 'rt-gate' ) ); ?>
			</form>
			<script>
				(function () {
					var nameInput = document.getElementById('rtg_asset_name');
					var slugInput = document.getElementById('rtg_asset_slug');
					if (!nameInput || !slugInput) return;
					nameInput.addEventListener('blur', function () {
						if (slugInput.value.trim()) return;
						slugInput.value = nameInput.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
					});
				})();
			</script>

			<h2><?php echo esc_html__( 'Existing Assets', 'rt-gate' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'ID', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Name', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Slug', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Type', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Created', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Actions', 'rt-gate' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $assets ) ) : ?>
						<?php foreach ( $assets as $asset ) : ?>
							<tr>
								<td><?php echo esc_html( $asset->id ); ?></td>
								<td><?php echo esc_html( $asset->name ); ?></td>
								<td><?php echo esc_html( $asset->slug ); ?></td>
								<td><?php echo esc_html( $asset->type ); ?></td>
								<td><?php echo esc_html( $asset->created_at ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-assets&edit_id=' . absint( $asset->id ) ) ); ?>"><?php echo esc_html__( 'Edit', 'rt-gate' ); ?></a>
									&nbsp;|&nbsp;
									<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this asset? This cannot be undone.', 'rt-gate' ) ); ?>');">
										<?php wp_nonce_field( 'rtg_delete_asset' ); ?>
										<input type="hidden" name="rtg_action" value="delete_asset" />
										<input type="hidden" name="delete_id" value="<?php echo esc_attr( $asset->id ); ?>" />
										<button type="submit" class="button-link button-link-delete"><?php echo esc_html__( 'Delete', 'rt-gate' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="6"><?php echo esc_html__( 'No assets found.', 'rt-gate' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render mappings page.
	 *
	 * @return void
	 */
	public static function render_mappings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rt-gate' ) );
		}

		global $wpdb;
		$mappings_table = $wpdb->prefix . 'rtg_mappings';
		$forms_table    = $wpdb->prefix . 'rtg_forms';
		$assets_table   = $wpdb->prefix . 'rtg_assets';

		$mappings = $wpdb->get_results( "SELECT m.id, m.form_id, m.asset_id, m.iframe_src_template, m.created_at, f.name AS form_name, a.name AS asset_name FROM {$mappings_table} m LEFT JOIN {$forms_table} f ON f.id = m.form_id LEFT JOIN {$assets_table} a ON a.id = m.asset_id ORDER BY m.id DESC" );
		$forms    = $wpdb->get_results( "SELECT id, name FROM {$forms_table} ORDER BY name ASC" );
		$assets   = $wpdb->get_results( "SELECT id, name FROM {$assets_table} ORDER BY name ASC" );
		$edit_id  = isset( $_GET['edit_id'] ) ? absint( wp_unslash( $_GET['edit_id'] ) ) : 0;
		$record   = null;

		if ( $edit_id > 0 ) {
			$record = $wpdb->get_row( $wpdb->prepare( "SELECT id, form_id, asset_id, iframe_src_template FROM {$mappings_table} WHERE id = %d", $edit_id ) );
		}

		$nonce = wp_create_nonce( 'rtg_save_mapping' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Mappings', 'rt-gate' ); ?></h1>
			<?php self::render_notice(); ?>
			<form method="post">
				<input type="hidden" name="rtg_action" value="save_mapping" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $record ? $record->id : 0 ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rtg_mapping_form_id"><?php echo esc_html__( 'Form', 'rt-gate' ); ?></label></th>
						<td>
							<select id="rtg_mapping_form_id" name="form_id">
								<?php foreach ( $forms as $form ) : ?>
									<option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $record ? $record->form_id : 0, $form->id ); ?>><?php echo esc_html( $form->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rtg_mapping_asset_id"><?php echo esc_html__( 'Asset', 'rt-gate' ); ?></label></th>
						<td>
							<select id="rtg_mapping_asset_id" name="asset_id">
								<?php foreach ( $assets as $asset ) : ?>
									<option value="<?php echo esc_attr( $asset->id ); ?>" <?php selected( $record ? $record->asset_id : 0, $asset->id ); ?>><?php echo esc_html( $asset->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rtg_iframe_src_template"><?php echo esc_html__( 'Iframe Source Template (optional)', 'rt-gate' ); ?></label></th>
						<td>
							<input id="rtg_iframe_src_template" name="iframe_src_template" type="text" class="large-text" value="<?php echo esc_attr( $record ? $record->iframe_src_template : '' ); ?>" placeholder="https://yoursite.github.io/gate/?asset={asset_slug}&t={token}" />
							<p class="description"><?php echo esc_html__( 'Optional fallback URL template. The form page can pass its own gate_url at submit time instead. Placeholders: {asset_slug} and {token}.', 'rt-gate' ); ?></p>
							<p class="description"><?php echo esc_html__( 'Example: https://yoursite.github.io/gate/?asset={asset_slug}&t={token}', 'rt-gate' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( $record ? esc_html__( 'Update Mapping', 'rt-gate' ) : esc_html__( 'Create Mapping', 'rt-gate' ) ); ?>
			</form>

			<h2><?php echo esc_html__( 'Existing Mappings', 'rt-gate' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'ID', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Form', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Asset', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Iframe Template', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Created', 'rt-gate' ); ?></th>
						<th><?php echo esc_html__( 'Actions', 'rt-gate' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $mappings ) ) : ?>
						<?php foreach ( $mappings as $mapping ) : ?>
							<tr>
								<td><?php echo esc_html( $mapping->id ); ?></td>
								<td><?php echo esc_html( $mapping->form_name ); ?></td>
								<td><?php echo esc_html( $mapping->asset_name ); ?></td>
								<td><?php echo esc_html( $mapping->iframe_src_template ); ?></td>
								<td><?php echo esc_html( $mapping->created_at ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-mappings&edit_id=' . absint( $mapping->id ) ) ); ?>"><?php echo esc_html__( 'Edit', 'rt-gate' ); ?></a>
									&nbsp;|&nbsp;
									<form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this mapping? This cannot be undone.', 'rt-gate' ) ); ?>');">
										<?php wp_nonce_field( 'rtg_delete_mapping' ); ?>
										<input type="hidden" name="rtg_action" value="delete_mapping" />
										<input type="hidden" name="delete_id" value="<?php echo esc_attr( $mapping->id ); ?>" />
										<button type="submit" class="button-link button-link-delete"><?php echo esc_html__( 'Delete', 'rt-gate' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr><td colspan="6"><?php echo esc_html__( 'No mappings found.', 'rt-gate' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rt-gate' ) );
		}

		$settings        = get_option( 'rtg_settings', array() );
		$settings        = is_array( $settings ) ? $settings : array();
		$ttl_minutes     = isset( $settings['token_ttl_minutes'] ) ? absint( $settings['token_ttl_minutes'] ) : 60;
		$allowed_origins = isset( $settings['allowed_origins'] ) ? (string) $settings['allowed_origins'] : '';
		$webhook_url     = isset( $settings['webhook_url'] ) ? (string) $settings['webhook_url'] : '';
		$webhook_secret  = isset( $settings['webhook_secret'] ) ? (string) $settings['webhook_secret'] : '';
		$webhook_events  = isset( $settings['webhook_events'] ) && is_array( $settings['webhook_events'] ) ? $settings['webhook_events'] : array();

		$nonce = wp_create_nonce( 'rtg_save_settings' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Settings', 'rt-gate' ); ?></h1>
			<?php self::render_notice(); ?>
			<form method="post">
				<input type="hidden" name="rtg_action" value="save_settings" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />

				<h2><?php echo esc_html__( 'Token Settings', 'rt-gate' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rtg_token_ttl_minutes"><?php echo esc_html__( 'Token TTL (minutes)', 'rt-gate' ); ?></label></th>
						<td>
							<input id="rtg_token_ttl_minutes" name="token_ttl_minutes" type="number" min="1" class="small-text" value="<?php echo esc_attr( $ttl_minutes ); ?>" />
							<p class="description"><?php echo esc_html__( 'How long access tokens remain valid after form submission. Default: 60 minutes.', 'rt-gate' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'CORS Settings', 'rt-gate' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rtg_allowed_origins"><?php echo esc_html__( 'Allowed Origins', 'rt-gate' ); ?></label></th>
						<td>
							<textarea id="rtg_allowed_origins" name="allowed_origins" rows="4" class="large-text" placeholder="github.io"><?php echo esc_textarea( $allowed_origins ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'One hostname per line. Subdomains are automatically allowed. Default: github.io. The site\'s own origin is always allowed.', 'rt-gate' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Webhook Settings', 'rt-gate' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rtg_webhook_url"><?php echo esc_html__( 'Webhook URL', 'rt-gate' ); ?></label></th>
						<td><input id="rtg_webhook_url" name="webhook_url" type="url" class="large-text" value="<?php echo esc_attr( $webhook_url ); ?>" placeholder="https://" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rtg_webhook_secret"><?php echo esc_html__( 'Webhook Secret', 'rt-gate' ); ?></label></th>
						<td>
							<input id="rtg_webhook_secret" name="webhook_secret" type="password" class="regular-text" value="<?php echo esc_attr( $webhook_secret ); ?>" autocomplete="off" />
							<p class="description"><?php echo esc_html__( 'Used to sign webhook payloads with HMAC-SHA256.', 'rt-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Webhook Events', 'rt-gate' ); ?></th>
						<td>
							<?php
							$all_webhook_events = array( 'form_submit', 'asset_access', 'asset_event' );
							foreach ( $all_webhook_events as $event_key ) :
								?>
								<label style="display:block; margin-bottom:6px;">
									<input type="checkbox" name="webhook_events[]" value="<?php echo esc_attr( $event_key ); ?>" <?php checked( in_array( $event_key, $webhook_events, true ) ); ?> />
									<?php echo esc_html( $event_key ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<?php submit_button( esc_html__( 'Save Settings', 'rt-gate' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the leads management page.
	 *
	 * @return void
	 */
	public static function render_leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'rt-gate' ) );
		}

		$lead_id = isset( $_GET['lead_id'] ) ? absint( wp_unslash( $_GET['lead_id'] ) ) : 0;
		if ( $lead_id > 0 ) {
			self::render_lead_detail( $lead_id );
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

		if ( ! class_exists( 'RTG_Leads_List_Table' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Leads', 'rt-gate' ) . '</h1>';
			echo '<p>' . esc_html__( 'Unable to load the leads table. Please deactivate and reactivate the plugin.', 'rt-gate' ) . '</p></div>';
			return;
		}

		$table = new RTG_Leads_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Leads', 'rt-gate' ); ?></h1>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rtg-leads&rtg_action=export_leads_csv' ), 'rtg_export_leads_csv' ) ); ?>">
					<?php echo esc_html__( 'Export CSV', 'rt-gate' ); ?>
				</a>
			</p>
			<form method="get">
				<input type="hidden" name="page" value="rtg-leads" />
				<?php $table->search_box( esc_html__( 'Search Email', 'rt-gate' ), 'rtg-leads' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render detail view for a single lead.
	 *
	 * @param int $lead_id Lead ID.
	 * @return void
	 */
	private static function render_lead_detail( $lead_id ) {
		global $wpdb;

		$leads_table = $wpdb->prefix . 'rtg_leads';
		$lead        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$leads_table} WHERE id = %d", $lead_id ) );

		if ( ! $lead ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Lead Not Found', 'rt-gate' ) . '</h1></div>';
			return;
		}

		$form_data = json_decode( (string) $lead->form_data, true );
		if ( ! is_array( $form_data ) ) {
			$form_data = array();
		}

		$events_table = $wpdb->prefix . 'rtg_events';
		$forms_table  = $wpdb->prefix . 'rtg_forms';
		$assets_table = $wpdb->prefix . 'rtg_assets';

		$events = $wpdb->get_results( $wpdb->prepare(
			"SELECT e.id, e.event_type, e.meta, e.created_at,
				COALESCE(f.name, '') AS form_name,
				COALESCE(a.name, '') AS asset_name,
				COALESCE(a.type, '') AS asset_type
			FROM {$events_table} e
			LEFT JOIN {$forms_table} f ON f.id = e.form_id
			LEFT JOIN {$assets_table} a ON a.id = e.asset_id
			WHERE e.lead_id = %d
			ORDER BY e.id DESC",
			$lead_id
		) );

		$accessed_assets = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.name, a.type, a.slug,
				GROUP_CONCAT(DISTINCT e.event_type ORDER BY e.event_type SEPARATOR ', ') AS event_types
			FROM {$events_table} e
			INNER JOIN {$assets_table} a ON a.id = e.asset_id
			WHERE e.lead_id = %d AND e.asset_id > 0
			GROUP BY a.id, a.name, a.type, a.slug
			ORDER BY a.name ASC",
			$lead_id
		) );
		?>
		<div class="wrap">
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-leads' ) ); ?>">&larr; <?php echo esc_html__( 'Back to Leads', 'rt-gate' ); ?></a>
			</p>
			<h1><?php echo esc_html( $lead->email ); ?></h1>

			<div class="rtg-lead-detail-grid">
				<div class="rtg-card">
					<h3><?php echo esc_html__( 'Form Data', 'rt-gate' ); ?></h3>
					<table class="widefat striped">
						<tbody>
							<?php if ( ! empty( $form_data ) ) : ?>
								<?php foreach ( $form_data as $key => $value ) : ?>
									<tr>
										<th style="width:30%"><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th>
										<td><?php echo esc_html( is_array( $value ) ? implode( ', ', $value ) : (string) $value ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td><?php echo esc_html__( 'No form data recorded.', 'rt-gate' ); ?></td></tr>
							<?php endif; ?>
							<tr>
								<th><?php echo esc_html__( 'Created', 'rt-gate' ); ?></th>
								<td><?php echo esc_html( $lead->created_at ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="rtg-card">
					<h3><?php echo esc_html__( 'Assets Accessed', 'rt-gate' ); ?></h3>
					<?php if ( ! empty( $accessed_assets ) ) : ?>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Asset', 'rt-gate' ); ?></th>
									<th><?php echo esc_html__( 'Type', 'rt-gate' ); ?></th>
									<th><?php echo esc_html__( 'Interactions', 'rt-gate' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $accessed_assets as $asset ) : ?>
									<tr>
										<td><?php echo esc_html( $asset->name ); ?></td>
										<td><?php echo esc_html( ucfirst( $asset->type ) ); ?></td>
										<td><?php echo esc_html( $asset->event_types ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p><?php echo esc_html__( 'No assets accessed yet.', 'rt-gate' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="rtg-card" style="margin-top: 20px;">
				<h3><?php echo esc_html__( 'Activity Timeline', 'rt-gate' ); ?></h3>
				<?php if ( ! empty( $events ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Event', 'rt-gate' ); ?></th>
								<th><?php echo esc_html__( 'Form', 'rt-gate' ); ?></th>
								<th><?php echo esc_html__( 'Asset', 'rt-gate' ); ?></th>
								<th><?php echo esc_html__( 'Details', 'rt-gate' ); ?></th>
								<th><?php echo esc_html__( 'Date', 'rt-gate' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $events as $event ) : ?>
								<?php
								$meta         = json_decode( (string) $event->meta, true );
								$meta_display = '';
								if ( is_array( $meta ) ) {
									unset( $meta['request_ip_hash'], $meta['request_ua_hash'] );
									if ( ! empty( $meta ) ) {
										$meta_display = wp_json_encode( $meta );
									}
								}
								?>
								<tr>
									<td><span class="rtg-event-badge rtg-event-<?php echo esc_attr( $event->event_type ); ?>"><?php echo esc_html( $event->event_type ); ?></span></td>
									<td><?php echo esc_html( $event->form_name ); ?></td>
									<td><?php echo esc_html( $event->asset_name ); ?></td>
									<td><?php if ( $meta_display ) : ?><code><?php echo esc_html( $meta_display ); ?></code><?php endif; ?></td>
									<td><?php echo esc_html( $event->created_at ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php echo esc_html__( 'No activity recorded yet.', 'rt-gate' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Query leads with current filters.
	 *
	 * @param int $limit  Row limit (-1 for unlimited).
	 * @param int $offset Row offset.
	 * @return array
	 */
	public static function query_leads( $limit = 20, $offset = 0 ) {
		global $wpdb;

		$leads_table  = $wpdb->prefix . 'rtg_leads';
		$events_table = $wpdb->prefix . 'rtg_events';
		$forms_table  = $wpdb->prefix . 'rtg_forms';

		$query_parts = self::build_lead_query_parts();
		$where_sql   = $query_parts['where_sql'];
		$params      = $query_parts['params'];
		$orderby     = $query_parts['orderby'];
		$order       = $query_parts['order'];

		$sql = "SELECT l.id, l.email, l.form_data, l.created_at,
			(SELECT COUNT(DISTINCT e.asset_id) FROM {$events_table} e WHERE e.lead_id = l.id AND e.asset_id > 0) AS assets_accessed,
			(SELECT MAX(e.created_at) FROM {$events_table} e WHERE e.lead_id = l.id) AS last_activity,
			(SELECT GROUP_CONCAT(DISTINCT f.name SEPARATOR ', ')
				FROM {$events_table} e
				INNER JOIN {$forms_table} f ON f.id = e.form_id
				WHERE e.lead_id = l.id AND e.event_type = 'form_submit') AS form_names
			FROM {$leads_table} l
			WHERE " . implode( ' AND ', $where_sql ) . "
			ORDER BY {$orderby} {$order}";

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
	 * Count leads with current filters.
	 *
	 * @return int
	 */
	public static function count_leads() {
		global $wpdb;

		$leads_table  = $wpdb->prefix . 'rtg_leads';

		$query_parts = self::build_lead_query_parts();
		$where_sql   = $query_parts['where_sql'];
		$params      = $query_parts['params'];

		$sql = "SELECT COUNT(*) FROM {$leads_table} l WHERE " . implode( ' AND ', $where_sql );

		if ( empty( $params ) ) {
			return (int) $wpdb->get_var( $sql );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Export filtered leads as CSV.
	 *
	 * @return void
	 */
	private static function export_leads_csv() {
		$rows = self::query_leads( -1, 0 );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=rtg-leads-' . gmdate( 'Ymd-His' ) . '.csv' );

		$fp = fopen( 'php://output', 'w' );
		if ( false === $fp ) {
			return;
		}

		fputcsv( $fp, array( 'id', 'email', 'name', 'company', 'user_type', 'form_data', 'form', 'assets_accessed', 'last_activity', 'created_at' ) );

		foreach ( $rows as $row ) {
			$form_data = json_decode( (string) $row->form_data, true );
			if ( ! is_array( $form_data ) ) {
				$form_data = array();
			}

			$name = '';
			if ( ! empty( $form_data['full_name'] ) ) {
				$name = $form_data['full_name'];
			} elseif ( ! empty( $form_data['first_name'] ) ) {
				$name = $form_data['first_name'];
				if ( ! empty( $form_data['last_name'] ) ) {
					$name .= ' ' . $form_data['last_name'];
				}
			}

			fputcsv(
				$fp,
				array(
					(int) $row->id,
					(string) $row->email,
					$name,
					isset( $form_data['company'] ) ? (string) $form_data['company'] : '',
					isset( $form_data['user_type'] ) ? (string) $form_data['user_type'] : '',
					(string) $row->form_data,
					isset( $row->form_names ) ? (string) $row->form_names : '',
					isset( $row->assets_accessed ) ? (int) $row->assets_accessed : 0,
					isset( $row->last_activity ) ? (string) $row->last_activity : '',
					(string) $row->created_at,
				)
			);
		}

		fclose( $fp );
	}

	/**
	 * Build normalized lead filters from request parameters.
	 *
	 * @return array
	 */
	private static function build_lead_filters_from_request() {
		return array(
			'email'     => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			'form_id'   => isset( $_GET['form_id'] ) ? absint( wp_unslash( $_GET['form_id'] ) ) : 0,
			'user_type' => isset( $_GET['user_type'] ) ? sanitize_text_field( wp_unslash( $_GET['user_type'] ) ) : '',
			'orderby'   => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '',
			'order'     => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '',
		);
	}

	/**
	 * Build normalized WHERE and ORDER BY SQL fragments for lead queries.
	 *
	 * @return array
	 */
	private static function build_lead_query_parts() {
		global $wpdb;

		$events_table = $wpdb->prefix . 'rtg_events';
		$filters      = self::build_lead_filters_from_request();
		$where_sql    = array( '1=1' );
		$params       = array();

		if ( ! empty( $filters['email'] ) ) {
			$where_sql[] = 'l.email LIKE %s';
			$params[]    = '%' . $wpdb->esc_like( $filters['email'] ) . '%';
		}

		if ( $filters['form_id'] > 0 ) {
			$where_sql[] = "l.id IN (SELECT DISTINCT e2.lead_id FROM {$events_table} e2 WHERE e2.form_id = %d AND e2.event_type = %s)";
			$params[]    = $filters['form_id'];
			$params[]    = 'form_submit';
		}

		if ( ! empty( $filters['user_type'] ) ) {
			$where_sql[] = 'l.form_data LIKE %s';
			$params[]    = '%' . $wpdb->esc_like( $filters['user_type'] ) . '%';
		}

		$allowed_orderby = array(
			'email'      => 'l.email',
			'created_at' => 'l.created_at',
		);

		$orderby = 'l.created_at';
		if ( ! empty( $filters['orderby'] ) && isset( $allowed_orderby[ $filters['orderby'] ] ) ) {
			$orderby = $allowed_orderby[ $filters['orderby'] ];
		}

		$order = 'DESC';
		if ( 'asc' === strtolower( $filters['order'] ) ) {
			$order = 'ASC';
		}

		return array(
			'where_sql' => $where_sql,
			'params'    => $params,
			'orderby'   => $orderby,
			'order'     => $order,
		);
	}

	/**
	 * Render admin notice from query parameter.
	 *
	 * @return void
	 */
	private static function render_notice() {
		if ( ! isset( $_GET['rtg_notice'] ) ) {
			return;
		}

		$notice = sanitize_text_field( wp_unslash( $_GET['rtg_notice'] ) );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $notice ); ?></p>
		</div>
		<?php
	}
}

RTG_Admin::init();

if ( is_admin() ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( class_exists( 'WP_List_Table' ) ) {
	/**
	 * Leads list table for the admin Leads page.
	 */
	class RTG_Leads_List_Table extends WP_List_Table {
		/**
		 * Define table columns.
		 *
		 * @return array
		 */
		public function get_columns() {
			return array(
				'email'           => esc_html__( 'Email', 'rt-gate' ),
				'name'            => esc_html__( 'Name', 'rt-gate' ),
				'company'         => esc_html__( 'Company', 'rt-gate' ),
				'user_type'       => esc_html__( 'User Type', 'rt-gate' ),
				'form_name'       => esc_html__( 'Form', 'rt-gate' ),
				'assets_accessed' => esc_html__( 'Assets', 'rt-gate' ),
				'last_activity'   => esc_html__( 'Last Activity', 'rt-gate' ),
				'created_at'      => esc_html__( 'Created', 'rt-gate' ),
			);
		}

		/**
		 * Define sortable columns.
		 *
		 * @return array
		 */
		public function get_sortable_columns() {
			return array(
				'email'      => array( 'email', false ),
				'created_at' => array( 'created_at', true ),
			);
		}

		/**
		 * Prepare table items with pagination.
		 *
		 * @return void
		 */
		public function prepare_items() {
			$per_page     = 20;
			$current_page = $this->get_pagenum();
			$offset       = ( $current_page - 1 ) * $per_page;

			$results     = RTG_Admin::query_leads( $per_page, $offset );
			$this->items = is_array( $results ) ? $results : array();
			$total_items = RTG_Admin::count_leads();

			$columns  = $this->get_columns();
			$hidden   = is_object( $this->screen ) ? get_hidden_columns( $this->screen ) : array();
			$sortable = $this->get_sortable_columns();
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
			echo esc_html__( 'No leads found.', 'rt-gate' );
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
			$forms = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}rtg_forms ORDER BY name ASC" );

			$current_form_id   = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
			$current_user_type = isset( $_GET['user_type'] ) ? sanitize_text_field( wp_unslash( $_GET['user_type'] ) ) : '';

			$user_type_options = array(
				'Corporate treasury professional (current system in place)',
				'Corporate treasury professional (planning a system change)',
				'Corporate treasury professional (early research / alignment)',
				'Treasury technology provider',
				'Treasury or finance consultant',
			);
			?>
			<div class="rtg-events-filters">
				<select name="form_id">
					<option value=""><?php echo esc_html__( 'All Forms', 'rt-gate' ); ?></option>
					<?php foreach ( $forms as $form ) : ?>
						<option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $current_form_id, (int) $form->id ); ?>><?php echo esc_html( $form->name ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="user_type">
					<option value=""><?php echo esc_html__( 'All User Types', 'rt-gate' ); ?></option>
					<?php foreach ( $user_type_options as $ut ) : ?>
						<option value="<?php echo esc_attr( $ut ); ?>" <?php selected( $current_user_type, $ut ); ?>><?php echo esc_html( $ut ); ?></option>
					<?php endforeach; ?>
				</select>

				<?php submit_button( esc_html__( 'Filter', 'rt-gate' ), '', '', false ); ?>
			</div>
			<?php
		}

		/**
		 * Render individual column values.
		 *
		 * @param object $item        Row data.
		 * @param string $column_name Column key.
		 * @return string
		 */
		public function column_default( $item, $column_name ) {
			$form_data = json_decode( (string) $item->form_data, true );
			if ( ! is_array( $form_data ) ) {
				$form_data = array();
			}

			switch ( $column_name ) {
				case 'email':
					$url = admin_url( 'admin.php?page=rtg-leads&lead_id=' . absint( $item->id ) );
					return '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $item->email ) . '</strong></a>';

				case 'name':
					$name = '';
					if ( ! empty( $form_data['full_name'] ) ) {
						$name = $form_data['full_name'];
					} elseif ( ! empty( $form_data['first_name'] ) ) {
						$name = $form_data['first_name'];
						if ( ! empty( $form_data['last_name'] ) ) {
							$name .= ' ' . $form_data['last_name'];
						}
					}
					return esc_html( $name );

				case 'company':
					$company = isset( $form_data['company'] ) ? $form_data['company'] : '';
					return esc_html( is_scalar( $company ) ? (string) $company : '' );

				case 'user_type':
					$user_type = isset( $form_data['user_type'] ) ? $form_data['user_type'] : '';
					return esc_html( is_scalar( $user_type ) ? (string) $user_type : '' );

				case 'form_name':
					return esc_html( ! empty( $item->form_names ) ? $item->form_names : '-' );

				case 'assets_accessed':
					return esc_html( absint( isset( $item->assets_accessed ) ? $item->assets_accessed : 0 ) );

				case 'last_activity':
					return esc_html( ! empty( $item->last_activity ) ? $item->last_activity : '-' );

				case 'created_at':
					return esc_html( $item->created_at );
			}

			return '';
		}
	}
}
