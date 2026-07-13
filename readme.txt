=== CF7 Database & Google Sheets ===
Contributors: arphost
Tags: contact form 7, database, google sheets, submissions, export
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Save Contact Form 7 submissions to your WordPress database and forward them to Google Sheets via a simple Apps Script webhook.

== Description ==

CF7 Database & Google Sheets captures every Contact Form 7 submission and:

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

1. Upload the `cf7-db-gsheets` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload.
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

== Changelog ==

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
