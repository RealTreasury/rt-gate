# ADR 0001: Token model stores only SHA-256 hashes

- Status: Accepted
- Date: 2026-02-20

## Context

The plugin grants access to gated assets using bearer-style tokens passed through external frontend flows (including URL query transport). If raw tokens are stored in the database, any DB exposure immediately becomes a full access compromise.

## Decision

Generate a 32-byte random token (`random_bytes(32)`), hex-encode it for transport, and persist only `sha256(token)` in `rtg_tokens.token_hash`.

Validation re-hashes presented token and matches by `(token_hash, asset_id)`. Token expiry remains mandatory.

## Consequences

### Positive

- Reduces blast radius of DB leaks (stored value cannot be used directly as bearer token).
- Aligns with least-secrets-at-rest posture.
- Keeps token checks deterministic and fast with indexable fixed-length hash.

### Tradeoffs

- Raw token cannot be recovered for support/debugging.
- Requires careful handling at issuance time because token is shown once.

## Alternatives considered

1. Store encrypted raw tokens.
   - Rejected for MVP complexity and key management risk.
2. Store raw tokens with short TTL only.
   - Rejected due to unacceptable secret-at-rest exposure.
