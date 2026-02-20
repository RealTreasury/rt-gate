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
		if ( 'real-treasury-gate_page_rtg-assets' !== $hook_suffix ) {
			return;
		}

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
			'rtg-forms',
			array( __CLASS__, 'render_forms_page' ),
			'dashicons-lock',
			56
		);

		add_submenu_page(
			'rtg-forms',
			esc_html__( 'Forms', 'rt-gate' ),
			esc_html__( 'Forms', 'rt-gate' ),
			'manage_options',
			'rtg-forms',
			array( __CLASS__, 'render_forms_page' )
		);

		add_submenu_page(
			'rtg-forms',
			esc_html__( 'Assets', 'rt-gate' ),
			esc_html__( 'Assets', 'rt-gate' ),
			'manage_options',
			'rtg-assets',
			array( __CLASS__, 'render_assets_page' )
		);

		add_submenu_page(
			'rtg-forms',
			esc_html__( 'Mappings', 'rt-gate' ),
			esc_html__( 'Mappings', 'rt-gate' ),
			'manage_options',
			'rtg-mappings',
			array( __CLASS__, 'render_mappings_page' )
		);

		if ( class_exists( 'RTG_Events' ) ) {
			RTG_Events::register_submenu();
		}
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

		$data = array(
			'name'          => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'fields_schema' => isset( $_POST['fields_schema'] ) ? sanitize_text_field( wp_unslash( $_POST['fields_schema'] ) ) : '',
			'consent_text'  => isset( $_POST['consent_text'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_text'] ) ) : '',
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
			<form method="post">
				<input type="hidden" name="rtg_action" value="save_form" />
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
				<input type="hidden" name="id" value="<?php echo esc_attr( $record ? $record->id : 0 ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rtg_form_name"><?php echo esc_html__( 'Name', 'rt-gate' ); ?></label></th>
						<td><input id="rtg_form_name" name="name" type="text" class="regular-text" value="<?php echo esc_attr( $record ? $record->name : '' ); ?>" required /></td>
					</tr>
					<tr>
						<th scope="row"><label for="rtg_fields_schema"><?php echo esc_html__( 'Fields Schema (JSON)', 'rt-gate' ); ?></label></th>
						<td><textarea id="rtg_fields_schema" name="fields_schema" rows="8" class="large-text"><?php echo esc_html( $record ? $record->fields_schema : '' ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="rtg_consent_text"><?php echo esc_html__( 'Consent Text', 'rt-gate' ); ?></label></th>
						<td><textarea id="rtg_consent_text" name="consent_text" rows="5" class="large-text"><?php echo esc_html( $record ? $record->consent_text : '' ); ?></textarea></td>
					</tr>
				</table>
				<?php submit_button( $record ? esc_html__( 'Update Form', 'rt-gate' ) : esc_html__( 'Create Form', 'rt-gate' ) ); ?>
			</form>

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
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-forms&edit_id=' . absint( $form->id ) ) ); ?>"><?php echo esc_html__( 'Edit', 'rt-gate' ); ?></a></td>
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
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-assets&edit_id=' . absint( $asset->id ) ) ); ?>"><?php echo esc_html__( 'Edit', 'rt-gate' ); ?></a></td>
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
						<th scope="row"><label for="rtg_iframe_src_template"><?php echo esc_html__( 'Iframe Source Template', 'rt-gate' ); ?></label></th>
						<td><input id="rtg_iframe_src_template" name="iframe_src_template" type="text" class="large-text" value="<?php echo esc_attr( $record ? $record->iframe_src_template : '' ); ?>" required /></td>
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
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=rtg-mappings&edit_id=' . absint( $mapping->id ) ) ); ?>"><?php echo esc_html__( 'Edit', 'rt-gate' ); ?></a></td>
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
