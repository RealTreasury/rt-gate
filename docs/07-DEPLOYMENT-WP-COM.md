# Deployment on WordPress.com

This repository is deployed via a **main-branch GitHub Actions trigger** plus WordPress.com deployment metadata.

## Deployment trigger

Workflow file:
- `.github/workflows/deploy-wpcom.yml`

Trigger:
- `on: push` to branch `main`

Implication:
- Merging a PR into `main` starts the deployment workflow automatically.

## Workflow steps

Job: `wpcom-sync` (Ubuntu)

1. **Checkout repository**
   - Uses `actions/checkout@v4`
2. **Validate metadata directory**
   - Ensures `.wordpress-com/` exists
3. **Trigger WordPress.com sync**
   - Reads secret `WPCOM_DEPLOY_WEBHOOK_URL`
   - Sends POST payload: `{"ref":"main","repository":"rt-gate"}`
   - Fails workflow if secret is missing or webhook call fails

## `.wordpress-com/` directory role

Path:
- `.wordpress-com/`

Purpose:
- Repository-level marker/metadata required by the WordPress.com GitHub deployment model used by this project.
- Presence is explicitly validated during CI deployment.

## Required GitHub secret

Repository secret:
- `WPCOM_DEPLOY_WEBHOOK_URL`

Without this secret:
- Deployment workflow exits with error and no sync is triggered.

## Operational flow summary

1. PR merged into `main`
2. GitHub Action `Deploy to WordPress.com` runs
3. Action verifies `.wordpress-com/` exists
4. Action POSTs to WordPress.com deployment webhook
5. WordPress.com syncs plugin from repository root

## Repository layout assumption

The plugin is deployed from repository root (not a nested `wp-content/plugins/...` layout), consistent with `_AI_CONTEXT.md` and `docs/00-START-HERE.md` invariants.
