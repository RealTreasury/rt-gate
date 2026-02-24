<?php
/**
 * Email notification dispatcher for form submissions.
 */
class RTG_Email {

	/**
	 * Orchestrate email notifications after a form submission.
	 *
	 * @param int    $form_id   Form ID.
	 * @param int    $lead_id   Lead ID.
	 * @param string $email     Lead email address.
	 * @param array  $fields    Submitted form fields.
	 * @param array  $assets    Array of asset items (slug, token, redirect_url, expires_at).
	 * @param string $form_name Form name.
	 * @return void
	 */
	public static function maybe_send_on_submit( $form_id, $lead_id, $email, $fields, $assets, $form_name ) {
		$settings = self::get_form_email_settings( $form_id );

		$lead_mode = isset( $settings['lead_email_mode'] ) ? $settings['lead_email_mode'] : 'none';
		if ( 'none' !== $lead_mode && ! empty( $email ) && is_email( $email ) ) {
			self::send_lead_email( $email, $fields, $assets, $form_name, $lead_mode );
		}

		$internal_notify = ! empty( $settings['internal_notify'] );
		if ( $internal_notify ) {
			$raw_recipients = isset( $settings['internal_recipients'] ) ? (string) $settings['internal_recipients'] : '';
			$recipients     = self::parse_recipient_list( $raw_recipients );

			if ( empty( $recipients ) ) {
				$admin_email = get_option( 'admin_email', '' );
				if ( ! empty( $admin_email ) && is_email( $admin_email ) ) {
					$recipients = array( $admin_email );
				}
			}

			if ( ! empty( $recipients ) ) {
				self::send_internal_notification( $recipients, $fields, $assets, $form_name, $form_id );
			}
		}
	}

	/**
	 * Send a confirmation email to the lead.
	 *
	 * @param string $email     Lead email.
	 * @param array  $fields    Submitted fields.
	 * @param array  $assets    Asset items.
	 * @param string $form_name Form name.
	 * @param string $mode      Either 'confirmation_only' or 'confirmation_and_links'.
	 * @return bool Whether wp_mail succeeded.
	 */
	public static function send_lead_email( $email, $fields, $assets, $form_name, $mode ) {
		$site_name = self::get_site_name();
		$subject   = sprintf(
			/* translators: %s: Site name. */
			__( 'Thank you for your submission — %s', 'rt-gate' ),
			$site_name
		);

		$body = self::build_lead_email_html( $fields, $assets, $form_name, $mode );

		return self::send_html_email( $email, $subject, $body );
	}

	/**
	 * Send an internal notification email about a new form submission.
	 *
	 * @param array  $recipients Array of email addresses.
	 * @param array  $fields     Submitted fields.
	 * @param array  $assets     Asset items.
	 * @param string $form_name  Form name.
	 * @param int    $form_id    Form ID.
	 * @return bool Whether wp_mail succeeded.
	 */
	public static function send_internal_notification( $recipients, $fields, $assets, $form_name, $form_id ) {
		$site_name = self::get_site_name();
		$lead_identity = self::build_lead_identity( $fields );
		$subject    = sprintf(
			/* translators: 1: Form name 2: Lead identity. */
			__( '[%1$s] New lead submission from %2$s', 'rt-gate' ),
			$site_name,
			$lead_identity
		);

		$body = self::build_internal_email_html( $fields, $assets, $form_name, $form_id );

		return self::send_html_email( implode( ',', $recipients ), $subject, $body );
	}

	/**
	 * Read per-form email settings from the database.
	 *
	 * @param int $form_id Form ID.
	 * @return array Parsed settings with defaults.
	 */
	private static function get_form_email_settings( $form_id ) {
		global $wpdb;

		$defaults = array(
			'lead_email_mode'     => 'none',
			'internal_notify'     => false,
			'internal_recipients' => '',
		);

		$table = $wpdb->prefix . 'rtg_forms';
		$raw   = $wpdb->get_var( $wpdb->prepare(
			"SELECT email_settings FROM {$table} WHERE id = %d",
			$form_id
		) );

		if ( empty( $raw ) ) {
			return $defaults;
		}

		$parsed = json_decode( $raw, true );
		if ( ! is_array( $parsed ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $parsed );
	}

	/**
	 * Build HTML body for the lead confirmation email.
	 *
	 * @param array  $fields    Submitted fields.
	 * @param array  $assets    Asset items.
	 * @param string $form_name Form name.
	 * @param string $mode      Email mode.
	 * @return string HTML body.
	 */
	private static function build_lead_email_html( $fields, $assets, $form_name, $mode ) {
		$site_name  = self::get_site_name();
		$first_name = '';

		if ( ! empty( $fields['first_name'] ) ) {
			$first_name = sanitize_text_field( $fields['first_name'] );
		} elseif ( ! empty( $fields['full_name'] ) ) {
			$first_name = sanitize_text_field( $fields['full_name'] );
		}

		$greeting = ! empty( $first_name )
			? sprintf( __( 'Hi %s,', 'rt-gate' ), esc_html( $first_name ) )
			: __( 'Hi,', 'rt-gate' );

		$html  = self::email_header( $site_name );
		$html .= '<p style="font-size:16px;color:#333333;">' . esc_html( $greeting ) . '</p>';
		$html .= '<p style="font-size:14px;color:#555555;">';
		$html .= sprintf(
			/* translators: 1: Form name 2: Site name. */
			esc_html__( 'Thank you for your submission to %1$s on %2$s. We have received your information and will be in touch if needed.', 'rt-gate' ),
			'<strong>' . esc_html( $form_name ) . '</strong>',
			esc_html( $site_name )
		);
		$html .= '</p>';

		if ( 'confirmation_and_links' === $mode && ! empty( $assets ) ) {
			$html .= '<p style="font-size:14px;color:#555555;">' . esc_html__( 'You can access your content using the links below:', 'rt-gate' ) . '</p>';
			$html .= '<table role="presentation" style="width:100%;border-collapse:collapse;margin:16px 0;">';
			foreach ( $assets as $asset ) {
				$slug        = isset( $asset['slug'] ) ? esc_html( $asset['slug'] ) : '';
				$redirect    = isset( $asset['redirect_url'] ) ? esc_url( $asset['redirect_url'] ) : '';
				$expires_at  = isset( $asset['expires_at'] ) ? esc_html( $asset['expires_at'] ) : '';

				if ( empty( $redirect ) ) {
					continue;
				}

				$html .= '<tr>';
				$html .= '<td style="padding:8px 12px;border-bottom:1px solid #eeeeee;">';
				$html .= '<a href="' . $redirect . '" style="color:#0073aa;text-decoration:none;font-weight:600;">' . $slug . '</a>';
				if ( ! empty( $expires_at ) ) {
					$html .= '<br><span style="font-size:12px;color:#999999;">';
					$html .= sprintf( esc_html__( 'Expires: %s UTC', 'rt-gate' ), $expires_at );
					$html .= '</span>';
				}
				$html .= '</td>';
				$html .= '</tr>';
			}
			$html .= '</table>';
		}

		$html .= self::email_footer( $site_name );

		return $html;
	}

	/**
	 * Build HTML body for the internal admin notification email.
	 *
	 * @param array  $fields    Submitted fields.
	 * @param array  $assets    Asset items.
	 * @param string $form_name Form name.
	 * @param int    $form_id   Form ID.
	 * @return string HTML body.
	 */
	private static function build_internal_email_html( $fields, $assets, $form_name, $form_id ) {
		$site_name    = self::get_site_name();
		$submitted_at = gmdate( 'Y-m-d H:i:s' ) . ' UTC';

		$html  = self::email_header( $site_name );
		$html .= '<p style="font-size:16px;color:#333333;font-weight:600;">';
		$html .= esc_html__( 'New Form Submission', 'rt-gate' );
		$html .= '</p>';

		$html .= '<table role="presentation" style="width:100%;border-collapse:collapse;margin:16px 0;">';
		$html .= self::internal_row( __( 'Form', 'rt-gate' ), esc_html( $form_name ) . ' (ID: ' . intval( $form_id ) . ')' );
		$html .= self::internal_row( __( 'Submitted', 'rt-gate' ), esc_html( $submitted_at ) );
		$html .= '</table>';

		$html .= '<p style="font-size:14px;color:#333333;font-weight:600;margin-top:20px;">';
		$html .= esc_html__( 'Lead Details', 'rt-gate' );
		$html .= '</p>';
		$html .= '<table role="presentation" style="width:100%;border-collapse:collapse;margin:8px 0;">';
		foreach ( $fields as $key => $value ) {
			$display_value = is_array( $value ) ? implode( ', ', array_map( 'sanitize_text_field', $value ) ) : sanitize_text_field( (string) $value );
			$html .= self::internal_row( esc_html( ucwords( str_replace( '_', ' ', $key ) ) ), esc_html( $display_value ) );
		}
		$html .= '</table>';

		if ( ! empty( $assets ) ) {
			$html .= '<p style="font-size:14px;color:#333333;font-weight:600;margin-top:20px;">';
			$html .= esc_html__( 'Gated Assets', 'rt-gate' );
			$html .= '</p>';
			$html .= '<ul style="padding-left:20px;margin:8px 0;">';
			foreach ( $assets as $asset ) {
				$slug       = isset( $asset['slug'] ) ? esc_html( $asset['slug'] ) : '';
				$expires_at = isset( $asset['expires_at'] ) ? esc_html( $asset['expires_at'] ) : '';
				$html .= '<li style="font-size:14px;color:#555555;margin-bottom:4px;">';
				$html .= '<strong>' . $slug . '</strong>';
				if ( ! empty( $expires_at ) ) {
					$html .= ' — ' . sprintf( esc_html__( 'token expires %s UTC', 'rt-gate' ), $expires_at );
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
		}

		$admin_url = admin_url( 'admin.php?page=rtg-leads' );
		$html .= '<p style="margin-top:24px;">';
		$html .= '<a href="' . esc_url( $admin_url ) . '" style="display:inline-block;padding:10px 20px;background-color:#0073aa;color:#ffffff;text-decoration:none;border-radius:4px;font-size:14px;">';
		$html .= esc_html__( 'View Leads in Admin', 'rt-gate' );
		$html .= '</a></p>';

		$html .= self::email_footer( $site_name );

		return $html;
	}

	/**
	 * Send an HTML email using wp_mail.
	 *
	 * @param string $to      Recipient(s).
	 * @param string $subject Subject line.
	 * @param string $body    HTML body.
	 * @return bool
	 */
	private static function send_html_email( $to, $subject, $body ) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: Online Form Submission <wordpress@realtreasury.com>',
		);

		return wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Build a readable lead identity string for notification subjects.
	 *
	 * @param array $fields Submitted fields.
	 * @return string
	 */
	private static function build_lead_identity( $fields ) {
		$first_name = self::get_field_value( $fields, array( 'first_name', 'firstname', 'first-name' ) );
		$last_name  = self::get_field_value( $fields, array( 'last_name', 'lastname', 'last-name' ) );
		$company    = self::get_field_value( $fields, array( 'company', 'company_name', 'organization', 'organisation' ) );

		$name = trim( trim( $first_name . ' ' . $last_name ) );

		if ( ! empty( $name ) && ! empty( $company ) ) {
			return $name . ' (' . $company . ')';
		}

		if ( ! empty( $name ) ) {
			return $name;
		}

		if ( ! empty( $company ) ) {
			return $company;
		}

		if ( ! empty( $fields['email'] ) ) {
			return sanitize_text_field( (string) $fields['email'] );
		}

		return 'unknown';
	}

	/**
	 * Get the first non-empty scalar value from a list of possible field keys.
	 *
	 * @param array $fields Form fields.
	 * @param array $keys   Candidate field keys.
	 * @return string
	 */
	private static function get_field_value( $fields, $keys ) {
		foreach ( $keys as $key ) {
			if ( ! isset( $fields[ $key ] ) || is_array( $fields[ $key ] ) ) {
				continue;
			}

			$value = trim( sanitize_text_field( (string) $fields[ $key ] ) );
			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Parse a newline-separated list of email addresses.
	 *
	 * @param string $raw Raw string (one email per line).
	 * @return array Valid email addresses.
	 */
	private static function parse_recipient_list( $raw ) {
		if ( empty( $raw ) ) {
			return array();
		}

		$lines  = preg_split( '/[\r\n,]+/', $raw );
		$emails = array();

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( ! empty( $trimmed ) && is_email( $trimmed ) ) {
				$emails[] = sanitize_email( $trimmed );
			}
		}

		return array_unique( $emails );
	}

	/**
	 * Get the site name for email branding.
	 *
	 * @return string
	 */
	private static function get_site_name() {
		$name = get_bloginfo( 'name' );
		return ! empty( $name ) ? $name : 'Real Treasury';
	}

	/**
	 * Shared email header HTML.
	 *
	 * @param string $site_name Site name.
	 * @return string
	 */
	private static function email_header( $site_name ) {
		$html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>';
		$html .= '<body style="margin:0;padding:0;background-color:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Oxygen,Ubuntu,sans-serif;">';
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5;">';
		$html .= '<tr><td align="center" style="padding:40px 20px;">';
		$html .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">';
		$html .= '<tr><td style="background-color:#1e3a5f;padding:24px 32px;">';
		$html .= '<h1 style="margin:0;font-size:20px;color:#ffffff;">' . esc_html( $site_name ) . '</h1>';
		$html .= '</td></tr>';
		$html .= '<tr><td style="padding:32px;">';

		return $html;
	}

	/**
	 * Shared email footer HTML.
	 *
	 * @param string $site_name Site name.
	 * @return string
	 */
	private static function email_footer( $site_name ) {
		$html  = '</td></tr>';
		$html .= '<tr><td style="padding:16px 32px;background-color:#f9f9f9;border-top:1px solid #eeeeee;">';
		$html .= '<p style="margin:0;font-size:12px;color:#999999;text-align:center;">';
		$html .= sprintf(
			/* translators: %s: Site name. */
			esc_html__( 'This email was sent by %s.', 'rt-gate' ),
			esc_html( $site_name )
		);
		$html .= '</p>';
		$html .= '</td></tr></table>';
		$html .= '</td></tr></table>';
		$html .= '</body></html>';

		return $html;
	}

	/**
	 * Build a table row for the internal notification email.
	 *
	 * @param string $label Row label.
	 * @param string $value Row value (pre-escaped).
	 * @return string
	 */
	private static function internal_row( $label, $value ) {
		$html  = '<tr>';
		$html .= '<td style="padding:6px 12px;border-bottom:1px solid #eeeeee;font-size:13px;color:#777777;width:120px;vertical-align:top;">' . esc_html( $label ) . '</td>';
		$html .= '<td style="padding:6px 12px;border-bottom:1px solid #eeeeee;font-size:14px;color:#333333;">' . $value . '</td>';
		$html .= '</tr>';

		return $html;
	}
}
