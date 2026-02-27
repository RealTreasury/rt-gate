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


## `formIdPolicy` guidance and examples

Use `formIdPolicy` when a page entry needs to clarify how `formId` should be interpreted:

### 1) `canonical`
Use when the page must enforce a specific form id.

```js
{
  pagePath: '/gate/treasury-guide',
  assetSlug: 'treasury-guide',
  formId: 42,
  storageKey: 'rtg:treasury-guide',
  formIdPolicy: 'canonical'
}
```

### 2) `mapping-driven`
Use when the page intentionally allows runtime mapping behavior and does not pin a canonical form id.

```js
{
  pagePath: '/gate/liquidity-overview',
  assetSlug: 'liquidity-overview',
  formId: null,
  storageKey: 'rtg:liquidity-overview',
  formIdPolicy: 'mapping-driven',
  notes: 'Asset is shared across campaigns and resolved via mapping manifest.'
}
```

### 3) `pending`
Use for temporary onboarding states where `formId` is currently unknown and follow-up is required.

```js
{
  pagePath: '/gate/q4-playbook',
  assetSlug: 'q4-playbook',
  formId: null,
  storageKey: 'rtg:q4-playbook',
  formIdPolicy: 'pending',
  notes: 'Waiting on marketing ops to finalize the canonical form id.'
}
```

Validation requirements enforced by `node scripts/validate-rtg-page-config.mjs`:
- If `formId` is `null`, `formIdPolicy` is required.
- If `formIdPolicy` is `pending`, `notes` must be non-empty.
- If `formIdPolicy` is `canonical`, `formId` cannot be `null`.

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
