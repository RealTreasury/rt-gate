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

		$privacy_policy_url = self::get_privacy_policy_url();
		$consent_text       = sprintf(
			/* translators: %s: Privacy policy URL. */
			__( 'I agree to receive updates and accept the privacy policy: %s', 'rt-gate' ),
			$privacy_policy_url
		);

		$data = array(
			'name'          => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'fields_schema' => isset( $_POST['fields_schema'] ) ? sanitize_text_field( wp_unslash( $_POST['fields_schema'] ) ) : '',
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
			<style>
				.rtg-form-builder-grid {
					display: grid;
					gap: 20px;
					grid-template-columns: minmax(520px, 1.35fr) minmax(780px, 2fr);
					margin: 16px 0 8px;
					align-items: start;
				}

				.rtg-card {
					background: #fff;
					border: 1px solid #dcdcde;
					border-radius: 8px;
					padding: 20px;
				}

				.rtg-card h2,
				.rtg-card h3 {
					margin-top: 0;
				}

				.rtg-form-builder-table td {
					vertical-align: top;
					padding: 10px 8px;
				}

				.rtg-form-builder-table th {
					padding: 10px 8px;
					white-space: nowrap;
				}

				.rtg-form-builder-table input,
				.rtg-form-builder-table select {
					width: 100%;
					min-width: 120px;
				}

				.rtg-form-builder-table .button {
					white-space: nowrap;
				}

				.rtg-form-builder-table {
					table-layout: auto;
				}

				.rtg-form-builder-scroller {
					overflow-x: auto;
					padding-bottom: 4px;
				}

				.rtg-form-builder-actions {
					display: flex;
					flex-wrap: wrap;
					gap: 8px;
					margin-top: 10px;
				}

				.rtg-form-builder-presets {
					display: flex;
					flex-wrap: wrap;
					gap: 8px;
					margin-bottom: 10px;
				}

				.rtg-form-builder-presets select {
					min-width: 220px;
				}

				.rtg-builder-status {
					margin-top: 10px;
					font-style: italic;
				}

				.rtg-f-extra-help {
					display: block;
					margin-top: 4px;
					color: #646970;
					font-size: 12px;
				}

				.rtg-helper-list {
					list-style: decimal;
					padding-left: 18px;
				}

				@media (max-width: 1400px) {
					.rtg-form-builder-grid {
						grid-template-columns: 1fr;
					}
				}

				@media (max-width: 782px) {
					.rtg-card {
						padding: 16px;
					}

					.rtg-form-builder-table th,
					.rtg-form-builder-table td {
						padding: 8px 6px;
					}
				}
			</style>

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
						'Corporate treasury practitioner',
						'Consultant',
						'Tech vendor',
						'Finance',
						'Other'
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

						event.preventDefault();

						if (event.target.classList.contains('rtg-remove-row')) {
							row.remove();
							setStatus('Field removed.');
							return;
						}

						if (event.target.classList.contains('rtg-move-up') && row.previousElementSibling) {
							rowsContainer.insertBefore(row, row.previousElementSibling);
							setStatus('Field moved up.');
							return;
						}

						if (event.target.classList.contains('rtg-move-down') && row.nextElementSibling) {
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
