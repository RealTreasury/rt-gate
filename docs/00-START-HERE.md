# AI AGENTS READ THIS FIRST

This repository is a WordPress.com-deployable plugin. Treat these constraints as hard invariants.

## Critical invariants (do not break)

1. **Token model is SHA-256 only**
   - Raw access tokens are generated with `random_bytes(32)` and hex encoded.
   - Raw tokens are **never stored**.
   - Persistence must use `sha256` hash only (`rtg_tokens.token_hash`, 64 chars).

2. **Token expiry is mandatory**
   - Every token has an `expires_at`.
   - `/validate` and `/event` must reject expired tokens.

3. **PII handling is constrained**
   - `/validate` must not return lead PII.
   - IP/User-Agent are stored as hashes (`sha256`) only (`ip_hash`, `ua_hash`, event request hashes).

4. **Database schema contracts are stable**
   - Exactly 6 core plugin tables: `rtg_forms`, `rtg_assets`, `rtg_mappings`, `rtg_leads`, `rtg_tokens`, `rtg_events`.
   - Table creation/updates happen through `dbDelta` in `RTG_DB::install()`.
   - Keep expected keys/uniques (notably `assets.slug` unique, `leads.email` unique, indexed `tokens.token_hash`).

5. **Strict separation of concerns**
   - `class-admin.php`: wp-admin CRUD UX only.
   - `class-rest.php`: API contracts, validation, rate limit, CORS policy.
   - `class-token.php`: token generation/hash/expiry logic.
   - `class-events.php`: event persistence and admin event reporting.
   - `class-webhook.php`: non-blocking outbound webhook dispatch only.
   - `class-db.php`: schema install/update only.

6. **Frontend/API boundary must remain decoupled**
   - Frontend can be hosted externally (e.g., GitHub Pages).
   - Cross-origin access is intentionally limited to `github.io` hosts.
   - API namespace is fixed at `/wp-json/rtg/v1`.

7. **Security controls are required behavior**
   - REST endpoints are rate-limited via transients per route + hashed IP.
   - CORS headers are only emitted for allowed `github.io` origins.

## First files to inspect before any changes

1. `_AI_CONTEXT.md`
2. `docs/01-ARCHITECTURE.md`
3. `docs/02-DATA-MODEL.md`
4. `docs/03-REST-API.md`
5. `includes/class-rest.php`, `includes/class-token.php`, `includes/class-db.php`

## Deployment assumptions

- Repo root is plugin root.
- WordPress.com GitHub Deployments deploy this plugin directly.
- Do not introduce assumptions requiring a `wp-content/plugins/...` repo layout.
