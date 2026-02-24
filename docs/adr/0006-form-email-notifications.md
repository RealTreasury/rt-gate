# ADR 0006: Per-form email notifications on submission

- Status: Accepted
- Date: 2026-02-24

## Context

After a form submission the only way to know a lead came in was to log into WP Admin and check the Leads page, or rely on an external webhook consumer. The business needs immediate visibility into new leads and wants leads to receive a confirmation email. Different forms have different purposes (webinar sign-up vs. download request), so notification behavior must be configurable per form.

## Decision

Add two optional email notifications triggered after `POST /submit`, both configured per form:

1. **Lead email** — sent to the submitter's email address. Three modes per form:
   - `none` (default) — no email sent.
   - `confirmation_only` — thank-you message with form/site name.
   - `confirmation_and_links` — thank-you message plus gated asset redirect URLs and expiry times.

2. **Internal admin notification** — sent to a configurable list of email addresses (falls back to WP admin email). Contains all submitted fields, form name/ID, timestamp, and gated asset details with a link to the Leads admin page.
   - Uses a branded sender header: `Online Form Submission <wordpress@realtreasury.com>`.
   - Subject uses lead identity (first + last name and company when available), with email only as a fallback.

### Implementation details

- New `RTG_Email` class in `includes/class-email.php` following the existing class-per-concern pattern.
- Per-form settings stored as a JSON `email_settings` column on `rtg_forms` (added via `dbDelta`).
- Emails sent via `wp_mail()` with HTML content type, compatible with WordPress.com hosting.
- Email dispatch called from `RTG_REST::handle_submit()` after event logging and webhook dispatch.

## Consequences

### Positive

- Admins get instant email notification of new leads without checking WP Admin.
- Leads receive professional confirmation emails, improving trust and engagement.
- Per-form configuration allows different email strategies for different content gates.

### Tradeoffs

- `wp_mail()` is synchronous, adding a small amount of latency to the API response. Acceptable for transactional email volumes.
- Email deliverability depends on the hosting environment's mail configuration (WordPress.com provides built-in email delivery).

## Alternatives considered

1. Global email settings (single configuration for all forms).
   - Rejected because different forms serve different purposes and need different notification behavior.
2. Async email via WP-Cron scheduled task.
   - Rejected for simplicity; transactional email volume is low and `wp_mail()` latency is acceptable.
