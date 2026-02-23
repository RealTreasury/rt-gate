# ADR 0005: Soft delete policy for admin event mutation

- Status: Accepted
- Date: 2026-02-23

## Context

The events table is an audit log used for admin reporting and CSV export. Product needs admin event mutation (`delete_event`) while preserving auditability requirements and minimizing destructive data loss.

## Decision

Adopt **soft delete** for event mutation:

- Extend `rtg_events` with `is_deleted`, `deleted_at`, and `deleted_by` columns.
- Implement `delete_event` admin action (nonce + `manage_options` capability).
- Hide soft-deleted events by default in:
  - events list queries,
  - events count queries,
  - events CSV export.
- Provide optional `include_deleted=1` filter in events admin table for audit visibility.

## Consequences

### Positive

- Maintains an auditable history of event records.
- Avoids irreversible data destruction from routine admin UI operations.
- Keeps reporting defaults clean while allowing privileged forensic access.

### Tradeoffs

- Slightly more complex query logic and schema.
- Requires explicit filter to inspect deleted records.

## Alternatives considered

1. Hard delete rows from `rtg_events`.
   - Rejected due to audit loss and inability to explain historical discrepancies.
2. Correction-event append-only model.
   - Rejected for now due to larger UX/query surface area for a minimal delete requirement.
