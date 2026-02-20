# ADR 0004: Salesforce integration is webhook-first for MVP

- Status: Accepted
- Date: 2026-02-20

## Context

Direct Salesforce OAuth integration introduces significant complexity (auth flow, token refresh, retries, failure handling, and ops support). MVP priority is reliable event delivery with minimal coupling to Salesforce API details.

## Decision

Adopt webhook-first outbound integration using non-blocking `wp_remote_post` from plugin runtime. Keep `RTG_Salesforce` OAuth methods scaffolded as future extension points.

Webhook triggers: `form_submit`, `asset_event` (and `asset_access` when enabled).

## Consequences

### Positive

- Faster MVP delivery with a stable outbound contract.
- Decouples plugin release cadence from Salesforce API changes.
- Allows middleware/iPaaS handling for retries, enrichment, and routing.

### Tradeoffs

- Requires external receiver service ownership.
- End-to-end delivery guarantees depend on downstream infrastructure.

## Alternatives considered

1. Build full OAuth + direct API push immediately.
   - Rejected as too much risk/effort for MVP timeline.
2. No external integration in MVP.
   - Rejected because lead/event forwarding is a core requirement.
