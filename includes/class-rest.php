<?php
/**
 * REST API controller for Real Treasury Gate.
 */
class RTG_REST {
	/**
	 * Namespace for routes.
	 *
	 * @var string
	 */
	const NAMESPACE = 'rtg/v1';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rate_limit_requests' ), 10, 3 );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'add_cors_headers' ), 10, 4 );
	}

	/**
	 * Register plugin REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_submit' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_validate' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/event',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_event' ),
				'permission_callback' => '__return_true',
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

		if ( 0 !== strpos( $route, '/' . self::NAMESPACE . '/' ) ) {
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

		if ( empty( $host ) || ! self::is_allowed_github_io_host( $host ) ) {
			return $served;
		}

		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Vary: Origin' );
		header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
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

		$form_id  = absint( $request->get_param( 'form_id' ) );
		$fields   = $request->get_param( 'fields' );
		$consent  = $request->get_param( 'consent' );
		$email    = '';
		$lead_id  = 0;
		$assets   = array();
		$primary  = '';

		if ( $form_id <= 0 || ! is_array( $fields ) || true !== (bool) $consent ) {
			return new WP_Error( 'rtg_invalid_submit', 'form_id, fields, and consent=true are required.', array( 'status' => 400 ) );
		}

		if ( empty( $fields['email'] ) ) {
			return new WP_Error( 'rtg_missing_email', 'Email is required.', array( 'status' => 400 ) );
		}

		$email = sanitize_email( $fields['email'] );
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'rtg_invalid_email', 'Email is invalid.', array( 'status' => 400 ) );
		}

		$forms_table = $wpdb->prefix . 'rtg_forms';
		$form_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$forms_table} WHERE id = %d", $form_id ) );
		if ( empty( $form_exists ) ) {
			return new WP_Error( 'rtg_form_not_found', 'Form not found.', array( 'status' => 404 ) );
		}

		$leads_table = $wpdb->prefix . 'rtg_leads';
		$ip_hash     = RTG_Utils::hash_ip( self::get_request_ip() );
		$ua_hash     = RTG_Utils::hash_user_agent( self::get_user_agent() );
		$form_data   = wp_json_encode( $fields );

		$lead_row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$leads_table} WHERE email = %s", $email ) );
		if ( $lead_row && ! empty( $lead_row->id ) ) {
			$lead_id = (int) $lead_row->id;
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

		if ( empty( $mapped_assets ) ) {
			return rest_ensure_response(
				array(
					'primary_redirect_url' => '',
					'assets'               => array(),
				)
			);
		}

		foreach ( $mapped_assets as $mapped_asset ) {
			$token_data = RTG_Token::issue_token( $lead_id, (int) $mapped_asset->asset_id );
			if ( is_wp_error( $token_data ) ) {
				continue;
			}

			$redirect_url = str_replace(
				array( '{asset_slug}', '{token}' ),
				array( sanitize_title( $mapped_asset->slug ), rawurlencode( $token_data['token'] ) ),
				(string) $mapped_asset->iframe_src_template
			);

			$asset_item = array(
				'slug'         => (string) $mapped_asset->slug,
				'redirect_url' => esc_url_raw( $redirect_url ),
				'expires_at'   => $token_data['expires_at'],
			);

			if ( empty( $primary ) ) {
				$primary = $asset_item['redirect_url'];
			}

			$assets[] = $asset_item;
		}

		self::insert_event( $lead_id, $form_id, isset( $mapped_assets[0]->asset_id ) ? (int) $mapped_assets[0]->asset_id : 0, 'form_submit', array( 'consent' => true ) );

		return rest_ensure_response(
			array(
				'primary_redirect_url' => $primary,
				'assets'               => $assets,
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

		$saved = self::insert_event( (int) $token_row->lead_id, $form_id, (int) $asset->id, $event_type, $meta );
		if ( ! $saved ) {
			return new WP_Error( 'rtg_event_save_failed', 'Unable to record event.', array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'recorded' => true ) );
	}

	/**
	 * Insert an event record.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param int    $form_id Form ID.
	 * @param int    $asset_id Asset ID.
	 * @param string $event_type Event type.
	 * @param array  $meta Event metadata.
	 * @return bool
	 */
	private static function insert_event( $lead_id, $form_id, $asset_id, $event_type, $meta ) {
		global $wpdb;

		$events_table = $wpdb->prefix . 'rtg_events';
		$inserted     = $wpdb->insert(
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
	 * Find a likely form ID for an asset.
	 *
	 * @param int $asset_id Asset ID.
	 * @return int
	 */
	private static function find_form_id_for_asset( $asset_id ) {
		global $wpdb;

		$mappings_table = $wpdb->prefix . 'rtg_mappings';
		$form_id        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT form_id FROM {$mappings_table} WHERE asset_id = %d ORDER BY id ASC LIMIT 1", $asset_id ) );

		return max( 0, $form_id );
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

	/**
	 * Check if host is github.io or subdomain.
	 *
	 * @param string $host Host name.
	 * @return bool
	 */
	private static function is_allowed_github_io_host( $host ) {
		$host = strtolower( (string) $host );

		if ( 'github.io' === $host ) {
			return true;
		}

		return strlen( $host ) > 10 && 0 === substr_compare( $host, '.github.io', -10 );
	}
}

RTG_REST::init();
