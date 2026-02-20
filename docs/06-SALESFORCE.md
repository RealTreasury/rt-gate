# Salesforce Integration

This plugin uses a **webhook-first integration path** for MVP, with an OAuth scaffold in place for a future direct API integration.

## Current implementation: webhook dispatch

Source:
- `includes/class-webhook.php`
- `includes/class-rest.php`

### Trigger points

Webhook dispatch is called from REST flows:
- On form submission: `RTG_REST::handle_submit()` dispatches `form_submit`
- On asset event: `RTG_REST::handle_event()` dispatches `asset_event`

The `_AI_CONTEXT.md` target model also includes `asset_access`; that event type can be enabled once the corresponding emission point is implemented.

### Settings contract

Webhook settings are read from WordPress option:
- Option key: `rtg_settings`
- Keys used:
  - `webhook_url`
  - `webhook_secret`
  - `webhook_events` (array allowlist)
  - Legacy toggles: `webhook_<event_name>`

### Outbound request format

Method:
- `wp_remote_post(..., [ 'blocking' => false, 'timeout' => 3 ])`

Headers:
- `Content-Type: application/json`
- `X-RTG-Signature: <hmac_sha256>` when secret configured

Body JSON:
- `event` (sanitized event key)
- `occurredAt` (ISO 8601 UTC)
- `payload` (event-specific payload)

### Signature verification contract (receiver side)

If `webhook_secret` is set:
- Signature = `hash_hmac( 'sha256', raw_body_json, webhook_secret )`
- Receiver should recompute and compare against `X-RTG-Signature`

## Webhook setup guide

1. Provision an HTTPS endpoint (middleware, iPaaS, or Salesforce-facing bridge).
2. Configure plugin option `rtg_settings['webhook_url']` to that endpoint.
3. Set `rtg_settings['webhook_secret']` to a shared secret.
4. Optionally set `rtg_settings['webhook_events']` to explicitly enable event names.
5. Verify inbound signatures and log/replay handling on the receiver.

## Future path: OAuth implementation

Source scaffold:
- `includes/class-salesforce.php` (`RTG_Salesforce`)

Scaffolded methods (currently TODO):
- `get_authorization_url( $state )`
- `exchange_code_for_tokens( $code )`
- `refresh_access_token()`
- `push_payload( $payload )`

### Recommended phased migration

1. Keep webhook as durable boundary for MVP reliability.
2. Implement OAuth auth-code flow in `RTG_Salesforce`.
3. Persist encrypted token set (access + refresh + expiry) in plugin settings/storage.
4. Add retryable push worker (or scheduled queue) for Salesforce API calls.
5. Run webhook and OAuth paths in parallel behind feature flags.
6. Migrate event delivery gradually after monitoring parity.

## ADR alignment

See:
- `docs/adr/0004-salesforce-webhook-first.md`

Any change to the integration approach should update/add ADRs in `docs/adr/`.
