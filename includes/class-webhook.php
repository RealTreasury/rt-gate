<?php
/**
 * Webhook dispatcher.
 */
class RTG_Webhook {
	/**
	 * Dispatch webhook for an event if enabled.
	 *
	 * @param string $event_name Event key (form_submit|asset_event).
	 * @param array  $payload Payload body.
	 * @return void
	 */
	public static function maybe_dispatch( $event_name, $payload = array() ) {
		$settings = self::get_settings();
		$url      = isset( $settings['webhook_url'] ) ? esc_url_raw( (string) $settings['webhook_url'] ) : '';

		if ( empty( $url ) || ! self::is_event_enabled( $event_name, $settings ) ) {
			return;
		}

		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$body = array(
			'event'      => sanitize_key( $event_name ),
			'occurredAt' => gmdate( 'c' ),
			'payload'    => $payload,
		);

		$secret      = isset( $settings['webhook_secret'] ) ? (string) $settings['webhook_secret'] : '';
		$body_json   = wp_json_encode( $body );
		$signature   = ! empty( $secret ) ? hash_hmac( 'sha256', (string) $body_json, $secret ) : '';
		$headers     = array( 'Content-Type' => 'application/json' );

		if ( ! empty( $signature ) ) {
			$headers['X-RTG-Signature'] = $signature;
		}

		wp_remote_post(
			$url,
			array(
				'headers'  => $headers,
				'body'     => $body_json,
				'blocking' => false,
				'timeout'  => 3,
			)
		);
	}

	/**
	 * Read plugin settings.
	 *
	 * @return array
	 */
	private static function get_settings() {
		$settings = get_option( 'rtg_settings', array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Determine if webhook event is enabled.
	 *
	 * @param string $event_name Event name.
	 * @param array  $settings Settings.
	 * @return bool
	 */
	private static function is_event_enabled( $event_name, $settings ) {
		if ( isset( $settings['webhook_events'] ) && is_array( $settings['webhook_events'] ) ) {
			return in_array( $event_name, $settings['webhook_events'], true );
		}

		$legacy_toggle_key = 'webhook_' . $event_name;
		if ( isset( $settings[ $legacy_toggle_key ] ) ) {
			return (bool) $settings[ $legacy_toggle_key ];
		}

		return true;
	}
}
