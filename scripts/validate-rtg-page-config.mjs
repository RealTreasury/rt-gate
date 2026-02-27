import pageGatingManifest from '../config/rtg-page-gating-manifest.js';

const FORM_ID_POLICIES = new Set(['canonical', 'mapping-driven', 'pending']);

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isObject(value) {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

const errors = [];

if (!Array.isArray(pageGatingManifest)) {
  errors.push('`config/rtg-page-gating-manifest.js` must export an array.');
} else {
  pageGatingManifest.forEach((entry, index) => {
    const label = `entry[${index}]`;

    if (!isObject(entry)) {
      errors.push(`${label} must be an object.`);
      return;
    }

    const { pagePath, assetSlug, formId, storageKey, formIdPolicy, notes } = entry;

    if (typeof pagePath !== 'string' || pagePath.trim() === '') {
      errors.push(`${label}.pagePath must be a non-empty string.`);
    }

    if (typeof assetSlug !== 'string' || assetSlug.trim() === '') {
      errors.push(`${label}.assetSlug must be a non-empty string.`);
    }

    if (!(typeof formId === 'number' || formId === null)) {
      errors.push(`${label}.formId must be a number or null.`);
    }

    if (typeof storageKey !== 'string' || storageKey.trim() === '') {
      errors.push(`${label}.storageKey must be a non-empty string.`);
    }

    if (formIdPolicy !== undefined && !FORM_ID_POLICIES.has(formIdPolicy)) {
      errors.push(`${label}.formIdPolicy must be one of: canonical, mapping-driven, pending.`);
    }

    if (notes !== undefined && typeof notes !== 'string') {
      errors.push(`${label}.notes must be a string when provided.`);
    }

    if (formId === null && formIdPolicy === undefined) {
      errors.push(`${label} has formId=null but is missing formIdPolicy.`);
    }

    if (formIdPolicy === 'pending' && (!notes || notes.trim() === '')) {
      errors.push(`${label} has formIdPolicy='pending' but notes is empty.`);
    }

    if (formIdPolicy === 'canonical' && formId === null) {
      errors.push(`${label} has formIdPolicy='canonical' but formId is null.`);
    }
  });
}

if (errors.length > 0) {
  console.error('RTG page config validation failed:');
  errors.forEach((message) => console.error(`- ${message}`));
  process.exit(1);
}

console.log(`RTG page config validation passed (${pageGatingManifest.length} entries).`);
