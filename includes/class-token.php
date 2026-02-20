<?php
/**
 * Token handling for Real Treasury Gate.
 */
class RTG_Token {
	/**
	 * Create and persist a token for a lead + asset pair.
	 *
	 * @param int $lead_id Lead ID.
	 * @param int $asset_id Asset ID.
	 * @param int $ttl_seconds Token TTL in seconds.
	 * @return array|WP_Error
	 */
	public static function issue_token( $lead_id, $asset_id, $ttl_seconds = 3600 ) {
		global $wpdb;

		$lead_id     = absint( $lead_id );
		$asset_id    = absint( $asset_id );
		$ttl_seconds = absint( $ttl_seconds );

		if ( $lead_id <= 0 || $asset_id <= 0 || $ttl_seconds <= 0 ) {
			return new WP_Error( 'rtg_invalid_token_request', 'Invalid token request.' );
		}

		try {
			$raw_token = bin2hex( random_bytes( 32 ) );
		} catch ( Exception $exception ) {
			return new WP_Error( 'rtg_token_generation_failed', 'Unable to generate secure token.' );
		}

		$token_hash = self::hash_token( $raw_token );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + $ttl_seconds );
		$table      = $wpdb->prefix . 'rtg_tokens';

		$inserted = $wpdb->insert(
			$table,
			array(
				'lead_id'    => $lead_id,
				'asset_id'   => $asset_id,
				'token_hash' => $token_hash,
				'expires_at' => $expires_at,
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'rtg_token_persist_failed', 'Unable to store token.' );
		}

		return array(
			'token'      => $raw_token,
			'token_hash' => $token_hash,
			'expires_at' => $expires_at,
		);
	}

	/**
	 * Hash a token for storage and lookup.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function hash_token( $token ) {
		return hash( 'sha256', (string) $token );
	}

	/**
	 * Validate whether an expiry datetime is still valid.
	 *
	 * @param string $expires_at UTC datetime in Y-m-d H:i:s.
	 * @return bool
	 */
	public static function is_not_expired( $expires_at ) {
		$expires_ts = strtotime( (string) $expires_at );

		if ( false === $expires_ts ) {
			return false;
		}

		return $expires_ts > time();
	}
}
