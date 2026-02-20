/*
 * Example external frontend integration for Real Treasury Gate.
 *
 * Demonstrates:
 * - Calling /submit
 * - Reading token from URL
 * - Calling /validate
 * - Safely loading iframe
 * - Sending /event telemetry (including video progress)
 */
(function () {
  'use strict';

  const WP_SITE_URL = 'https://realtreasury.com';
  const API_BASE = `${WP_SITE_URL}/wp-json/rtg/v1`;

  /**
   * Safely parse JSON responses and throw on non-2xx.
   */
  async function parseJsonOrThrow(response) {
    const text = await response.text();
    let json = {};

    try {
      json = text ? JSON.parse(text) : {};
    } catch (err) {
      throw new Error(`Invalid JSON response (${response.status})`);
    }

    if (!response.ok) {
      const message = json.message || `Request failed (${response.status})`;
      throw new Error(message);
    }

    return json;
  }

  async function submitGateForm(formId, fields) {
    try {
      const response = await fetch(`${API_BASE}/submit`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          form_id: formId,
          fields,
          consent: true,
        }),
      });

      const data = await parseJsonOrThrow(response);
      console.log('Submit success:', data);
      return data;
    } catch (error) {
      console.error('Submit failed:', error);
      throw error;
    }
  }

  function getTokenFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('t') || '';
  }

  function safeIframeSrc(src) {
    try {
      const url = new URL(src, window.location.origin);
      if (!['https:'].includes(url.protocol)) {
        throw new Error('Only HTTPS iframe sources are allowed');
      }
      return url.toString();
    } catch (error) {
      console.error('Unsafe iframe src:', error);
      return null;
    }
  }

  async function validateToken(token, assetSlug) {
    try {
      const response = await fetch(`${API_BASE}/validate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, asset_slug: assetSlug }),
      });

      return await parseJsonOrThrow(response);
    } catch (error) {
      console.error('Validation failed:', error);
      throw error;
    }
  }

  async function sendEvent(token, assetSlug, eventType, meta = {}) {
    try {
      const response = await fetch(`${API_BASE}/event`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token,
          asset_slug: assetSlug,
          event_type: eventType,
          meta,
        }),
      });

      const result = await parseJsonOrThrow(response);
      console.log('Event recorded:', eventType, result);
      return result;
    } catch (error) {
      console.error(`Event failed (${eventType}):`, error);
      return null;
    }
  }

  async function loadGatedAssetIntoIframe(options) {
    const { token, assetSlug, iframeSelector } = options;

    try {
      const validate = await validateToken(token, assetSlug);
      if (!validate.valid || !validate.asset) {
        throw new Error('Token not valid for this asset');
      }

      const iframe = document.querySelector(iframeSelector);
      if (!iframe) {
        throw new Error(`Iframe not found: ${iframeSelector}`);
      }

      let srcCandidate = '';
      const asset = validate.asset;

      if (asset.type === 'video' && asset.config && asset.config.embed_url) {
        srcCandidate = asset.config.embed_url;
      } else if (asset.type === 'link' && asset.config && asset.config.target_url) {
        srcCandidate = asset.config.target_url;
      } else if (asset.type === 'download' && asset.config && asset.config.file_url) {
        srcCandidate = asset.config.file_url;
      }

      const src = safeIframeSrc(srcCandidate);
      if (!src) {
        throw new Error('No safe iframe src could be derived from asset config');
      }

      iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-downloads');
      iframe.setAttribute('referrerpolicy', 'no-referrer');
      iframe.src = src;

      await sendEvent(token, assetSlug, 'page_view', { source: 'gate.js' });
      return validate;
    } catch (error) {
      console.error('Failed to load gated asset:', error);
      throw error;
    }
  }

  /**
   * Video progress ping helper.
   * Call this from your video player integration when milestones are crossed.
   */
  async function pingVideoProgress(token, assetSlug, progressPercent) {
    try {
      const allowed = [25, 50, 75, 90, 100];
      if (!allowed.includes(progressPercent)) {
        throw new Error(`Unsupported progress milestone: ${progressPercent}`);
      }

      await sendEvent(token, assetSlug, 'video_progress', {
        progress: progressPercent,
      });
    } catch (error) {
      console.error('Video progress event failed:', error);
    }
  }

  // Expose helpers for implementation usage.
  window.RTGExampleGate = {
    submitGateForm,
    getTokenFromUrl,
    loadGatedAssetIntoIframe,
    sendEvent,
    pingVideoProgress,
  };
})();
