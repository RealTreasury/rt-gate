# Data Model

All tables are created by `RTG_DB::install()` with prefix: `$wpdb->prefix . 'rtg_'`.

## Tables (8)

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
- UNIQUE composite key: (`form_id`, `asset_id`) (enforced via dbDelta)
- `iframe_src_template` longtext
- `created_at` datetime

Relationship role:
- Implements many-to-many from forms to assets with at most one mapping row per (`form_id`, `asset_id`) pair.
- Drives redirect URL generation during `/submit`.

## 4) `rtg_leads`
Purpose: lead identity and submitted field payload.

Columns:
- `id` bigint unsigned PK
- `email` varchar(191) UNIQUE
- `form_data` longtext (JSON payload as string; supports legacy flat shape and history-aware shape)
- `ip_hash` varchar(191) (sha256)
- `ua_hash` varchar(191) (sha256)
- `created_at` datetime

Relationships:
- One lead can have many tokens via `rtg_tokens.lead_id`.
- One lead can have many events via `rtg_events.lead_id`.

`form_data` JSON semantics (backward compatible):
- Legacy rows may still contain a flat object (example: `{"email":"x@example.com","company":"Acme"}`).
- New submit upserts write an envelope with:
  - `latest`: latest submitted fields object (used by current lead list/detail UX)
  - `history`: object keyed by `form_id`, then submission timestamp, storing payload snapshots
  - top-level legacy-compatible keys copied from `latest` (for older logic and exports)
- Example new shape:
  - `{ "latest": {"email":"x@example.com"}, "history": {"12": {"2026-01-01T12:00:00Z": {"email":"x@example.com"}}}, "email": "x@example.com" }`


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
- `is_deleted` tinyint(1) (indexed, default `0`)
- `deleted_at` datetime nullable
- `deleted_by` bigint unsigned (admin user ID, default `0`)
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


## Lead lifecycle and deletion semantics

Admin-initiated lead deletion uses explicit hard-deletion with referential cleanup:

1. Delete related `rtg_tokens` records by `lead_id`.
2. Delete related `rtg_events` records by `lead_id`.
3. Delete the `rtg_leads` record.

Rationale:
- Avoids orphaned rows because schema does not enforce foreign-key cascades.
- Removes direct lead-linked PII (`email`, `form_data`) from admin lookup paths and exports.
- Keeps token and event invariants intact by removing records that can no longer be safely attributed.

## Event deletion semantics

Admin-initiated event deletion uses **soft delete** to preserve auditability while hiding records from default admin views and CSV export:

- `is_deleted = 1` marks event as removed from normal reporting.
- `deleted_at` stores UTC deletion timestamp.
- `deleted_by` stores the admin user ID that performed the delete action.

Query behavior:
- `RTG_Events::query_events()` and `RTG_Events::count_events()` enforce `e.is_deleted = 0` by default.
- Admin UI can include deleted rows only when `include_deleted=1` filter is set.
- CSV export uses the same filtered query path and therefore excludes deleted rows by default.


## 7) `rtg_form_revisions`
Purpose: append-only snapshots of prior `rtg_forms` states for admin rollback.

Columns:
- `id` bigint unsigned PK
- `form_id` bigint unsigned (indexed)
- `snapshot` longtext (JSON-encoded prior form row)
- `edited_by` bigint unsigned (admin user ID)
- `restored_from_revision_id` bigint unsigned (0 unless created by restore)
- `created_at` datetime (indexed)

## 8) `rtg_mapping_revisions`
Purpose: append-only snapshots of prior `rtg_mappings` states for admin rollback.

Columns:
- `id` bigint unsigned PK
- `mapping_id` bigint unsigned (indexed)
- `snapshot` longtext (JSON-encoded prior mapping row)
- `edited_by` bigint unsigned (admin user ID)
- `restored_from_revision_id` bigint unsigned (0 unless created by restore)
- `created_at` datetime (indexed)
