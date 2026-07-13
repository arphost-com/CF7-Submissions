=== CF7 Database & Google Sheets ===
Contributors: arphost
Tags: contact form 7, database, google sheets, submissions, export
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.4
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

= Google Sheets setup (optional) =

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

== Frequently Asked Questions ==

= Does it capture submissions if the email fails to send? =

Yes. Capture hooks `wpcf7_before_send_mail`, so the submission is saved even if SMTP delivery fails.

= Which forms are captured? =

All CF7 forms. Use the `cf7dbgs_capture_submission` filter to exclude specific forms.

= Are file uploads stored? =

Only the posted field values are stored; uploaded files are handled by CF7 as usual and are not copied.

== Changelog ==

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
