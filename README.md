# CF7 Database & Google Sheets

Saves every Contact Form 7 submission to your WordPress database, and sends it to Google Sheets. Browse, search, export CSV, and resend from **WP Admin → CF7 Submissions**.

There are **two ways** to connect Google Sheets. Pick ONE (you can switch anytime in Settings → Delivery method):

| | Option A: Google Sheets API | Option B: Apps Script webhook |
|---|---|---|
| Google-side setup | One-time, in Google Cloud Console | Paste a script, deploy it |
| Ongoing maintenance | None | Redeploy the script when it changes |
| Where routing lives | WordPress settings | In the script code |
| Best for | Set it and forget it (recommended) | No Google Cloud account |

---

## Option A: Google Sheets API (recommended — no script)

**One-time Google setup (~5 minutes):**

1. Go to https://console.cloud.google.com and sign in.
2. Top bar → project dropdown → **New Project** → name it anything (e.g. "Website Forms") → Create.
3. Menu → **APIs & Services → Library** → search **Google Sheets API** → **Enable**.
4. Menu → **IAM & Admin → Service Accounts** → **Create Service Account** → name it (e.g. "wp-forms") → Create → skip the optional role screens → Done.
5. Click the account you just made → **Keys** tab → **Add Key → Create new key → JSON** → Create. A `.json` file downloads. Open it in a text editor.

**WordPress setup:**

6. **CF7 Submissions → Settings**:
   - Check **Send to Google Sheets**
   - Delivery method: **Google Sheets API**
   - **Service account JSON**: paste the entire contents of the downloaded file
   - **Spreadsheet ID**: the long code from your sheet's URL
     (`https://docs.google.com/spreadsheets/d/`**`THIS_PART`**`/edit`)
   - Save.
7. The settings page now shows an email like `wp-forms@your-project.iam.gserviceaccount.com`.
   Open your Google Sheet → **Share** → add that email as **Editor**.

Done. Each form writes to its own tab (named after the form, created automatically, columns built from the fields).

**Optional — per-form routing** (Settings → Per-form routing, one per line):

```
Contact Vic=Submissions                      ← custom tab name
Yard Sign Request=SPREADSHEET_ID!Yard Signs  ← a different spreadsheet
```

If a form uses a different spreadsheet, share that one with the service account too.

---

## Option B: Apps Script webhook

1. Create a Google Sheet. Copy its ID from the URL.
2. Go to https://script.google.com → **New project** → delete the sample code → paste in `google-apps-script-example.js` (bundled with this plugin).
3. Set `DEFAULT_SHEET_ID` at the top to your sheet's ID.
4. **Deploy → New deployment → Web app**:
   - Execute as: **Me**
   - Who has access: **Anyone**
   - Deploy, authorize when asked, copy the **Web app URL**.
5. **CF7 Submissions → Settings**:
   - Check **Send to Google Sheets**
   - Delivery method: **Webhook**
   - **Webhook URL**: paste the URL → Save.

Each form gets its own tab automatically. To customize tabs/spreadsheets, edit the `ROUTES` block at the top of the script.

> ⚠️ **Editing the script later?** Use **Deploy → Manage deployments → ✏️ → Version: "New version" → Deploy**.
> Do NOT use "New deployment" — that creates a NEW URL while WordPress keeps posting to the old one, and your changes silently never take effect.

---

## Field mapping (both options)

By default fields are sent under their CF7 names (`first-name`, `your-email`). To send prettier keys, use **Settings → Field mapping** — or just click **Auto-map** next to a form and save. Matching is forgiving: `First Name` matches `first-name`. Unmapped fields still get sent under their original names.

```
First Name=firstName
your-email=email
tel-269=phone
```

---

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| **failed — HTTP 401: Page Not Found / "Sorry, unable to open the file"** (webhook) | The deployment isn't publicly reachable. In Manage deployments set **Who has access: Anyone**, and make sure you copied the **/exec** URL from Manage deployments — not the editor's /dev test URL. Then update the URL in Settings and Resend. |
| **failed — Failed to save to sheet / Sheet write failed** (webhook) | Usually `DEFAULT_SHEET_ID` at the top of the script is unset or wrong — set it to the long ID from the spreadsheet URL and deploy a New version. Exact reason: script editor → View → Executions. |
| Sheets status: **failed — HTTP 400** (webhook) | Old plugin version. Update to 1.0.2+ (follows Google's redirect correctly). |
| **failed — Script error: Missing required fields** | The deployed script is an old version that validates fields the form doesn't have. Redeploy the bundled script (Manage deployments → New version). |
| Status says **sent** but nothing appears in the sheet | Old v1 scripts reported success even when the write failed. Redeploy the bundled script — it reports failures honestly. Then use **Resend** on the row. |
| **failed — Sheets API: ... did you share the spreadsheet ...** (API) | Share the spreadsheet with the service account email (Editor). Shown on the settings page. |
| **failed — Google rejected the service account** (API) | The JSON key is wrong/incomplete/revoked. Paste the whole downloaded file. |
| **failed — No webhook URL configured** | Delivery method is Webhook but the URL field is empty — or you meant to pick API mode. |
| Edited the Apps Script but behavior didn't change | You made a *New deployment* (new URL). Update the existing deployment instead, or paste the new URL into Settings. |
| Data lands in the wrong tab | Tab = form title by default. Override per form: Settings → Per-form routing (API) or `ROUTES` in the script (webhook). |
| Submission missing entirely | Check CF7 Submissions list — if it's there, the problem is delivery (see rows above). If not, the form never validated/submitted. |

Every submission is stored in WordPress **before** any Google delivery, so nothing is ever lost — fix the connection and hit **Resend**.
