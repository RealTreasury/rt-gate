# Testing Guide

This document defines how to validate Real Treasury Gate safely without violating security and architecture invariants.

## Testing goals

- Confirm end-to-end form → token → gated asset flow works.
- Verify token security invariants (hashing only, expiry enforced).
- Verify REST contract stability for external frontends.
- Verify event telemetry and admin export behavior.

## Test levels

## 1) Static checks

Run these before functional testing:

- PHP lint for all plugin files.
- WordPress coding standards checks (PHPCS) where configured.
- Basic docs consistency checks (required docs exist, route names match implementation).

## 2) Unit/service tests (when test harness is available)

Focus classes:

- `RTG_Token`
  - `issue_token()` returns raw token but stores only SHA-256 hash.
  - `is_not_expired()` rejects expired timestamps.
- `RTG_Utils`
  - IP and UA hashing always returns SHA-256 values.
- `RTG_REST` payload validators
  - Reject invalid submit/event payloads with correct error codes.

## 3) Integration tests

### `/submit`

Validate:

- Requires `form_id`, `fields.email`, and `consent=true`.
- Upserts `rtg_leads` by email.
- Issues one token per mapped asset.
- Returns `primary_redirect_url` and `assets[]` list.
- Emits `form_submit` event.

### `/validate`

Validate:

- Unknown `asset_slug` is rejected.
- Invalid token rejected.
- Expired token rejected.
- Valid token returns only non-PII asset payload.

### `/event`

Validate:

- Accepts supported event types only.
- `video_progress` accepts only expected milestones.
- Rejects expired token.
- Persists to `rtg_events` and triggers webhook flow when enabled.

## 4) Admin UX checks

- Create/edit forms.
- Create/edit assets (download/video/link).
- Create mappings and verify template placeholders (`{asset_slug}`, `{token}`).
- Events table filters (form/asset/email/event/date).
- CSV export works with nonce protection.

## Security regression checklist

- Raw token is never persisted in DB.
- `/validate` never returns lead email or form data.
- CORS headers only for `github.io` origins.
- Rate limiting returns 429 after burst.
- IP and User-Agent are stored only as hashes.

## Suggested CI quality gate order

1. Lint (`php -l`)
2. PHPCS (WordPress ruleset)
3. PHPUnit/integration tests (if present)
4. Documentation link/contract check

## Definition of done

A change is ready when:

- Security invariants remain true.
- API behavior is backward compatible unless intentionally versioned.
- Docs and ADRs are updated if architecture/security/integration decisions changed.
