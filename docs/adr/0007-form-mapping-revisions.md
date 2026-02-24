# ADR 0007: Append-only form/mapping revisions for admin restores

- Status: Accepted
- Date: 2026-02-24

## Context

Admins need a safe way to change forms and mappings while preserving an audit trail and enabling rollback when misconfigurations are introduced.

## Decision

Add two new tables:

1. `rtg_form_revisions`
2. `rtg_mapping_revisions`

Each revision row stores:
- owning record ID (`form_id` or `mapping_id`)
- JSON snapshot of the pre-change state
- editor user ID
- optional `restored_from_revision_id` when snapshot came from a restore action
- timestamp

Behavioral rules:
- On successful update in `save_form()` / `save_mapping()`, persist the prior state first.
- Restore actions apply a selected snapshot and first persist the current state as a new revision.
- Revision history is append-only (no in-place mutation/deletion path).

## Consequences

### Positive

- Safer admin editing with one-click rollback.
- Auditability of who changed what and when.
- Restore operations remain traceable due to append-only trail.

### Tradeoffs

- Additional storage for snapshots.
- Extra DB writes on update/restore paths.
