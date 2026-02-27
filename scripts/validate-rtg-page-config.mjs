import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import pageGatingManifest from '../config/rtg-page-gating-manifest.js';

const FORM_ID_POLICIES = new Set(['canonical', 'mapping-driven', 'pending']);
const CANDIDATE_EXTENSIONS = new Set(['.html', '.js', '.jsx', '.mjs', '.cjs', '.ts', '.tsx', '.php']);
const SCAN_DIRECTORIES = ['assets', 'templates', 'examples'];
const SKIP_DIRECTORY_NAMES = new Set(['.git', 'node_modules', 'vendor']);
const SKIP_DIRECTORY_PATHS = new Set(['examples/qa']);
const ENDPOINT_MARKERS = [
  /\/form\/\{id\}/,
  /\/form\/\{form_id\}/,
  /\/submit\b/,
  /\/validate\b/,
  /\/gate\/[A-Za-z0-9_-]+/,
  /\/gate\/\{slug\}/,
];
const RTG_CONFIG_PATTERN = /window\.RTG_CONFIG\b/;

/**
 * @param {unknown} value
 * @returns {value is Record<string, unknown>}
 */
function isObject(value) {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * @param {string} rootDir
 * @returns {Promise<string[]>}
 */
async function collectCandidateFiles(rootDir) {
  const output = [];

  /**
   * @param {string} currentDir
   * @returns {Promise<void>}
   */
  async function walk(currentDir) {
    let entries = [];

    try {
      entries = await readdir(currentDir, { withFileTypes: true });
    } catch (error) {
      if (error && typeof error === 'object' && 'code' in error && error.code === 'ENOENT') {
        return;
      }
      throw error;
    }

    for (const entry of entries) {
      const fullPath = path.join(currentDir, entry.name);
      const relativePath = path.relative(rootDir, fullPath).replace(/\\/g, '/');

      if (entry.isDirectory()) {
        if (SKIP_DIRECTORY_NAMES.has(entry.name) || SKIP_DIRECTORY_PATHS.has(relativePath)) {
          continue;
        }

        await walk(fullPath);
        continue;
      }

      if (!entry.isFile()) {
        continue;
      }

      const extension = path.extname(entry.name).toLowerCase();
      if (CANDIDATE_EXTENSIONS.has(extension)) {
        output.push(fullPath);
      }
    }
  }

  for (const scanDirectory of SCAN_DIRECTORIES) {
    await walk(path.join(rootDir, scanDirectory));
  }

  return output;
}

/**
 * @param {string[]} filePaths
 * @param {string} rootDir
 * @returns {Promise<string[]>}
 */
async function findMissingRTGConfigFiles(filePaths, rootDir) {
  const missingConfigFiles = [];

  for (const filePath of filePaths) {
    const source = await readFile(filePath, 'utf8');
    const hasEndpointMarker = ENDPOINT_MARKERS.some((pattern) => pattern.test(source));

    if (!hasEndpointMarker) {
      continue;
    }

    if (!RTG_CONFIG_PATTERN.test(source)) {
      missingConfigFiles.push(path.relative(rootDir, filePath));
    }
  }

  return missingConfigFiles.sort();
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

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const candidateFiles = await collectCandidateFiles(repoRoot);
const filesMissingRTGConfig = await findMissingRTGConfigFiles(candidateFiles, repoRoot);

if (filesMissingRTGConfig.length > 0) {
  console.warn('RTG page contract warning summary:');
  filesMissingRTGConfig.forEach((filePath) => {
    console.warn(`- Endpoint markers found without window.RTG_CONFIG: ${filePath}`);
  });

  errors.push(
    `${filesMissingRTGConfig.length} candidate file(s) reference RT Gate endpoints without window.RTG_CONFIG. Add window.RTG_CONFIG or remove endpoint usage markers.`,
  );
}

if (errors.length > 0) {
  console.error('RTG page config validation failed:');
  errors.forEach((message) => console.error(`- ${message}`));
  process.exit(1);
}

console.log(`RTG page config validation passed (${pageGatingManifest.length} entries).`);
