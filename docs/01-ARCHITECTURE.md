# Real Treasury Gate Architecture

## Runtime components

- **Plugin bootstrap**: `rt-gate.php`
  - Defines plugin constants.
  - Loads all core classes from `includes/`.
  - Registers activation hook for DB install.
- **Admin layer**: `RTG_Admin`
  - Manages Forms, Assets, and Form→Asset Mapping records in wp-admin.
- **REST layer**: `RTG_REST`
  - Exposes public API under `/wp-json/rtg/v1`.
  - Implements request validation, route-level rate limiting, and CORS controls.
- **Domain services**:
  - `RTG_Token` for token issue/hash/expiry checks.
  - `RTG_Events` for event persistence/reporting.
  - `RTG_Webhook` for outbound event fan-out.

## Plugin execution flow

1. **Plugin load**
   - WordPress loads `rt-gate.php`.
   - Class files are required.
   - Runtime hooks are registered by class `init()` methods.

2. **Activation / schema install**
   - `register_activation_hook(... RTG_DB::install)` runs.
   - `RTG_DB::install()` creates/updates all `rtg_*` tables using `dbDelta`.

3. **Admin configuration**
   - Admin creates:
     - Form (`rtg_forms`)
     - Asset (`rtg_assets`)
     - Mapping (`rtg_mappings`) with `iframe_src_template`
   - Template supports placeholders:
     - `{asset_slug}`
     - `{token}`

4. **Lead submission (`POST /submit`)**
   - REST validation checks `form_id`, `fields`, and `consent=true`.
   - Lead is upserted by unique email into `rtg_leads`.
   - For each mapped asset:
     - issue token (`RTG_Token::issue_token`)
     - store only token hash in `rtg_tokens`
     - build redirect URL from mapping template
   - Logs `form_submit` event and returns redirect URLs.

5. **Asset access (`POST /validate`)**
   - Frontend posts `token` + `asset_slug`.
   - API resolves asset by slug and token row by `sha256(token)` + asset_id.
   - Expiry is enforced.
   - On success, returns non-PII asset payload used to render content.

6. **Behavior tracking (`POST /event`)**
   - Frontend posts token-authenticated event payload.
   - API validates event type and video progress constraints.
   - Event is written to `rtg_events`.
   - Webhook dispatch can emit event externally.

## GitHub-hosted frontend ↔ WP REST API interaction

1. User lands on external frontend page (e.g., GitHub Pages).
2. Frontend submits form payload to:
   - `https://<wp-site>/wp-json/rtg/v1/submit`
3. API response provides tokenized redirect URLs.
4. Frontend opens gated asset page with token in query string.
5. Gated page calls `/validate` to obtain asset config.
6. Frontend emits usage telemetry to `/event`.

### Cross-origin contract

- Server accepts cross-origin browser access only for origins on `github.io`.
- REST server returns:
  - `Access-Control-Allow-Origin: <origin>`
  - `Access-Control-Allow-Methods: POST, OPTIONS`
  - `Access-Control-Allow-Headers: Content-Type, Authorization`
- Non-`github.io` origins should not rely on browser access.

## Security boundaries

- Token secrecy boundary: only raw token at issuance/transport, never at rest.
- Data minimization boundary: validation responses exclude lead identity.
- Abuse boundary: transient-based rate limiting per route + requester hash.
