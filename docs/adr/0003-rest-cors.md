# ADR 0003: CORS allowlist restricted to `github.io` origins

- Status: Accepted
- Date: 2026-02-20

## Context

The frontend is expected to be hosted externally (MVP target: GitHub Pages). Public REST routes need browser access, but unrestricted CORS would allow arbitrary third-party sites to call endpoints directly from user browsers.

## Decision

In `rest_pre_serve_request`, emit CORS headers only when `Origin` host is `github.io` or a `*.github.io` subdomain.

Allowed methods: `POST, OPTIONS`.
Allowed headers: `Content-Type, Authorization`.

## Consequences

### Positive

- Supports intended GitHub Pages deployment model.
- Narrows browser-call surface compared with wildcard CORS.
- Maintains explicit, auditable trust boundary.

### Tradeoffs

- Non-`github.io` frontends must use a server-side proxy or code change.
- Multi-environment frontend hosting needs planned allowlist updates.

## Alternatives considered

1. `Access-Control-Allow-Origin: *`
   - Rejected due to unnecessarily broad exposure.
2. Dynamic admin-configurable allowlist for MVP.
   - Deferred to avoid configuration complexity early on.
