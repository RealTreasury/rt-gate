# REST API Contract (`/wp-json/rtg/v1`)

Base path:
- `https://<wp-site>/wp-json/rtg/v1`

Supported endpoints:
- `POST /submit`
- `POST /validate`
- `POST /event`

## Common request requirements

Headers:
- `Content-Type: application/json` (required for JSON body)
- `Origin: https://<something>.github.io` (required for browser CORS access)

Optional/allowed headers:
- `Authorization` (allowed by CORS policy but not used by endpoint auth)

Rate limiting:
- 10 requests/minute per route per requester hash.
- Exceeded limit returns HTTP `429` with WP error code `rtg_rate_limited`.

---

## `POST /submit`

Create/update lead and issue per-asset tokenized redirect URLs.

### Request JSON

```json
{
  "form_id": 123,
  "fields": {
    "email": "user@example.com",
    "first_name": "Ada",
    "company": "Example Co"
  },
  "consent": true,
  "honeypot": ""
}
```

### Honeypot behavior

- Every submit payload must include a honeypot field and keep it empty (`"honeypot": ""` or `fields._rtg_hp = ""`).
- Non-empty honeypot submissions are treated as bot traffic and receive an empty success payload with no issued tokens.



Required fields:
- `form_id` (integer > 0)
- `fields` (object/associative array)
- `fields.email` (valid email)
- `consent` (must evaluate to `true`)

### Success response (HTTP 200)

```json
{
  "primary_redirect_url": "https://<github-pages>/gate/asset-slug?t=<raw-token>",
  "assets": [
    {
      "slug": "asset-slug",
      "redirect_url": "https://<github-pages>/gate/asset-slug?t=<raw-token>",
      "expires_at": "2026-01-01 12:34:56"
    }
  ]
}
```

### Error responses

- `400 rtg_invalid_submit` when missing/invalid `form_id`, `fields`, or `consent`
- `400 rtg_missing_email` when `fields.email` absent
- `400 rtg_invalid_email` when email invalid
- `404 rtg_form_not_found` when form not found
- `500 rtg_lead_upsert_failed` when lead save fails

---

## `POST /validate`

Validate token for a specific asset and return renderable asset config.

### Request JSON

```json
{
  "token": "<raw-token>",
  "asset_slug": "asset-slug"
}
```

Required fields:
- `token` (string)
- `asset_slug` (string; slug normalized server-side)

### Success response (valid token, HTTP 200)

```json
{
  "valid": true,
  "asset": {
    "type": "video",
    "config": {
      "provider": "youtube",
      "video_id": "abc123"
    }
  },
  "expires_at": "2026-01-01 12:34:56"
}
```

### Invalid/not-found response (HTTP 200)

```json
{
  "valid": false
}
```

Notes:
- Endpoint intentionally avoids returning lead PII.

---

## `POST /event`

Record an asset interaction event tied to a valid token.

### Request JSON

```json
{
  "token": "<raw-token>",
  "asset_slug": "asset-slug",
  "event_type": "video_progress",
  "meta": {
    "progress": 75
  }
}
```

Required fields:
- `token` (string)
- `asset_slug` (string)
- `event_type` in:
  - `page_view`
  - `download_click`
  - `video_play`
  - `video_progress`
- `meta` (object; defaults to `{}` if omitted/non-object)

Additional rule:
- If `event_type = video_progress`, then `meta.progress` must be one of:
  - `25`, `50`, `75`, `90`, `100`

### Success response (HTTP 200)

```json
{
  "recorded": true
}
```

### Error responses

- `400 rtg_invalid_event` invalid base payload/event_type
- `400 rtg_invalid_progress` invalid/missing video progress value
- `404 rtg_asset_not_found` unknown asset slug
- `401 rtg_invalid_token` token invalid or expired
- `500 rtg_event_save_failed` persistence failure
