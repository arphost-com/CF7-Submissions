=== ARPHost CF7 Submission Archive ===
Contributors: hostalot
Tags: contact form 7, database, google sheets, submissions, export
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: contact-form-7
Stable tag: 1.2.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Save Contact Form 7 submissions to your WordPress database and forward them to Google Sheets via a simple Apps Script webhook.

== Description ==

ARPHost CF7 Submission Archive captures every Contact Form 7 submission and:

* Stores it in a dedicated database table (survives mail delivery failures — capture happens before mail is sent)
* Optionally forwards it as JSON to a Google Apps Script Web App, which appends it to a Google Sheet
* Provides an admin browser (filter by form, search, view detail, delete, resend to Sheets)
* Exports submissions to CSV
* Tracks per-submission Google Sheets delivery status, with one-click resend

No Google API keys, OAuth, or service accounts required — the Sheets integration uses a free Google Apps Script Web App you deploy in about two minutes (companion script included in `google-apps-script-example.js`).

= Privacy =

IP address and user-agent storage are **off by default**. Enable them in Settings only if your privacy policy covers it.

= Developer hooks =

* `cf7dbgs_capture_submission` — filter; return false to skip capturing a submission
* `cf7dbgs_store_fields` — filter fields before DB storage
* `cf7dbgs_webhook_payload` — filter the JSON payload sent to the webhook
* `cf7dbgs_webhook_args` — filter wp_remote_post args
* `cf7dbgs_after_store` — action fired after a row is stored

== Installation ==

1. Upload the `arphost-cf7-submission-archive` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload.
2. Activate the plugin. Contact Form 7 must be active.
3. Submissions are stored in the database immediately. Find them under **CF7 Submissions** in the admin menu.

= Google Sheets setup — API mode, no Apps Script (recommended) =

1. Go to https://console.cloud.google.com → create (or pick) a project → APIs & Services → enable the **Google Sheets API**.
2. IAM & Admin → Service Accounts → Create service account (any name, no roles needed) → Keys → Add key → JSON. A .json file downloads.
3. In WordPress: **CF7 Submissions → Settings** — choose *Google Sheets API*, paste the JSON file's contents into *Service account JSON*, and put your spreadsheet's ID in *Spreadsheet ID*.
4. Open the Google Sheet → Share → add the service account's email (shown on the settings page after saving) as **Editor**.

That's it. Each form gets its own tab automatically. Use *Per-form routing* to rename tabs or send a form to a different spreadsheet (`Form Title=Tab` or `Form Title=SPREADSHEET_ID!Tab`).

= Google Sheets setup — webhook mode (Apps Script) =

1. Create a Google Sheet with a tab named `Submissions`.
2. Go to script.google.com, create a project, and paste in `google-apps-script-example.js` (bundled with this plugin). Set your `SHEET_ID`.
3. Deploy → New deployment → Web app → Execute as *you*, access *Anyone*. Copy the deployment URL.
4. In WordPress: **CF7 Submissions → Settings** — enable *Send to Google Sheets* and paste the URL.
5. Optionally add a field mapping so your CF7 field names become friendlier payload keys. Matching is forgiving: case-insensitive, and spaces/underscores count as hyphens — so `First Name` matches the CF7 field `first-name`:

    First Name=firstName
    Last Name=lastName
    your-email=email
    Volunteer=volunteers
    Yard Sign=yardSign

The left side must still correspond to the CF7 field *name* (the name inside the form tag, e.g. `[email* your-email]` is `your-email`), not the visible label.

Checkbox fields with multiple selections are sent as JSON arrays; single-value fields are sent as plain strings.

= Adding a new form later =

Nothing special is required. Every payload carries `formTitle`/`formId`, and the companion Apps Script creates a new sheet tab (named after the form) with columns built from the fields automatically. Optionally add field-map lines if you want friendlier payload keys — the Settings page lists each form's detected fields.

== Frequently Asked Questions ==

= Does it capture submissions if the email fails to send? =

Yes. Capture hooks `wpcf7_before_send_mail`, so the submission is saved even if SMTP delivery fails.

= Which forms are captured? =

All CF7 forms. Use the `cf7dbgs_capture_submission` filter to exclude specific forms.

= Are file uploads stored? =

Only the posted field values are stored; uploaded files are handled by CF7 as usual and are not copied.

== External services ==

This plugin connects to Google services to forward Contact Form 7 submissions to a Google Sheet. Both connections are **off by default** (Settings → *Send to Google Sheets*) and use *your own* Google account/spreadsheet — no data passes through any ARPHost server.

= Google Sheets API (recommended mode) =

When *Delivery method* is set to *Google Sheets API*, the plugin sends a submission's mapped field values directly to the Google Sheets API (`sheets.googleapis.com`) using a Google Cloud service account you create and paste into Settings. Before that, it exchanges the service account's key for a short-lived OAuth2 access token via Google's token endpoint (`oauth2.googleapis.com`).

* What is sent: the submission's mapped field values (plus `formTitle`/`formId`), sent as a new row appended to the spreadsheet/tab you configured. Anti-spam/captcha tokens (reCAPTCHA, hCaptcha, Turnstile) are stripped before sending. For authentication, a signed JWT derived from your own service-account credentials is sent to obtain the access token — no data is sent to any third party you did not configure.
* When it's sent: immediately after a matching Contact Form 7 submission (if enabled), or when an admin manually clicks "Resend" on a stored submission.
* Service: Google Sheets API / Google Cloud Platform, operated by Google LLC. [Terms of Service](https://developers.google.com/terms) · [Google Privacy Policy](https://policies.google.com/privacy)

= Google Apps Script webhook (alternate mode) =

When *Delivery method* is set to *Webhook*, the same submission payload (JSON) is POSTed instead to a Google Apps Script Web App URL that you deploy yourself from `google-apps-script-example.js` (bundled with this plugin). That script runs on Google's infrastructure (`script.google.com` / `script.googleusercontent.com`) under your own Google account and appends the row to your sheet.

* What is sent: the same mapped field values described above (`formTitle`/`formId` plus form fields, captcha tokens stripped), sent as the POST body.
* When it's sent: immediately after a matching submission (if enabled), or on manual "Resend".
* Service: Google Apps Script, operated by Google LLC. [Terms of Service](https://developers.google.com/terms) · [Google Privacy Policy](https://policies.google.com/privacy)

== Changelog ==

= 1.2.1 =
* Moved the field-mapping helper script out of an inline `<script>` tag (admin/class-cf7dbgs-admin.php) into `admin/js/cf7dbgs-field-mapping.js`, enqueued via `wp_enqueue_script()` on the Settings page only. No functional change.
* Docs: added the "External services" section describing the Google Sheets API / OAuth2 and Google Apps Script webhook connections, per WordPress.org plugin review feedback.

= 1.2.0 =
* Renamed plugin to "ARPHost CF7 Submission Archive" (slug `arphost-cf7-submission-archive`, text domain to match) per WordPress.org plugin review feedback — the prior name/slug led with the Contact Form 7 ("CF7") trademark and was too close to other CF7/Sheets integration plugin names. No functional changes. Contributors line corrected to the actual WordPress.org account (`hostalot`). Added the `Requires Plugins: contact-form-7` header.

= 1.1.9 =
* Fix: text domain reverted (again) to `cf7-database-google-sheets` — this is the canonical slug going forward (matches the plugin's readable name and is what WordPress Playground / Plugin Check expects). The plugin's packaged folder and zip are now also named `cf7-database-google-sheets` to match, so the slug is consistent everywhere. The GitLab project/repo itself stays named `cf7-db-gsheets` — only the WordPress-facing plugin slug changed.

= 1.1.8 =
* Fix: text domain reverted to `cf7-db-gsheets` (matches the actual plugin slug at the time) — resolved Plugin Check errors on dev.arphost.com, but broke it on WordPress Playground/Plugin Check (which expects `cf7-database-google-sheets`). Superseded by 1.1.9.

= 1.1.7 =
* Annotated the whitelisted ORDER BY columns for Plugin Check (documented false positive; $orderby is limited to four hardcoded column names).

= 1.1.6 =
* Text domain renamed to cf7-database-google-sheets to match the WordPress.org slug.
* Submissions query rewritten with fully literal prepared statements (resolves all Plugin Check security warnings).

= 1.1.5 =
* Plugin Check compliance: table names now use the %i identifier placeholder in all queries (requires WordPress 6.2+), removed the deprecated load_plugin_textdomain() call, and annotated intentional custom-table queries.

= 1.1.4 =
* Improvement: sheet timestamps now include the timezone. Apps Script writes Mountain Time via a TIME_ZONE constant (e.g. "2026-07-13 08:43:21 MDT"); API mode uses the WordPress timezone setting with zone label.

= 1.1.3 =
* Improvement: bundled Apps Script rewritten as a universal zero-config version — create it inside the sheet (Extensions > Apps Script), no spreadsheet ID, works with any form automatically, and reports full errors back to WordPress.

= 1.1.2 =
* Improvement: the bundled Apps Script now reports the real sheet-write error to WordPress (e.g. bad spreadsheet ID) instead of a generic "Failed to save to sheet".

= 1.1.1 =
* Fix: Cloudflare Turnstile, hCaptcha, and other captcha tokens are no longer stored or sent to Sheets (previously only reCAPTCHA was excluded).

= 1.1.0 =
* Feature: direct Google Sheets API mode — no Apps Script required. Paste a service-account JSON key, share the sheet with the service account, done. Auto-creates one tab per form, manages columns, supports per-form routing to custom tabs or entirely different spreadsheets from the Settings screen. Webhook mode remains available.

= 1.0.8 =
* Docs: bundled Apps Script example is now a multi-form/multi-sheet router — per-form tabs (auto-created) plus optional ROUTES config to send any form to a custom tab name or a completely different spreadsheet.

= 1.0.7 =
* UI: the submissions list and detail views now show human-friendly field labels ("First Name", "Email", "Phone") instead of raw CF7 field names ("first-name", "your-email", "tel-269"). Uses your field map, then humanizes.

= 1.0.6 =
* Feature: "Auto-map" button per form in Settings — reads the form's fields from Contact Form 7 and fills the mapping automatically (email fields → email, tel fields → phone, "your-" prefixes stripped, names camelCased). Fields already mapped or needing no rename are skipped.

= 1.0.5 =
* Feature: every webhook payload now includes formTitle and formId, so one webhook can serve many forms (e.g. one Google Sheet tab per form). Resends include them too.

= 1.0.4 =
* Feature: forgiving field-map matching — "First Name" now matches the CF7 field "first-name" (case-insensitive; spaces and underscores count as hyphens).
* Feature: the Settings page now lists every Contact Form 7 form's detected field names; click a field to add it to the mapping box.

= 1.0.3 =
* Fix: single-value arrays from CF7 select fields (e.g. state) are flattened to scalars in the webhook payload — Apps Script appendRow() fails silently on array values.

= 1.0.2 =
* Fix: Google rejected webhook posts with HTTP 400 — WP re-POSTs to the Apps Script 302 redirect target, which only accepts GET. The redirect is now followed manually with GET.

= 1.0.1 =
* Fix: Apps Script webhooks that return HTTP 200 with `{"success":false}` in the body are now correctly recorded as failed (previously marked "sent").
* Fix: webhook timeout raised 8s → 15s to survive Apps Script cold starts.
* Docs: full field-map example (city/state/comments) matching scripts that validate required fields.

= 1.0.0 =
* Initial release: DB storage, Google Sheets webhook forwarding, admin browser, CSV export, resend, privacy-safe defaults.
