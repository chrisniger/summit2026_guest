# Phase 2 — Info Com implementation

## Delivered scope

Phase 2 adds working onsite registration/edit/detail screens, normalized phone/name lookup, a mobile Livewire check-in desk, original-timestamp automatic attendance, explicit manual check-in, Super Admin void/restore corrections, CSV preview/review/commit/retry, database-queued imports, attendance summaries, filtered attendee lists and CSV exports. All writes and reports stay within Info Com; no guest, assignment or contact records are created or synchronized.

The original staff accounts and password policy (minimum eight characters, no complexity requirements) remain in place. No production attendee/guest source files or sample operational records were loaded.

## Attendance and identity decisions

- Onsite registration uses the server instant and a per-form UUID fingerprint. Repeated submissions of the same form create one registration event.
- Phone normalization supports Nigerian 0/234/+234 variants, with raw values retained. Shared phones never cause automatic merging. Search is debounced and paginated.
- Exactly one enabled, unlocked session must contain the original timestamp; start is inclusive and end exclusive. Virtual preference never proves physical attendance. No times were invented for the eight real conference sessions.
- Manual check-in revalidates the displayed session inside a transaction. An event row lock serializes registration/check-in/schedule changes, and the database unique pair protects retries. Confirmation requires choosing the attendee; search resets and regains focus after success.
- Super Admin may void or restore the same attendance row with a required reason and audit history. Voided records never silently restore during ordinary check-in/import. General backdate/transfer controls are not included in this release.
- A reviewed CSV match appends a registration event without overwriting trusted profile fields. If its original time predates the existing earliest registration, the provenance cutoff is updated to that earlier time.

## Import contract

CSV uploads must have all 18 named template headers; order may vary and UTF-8 BOM is supported. Limits are 5 MB and 10,000 non-empty rows. Text must be UTF-8, original timestamps must be explicit-offset ISO values in 2026 and cannot be in the future. Optional fields and nullable boolean answers are preserved.

Preview is a dry run. Full source files are encrypted with Laravel Crypt on the private local disk; normalized/raw rows and validation results stay in separate Info Com tables. Binary data, bad headers, malformed rows/timestamps and invalid field values are rejected or quarantined.

Exact source-row fingerprints skip repeated rows across files. Shared phones/names or ambiguous candidates require an explicit decision: create a separate person, match a reviewed Info Com ID or skip. Nothing silently merges or overwrites a trusted profile. Every review row must be resolved before confirmation.

The imports queue processes at most 100 pending rows per transaction, then dispatches the next chunk. Completed rows and source events are idempotent under duplicate delivery. An interrupted chunk rolls back; the batch pauses with a safe retry action. Worker permissions are rechecked for every chunk. Raw row contents and passwords are excluded from application error logs.

Import history shows progress, created/matched/skipped/invalid/review counts and automatic attendance. Authorized users can download a row-results CSV; no raw-file public download is exposed.

## Reports and access

Reports filter by session, day, name/phone, source, preference, membership and first-time answer. Counts distinguish unique people from session check-ins. A session's eligible cohort uses the original registration timestamp (creation time if unknown) up to the earlier of now and session end. Unconfigured sessions show no absent/rate result. Status filtering affects the attendee list, while summary cards retain the labelled cohort definition.

Daily counts deduplicate people across morning/evening. Breakdown tables show up to 20 registration groups with unanswered values labelled. Guest Service never enters these calculations.

Info Com can use its workspace and read attendance reports. Imports require an explicit info.import grant (Super Admin by default). Attendee-matrix and summary exports require Super Admin. Exports use the same filtered cohort, neutralize spreadsheet formula prefixes, and are audited. Field values are HTML escaped throughout the UI.

## Schema and local operation

Migration 2026_10_18_124840_extend_info_import_progress adds only import progress, failure and private-file-path fields. It was applied to the existing application database without refreshing or deleting its contents.

Local UI: http://127.0.0.1:8000/info-com/check-in

Imports require a worker in addition to the web server:

```powershell
php artisan queue:work --queue=imports --sleep=1 --tries=3 --timeout=120
```

The local worker is started for this installation. Production should supervise/restart workers, use the database queue, configure backups and encrypted storage/retention, and verify platform timeout handling.

## Verification

| Check | Result |
|---|---|
| php artisan test --compact | **97 passed, 536 assertions** |
| node tests/Browser/smoke.mjs | **11 scenario groups passed**, including real 101-row queue processing and concurrent PHP check-in |
| php vendor/laravel/pint/builds/pint --format agent | Passed |
| php artisan view:cache | Passed |
| npm run build | Passed |
| Composer strict validation | Passed |
| Additive application migration / migration status | Applied successfully; all six application migrations recorded |
| Local HTTP GET /login | 200 |

Initial integration tests caught and fixed an import-preview class reference error and a streamed-response assertion. Browser QA verified the corrected pages. No failed check is counted as a success. The root directory has no Git repository, so full-project Pint formatting was used instead of dirty-only formatting. No commit, push or external deployment was performed.

Automated and browser evidence is recorded in IMPLEMENTATION_PROGRESS.md. The test suite uses only summit_2026_test. Browser fixtures are restricted to that database, use a synthetic current test session, and are removed afterward. The eight real application sessions remain unchanged.

Browser checks cover desktop and 360px layouts, onsite forms, automatic attendance, normalized shared-phone lookup, manual confirmation/reset, CSV preview and actual database-queue commits, result/matrix downloads and role denials. Two separate PHP processes exercise a real simultaneous check-in against MariaDB. The final CSV browser scenario uses 101 rows to exercise multiple worker chunks.

## Remaining work

Guest Service batch entry/import, officer assignments, follow-up activity/status workflow and follow-up reports remain Phases 3–5. Production mail/HTTPS/backups, physical Android/Safari/network acceptance, sustained volume/concurrency testing and approved operational correction/retention policies remain launch work. Actual conference session start/end times are still administrator configuration. Historical 2025 guest workbooks must use the future independent Guest Service importer.

