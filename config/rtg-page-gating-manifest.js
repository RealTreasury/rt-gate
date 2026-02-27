/**
 * @typedef {'canonical' | 'mapping-driven' | 'pending'} RTGFormIdPolicy
 *
 * @typedef {Object} RTGPageGatingManifestEntry
 * @property {string} pagePath
 * @property {string} assetSlug
 * @property {number | null} formId
 * @property {string} storageKey
 * @property {RTGFormIdPolicy=} formIdPolicy Optional policy metadata when `formId` is unresolved or intentionally delegated.
 * @property {string=} notes Optional operator notes for follow-up or rationale.
 */

/**
 * Page-level gating manifest used by validation tooling.
 *
 * @type {RTGPageGatingManifestEntry[]}
 */
export const pageGatingManifest = [];

export default pageGatingManifest;
