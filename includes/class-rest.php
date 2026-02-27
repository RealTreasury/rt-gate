<?php
/**
 * REST API controller for Real Treasury Gate.
 */
class RTG_REST {
	/**
	 * Reserved field key used for honeypot submissions.
	 *
	 * @var string
	 */
	const HONEYPOT_FIELD_KEY = '_rtg_hp';

	/**
	 * Namespace for routes.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'rtg/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'allow_public_access' ), 10, 1 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rate_limit_requests' ), 10, 3 );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'add_cors_headers' ), 10, 4 );
	}

	/**
	 * Allow unauthenticated access to public rtg/v1 routes.
	 *
	 * Security plugins (Wordfence, iThemes, etc.) and application-password
	 * authentication can hook into rest_authentication_errors and return a
	 * WP_Error for every unauthenticated request, producing a 403 before
	 * the route's own permission_callback is ever evaluated.  This filter
	 * short-circuits that check for the rtg/v1 namespace so that the four
	 * public endpoints (/form, /submit, /validate, /event) remain accessible
	 * from browser JavaScript on gated pages.
	 *
	 * @param WP_Error|null|true $errors Existing authentication result.
	 * @return WP_Error|null|true
	 */
	public static function allow_public_access( $errors ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( false !== strpos( $request_uri, '/wp-json/' . self::REST_NAMESPACE . '/' ) ||
			 false !== strpos( $request_uri, '/?rest_route=/' . self::REST_NAMESPACE . '/' ) ) {
			return true;
		}

		return $errors;
	}

	/**
	 * Register plugin REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'form_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'mapping_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'fields'  => array(
						'required' => true,
						'type'     => 'object',
					),
					'consent'  => array(
						'required' => true,
						'type'     => 'boolean',
					),
					'gate_url' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
					'asset_slug' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_validate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'asset_slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/form/(?P<form_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_form_schema' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'form_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/gate/(?P<asset_slug>[a-z0-9-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_gate_schema' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'asset_slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/event',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_event' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'asset_slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
					'event_type' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'page_view', 'download_click', 'video_play', 'video_progress' ),
					),
					'meta'       => array(
						'required' => false,
						'type'     => 'object',
						'default'  => array(),
					),
				),
			)
		);
	}

	/**
	 * Rate limit requests using transients.
	 *
	 * @param mixed           $result  Current response.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function rate_limit_requests( $result, $server, $request ) {
		$route = $request->get_route();

		if ( 0 !== strpos( $route, '/' . self::REST_NAMESPACE . '/' ) ) {
			return $result;
		}

		$ip_hash       = RTG_Utils::hash_ip( self::get_request_ip() );
		$bucket_key    = 'rtg_rl_' . md5( $ip_hash . '|' . $route );
		$request_count = (int) get_transient( $bucket_key );

		if ( $request_count >= 10 ) {
			return new WP_Error( 'rtg_rate_limited', 'Too many requests. Please retry shortly.', array( 'status' => 429 ) );
		}

		set_transient( $bucket_key, $request_count + 1, MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Add CORS headers for allowed github.io origins.
	 *
	 * @param bool            $served  Whether served.
	 * @param WP_HTTP_Response $result Result.
	 * @param WP_REST_Request $request Request.
	 * @param WP_REST_Server  $server Server.
	 * @return bool
	 */
	public static function add_cors_headers( $served, $result, $request, $server ) {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';

		if ( empty( $origin ) ) {
			return $served;
		}

		$host = wp_parse_url( $origin, PHP_URL_HOST );

		if ( empty( $host ) || ! self::is_allowed_origin( $host ) ) {
			return $served;
		}

		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Vary: Origin' );
		header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization' );

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
			status_header( 200 );
			return true;
		}

		return $served;
	}

	/**
	 * Handle POST /submit.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_submit( WP_REST_Request $request ) {
		global $wpdb;

		$mapping_id = absint( $request->get_param( 'mapping_id' ) );
		$form_id    = absint( $request->get_param( 'form_id' ) );
		$fields     = $request->get_param( 'fields' );
		$consent    = $request->get_param( 'consent' );
		$email      = '';
		$lead_id    = 0;
		$assets     = array();
		$primary    = '';
		$honeypot   = self::extract_honeypot_value( $request, $fields );
		$gate_url   = (string) $request->get_param( 'gate_url' );

		/* --- Resolve form_id (and optional single-asset scope) from mapping_id --- */
		$scoped_mapping = null;

		if ( $mapping_id > 0 ) {
			$mappings_table = $wpdb->prefix . 'rtg_mappings';
			$assets_table   = $wpdb->prefix . 'rtg_assets';
			$scoped_mapping = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT m.id, m.form_id, m.asset_id, m.iframe_src_template, a.slug
					FROM {$mappings_table} m
					INNER JOIN {$assets_table} a ON a.id = m.asset_id
					WHERE m.id = %d",
					$mapping_id
				)
			);

			if ( empty( $scoped_mapping ) ) {
				return new WP_Error( 'rtg_mapping_not_found', 'Mapping not found.', array( 'status' => 404 ) );
			}

			$form_id = (int) $scoped_mapping->form_id;
		}

		if ( $form_id <= 0 || ! is_array( $fields ) || true !== (bool) $consent ) {
			return new WP_Error( 'rtg_invalid_submit', 'mapping_id (or form_id), fields, and consent=true are required.', array( 'status' => 400 ) );
		}

		if ( '' !== $honeypot ) {
			return rest_ensure_response(
				array(
					'primary_redirect_url' => '',
					'assets'               => array(),
				)
			);
		}

		if ( empty( $fields['email'] ) ) {
			return new WP_Error( 'rtg_missing_email', 'Email is required.', array( 'status' => 400 ) );
		}

		$email = sanitize_email( $fields['email'] );
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'rtg_invalid_email', 'Email is invalid.', array( 'status' => 400 ) );
		}

		$forms_table = $wpdb->prefix . 'rtg_forms';
		$form_row    = $wpdb->get_row( $wpdb->prepare( "SELECT id, name FROM {$forms_table} WHERE id = %d", $form_id ) );
		if ( empty( $form_row ) ) {
			return new WP_Error( 'rtg_form_not_found', 'Form not found.', array( 'status' => 404 ) );
		}
		$form_name = isset( $form_row->name ) ? $form_row->name : '';

		$leads_table = $wpdb->prefix . 'rtg_leads';
		$ip_hash     = RTG_Utils::hash_ip( self::get_request_ip() );
		$ua_hash     = RTG_Utils::hash_user_agent( self::get_user_agent() );
		$submitted_at = gmdate( 'Y-m-d\TH:i:s\Z' );

		$lead_row = $wpdb->get_row( $wpdb->prepare( "SELECT id, form_data FROM {$leads_table} WHERE email = %s", $email ) );
		if ( $lead_row && ! empty( $lead_row->id ) ) {
			$lead_id = (int) $lead_row->id;
			$form_data = self::build_form_data_payload( $lead_row->form_data, $form_id, $fields, $submitted_at );
			$wpdb->update(
				$leads_table,
				array(
					'form_data' => $form_data,
					'ip_hash'   => $ip_hash,
					'ua_hash'   => $ua_hash,
				),
				array( 'id' => $lead_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$form_data = self::build_form_data_payload( null, $form_id, $fields, $submitted_at );
			$wpdb->insert(
				$leads_table,
				array(
					'email'     => $email,
					'form_data' => $form_data,
					'ip_hash'   => $ip_hash,
					'ua_hash'   => $ua_hash,
				),
				array( '%s', '%s', '%s', '%s' )
			);
			$lead_id = (int) $wpdb->insert_id;
		}

		if ( $lead_id <= 0 ) {
			return new WP_Error( 'rtg_lead_upsert_failed', 'Unable to save lead.', array( 'status' => 500 ) );
		}

		/* --- Resolve mapped assets: single (mapping_id), scoped (asset_slug), or all (form_id fallback) --- */
		$submit_asset_slug = sanitize_title( (string) $request->get_param( 'asset_slug' ) );

		if ( $scoped_mapping ) {
			$mapped_assets = array( $scoped_mapping );
		} elseif ( ! empty( $submit_asset_slug ) ) {
			$mappings_table = $wpdb->prefix . 'rtg_mappings';
			$assets_table   = $wpdb->prefix . 'rtg_assets';
			$scoped_row     = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT a.id AS asset_id, a.slug, m.iframe_src_template
					FROM {$mappings_table} m
					INNER JOIN {$assets_table} a ON a.id = m.asset_id
					WHERE m.form_id = %d AND a.slug = %s
					ORDER BY m.id ASC
					LIMIT 1",
					$form_id,
					$submit_asset_slug
				)
			);
			if ( $scoped_row ) {
				$mapped_assets = array( $scoped_row );
			} else {
				$mapped_assets = array();
			}
		} else {
			$mappings_table = $wpdb->prefix . 'rtg_mappings';
			$assets_table   = $wpdb->prefix . 'rtg_assets';
			$mapped_assets  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.id AS asset_id, a.slug, m.iframe_src_template
					FROM {$mappings_table} m
					INNER JOIN {$assets_table} a ON a.id = m.asset_id
					WHERE m.form_id = %d
					ORDER BY m.id ASC",
					$form_id
				)
			);
		}

		if ( empty( $mapped_assets ) ) {
			return rest_ensure_response(
				array(
					'primary_redirect_url' => '',
					'assets'               => array(),
				)
			);
		}

		$rtg_settings   = get_option( 'rtg_settings', array() );
		$ttl_minutes    = isset( $rtg_settings['token_ttl_minutes'] ) ? absint( $rtg_settings['token_ttl_minutes'] ) : 60;
		$ttl_seconds    = max( 60, $ttl_minutes * 60 );

		foreach ( $mapped_assets as $mapped_asset ) {
			$token_data = RTG_Token::issue_token( $lead_id, (int) $mapped_asset->asset_id, $ttl_seconds );
			if ( is_wp_error( $token_data ) ) {
				continue;
			}

			$asset_slug  = sanitize_title( $mapped_asset->slug );
			$raw_token   = $token_data['token'];

			if ( ! empty( $gate_url ) ) {
				$redirect_url = self::append_query_params( $gate_url, array(
					'asset' => $asset_slug,
					't'     => $raw_token,
				) );
			} elseif ( ! empty( $mapped_asset->iframe_src_template ) ) {
				$redirect_url = str_replace(
					array( '{asset_slug}', '{token}' ),
					array( $asset_slug, rawurlencode( $raw_token ) ),
					(string) $mapped_asset->iframe_src_template
				);
			} else {
				$redirect_url = '';
			}

			$asset_item = array(
				'slug'         => (string) $mapped_asset->slug,
				'token'        => $raw_token,
				'redirect_url' => esc_url_raw( $redirect_url ),
				'expires_at'   => $token_data['expires_at'],
			);

			if ( empty( $primary ) ) {
				$primary = $asset_item['redirect_url'];
			}

			$assets[] = $asset_item;
		}

		$primary_asset_id = isset( $mapped_assets[0]->asset_id ) ? (int) $mapped_assets[0]->asset_id : 0;
		RTG_Events::log_event( $lead_id, $form_id, $primary_asset_id, 'form_submit', array( 'consent' => true ) );
		RTG_Webhook::maybe_dispatch(
			'form_submit',
			array(
				'form_id'  => $form_id,
				'lead_id'  => $lead_id,
				'asset_id' => $primary_asset_id,
				'assets'   => $assets,
			)
		);
		RTG_Email::maybe_send_on_submit( $form_id, $lead_id, $email, $fields, $assets, $form_name );

		return rest_ensure_response(
			array(
				'primary_redirect_url' => $primary,
				'assets'               => $assets,
			)
		);
	}

	/**
	 * Handle GET /form/{form_id}.
	 *
	 * Returns the public form schema (fields and consent text) so external
	 * pages can dynamically render the form without hard-coding field definitions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_form_schema( WP_REST_Request $request ) {
		global $wpdb;

		$form_id     = absint( $request->get_param( 'form_id' ) );
		$forms_table = $wpdb->prefix . 'rtg_forms';

		$form = $wpdb->get_row( $wpdb->prepare( "SELECT id, fields_schema, consent_text FROM {$forms_table} WHERE id = %d", $form_id ) );
		if ( empty( $form ) ) {
			return new WP_Error( 'rtg_form_not_found', 'Form not found.', array( 'status' => 404 ) );
		}

		$fields = json_decode( (string) $form->fields_schema, true );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		return rest_ensure_response(
			array(
				'form_id'      => (int) $form->id,
				'fields'       => $fields,
				'consent_text' => (string) $form->consent_text,
			)
		);
	}

	/**
	 * Handle GET /gate/{asset_slug}.
	 *
	 * Resolves an asset slug to its mapped form via the rtg_mappings table
	 * and returns the form schema. This allows frontend pages to identify
	 * themselves by asset slug instead of hardcoding a form ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_gate_schema( WP_REST_Request $request ) {
		global $wpdb;

		$asset_slug = sanitize_title( (string) $request->get_param( 'asset_slug' ) );
		$asset      = self::find_asset_by_slug( $asset_slug );

		if ( empty( $asset ) ) {
			return new WP_Error( 'rtg_asset_not_found', 'Asset not found.', array( 'status' => 404 ) );
		}

		$mapping = self::find_mapping_for_asset( (int) $asset->id );
		if ( empty( $mapping ) ) {
			return new WP_Error( 'rtg_no_mapping', 'No form mapped to this asset.', array( 'status' => 404 ) );
		}

		$form_id = (int) $mapping->form_id;

		$forms_table = $wpdb->prefix . 'rtg_forms';
		$form        = $wpdb->get_row( $wpdb->prepare( "SELECT id, fields_schema, consent_text FROM {$forms_table} WHERE id = %d", $form_id ) );

		if ( empty( $form ) ) {
			return new WP_Error( 'rtg_form_not_found', 'Mapped form not found.', array( 'status' => 404 ) );
		}

		$fields = json_decode( (string) $form->fields_schema, true );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		return rest_ensure_response(
			array(
				'form_id'      => (int) $form->id,
				'mapping_id'   => (int) $mapping->id,
				'fields'       => $fields,
				'consent_text' => (string) $form->consent_text,
				'asset_slug'   => $asset_slug,
			)
		);
	}

	/**
	 * Handle POST /validate.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_validate( WP_REST_Request $request ) {
		$token      = (string) $request->get_param( 'token' );
		$asset_slug = sanitize_title( (string) $request->get_param( 'asset_slug' ) );
		$asset      = self::find_asset_by_slug( $asset_slug );

		if ( empty( $token ) || empty( $asset_slug ) || empty( $asset ) ) {
			return rest_ensure_response( array( 'valid' => false ) );
		}

		$token_row = self::find_token_for_asset( $token, (int) $asset->id );
		if ( empty( $token_row ) || ! RTG_Token::is_not_expired( $token_row->expires_at ) ) {
			return rest_ensure_response( array( 'valid' => false ) );
		}

		return rest_ensure_response(
			array(
				'valid'      => true,
				'asset'      => array(
					'type'   => (string) $asset->type,
					'config' => json_decode( (string) $asset->config, true ),
				),
				'expires_at' => (string) $token_row->expires_at,
			)
		);
	}

	/**
	 * Handle POST /event.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_event( WP_REST_Request $request ) {
		$token      = (string) $request->get_param( 'token' );
		$asset_slug = sanitize_title( (string) $request->get_param( 'asset_slug' ) );
		$event_type = sanitize_key( (string) $request->get_param( 'event_type' ) );
		$meta       = $request->get_param( 'meta' );

		$allowed_events = array( 'page_view', 'download_click', 'video_play', 'video_progress' );
		if ( empty( $token ) || empty( $asset_slug ) || ! in_array( $event_type, $allowed_events, true ) ) {
			return new WP_Error( 'rtg_invalid_event', 'Invalid event payload.', array( 'status' => 400 ) );
		}

		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		if ( 'video_progress' === $event_type ) {
			$progress = isset( $meta['progress'] ) ? (int) $meta['progress'] : 0;
			if ( ! in_array( $progress, array( 25, 50, 75, 90, 100 ), true ) ) {
				return new WP_Error( 'rtg_invalid_progress', 'video_progress requires progress=25/50/75/90/100.', array( 'status' => 400 ) );
			}
		}

		$asset = self::find_asset_by_slug( $asset_slug );
		if ( empty( $asset ) ) {
			return new WP_Error( 'rtg_asset_not_found', 'Asset not found.', array( 'status' => 404 ) );
		}

		$token_row = self::find_token_for_asset( $token, (int) $asset->id );
		if ( empty( $token_row ) || ! RTG_Token::is_not_expired( $token_row->expires_at ) ) {
			return new WP_Error( 'rtg_invalid_token', 'Token is invalid or expired.', array( 'status' => 401 ) );
		}

		$form_id = self::find_form_id_for_asset( (int) $asset->id );

		$saved = RTG_Events::log_event( (int) $token_row->lead_id, $form_id, (int) $asset->id, $event_type, $meta );
		if ( ! $saved ) {
			return new WP_Error( 'rtg_event_save_failed', 'Unable to record event.', array( 'status' => 500 ) );
		}

		RTG_Webhook::maybe_dispatch(
			'asset_event',
			array(
				'lead_id'    => (int) $token_row->lead_id,
				'form_id'    => $form_id,
				'asset_id'   => (int) $asset->id,
				'asset_slug' => $asset_slug,
				'event_type' => $event_type,
				'meta'       => $meta,
			)
		);

		return rest_ensure_response( array( 'recorded' => true ) );
	}


	/**
	 * Get asset by slug.
	 *
	 * @param string $asset_slug Asset slug.
	 * @return object|null
	 */
	private static function find_asset_by_slug( $asset_slug ) {
		global $wpdb;

		$assets_table = $wpdb->prefix . 'rtg_assets';
		return $wpdb->get_row( $wpdb->prepare( "SELECT id, type, config, slug FROM {$assets_table} WHERE slug = %s", $asset_slug ) );
	}

	/**
	 * Find token for given raw token + asset.
	 *
	 * @param string $token Raw token.
	 * @param int    $asset_id Asset ID.
	 * @return object|null
	 */
	private static function find_token_for_asset( $token, $asset_id ) {
		global $wpdb;

		$tokens_table = $wpdb->prefix . 'rtg_tokens';
		$token_hash   = RTG_Token::hash_token( $token );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, lead_id, asset_id, expires_at
				FROM {$tokens_table}
				WHERE token_hash = %s AND asset_id = %d
				ORDER BY id DESC
				LIMIT 1",
				$token_hash,
				absint( $asset_id )
			)
		);
	}

	/**
	 * Find the first mapping row for a given asset.
	 *
	 * @param int $asset_id Asset ID.
	 * @return object|null Row with id, form_id, asset_id, iframe_src_template.
	 */
	private static function find_mapping_for_asset( $asset_id ) {
		global $wpdb;

		$mappings_table = $wpdb->prefix . 'rtg_mappings';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, form_id, asset_id, iframe_src_template FROM {$mappings_table} WHERE asset_id = %d ORDER BY id ASC LIMIT 1",
				$asset_id
			)
		);
	}

	/**
	 * Find a likely form ID for an asset.
	 *
	 * @param int $asset_id Asset ID.
	 * @return int
	 */
	private static function find_form_id_for_asset( $asset_id ) {
		$mapping = self::find_mapping_for_asset( $asset_id );

		return $mapping ? max( 0, (int) $mapping->form_id ) : 0;
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
	 * Check if a host is in the allowed origins list.
	 *
	 * Checks the configurable allowed origins from settings, falling back
	 * to github.io if none configured. The site's own host is always allowed.
	 *
	 * @param string $host Host name.
	 * @return bool
	 */
	private static function is_allowed_origin( $host ) {
		$host = strtolower( (string) $host );

		// Always allow the site's own host.
		$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( ! empty( $site_host ) && $host === $site_host ) {
			return true;
		}

		$rtg_settings    = get_option( 'rtg_settings', array() );
		$origins_raw     = isset( $rtg_settings['allowed_origins'] ) ? (string) $rtg_settings['allowed_origins'] : '';
		$origins         = array_filter( array_map( 'trim', explode( "\n", strtolower( $origins_raw ) ) ) );

		if ( empty( $origins ) ) {
			$origins = array( 'github.io' );
		}

		foreach ( $origins as $allowed ) {
			if ( $host === $allowed ) {
				return true;
			}
			$suffix = '.' . $allowed;
			if ( strlen( $host ) > strlen( $suffix ) && substr_compare( $host, $suffix, -strlen( $suffix ) ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract honeypot value from request payload.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param mixed           $fields  Submitted fields payload.
	 * @return string
	 */
	private static function extract_honeypot_value( WP_REST_Request $request, $fields ) {
		$honeypot = $request->get_param( 'honeypot' );

		if ( is_array( $fields ) && isset( $fields[ self::HONEYPOT_FIELD_KEY ] ) ) {
			$honeypot = $fields[ self::HONEYPOT_FIELD_KEY ];
		}

		if ( ! is_scalar( $honeypot ) ) {
			return '';
		}

		return trim( (string) $honeypot );
	}

	/**
	 * Build lead form_data payload with backward-compatible latest and history.
	 *
	 * @param string|null $existing_form_data Existing form_data JSON string.
	 * @param int         $form_id            Submitted form ID.
	 * @param array       $fields             Submitted form fields.
	 * @param string      $submitted_at       UTC timestamp in ISO-8601 format.
	 * @return string
	 */
	private static function build_form_data_payload( $existing_form_data, $form_id, array $fields, $submitted_at ) {
		$payload = self::decode_form_data_payload( $existing_form_data );

		if ( ! isset( $payload['history'] ) || ! is_array( $payload['history'] ) ) {
			$payload['history'] = array();
		}

		$form_history_key = (string) absint( $form_id );
		if ( ! isset( $payload['history'][ $form_history_key ] ) || ! is_array( $payload['history'][ $form_history_key ] ) ) {
			$payload['history'][ $form_history_key ] = array();
		}

		$timestamp_key = (string) $submitted_at;
		$collision     = 1;
		while ( isset( $payload['history'][ $form_history_key ][ $timestamp_key ] ) ) {
			$timestamp_key = $submitted_at . '_' . $collision;
			++$collision;
		}

		$payload['history'][ $form_history_key ][ $timestamp_key ] = $fields;
		$payload['latest']                                   = $fields;

		foreach ( $fields as $field_key => $field_value ) {
			if ( is_string( $field_key ) ) {
				$payload[ $field_key ] = $field_value;
			}
		}

		return wp_json_encode( $payload );
	}

	/**
	 * Decode lead form_data payload into a normalized array.
	 *
	 * @param string|null $form_data_json Raw form_data JSON.
	 * @return array
	 */
	private static function decode_form_data_payload( $form_data_json ) {
		$payload = json_decode( (string) $form_data_json, true );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		if ( isset( $payload['latest'] ) && is_array( $payload['latest'] ) ) {
			return $payload;
		}

		$legacy_fields = array();
		foreach ( $payload as $key => $value ) {
			if ( 'history' !== $key && is_string( $key ) ) {
				$legacy_fields[ $key ] = $value;
			}
		}

		if ( empty( $legacy_fields ) ) {
			return array(
				'latest'  => array(),
				'history' => array(),
			);
		}

		return array_merge(
			$legacy_fields,
			array(
				'latest'  => $legacy_fields,
				'history' => isset( $payload['history'] ) && is_array( $payload['history'] ) ? $payload['history'] : array(),
			)
		);
	}

	/**
	 * Append query parameters to a URL, respecting existing query strings.
	 *
	 * @param string $url    Base URL.
	 * @param array  $params Key-value pairs to append.
	 * @return string
	 */
	private static function append_query_params( $url, array $params ) {
		foreach ( $params as $key => $value ) {
			$url = add_query_arg( rawurlencode( $key ), rawurlencode( $value ), $url );
		}
		return $url;
	}
}

RTG_REST::init();
