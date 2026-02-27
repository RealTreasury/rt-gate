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

## Entry templates

Use the following templates when adding new manifest entries so each key is present and naming stays consistent.

### `config/rtg-page-gating-manifest.js` entry template

```js
{
  // Route (pathname only) where the gated page is hosted.
  pagePath: '/gate/<page-slug>',

  // Slug used by RTG token validation and asset lookup.
  assetSlug: '<asset-slug>',

  // Canonical form id for this page, or null when mapping-driven/pending.
  formId: 42,

  // REQUIRED collision-resistant key pattern: rtg:<assetSlug>:<pageSlug>:v1
  // Example: rtg:treasury-guide:treasury-guide:v1
  storageKey: 'rtg:<asset-slug>:<page-slug>:v1',

  // Optional policy when formId needs explicit interpretation.
  // Allowed values: 'canonical' | 'mapping-driven' | 'pending'
  formIdPolicy: 'canonical',

  // Required when formIdPolicy is 'pending'; optional otherwise.
  notes: 'Use for rollout context, ownership, or follow-up instructions.'
}
```

### `config/rtg-form-asset-manifest.js` entry template

```js
{
  assetSlug: '<asset-slug>',

  // Canonical policy:
  // - set to a numeric form id when the asset is bound to one canonical form.
  // - set to null when the asset intentionally uses mapping-driven resolution.
  canonicalFormId: 42,

  // Optional human context for mapping-driven or transitional states.
  notes: 'Describe why canonicalFormId is null, and where form routing is resolved.'
}

// Canonical example
{
  assetSlug: 'treasury-guide',
  canonicalFormId: 42,
  notes: 'Canonical form id for the treasury-guide asset.'
}

// Mapping-driven example
{
  assetSlug: 'liquidity-overview',
  canonicalFormId: null,
  notes: 'Resolved by runtime mapping; pair page storage keys like rtg:liquidity-overview:<page-slug>:v1.'
}
```


## `formIdPolicy` guidance and examples

Use `formIdPolicy` when a page entry needs to clarify how `formId` should be interpreted:

### 1) `canonical`
Use when the page must enforce a specific form id.

```js
{
  pagePath: '/gate/treasury-guide',
  assetSlug: 'treasury-guide',
  formId: 42,
  storageKey: 'rtg:treasury-guide:treasury-guide:v1',
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
  storageKey: 'rtg:liquidity-overview:liquidity-overview:v1',
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
  storageKey: 'rtg:q4-playbook:q4-playbook:v1',
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
