# Security Model

This document summarizes the runtime security controls implemented by Real Treasury Gate.

## 1) Token hashing and lifecycle

Source:
- `includes/class-token.php`
- `includes/class-rest.php`

### Generation

`RTG_Token::issue_token( $lead_id, $asset_id, $ttl )`:
- Generates a raw token with `random_bytes(32)` and `bin2hex(...)`
- Hashes it with SHA-256 (`RTG_Token::hash_token()`)
- Stores only `token_hash` in `rtg_tokens`
- Returns raw token **only** to the caller for immediate redirect URL assembly

### Storage invariant

- Raw token is never persisted
- Database column `rtg_tokens.token_hash` stores a 64-char SHA-256 digest

### Validation

`RTG_REST::find_token_for_asset()`:
- Re-hashes provided raw token with SHA-256
- Looks up by `(token_hash, asset_id)`

Endpoints enforcing token validity:
- `POST /validate`
- `POST /event`

### Expiry enforcement

`RTG_Token::is_not_expired( $expires_at )` is required in:
- `RTG_REST::handle_validate()`
- `RTG_REST::handle_event()`

If missing/expired, token is rejected.

## 2) Rate limiting

Source:
- `includes/class-rest.php` (`RTG_REST::rate_limit_requests`)

Mechanism:
- Hook: `rest_pre_dispatch`
- Applies only to routes under `/rtg/v1/`
- Key basis: `md5( hash_ip(remote_ip) + '|' + route )`
- Storage: WordPress transients
- Budget: `10` requests per `MINUTE_IN_SECONDS` per route bucket

Failure response:
- `WP_Error( 'rtg_rate_limited', ..., status 429 )`

## 3) CORS rules

Source:
- `includes/class-rest.php` (`RTG_REST::add_cors_headers`)

Mechanism:
- Hook: `rest_pre_serve_request`
- Reads `HTTP_ORIGIN`
- Parses host and allows only:
  - `github.io`
  - `*.github.io`

Allowed response headers for approved origins:
- `Access-Control-Allow-Origin: <origin>`
- `Vary: Origin`
- `Access-Control-Allow-Methods: POST, OPTIONS`
- `Access-Control-Allow-Headers: Content-Type, Authorization`

Preflight:
- For `OPTIONS`, returns status `200` and short-circuits response serving.

## 4) WordPress nonce protections

Nonces are used for privileged wp-admin actions.

### Admin CRUD nonces

Source:
- `includes/class-admin.php`

Actions and checks:
- Forms save: nonce action `rtg_save_form` (`check_admin_referer( 'rtg_save_form' )`)
- Assets save: nonce action `rtg_save_asset` (`check_admin_referer( 'rtg_save_asset' )`)
- Mappings save: nonce action `rtg_save_mapping` (`check_admin_referer( 'rtg_save_mapping' )`)

### Events export nonce

Source:
- `includes/class-events.php`

Action and check:
- CSV export link created with `wp_nonce_url(..., 'rtg_export_events_csv')`
- Handler validates with `check_admin_referer( 'rtg_export_events_csv' )`

## 5) PII and request fingerprint handling

Relevant source:
- `includes/class-rest.php`
- `includes/class-events.php`
- `includes/class-utils.php`

Current constraints:
- `/validate` returns only token validity + asset payload (no lead PII)
- Lead table stores `ip_hash` and `ua_hash`, not raw values
- Event metadata automatically appends hashed request IP/UA

## 6) Bot mitigation with submit honeypot

Source:
- `includes/class-rest.php` (`RTG_REST::handle_submit`, `RTG_REST::extract_honeypot_value`)

Mechanism:
- `POST /submit` accepts an explicit `honeypot` value (or `fields._rtg_hp`).
- Legitimate clients must keep the honeypot empty.
- If honeypot is non-empty, the request is treated as likely bot traffic and returns an empty successful payload (`primary_redirect_url` empty and `assets` empty).
- No lead upsert, token issuance, event logging, or webhook dispatch occurs for honeypot-triggered submissions.
