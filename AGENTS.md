# AI Agent Contribution Rules

If you are an AI coding agent working in this repository, follow these rules on every change:

1. Read the project documentation first, starting with `_AI_CONTEXT.md` and then `docs/00-START-HERE.md` once available.
2. Preserve architectural and security invariants; do not change token, data, or API guarantees without explicit approval.
3. If your change alters architecture, data model, security posture, or integration approach, update or add an ADR in `docs/adr/`.
4. Keep docs in sync with code changes (especially architecture, API, deployment, and extension docs).
5. Do not break existing invariants for token hashing, expiry enforcement, PII handling, and WordPress.com deployment assumptions.
6. Include tests/checks for new behavior and ensure local quality gates pass before handing off.
