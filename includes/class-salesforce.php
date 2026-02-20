<?php
/**
 * Salesforce OAuth scaffold.
 */
class RTG_Salesforce {
	/**
	 * OAuth client ID.
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * OAuth client secret.
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * OAuth redirect URI.
	 *
	 * @var string
	 */
	private $redirect_uri;

	/**
	 * Salesforce login base URL.
	 *
	 * @var string
	 */
	private $login_url;

	/**
	 * Initialize scaffold properties.
	 *
	 * @param array $config Optional configuration.
	 */
	public function __construct( $config = array() ) {
		$this->client_id     = isset( $config['client_id'] ) ? (string) $config['client_id'] : '';
		$this->client_secret = isset( $config['client_secret'] ) ? (string) $config['client_secret'] : '';
		$this->redirect_uri  = isset( $config['redirect_uri'] ) ? esc_url_raw( (string) $config['redirect_uri'] ) : '';
		$this->login_url     = isset( $config['login_url'] ) ? esc_url_raw( (string) $config['login_url'] ) : 'https://login.salesforce.com';
	}

	/**
	 * Build the Salesforce OAuth authorization URL.
	 *
	 * @param string $state CSRF state token.
	 * @return string
	 */
	public function get_authorization_url( $state ) {
		// TODO: Build and return OAuth authorization URL.
		return '';
	}

	/**
	 * Exchange OAuth authorization code for tokens.
	 *
	 * @param string $code Authorization code.
	 * @return array|WP_Error
	 */
	public function exchange_code_for_tokens( $code ) {
		// TODO: Call Salesforce token endpoint and persist token payload.
		return new WP_Error( 'rtg_salesforce_not_implemented', 'Not implemented yet.' );
	}

	/**
	 * Refresh OAuth access token.
	 *
	 * @return array|WP_Error
	 */
	public function refresh_access_token() {
		// TODO: Refresh OAuth token using saved refresh token.
		return new WP_Error( 'rtg_salesforce_not_implemented', 'Not implemented yet.' );
	}

	/**
	 * Push lead/event payload to Salesforce.
	 *
	 * @param array $payload Data payload.
	 * @return array|WP_Error
	 */
	public function push_payload( $payload ) {
		// TODO: Send payload to Salesforce endpoint with OAuth access token.
		return new WP_Error( 'rtg_salesforce_not_implemented', 'Not implemented yet.' );
	}
}
