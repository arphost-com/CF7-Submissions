/**
 * Companion Google Apps Script for the CF7 Database & Google Sheets plugin.
 *
 * SETUP:
 * 1. Create a Google Sheet with a tab named "Submissions".
 * 2. At https://script.google.com create a project and paste this file in.
 * 3. Set SHEET_ID below (from the sheet URL).
 * 4. Deploy > New deployment > Web app:
 *    - Execute as: Me
 *    - Who has access: Anyone
 * 5. Copy the deployment URL into the plugin settings
 *    (WP Admin > CF7 Submissions > Settings > Webhook URL).
 *
 * The plugin POSTs JSON. Keys depend on your CF7 field names and any
 * field mapping you configure. Arrays (checkboxes) arrive as JSON arrays.
 * This script writes a header row automatically from the keys of the
 * first submission it receives, then appends one row per submission.
 */

const SHEET_ID = 'PASTE_YOUR_SHEET_ID_HERE';
const SHEET_NAME = 'Submissions';

// Optional: also email each submission. Leave blank to disable.
const RECIPIENT_EMAIL = '';

function doPost(e) {
  try {
    const data = JSON.parse(e.postData.contents);
    const sheet = SpreadsheetApp.openById(SHEET_ID).getSheetByName(SHEET_NAME);
    if (!sheet) throw new Error('Sheet "' + SHEET_NAME + '" not found');

    // Establish headers from first submission.
    let headers = [];
    if (sheet.getLastRow() === 0) {
      headers = ['Timestamp'].concat(Object.keys(data));
      sheet.appendRow(headers);
    } else {
      headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    }

    // Add any new keys as new columns.
    const newKeys = Object.keys(data).filter(function (k) {
      return headers.indexOf(k) === -1;
    });
    if (newKeys.length) {
      sheet.getRange(1, headers.length + 1, 1, newKeys.length).setValues([newKeys]);
      headers = headers.concat(newKeys);
    }

    const row = headers.map(function (h) {
      if (h === 'Timestamp') return new Date();
      const v = data[h];
      if (v === undefined || v === null) return '';
      return Array.isArray(v) ? v.join(', ') : v;
    });
    sheet.appendRow(row);

    if (RECIPIENT_EMAIL) {
      GmailApp.sendEmail(
        RECIPIENT_EMAIL,
        'New form submission',
        JSON.stringify(data, null, 2)
      );
    }

    return ContentService.createTextOutput(
      JSON.stringify({ success: true })
    ).setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(
      JSON.stringify({ success: false, error: String(err) })
    ).setMimeType(ContentService.MimeType.JSON);
  }
}

function doGet() {
  return ContentService.createTextOutput(
    JSON.stringify({ status: 'ok' })
  ).setMimeType(ContentService.MimeType.JSON);
}
