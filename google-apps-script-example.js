/**
 * UNIVERSAL FORMS → SHEET SCRIPT (v5)
 * Works with ANY Contact Form 7 form, automatically. No configuration.
 *
 * SETUP (2 minutes, no spreadsheet ID needed):
 * 1. Open your Google Sheet.
 * 2. Extensions → Apps Script (this creates a script INSIDE the sheet).
 * 3. Delete the sample code, paste this whole file, click Save.
 * 4. Deploy → New deployment → gear icon → Web app:
 *      Execute as: Me
 *      Who has access: Anyone        ← must be "Anyone"
 *    Click Deploy, authorize with your Google account when asked.
 * 5. Copy the Web app URL (ends in /exec) into
 *    WP Admin → CF7 Submissions → Settings → Webhook URL.
 *
 * HOW IT WORKS:
 * - Each form gets its own tab, named after the form, created automatically.
 * - Columns are created from the form's fields; new fields = new columns.
 * - Add a new form in WordPress → a new tab appears. Nothing to edit here.
 * - Any error is sent back to WordPress and shown in the submission's
 *   "Sheets response" — no need to dig through script logs.
 *
 * EDITING LATER: Deploy → Manage deployments → pencil ✏ →
 * Version: "New version" → Deploy. (Never "New deployment" — that makes a
 * NEW URL and WordPress keeps posting to the old one.)
 */

function doPost(e) {
    try {
        const data = JSON.parse(e.postData.contents);

        // The spreadsheet this script is attached to — no ID needed.
        const ss = SpreadsheetApp.getActiveSpreadsheet();
        if (!ss) {
            throw new Error('Not attached to a spreadsheet. Create this script via Extensions > Apps Script inside your Google Sheet.');
        }

        // One tab per form, named after the form title.
        const tabName = text(data.formTitle) || 'Submissions';
        const sheet = ss.getSheetByName(tabName) || ss.insertSheet(tabName);

        // Field keys (skip plugin metadata).
        const keys = Object.keys(data).filter(function (k) {
            return k !== 'formTitle' && k !== 'formId';
        });

        // Header row: create on first submission, extend when fields appear.
        let headers;
        if (sheet.getLastRow() === 0) {
            headers = ['Timestamp'].concat(keys);
            sheet.appendRow(headers);
            sheet.setFrozenRows(1);
        } else {
            headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0].map(text);
            const newKeys = keys.filter(function (k) { return headers.indexOf(k) === -1; });
            if (newKeys.length) {
                sheet.getRange(1, headers.length + 1, 1, newKeys.length).setValues([newKeys]);
                headers = headers.concat(newKeys);
            }
        }

        // Append the row, aligned to headers.
        sheet.appendRow(headers.map(function (h) {
            return h === 'Timestamp' ? new Date() : text(data[h]);
        }));

        return out({ success: true, message: 'Saved to tab "' + tabName + '"' });
    } catch (err) {
        // Full error goes back to WordPress — visible in "Sheets response".
        return out({ success: false, error: String(err) });
    }
}

/** Any value → clean cell text (arrays joined, null → ''). */
function text(v) {
    if (v === undefined || v === null) return '';
    if (Array.isArray(v)) return v.join(', ');
    return String(v);
}

function out(obj) {
    return ContentService.createTextOutput(JSON.stringify(obj))
        .setMimeType(ContentService.MimeType.JSON);
}

/** Health check: open the /exec URL in a browser, should show version 5. */
function doGet() {
    return out({ status: 'ok', version: 5 });
}
