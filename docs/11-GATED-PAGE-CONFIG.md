# 11. Gated Page Configuration

## New Page Onboarding Checklist

When onboarding a new gated page, complete all of the following steps:

1. Add a new page entry to `config/rtg-page-gating-manifest.js` with:
   - `pagePath`
   - `assetSlug`
   - `formId`
   - `storageKey`
2. Add or update the `assetSlug` policy in `config/rtg-form-asset-manifest.js` with `canonicalFormId` set to either:
   - a number (for a form-bound asset), or
   - `null` (for assets that intentionally do not enforce a canonical form id)
3. Ensure the page source sets:
   - `window.RTG_CONFIG.assetSlug`, and
   - `window.RTG_CONFIG.formId` (when the page requires form binding)
4. Run `node scripts/validate-rtg-page-config.mjs` before merge.

## Definition of ready

- **Pass:** The page manifest entry exists and includes `pagePath`, `assetSlug`, `formId`, and `storageKey`.
- **Pass:** The `assetSlug` policy is present in `rtg-form-asset-manifest.js` with valid `canonicalFormId` (`number` or `null`).
- **Pass:** Page source exposes the required `window.RTG_CONFIG` values for runtime validation.
- **Pass:** `node scripts/validate-rtg-page-config.mjs` passes with no errors.
- **Fail:** Any checklist item above is missing, inconsistent, or validation fails.

## Open Form-ID Follow-ups

Any page manifest entry using `formId: null` for an asset that is **not** permanently mapping-driven must be tracked in this table until the `formId` is resolved.

| pagePath | assetSlug | temporary formId state (`null`) | owner | target date | notes |
| --- | --- | --- | --- | --- | --- |
| _Add follow-up item_ | _asset slug_ | `null` | _owner_ | _YYYY-MM-DD_ | _Reason for temporary null and planned fix_ |
