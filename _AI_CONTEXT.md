# Real Treasury Gate (rt-gate) - AI Context Document

You are acting as a senior WordPress.com plugin engineer and AI-documentation architect. This document is your single source of truth for the `rt-gate` repository.

## CONTEXT (IMPORTANT)
This repository will be deployed automatically to WordPress.com using WordPress.com GitHub Deployments, exactly like the existing “treasury-tech-portal” plugin.

**Key facts:**
- Repo root == plugin root
- NO `wp-content/` folder in the repo
- WordPress.com deploys the repo into the correct plugin location automatically
- Repo includes a `.wordpress-com/` directory for deployment metadata
- Deployment occurs on merge to `main`

## PRIMARY GOAL
Build a WordPress.com-deployable plugin that provides admin-managed gated content with:
- multiple forms
- multiple assets (downloads, videos, links)
- flexible mapping so different forms gate different assets
- token-based access for iframe-hosted gated pages
- event tracking (downloads + video engagement)
- Salesforce integration (MVP via webhook; OAuth scaffolded)

## SECONDARY GOAL (CRITICAL)
Construct the repository with excellent AI-first documentation so future AI coding agents (Claude, Codex, etc.) can safely extend the plugin without breaking architecture or security.

---

## REPO STRUCTURE
Repo name: `rt-gate`

```text
rt-gate/
├─ .github/workflows/
├─ .wordpress-com/               (REQUIRED for WP.com deployments)
├─ assets/
│  ├─ css/
│  └─ js/
├─ docs/
│  ├─ 00-START-HERE.md
│  ├─ 01-ARCHITECTURE.md
│  ├─ 02-DATA-MODEL.md
│  ├─ 03-REST-API.md
│  ├─ 04-ADMIN-UI.md
│  ├─ 05-SECURITY.md
│  ├─ 06-SALESFORCE.md
│  ├─ 07-DEPLOYMENT-WP-COM.md
│  ├─ 08-TESTING.md
│  ├─ 09-EXTENDING.md
│  ├─ 10-TROUBLESHOOTING.md
│  └─ adr/
│     ├─ 0001-token-model.md
│     ├─ 0002-db-schema.md
│     ├─ 0003-rest-cors.md
│     └─ 0004-salesforce-webhook-first.md
├─ examples/
│  └─ gate.js
├─ includes/
│  ├─ class-db.php
│  ├─ class-admin.php
│  ├─ class-events-table.php
│  ├─ class-rest.php
│  ├─ class-token.php
│  ├─ class-events.php
│  ├─ class-webhook.php
│  ├─ class-salesforce.php   (OAuth scaffold only)
│  └─ class-utils.php
├─ languages/
├─ templates/
├─ tests/
├─ AGENTS.md                 (AI contribution rules)
├─ CHANGELOG.md
├─ readme.txt                (WordPress plugin readme)
└─ rt-gate.php               (main plugin bootstrap)
CORE FUNCTIONAL REQUIREMENTS
ADMIN UI (WP Admin → “Real Treasury Gate”)
A) Forms

Create/Edit forms

Email required

Arbitrary field schema stored as JSON

Consent text (versioned); store consent timestamp + hash

Shortcode: [rtg_form id="X"]

B) Assets / Resources

Create/Edit assets

Types: download (file_url), video (provider, video_id or embed_url), link (target_url)

Unique slug per asset

C) Form → Asset Mapping (CORE REQUIREMENT)

One form can map to one or many assets

Each mapping stores: form_id, asset_id, iframe_src_template
(Example template: https://<github-pages>/gate/{asset_slug}?t={token})

Token is always issued PER ASSET

D) Events

Admin table (WP_List_Table)

Filters: form, asset, email, event type, date range

Export CSV

DATABASE TABLES (Prefix: $wpdb->prefix . 'rtg_')
Must be installed via dbDelta using $wpdb->get_charset_collate().

forms: id (bigint), name (varchar), fields_schema (json), consent_text (text), created_at (datetime)

assets: id (bigint), name (varchar), slug (varchar, unique), type (varchar), config (json), created_at (datetime)

mappings: id (bigint), form_id (bigint), asset_id (bigint), iframe_src_template (text), created_at (datetime)

leads: id (bigint), email (varchar, unique), form_data (json), ip_hash (varchar), ua_hash (varchar), created_at (datetime)

tokens: id (bigint), lead_id (bigint), asset_id (bigint), token_hash (varchar 64), expires_at (datetime), created_at (datetime)

events: id (bigint), lead_id (bigint), form_id (bigint), asset_id (bigint), event_type (varchar), meta (json), created_at (datetime)

REST API (Namespace: /wp-json/rtg/v1)
POST /submit

Input: form_id, fields (email required), consent=true

Behavior: Validate fields, upsert lead by email (hash IP/UA), look up mapped assets, issue token(s), record form_submit event.

Output: { "primary_redirect_url": "...", "assets": [{ "slug": "...", "redirect_url": "...", "expires_at": "..." }] }

POST /validate

Input: token, asset_slug

Output: { "valid": true/false, "asset": { "type": "...", "config": {} }, "expires_at": "..." }

Constraint: NEVER return lead PII.

POST /event

Input: token, asset_slug, event_type, meta

Events: page_view, download_click, video_play, video_progress (25/50/75/90/100)

API Security Rules:

32-byte random token, stored ONLY as a sha256 hash.

Enforce token expiry.

Transient-based rate limiting.

CORS allowlist for github.io domains via rest_pre_serve_request.

SALESFORCE INTEGRATION (MVP)
Webhook-based integration (Non-blocking requests using wp_remote_post).

Admin settings: webhook_url, webhook_secret.

Fire webhook on: form_submit, asset_access, asset_event.

Scaffold class-salesforce.php (OAuth classes/methods) but leave bodies as // TODO.

AI-FIRST DOCUMENTATION REQUIREMENTS
docs/00-START-HERE.md must include: “If you are an AI agent, read this first” and define invariants.

AGENTS.md: Workflow rules (e.g., must run PHPCS/PHPUnit).

docs/09-EXTENDING.md: Concrete WP filter examples for adding new asset types.

ADRs must explain WHY decisions were made.

Inline PHP docblocks for all public methods.

PLACEHOLDERS
WP_SITE_URL = https://realtreasury.com

GITHUB_GATE_ORIGIN = https://<github-user>.github.io
