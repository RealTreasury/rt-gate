# Troubleshooting

Use this guide to quickly diagnose common runtime and integration issues.

## 1) `POST /submit` returns 400

Common causes:

- Missing `form_id`
- Missing `fields.email`
- `consent` not strictly `true`

Check:

- Request JSON shape matches `docs/03-REST-API.md`
- Form exists and has asset mappings

## 2) `POST /validate` returns `rtg_invalid_token`

Common causes:

- Token copied incorrectly from URL
- Token expired
- Asset slug mismatch

Check:

- Querystring `t` value is intact
- Submitted `asset_slug` matches mapped asset
- Token TTL expectations in `includes/class-token.php`

## 3) CORS errors in browser console

Common causes:

- Frontend origin is not a `github.io` host
- Missing `Origin` header in custom client

Check:

- Page is served from `<something>.github.io`
- Response includes `Access-Control-Allow-Origin`
- Preflight `OPTIONS` reaches `/wp-json/rtg/v1/...`

## 4) Events are not appearing in wp-admin

Common causes:

- Frontend is not calling `/event`
- Event payload fails validation
- Token invalid/expired during event send

Check:

- Network log contains successful `/event` POST
- `event_type` is allowed
- For `video_progress`, milestone is one of 25/50/75/90/100

## 5) Webhook receiver gets no traffic

Common causes:

- `rtg_settings.webhook_url` not set
- Event disabled by webhook event allowlist
- Receiver rejects signature

Check:

- Plugin settings contain valid HTTPS URL
- `webhook_events` includes emitted event names
- Receiver compares `X-RTG-Signature` using shared secret

## 6) Admin CSV export fails

Common causes:

- Nonce expired
- User lacks required capability

Check:

- Refresh admin page and retry export
- Confirm account has `manage_options`

## 7) Plugin activation issues

Common causes:

- DB schema install failed
- Environment mismatch (WordPress/PHP version)

Check:

- Plugin error logs for `RTG_DB::install()`
- Required versions from `rt-gate.php` header

## Escalation checklist

When opening an issue, include:

- Endpoint and payload (redacted)
- Error code/message
- WordPress + PHP versions
- Origin URL (for CORS issues)
- Timestamp and expected behavior
