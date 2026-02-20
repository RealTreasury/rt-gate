# ADR 0002: Relational schema with six core `rtg_*` tables

- Status: Accepted
- Date: 2026-02-20

## Context

The product requires many-to-many mapping between forms and assets, per-lead token issuance, and auditable event history. WordPress options/meta storage would make filtering, joins, and export workflows brittle and expensive.

## Decision

Use a normalized relational model installed via `dbDelta` with six plugin tables:

1. `rtg_forms`
2. `rtg_assets`
3. `rtg_mappings`
4. `rtg_leads`
5. `rtg_tokens`
6. `rtg_events`

Use unique/index constraints for high-frequency lookups (`assets.slug`, `leads.email`, `tokens.token_hash`, event filtering keys).

## Consequences

### Positive

- Predictable query performance for admin filters and API lookups.
- Clear boundaries between domain entities.
- Easier long-term evolution than overloading generic post/meta tables.

### Tradeoffs

- Requires schema migration discipline across releases.
- Slightly higher initial implementation overhead.

## Alternatives considered

1. Custom post types + post meta.
   - Rejected due to join-heavy reporting and weaker type constraints.
2. Single denormalized events-first table.
   - Rejected because it complicates lead/token lifecycle logic.
