/**
 * Companion Google Apps Script for the CF7 Database & Google Sheets plugin.
 * Multi-form / multi-sheet router.
 *
 * SETUP:
 * 1. Create a Google Sheet and copy its ID from the URL.
 * 2. At https://script.google.com create a project and paste this file in.
 * 3. Set DEFAULT_SHEET_ID below.
 * 4. Deploy > New deployment > Web app:
 *    - Execute as: Me
 *    - Who has access: Anyone
 * 5. Copy the deployment URL into the plugin settings
 *    (WP Admin > CF7 Submissions > Settings > Webhook URL).
 *
 * ROUTING:
 * The plugin (v1.0.5+) sends formTitle with every submission. Each form
 * gets its own tab, named after the form and created automatically, with
 * columns built from the submission's fields. To customize, add entries
 * to ROUTES — per-form tab names and/or entirely different spreadsheets.
 * Adding a new CF7 form in WordPress needs NO changes here.
 *
 * UPDATING LATER: Deploy > Manage deployments > Edit (pencil) >
 * Version: "New version" > Deploy. (A "New deployment" would change the
 * URL — WordPress would keep posting to the old code.)
 */

// CONFIGURATION
const DEFAULT_SHEET_ID = 'PASTE_YOUR_SHEET_ID_HERE';

// Optional: email each submission somewhere. Leave blank to disable.
const RECIPIENT_EMAIL = '';

/**
 * ROUTES — where each form's submissions go. Key = CF7 form title.
 *   tab:     tab name (created automatically if missing)
 *   sheetId: a DIFFERENT spreadsheet's ID. Omit to use DEFAULT_SHEET_ID.
 *            The script's account must have edit access to that sheet.
 * Forms not listed here: DEFAULT_SHEET_ID, tab named after the form.
 *
 * Examples:
 *   'Contact Us':        { tab: 'Submissions' },
 *   'Yard Sign Request': { tab: 'Yard Signs', sheetId: '1AbC...xyz' },
 */
const ROUTES = {};

// Payload keys that should not become sheet columns.
const META_KEYS = ['formTitle', 'formId'];

function asText(v) {
    if (v === undefined || v === null) return '';
    if (Array.isArray(v)) return v.join(', ');
    return String(v);
}

function doPost(e) {
    try {
        const data = JSON.parse(e.postData.contents);
        const formTitle = asText(data.formTitle) || 'Submissions';

        const saveSuccess = saveToSheet(formTitle, data);
        const emailSuccess = RECIPIENT_EMAIL ? sendEmailNotification(formTitle, data) : true;

        if (!saveSuccess) {
            return jsonOut({ success: false, error: 'Failed to save to sheet' });
        }
        return jsonOut({ success: true, message: 'Thank you for your submission!' });
    } catch (error) {
        console.error('Error processing form:', error);
        return jsonOut({ success: false, error: error.toString() });
    }
}

function jsonOut(obj) {
    return ContentService.createTextOutput(JSON.stringify(obj))
        .setMimeType(ContentService.MimeType.JSON);
}

/** Get (or create) the tab for a form, honoring ROUTES overrides. */
function sheetFor(formTitle) {
    const route = ROUTES[formTitle] || {};
    const ss = SpreadsheetApp.openById(route.sheetId || DEFAULT_SHEET_ID);
    const name = route.tab || formTitle;
    let sheet = ss.getSheetByName(name);
    if (!sheet) {
        sheet = ss.insertSheet(name);
    }
    return sheet;
}

/** Append a submission; headers/columns are managed automatically. */
function saveToSheet(formTitle, data) {
    try {
        const sheet = sheetFor(formTitle);
        const keys = Object.keys(data).filter(function (k) { return META_KEYS.indexOf(k) === -1; });

        let headers;
        if (sheet.getLastRow() === 0) {
            headers = ['Timestamp'].concat(keys);
            sheet.appendRow(headers);
            sheet.setFrozenRows(1);
        } else {
            headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0].map(asText);
        }

        const newKeys = keys.filter(function (k) { return headers.indexOf(k) === -1; });
        if (newKeys.length) {
            sheet.getRange(1, headers.length + 1, 1, newKeys.length).setValues([newKeys]);
            headers = headers.concat(newKeys);
        }

        const row = headers.map(function (h) {
            if (h === 'Timestamp') return new Date();
            return asText(data[h]);
        });
        sheet.appendRow(row);
        return true;
    } catch (error) {
        console.error('Error saving to sheet:', error);
        return false;
    }
}

function sendEmailNotification(formTitle, data) {
    try {
        let body = '==== NEW SUBMISSION: ' + formTitle + ' ====\n\n';
        Object.keys(data).forEach(function (k) {
            if (META_KEYS.indexOf(k) !== -1) return;
            body += k + ': ' + (asText(data[k]) || '(empty)') + '\n';
        });
        GmailApp.sendEmail(RECIPIENT_EMAIL, 'New form submission: ' + formTitle, body);
        return true;
    } catch (error) {
        console.error('Error sending email:', error);
        return false;
    }
}

function doGet() {
    return jsonOut({ status: 'ok', router: true });
}
