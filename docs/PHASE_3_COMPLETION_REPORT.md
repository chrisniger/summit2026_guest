# Phase 3 — Guest Service capture and imports

Completed locally on 18 October 2026 in D:\Apps\summit2026_plus. Phases 1–2 are preserved. Phase 4 assignment/contact services and Phase 5 launch work remain open.

## Delivered behavior

- Guest entry, save-and-add-another with session retention, scoped paginated search/list/detail/edit, event-year/session/day/status/assignment/archive filters, and mobile layouts.
- Full name, phone and an authoritative session are required. Disabled, historical and ended sessions remain valid encounter selections. Capture timestamp is separate from the selected encounter and stored in UTC.
- Guest-only duplicate candidates require an explicit separate-encounter decision. Data Entry sees its own records/candidates, officers see active assigned records, and administrators see their permitted Guest Service scope.
- UUID submissions are actor scoped and replay once. Real simultaneous PHP connections were checked. Fresh locking reads after the event lock avoid MariaDB repeatable-read snapshots missing a just-committed replay.
- Versioned edits reject stale changes with 409. Profile requests cannot overwrite creator, provenance, current status, assignment or import references. Creation/corrections are audited without personal field values.
- Independent guest CSV and genuine XLSX import inspection, worksheet selection, event descriptor/session mapping, source timezone confirmation, dry-run preview, row review, explicit commit, 100-row database-queue chunks, progress, safe retry and CSV result downloads.
- Historical session configuration is Super Admin only. It accepts actual supplied dates/day/classification for years through 2025; it leaves times null and sessions disabled. It does not alter the eight 2026 sessions.
- Guest imports require explicit guest.import AND guest.create permissions. Super Admin can inspect all batches; other granted importers see only their own batches. Defaults were not broadened.
- Existing-encounter decisions retain that encounter's profile/status and append import provenance. They require authorization and the same session. Distinct encounters can remain separate even with identical names/phones.
- Formula/error values are quarantined. Mixed header order and documented aliases work. Empty worksheets are ignored. Source officer/status/comment text is preserved, reviewed and never treated as a new staff account, assignment or evidence of successful contact.
- Every newly imported guest starts NOT CONTACTED and unassigned. No Info Com lookup, matching, writes or attendance creation occurs.

## Implementation map

Routes are under /follow-up/guests and /follow-up/imports. Views are resources/views/guest. Navigation and dashboard actions link to the implemented screens.

- GuestController, GuestRequest, GuestCapture: capture, policies, filters, correction and idempotency.
- GuestImportController, GuestImport, GuestWorkbook: upload, parsing, mapping, review, configuration and results.
- GuestImportBatch, GuestImportRow and CommitGuestImport: separate guest provenance and queued processing.
- Migration 2026_10_18_191352_extend_guest_capture_and_imports: nullable unique source_fingerprint and version on guests; private path/progress/failure fields on batches; uniqueness of (batch, worksheet, row) on source rows. The replacement index is created before removing the old index to preserve its MariaDB foreign-key support.

The additive migration was applied successfully to summit_2026. Existing users, credentials, APP_KEY, data counts and eight sessions were compared before/after and preserved. Application counts at completion: one user, eight sessions, zero attendees, registration events, attendance and guests. Synthetic testing used summit_2026_test only.

## Import contract and limitations

CSV headers: Event Date, Event Session, Full Name, Phone, Gender, Home Address, Heard About, Comment. Column order may vary. Dates use YYYY-MM-DD; General/Morning/Evening (or AM/PM) must resolve to the explicitly selected session. Malformed row values remain invalid in the preview/results; correct them in a new upload.

Historical XLSX targets explicitly configured 2025 sessions. The original 2025 personal-data workbook was not supplied, so real OOXML synthetic workbooks cover its documented aliases, mixed order, sheets, repeated encounters, errors and contradictions. Actual workbook acceptance still needs an approved redacted sample.

No dependency was added. The bounded reader uses installed ZipArchive, DOM and libxml. It reads literal/shared/rich text and numeric cells and never evaluates formulas. It rejects malformed XML, entities/DTDs, macros, external links, embedded objects, invalid row references, unsupported headers and 1904-date workbooks. Required extensions: zip, dom, libxml, mbstring, fileinfo.

Limits: 5 MB upload, 30 MB expanded ZIP, 2,000 ZIP entries, 50 worksheets, 100 columns, 10,000 non-empty rows, 10,000 characters per cell, 200 worksheet/event descriptor mapping groups. Larger/more varied sources must be split. Password-protected XLSX, XLS and XLSM are not supported.

Originals are Laravel-encrypted on the private local disk. Header names are mapped through explicit aliases; unknown columns are retained as raw source metadata. Source local timestamps require an explicit Africa/Lagos or UTC choice. ISO timestamps with offsets, YYYY-MM-DD HH:MM[:SS], and standard Excel serial dates are supported; ambiguous slash dates, invalid dates and future capture times are quarantined.

Source replay identity includes worksheet, source row, resolved session and canonical header/value content. Reuploading that source skips already-created/matched rows even if CSV headers reorder. Repositioned/changed rows go through duplicate review rather than silently merging encounters. Cross-sheet repeats remain separate candidates for review. Current guest status and assignments are never inferred from legacy text; canonical historical assignment/contact migration requires a separately approved mode after Phase 4.

Workers recheck actor activation/permissions before every transactional chunk. Completed chunks persist; a failing chunk rolls back and the operator explicitly retries pending rows. Linking to an existing encounter preserves its profile; it is not an automatic profile-correction workflow.

Do not roll back the worksheet-aware uniqueness migration over imported multi-sheet data without a reviewed migration plan: the older schema could not represent repeated physical row numbers across worksheets.

## Verification

- PHP 8.4.22, Laravel 13.37.0, Livewire 4.4.7, MariaDB 10.4.32; installed dependencies from existing lock files.
- PHPUnit: 125 tests passed, 730 assertions, against the guarded summit_2026_test database.
- The existing Phase 1–2 browser regression also passed all 11 groups (all five roles, Livewire, attendance concurrency, registration/import/report/mobile flows). Together with the five Phase 3 groups, 16 real browser/concurrency groups passed.
- Five real Phase 3 Chrome scenario groups passed: simultaneous independent PHP capture; mobile batch/session retention; duplicate review and correction; officer scope/detail; 101-row database-queue import spanning chunks and result download.
- Desktop 1440px and mobile 360px screenshots inspected; no horizontal page overflow or JavaScript errors. Screenshots wait for mobile sidebar transitions to settle.
- Additional feature cases cover mixed XLSX headers, inline/shared/rich strings, empty sheets, numeric timestamps, source conflicts, formulas/errors, unsafe archives/XML, own-entry access, explicit importer grants, forged fields, cross-batch IDs, wrong years/sessions, transaction rollback/retry and revoked actors.
- Pint --dirty --format agent, Blade compilation, Vite production build, Composer strict validation, route inspection and migration status passed.
- Test credentials are random and fixture accounts/data are removed afterward. Browser artifacts are private/ignored under storage/app/guest-browser-checks.
- tests/Browser/guest-smoke.mjs uses an externally available Playwright module, optionally supplied through PLAYWRIGHT_MODULE. No application dependency or lock-file change is required. Existing tests/Browser/smoke.mjs remains the Phase 1–2 browser regression.

Two initial test invocations accidentally overlapped on the disposable test database; they were stopped and rerun sequentially. The first browser race correctly exposed a repeatable-read idempotency defect; it was fixed and the independent connection check then passed. A missing Vite manifest on the first fresh-checkout run was resolved by building assets. None of these failures touched application data.

## Remaining work

Phase 4: actual bulk assignment/reassignment/unassignment, officer attempts and status transitions/history, follow-up reporting and exports. Current Phase 3 reads assignment/status foundations but exposes no pretend contact or assignment action.

Phase 5: physical devices/iOS, accessibility review, sustained realistic-volume/concurrency testing, production mail, HTTPS/secure cookies, least-privilege supported database deployment, supervised workers, backups/restore, retention and monitoring.

The owner must supply real 2026 clock times and verified historical sessions/source data. Guest capture does not require attendance windows to be configured. No production deployment was performed.

Local runtime: the previous workspace worker was stopped. The new D:\Apps\summit2026_plus checkout serves http://127.0.0.1:8000 (login HTTP 200) with a database imports worker. Observed parent server PID 29288, listener PID 27332 and worker PID 10048; recheck IDs before managing processes. These are local development processes, not production supervision. Changes have not been committed or pushed in this Phase 3 task.
