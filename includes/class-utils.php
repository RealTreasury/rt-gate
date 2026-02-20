<?php
/**
 * Utility helpers for Real Treasury Gate.
 */
class RTG_Utils {
	/**
	 * Return a standardized success JSON payload.
	 *
	 * @param array $data Optional payload data.
	 * @param int   $status_code HTTP status code.
	 * @return void
	 */
	public static function json_success( $data = array(), $status_code = 200 ) {
		wp_send_json(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status_code
		);
	}

	/**
	 * Return a standardized error JSON payload.
	 *
	 * @param string $message Error message.
	 * @param int    $status_code HTTP status code.
	 * @param array  $data Optional payload data.
	 * @return void
	 */
	public static function json_error( $message, $status_code = 400, $data = array() ) {
		wp_send_json(
			array(
				'success' => false,
				'error'   => array(
					'message' => sanitize_text_field( $message ),
					'data'    => $data,
				),
			),
			$status_code
		);
	}

	/**
	 * Hash any input value with SHA-256.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function hash_value( $value ) {
		return hash( 'sha256', (string) $value );
	}

	/**
	 * Hash an IP address.
	 *
	 * @param string $ip_address Raw IP.
	 * @return string
	 */
	public static function hash_ip( $ip_address ) {
		$normalized_ip = sanitize_text_field( (string) $ip_address );
		return self::hash_value( $normalized_ip );
	}

	/**
	 * Hash a user agent string.
	 *
	 * @param string $user_agent Raw user agent.
	 * @return string
	 */
	public static function hash_user_agent( $user_agent ) {
		$normalized_user_agent = sanitize_text_field( (string) $user_agent );
		return self::hash_value( $normalized_user_agent );
	}

	/**
	 * Get the current request IP address.
	 *
	 * @return string
	 */
	public static function get_request_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return '';
	}

	/**
	 * Get the current request user agent.
	 *
	 * @return string
	 */
	public static function get_user_agent() {
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}

		return '';
	}
}
