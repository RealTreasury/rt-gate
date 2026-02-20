# Data Model

All tables are created by `RTG_DB::install()` with prefix: `$wpdb->prefix . 'rtg_'`.

## Tables (6)

## 1) `rtg_forms`
Purpose: define lead-capture form metadata.

Columns:
- `id` bigint unsigned PK
- `name` varchar(191)
- `fields_schema` longtext (JSON payload as string)
- `consent_text` longtext
- `created_at` datetime

Relationships:
- One form can map to many assets via `rtg_mappings.form_id`.
- One form can have many events via `rtg_events.form_id`.

## 2) `rtg_assets`
Purpose: define gated resources.

Columns:
- `id` bigint unsigned PK
- `name` varchar(191)
- `slug` varchar(191) UNIQUE
- `type` varchar(50) (`download|video|link`)
- `config` longtext (JSON payload as string)
- `created_at` datetime

Relationships:
- One asset can be mapped from many forms via `rtg_mappings.asset_id`.
- One asset can have many tokens via `rtg_tokens.asset_id`.
- One asset can have many events via `rtg_events.asset_id`.

## 3) `rtg_mappings`
Purpose: join table between forms and assets + redirect template.

Columns:
- `id` bigint unsigned PK
- `form_id` bigint unsigned (indexed)
- `asset_id` bigint unsigned (indexed)
- `iframe_src_template` longtext
- `created_at` datetime

Relationship role:
- Implements many-to-many from forms to assets.
- Drives redirect URL generation during `/submit`.

## 4) `rtg_leads`
Purpose: lead identity and submitted field payload.

Columns:
- `id` bigint unsigned PK
- `email` varchar(191) UNIQUE
- `form_data` longtext (JSON payload as string)
- `ip_hash` varchar(191) (sha256)
- `ua_hash` varchar(191) (sha256)
- `created_at` datetime

Relationships:
- One lead can have many tokens via `rtg_tokens.lead_id`.
- One lead can have many events via `rtg_events.lead_id`.

## 5) `rtg_tokens`
Purpose: per-lead/per-asset access grants.

Columns:
- `id` bigint unsigned PK
- `lead_id` bigint unsigned (indexed)
- `asset_id` bigint unsigned (indexed)
- `token_hash` varchar(64) (indexed, sha256 of raw token)
- `expires_at` datetime (indexed)
- `created_at` datetime

Relationships:
- Belongs to one lead (`lead_id`).
- Belongs to one asset (`asset_id`).

Constraints/invariants:
- Raw token is never stored.
- Token is looked up by `(token_hash, asset_id)`.
- Expiry is required for all token checks.

## 6) `rtg_events`
Purpose: immutable activity log for form submits and asset interactions.

Columns:
- `id` bigint unsigned PK
- `lead_id` bigint unsigned (indexed)
- `form_id` bigint unsigned (indexed)
- `asset_id` bigint unsigned (indexed)
- `event_type` varchar(100) (indexed)
- `meta` longtext (JSON payload as string)
- `created_at` datetime (indexed)

Relationships:
- Many events can reference same lead/form/asset.
- Event email lookups in admin are done via join to `rtg_leads`.

## Relationship summary

- `forms (1) -> (N) mappings`
- `assets (1) -> (N) mappings`
- `leads (1) -> (N) tokens`
- `assets (1) -> (N) tokens`
- `leads (1) -> (N) events`
- `forms (1) -> (N) events`
- `assets (1) -> (N) events`
