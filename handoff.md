# SUMMIT 2026 — complete developer / AI handoff

**Snapshot date:** 18 October 2026, Africa/Lagos (WAT).  
**Workspace:** `D:\Apps\summit2026_plus`  
**Owner / existing administrator:** Chris — `chrisniger101@gmail.com`  
**Current delivery:** Phases 1–5 implemented for the local internal app; production hosting is deferred by the owner.  
**Purpose:** Give another AI or developer enough context to continue without repeating discovery, resetting the application, confusing the two domains, or claiming unfinished features work.

This document describes the current implementation, verified operating state, original requirements, decisions, limitations, and continuation work. The source files and migrations remain authoritative for exact executable behavior. Read this alongside `AGENTS.md`, the three root prompts and `docs/`.

The original handoff was a Phase 2 snapshot. The Phase 5 update below is authoritative; older phase status, process IDs and uncommitted-work statements are historical.

## Current Phase 5 local release — authoritative update

The owner explicitly requested local internal operation and a final commit/push to https://github.com/chrisniger/summit2026_guest. The release includes all Phase 3–5 source changes and updated docs on main. Use `git log -1` and `git ls-remote origin refs/heads/main` for the actual release revision; do not rely on the old initial f7d2647 snapshot below. No production deployment is part of this release.

Read docs/PHASE_5_COMPLETION_REPORT.md and docs/LOCAL_OPERATIONS_RUNBOOK.md first. Local URL remains http://127.0.0.1:8000. Start/reuse the app, imports worker and scheduler with `.\start-local.ps1 -RestartWorkers`; it preserves environment/key/data and refuses a foreign port owner. PHP defaults to D:\php84\php.exe; XAMPP provides the running MariaDB at 127.0.0.1:3306. Runtime PID receipts are private in storage/app/local-processes.json. Recheck process command lines because PIDs change. Do not restore the removed E: workspace or launch duplicate servers.

New Phase 5 code: app/Http/Middleware/ProtectResponse.php; app/Services/Operations.php and LocalBackup.php; app/Console/Commands/OperationsCheck.php, ImportMaintenance.php, LocalBackupCommand.php and VerifyLocalBackup.php; resources/views/admin/operations.blade.php; generic errors/409,419,429,500,503 and recovery.blade.php; start-local.ps1; tests/Feature/ReleaseOperationsTest.php; tests/Browser/release-fixtures.php and release-smoke.mjs. Existing bootstrap/provider/routes/config/import jobs/services, SessionManager, layouts and app.js integrate the changes. No new dependency or Phase 5 schema migration.

ProtectResponse applies private no-store plus browser privacy/security headers, including error responses. HSTS only on HTTPS. Native forms block offline saves while retaining current page inputs and prevent double native submission; Livewire owns its own submission lifecycle. No offline values are written to browser storage, and reload/navigation/browser closure does not preserve them. Recovery pages do not expose raw exceptions or depend on database/auth/Vite. Session lock/unlock is audited and preserves configured clock boundaries.

Queue retry_after defaults to 300 seconds, longer than job timeout 120. Original-upload failure cannot create a batch; delayed job failure cannot regress completed status. Queue-loop and scheduler cache heartbeats support the read-only Super Admin /admin/operations page and `php artisan summit:doctor` (`--json`, optional `--production`). Production checks intentionally fail the local configuration. Scheduler does not delete anything or automatically back up. Local warnings for real staffing, actual times and MariaDB lifecycle are expected until resolved.

Import maintenance: `summit:imports:maintain recover` dry-runs batches stalled processing over ten minutes; `--apply` marks eligible batches failed under locks, then retry via authorized import screens. `prune --days=N` requires an explicit positive approved age; dry-run first, then deliberate --apply. Only completed originals beneath guarded domain paths are removed; source rows/provenance remain. No retention age has been approved/applied to main DB, and backups retain their own copies.

Encrypted local backups: `summit:backup --password-file=storage/app/private/backup-recovery-key.txt --dump-binary=D:\xampp\mysql\bin\mysqldump.exe`. The private random password file already exists; never print it, commit it or replace it without preserving recovery of existing archives. Keep a separate protected copy. Snapshots contain SQL, archived APP_KEY, encrypted private originals and SHA manifest under AES-256 ZIP encryption. Full .env is excluded; keep protected configuration separately. The command briefly enters maintenance, preserves prior maintenance state and refuses outstanding imports. Expanded snapshots are capped at 32 MiB for local PHP 128 MiB memory; larger data needs an approved streaming solution. Temporary plaintext/client-option files are cleaned up.

Archives/verification receipts are database-scoped: storage/app/private/backups/summit_2026 (actual app) and summit_2026_test (synthetic tests). An early unscoped private archive may remain in the parent directory. Verify with `summit:backup:verify {archive} --password-file=storage/app/private/backup-recovery-key.txt --mysql-binary=D:\xampp\mysql\bin\mysql.exe`. Only a newly created summit_2026_restore_check rehearsal DB is restored/dropped; existing target and active-connection target are refused. All21 critical table counts, staff identity/password hashes and original decryptability are checked. Wrong password fails safely. This is a rehearsal, not live replacement. Doctor treats a scoped receipt less than 24 hours old as fresh; arrange manual backups and off-device copies until automation is explicitly configured.

Release verification: 165 PHPUnit tests / 1007 assertions, four sequential Chrome suites/30 scenario groups; 1,749 Info Com + 1,000 Guest Service queued CSV rows with deliberate same-phone independence and worker recovery; 16 simultaneous check-ins; 200 guest assignments and 8 same-UUID contact processes yielding one activity; encrypted restore of 21 tables and 2 synthetic originals; wrong-password rejection; offline reconnect/save once; 320/360/768/1440px layouts, labels/keyboard/reduced motion; report download reconciliation. Main encrypted backup restore also matches 21 tables and saved credentials. Tests use summit_2026_test only, never main; browser/automated suites must run sequentially. Evidence under ignored storage/app/*-checks. Pint/build/Blade/Composer checks pass; npm and Composer audits reported zero advisories when checked.

Preserved main DB state at release verification: one existing Super Admin, eight 2026 sessions disabled with actual clock times unset; no operational attendees/guests/assignments/activities or imported originals. Owner password was not changed, stored in source or added to docs. Password policy remains minimum 8 with any composition, verifying the saved hash. Do not seed sample staff into main. Staff bootstrap refuses an existing Super Admin.

Remaining owner work: actual WAT times and explicit session enablement; real team accounts/permissions; approved retention/correction choices; verified historical source/session mapping; separate safe recovery-password/archive copies; rehearsal on actual team devices/network. Future production requires host/domain approval, maintained database, least privilege, HTTPS, production settings, real mail if needed, process supervision and scheduled/off-device backup/monitoring. Physical iOS/Android and internet-hosted behavior were not certified. The app remains local as requested.

## Historical Phase 4 implementation update

The user authorized Phase 4 and it is now implemented in D:\Apps\summit2026_plus. Read docs/PHASE_4_COMPLETION_REPORT.md and the latest IMPLEMENTATION_PROGRESS.md first. This update supersedes the Phase 3/Phase 2 missing-work statements below. The existing Git remote remains chrisniger/summit2026_guest, main at initial commit f7d2647; Phase 3–4 edits are local and uncommitted.

Delivered: exact bulk review/confirmation; transactional assignment/reassignment/unassignment with retained ownership history; current-assignment officer dashboard; tap-to-call and actual contact/notes; current status plus activity/status history; scoped follow-up metrics, officer performance, roster/timeline and five CSV exports. Phase 5 release work is next.

New code: FollowUpAssignments, FollowUpContact, FollowUpReports, FollowUpActivityRequest, FollowUpController, FollowUpReportController; guest/assignment-confirm.blade.php, guest/contact-panel.blade.php, guest/reports.blade.php; FollowUpAssignmentTest, FollowUpContactTest, FollowUpReportTest; tests/Browser/follow-up-fixtures.php and follow-up-smoke.mjs. Dashboard, GuestController, policies/Gates, model relationships, navigation and guest detail/queue integrate the new services. No dependency was added.

Migration 2026_10_18_193725_extend_follow_up_operations is applied. It adds nullable unique source_fingerprint and guest/attempt-time index to follow_up_activities; follow_up_assignments adds closure_reason, preserving the original assignment reason. Existing users, password hash, APP_KEY, data counts and eight sessions are preserved. No application rows were seeded for operational guests/officers.

Assignment confirmation is an encrypted, actor-bound, 15-minute snapshot of ascending guest IDs, guest versions and current assignment IDs. Reason is required. Commit rechecks access, archival state, versions/current owners and active officer role, then changes the entire selection atomically. Assign accepts unassigned only; Reassign explicitly replaces owners; Unassign closes without deleting. Same owner is unchanged. Stale/replayed changed confirmations return 409 without duplicate history. Ownership changes increment guest.version; existing capture/contact forms become stale.

Contact inputs: submission_id UUID, version, expected assignment_id, method, outcome, attempted_at (Y-m-dTH:i in WAT), comment and explicit two_way_confirmed / failed_attempt_confirmed as required. Activity/current status/version/history/audit commit together. Ordinary officers must still own the active assignment under row locks. Actor-scoped fingerprints replay once under the same current assignment. A later actual attempt timestamp determines current status; earlier attempts remain recorded without overwriting it. NOTE_ONLY leaves status unchanged and does not count as an actual attempt.

Officer privacy is stricter than guest profile access: after reassignment former officers lose guest/detail/write/export access immediately; new officers see only their own activity under the current assignment. Managers can see full permitted histories. Data Entry sees no contact histories by default. Current status itself remains visible with the authorized profile.

Routes: POST /follow-up/assignments/preview and /commit; POST /follow-up/guests/{guest}/activities; GET /follow-up/reports and /reports/export. Assignment endpoints require guest.assign; reports Gate allows guest.report.view managers or own-scope officers. Five CSV export types are guests, assignments, status, officers and activities.

Report cohort filters: year/session/day/encounter dates/name/phone/status/current officer/assignment/source/archive. Activity filters: WAT date boundaries and actor. Current status/ownership are never presented as historical snapshots. Success = distinct successful guests / distinct attempted guests in the same cohort/activity period; NOTE_ONLY excluded. Current workload counts active assignments only. Actor performance can retain credit after reassignment. Untouched = no actual attempt under current owner/current assignment in the selected period. Inactive officers retain visible manager workload/history but cannot receive new assignments.

Verification: 154 tests / 936 assertions; seven Phase 4 plus sixteen rerun prior-phase real Chrome/concurrency groups (23 total), including duplicate contacts, competing owners and contact-versus-reassignment. Full build/format/Blade/Composer/migration checks pass. Evidence is private/ignored under storage/app/follow-up-browser-checks. All browser fixtures use summit_2026_test and are removed afterward; run tests/browsers sequentially.

Local runtime still uses http://127.0.0.1:8000 and the existing imports worker in this checkout. Login returns 200. No production deployment or commit/push occurred in Phase 4. Next authorized implementation should focus on Phase 5 when requested; preserve actual session/staff/retention/production decisions for the owner.

## Historical Phase 3 implementation update

The application was committed and pushed to https://github.com/chrisniger/summit2026_guest (main, initial commit f7d2647), then cloned into D:\Apps\summit2026_plus. Phase 3 changes are local work in this checkout. The older Phase 2 snapshot below remains useful for foundational source details; its missing-Guest-Service statements, old runtime process IDs and next-phase recommendations are superseded by this update.

Read docs/PHASE_3_COMPLETION_REPORT.md and the latest IMPLEMENTATION_PROGRESS.md first. Phase 3 now delivers guest entry, session-retaining batch capture, own-scope browsing/search/detail/edit, version conflicts, explicit guest-only duplicate review, and independent CSV/2025 XLSX upload/mapping/preview/review/queued commit/progress/retry/results. Assignment/contact workflows and follow-up performance reporting are Phase 4.

Source additions: GuestController, GuestImportController, GuestRequest, GuestCapture, GuestImport, GuestWorkbook, GuestImportBatch, GuestImportRow, CommitGuestImport, resources/views/guest/*, GuestCaptureTest, GuestImportTest and tests/Browser/guest-smoke.mjs. Routes use /follow-up/guests and /follow-up/imports with guest.* permissions and per-record policies. Imports require guest.import plus guest.create; non-Super Admin users can access only their own batches.

New additive migration: 2026_10_18_191352_extend_guest_capture_and_imports.php. guest_records adds nullable unique source_fingerprint and integer version; guest_import_batches adds private_path, rows_review, rows_processed, rows_skipped and failure_message. Guest import row uniqueness now includes worksheet_name, allowing repeated physical row numbers across worksheets. The migration is applied. Existing credentials, APP_KEY, users, data and eight sessions were preserved.

Guest creation never calls Info Com services. Captures accept an explicit disabled/ended/historical encounter session. Creation defaults NOT CONTACTED and unassigned. Actor-scoped UUID retry and event locks use fresh locking reads to handle MariaDB repeatable-read snapshots. Editing uses integer version and 409 for stale changes. Legacy metadata is preserved without importing current assignment/status/activity.

XLSX uses installed PHP ZIP/DOM/libxml extensions; no package was added. It is a bounded literal OOXML reader, not CSV disguised as XLSX. Required header aliases, selected worksheets and explicit worksheet/event-to-session mapping are supported. Configure verified 2025 historical sessions using the Super Admin form on Guest imports. Unsupported macros/formulas/errors/unsafe XML and ambiguous dates are rejected/quarantined. Limits and exact replay identity are documented in the completion report.

Verified: 125 PHPUnit tests / 730 assertions; five Phase 3 plus eleven Phase 1–2 real Chrome/concurrency groups; 101-row actual queue import; mobile 360px and desktop 1440px layouts; Pint dirty, Composer validation, Vite build, Blade compile and migration status. Browser fixtures are restricted to summit_2026_test and cleaned up. Screenshots/results are ignored/private under storage/app/guest-browser-checks. PLAYWRIGHT_MODULE may point to an already installed external Playwright package when running the browser script.

Next authorized phase, when requested: Phase 4 — transactional assignments/reassignments, scoped officer contact attempts/status history, reports and exports. Keep Phase 5 launch work separate. No production deployment has occurred.

## Contents

1. Immediate orientation and safe continuation
2. User instructions and specification precedence
3. Product boundaries and business rules
4. Delivery status
5. Environment, versions, runtime and account state
6. Startup, restart and recovery commands
7. Authentication, roles and permissions
8. Conference sessions and time handling
9. Database schema and invariants
10. Application source map
11. Route inventory
12. Info Com registration and editing
13. Phone lookup and check-in
14. CSV import contract and processing
15. Reports and exports
16. Guest Service foundations and missing workflows
17. Phase 3 implementation plan
18. Phase 4 implementation plan
19. Phase 5 and launch work
20. Design system and frontend conventions
21. Tests, browser checks and evidence
22. Security, privacy, concurrency and operating rules
23. Known limitations, edge cases and technical cautions
24. Troubleshooting
25. Documentation and file inventory
26. Decisions still needed
27. Acceptance checklist and first continuation prompt

## 1. Immediate orientation and safe continuation

- This is a working **Laravel monolith**, not a frontend prototype.
- **Do not scaffold another Laravel application.**
- **Do not replace the current database or overwrite `.env`.**
- The application database is **`summit_2026`** on the running XAMPP MariaDB instance.
- The disposable testing database is **`summit_2026_test`** on the same instance.
- Never run `migrate:fresh`, `db:wipe`, test fixtures or destructive resets against `summit_2026`.
- Preserve the existing administrator, password hash, `APP_KEY`, event/session configuration and any data entered after this snapshot.
- Git remote is https://github.com/chrisniger/summit2026_guest on main. Inspect git status before changes; Phase 3 changes are local. No production deployment has occurred.
- The existing application runs at [http://127.0.0.1:8000](http://127.0.0.1:8000).
- The local imports worker is also running.
- Check processes/ports before launching duplicates. Process IDs in this handoff are historical observations and must be revalidated.
- The user authorized Phases 3 and 4, now implemented. Begin Phase 5 when requested.
- When asked to continue the next phase, start with **Phase 5 stabilization and release**.
- Assignment/contact workflows are implemented and verified; see the authoritative Phase 4 update above.

### Quick inspection

Run from the project root in PowerShell:

```powershell
Set-Location -LiteralPath 'D:\Apps\summit2026_plus'
php -v
php artisan --version
php D:\php84\composer.phar show --direct
php artisan migrate:status
php artisan route:list --except-vendor
Get-CimInstance Win32_Process -Filter "name='php.exe'" |
    Select-Object ProcessId, CommandLine
```

Read the code and the latest progress reports before making changes. Use `rg` / `rg --files` for searches.

## 2. User instructions and specification precedence

### Explicit user direction established in this chat

1. Read `prompt1.md`, `prompt2.md` and `prompt3.md`.
2. Build the application using the already-running **XAMPP database**.
3. Create the owner's Super Admin account using the email above and the password they supplied.
4. Change the password policy to **minimum 8 characters with no character-composition requirements** because this is an internal app.
5. Build Phase 2: Info Com registration, search, attendance, CSV imports and reports.
6. Prepare an exhaustive `handoff.md` for another AI.

The existing administrator was created and a real HTTP login was verified. Its owner-chosen password is deliberately **not reproduced in this file**. It is hashed in the database. Preserve it; obtain it from the owner through an appropriate private channel if interactive login is necessary. There are no surviving generic test credentials.

“Any password should work” was implemented as **any character combination of at least eight characters**, not an authentication bypass. Login must still match the saved account password.

### Controlling documents

- `prompt1.md`: Phase 1 implementation requirements, secure architecture, independent modules, five roles, migrations, session inventory, tests and evidence-based reports.
- `prompt2.md`: authoritative conference day/session classification in **both** modules.
- `prompt3.md`: official Summit Bible Church visual identity and reusable design system.
- Numbered `docs/01_...` through `docs/12_...`: controlling v3 requirements.
- `docs/README.md`: document guide and architectural boundary.
- `docs/CODEX_IMPLEMENTATION_PROMPT.md`: implementation direction.
- `docs/ARCHITECTURE_DECISIONS.md`: decisions made to resolve specification differences.
- `docs/IMPLEMENTATION_PROGRESS.md` and completion reports: actual delivered state.

### Resolved conflicts

- Both copies of the older v2 specification were identical when checked and are superseded by v3 and later confirmed instructions.
- Use **`conference_sessions`**, not the older `info_conference_sessions` name.
- Info Com attendance and Guest Service independently reference this shared lookup.
- Guest date/day/session labels are **derived through the session relation**, not duplicated as editable guest fields.
- Keep **separate Info Com and Guest Service import tables/pipelines**, despite an older v2 suggestion of generic shared import tables.
- Use the native Laravel Gates/Policies plus explicit permission pivots already implemented; **Spatie is not installed**.
- One `guest_records` row currently represents **one independently captured encounter**. No separate shared guest profile/encounter reconciliation system has been built.
- The latest password instruction supersedes old 12-character/mixed-case/number hints. Do not reintroduce those older rules.
- Some original documents still use future-tense descriptions of features now delivered in Phase 2. Completion reports and executable code clarify current status.
- Illustrative API contracts are **not a guarantee that those exact JSON endpoints exist**. See the actual route inventory below.

## 3. Product boundaries and business rules

### Product

**SUMMIT 2026 — Conference Registration, Attendance and Guest Follow-Up Management System**, for Summit Bible Church.

Conference dates: **27 October–1 November 2026**.  
Presentation timezone: **Africa/Lagos**.  
Application/storage timezone: **UTC**.

### Two independent operational domains

**Info Com**

- Participant registration and imported registrations.
- Participant name/phone lookup.
- Session attendance, check-in, attendance correction.
- Attendance analytics and reports.

**Guest Service / Follow-Up**

- Explicit, independently entered/imported guest records.
- Session classification of the actual guest encounter.
- Admin assignment/reassignment to Follow-Up Officers.
- Contact attempts, current status, immutable-ish history and reporting.

Shared infrastructure is allowed: staff authentication, roles, navigation, events/sessions, audit infrastructure and Super Admin administration.

### Non-negotiable isolation

A person called Mike may be registered in Info Com and separately entered as a Guest Service guest with the same phone number. These remain independent records.

Never:

- Automatically create a guest from registration or attendance.
- Classify someone as a guest because they are a non-member, first-time participant, present or absent.
- Introduce an attendee-to-guest foreign key.
- Match, merge or synchronize participants across the two modules.
- Pull an attendee's contact details into Guest Service automatically.
- Let guest capture/contact updates alter attendance.
- Include guests in Info Com attendance denominators.
- Compute combined “unique people” totals across the two datasets.
- Route historical guest workbooks through the Info Com importer.

Matching phones are **not unique identity**, even within a single domain. Show candidates for human review; never silently merge by phone.

### Guest defaults and contact meaning

A newly captured guest starts:

- `current_status = 'NOT CONTACTED'`.
- No active assignment: display **UNASSIGNED**.

Exactly three current statuses:

- `NOT CONTACTED`
- `CONTACTED`
- `UNREACHABLE`

**CONTACTED means successful two-way contact**, not merely an outbound SMS or unanswered call. Record a genuine outcome and activity history. “No attempts” is distinct from “not contacted after an attempt.”

### Exclusions

No payments, QR check-in, native mobile app, SMS sending integration or unrelated integrations were authorized for the MVP. No optional Report Viewer role is implemented.

## 4. Delivery status

| Area | Current state |
|---|---|
| Laravel / Blade / Livewire / Alpine / Tailwind foundation | Implemented |
| Login, logout, reset screens and broker | Implemented; real outbound email not configured |
| Five roles, permissions, gates and per-record policies | Implemented |
| Staff create / role changes / deactivate | Implemented |
| Initial Super Admin bootstrap | Implemented; an administrator already exists |
| Shared event and eight conference sessions | Implemented; real times still null and disabled |
| Session schedule editor and overlap prevention | Implemented |
| Shared branded shell, role navigation, denied page | Implemented |
| Audit writer and paginated audit viewer | Implemented; audit search is not built |
| Independent Info Com / Guest Service schemas | Implemented |
| Info Com registration / edit / detail | Implemented |
| Debounced name / Nigerian phone lookup | Implemented |
| Auto and manual exact-session check-in | Implemented |
| Repeated submission / duplicate attendance protection | Implemented |
| Attendance void / restore with required reason | Implemented, Super Admin only |
| General attendance backdate / transfer UI | Not implemented; operational policy needed |
| Info Com CSV upload / dry-run / review / confirm | Implemented |
| Queued chunks / progress / retry / row results | Implemented |
| Attendance filters, daily/session summaries and breakdowns | Implemented |
| Safe CSV attendee matrix and summary export | Implemented; Super Admin only |
| Guest models / default statuses / scope / policies | Foundations implemented |
| Guest entry, batch entry, guest list/detail/edit UI | **Not implemented** |
| Guest CSV / historical XLSX importer | **Not implemented** |
| Guest import batch / row models and jobs | **Not implemented**; tables exist |
| Bulk assignment / reassignment workflows | **Not implemented** |
| Officer contact attempts and status transition service | **Not implemented** |
| Complete Follow-Up reports and exports | **Not implemented** |
| General settings / retention editor | **Not implemented**; settings table exists |
| Dark-mode UI | Not implemented; semantic token structure only |
| Production deployment / mail / backups / recovery | Not completed |
| Physical Android / Safari / flaky network / sustained load tests | Not completed |

The Follow-Up Admin, Data Entry and Officer landing screens are branded **workspace shells with real scoped counts**, not complete operational modules.

## 5. Environment, versions, runtime and account state

### Machine and tools

| Item | Verified value |
|---|---|
| OS / shell | Windows, PowerShell |
| Project | `D:\Apps\summit2026_plus` |
| PHP executable | `D:\php84\php.exe` |
| PHP version | 8.4.22 |
| PHP requirement declared in Composer | `^8.4.1` |
| Composer phar | `D:\php84\composer.phar` |
| Composer version observed during setup | 2.10.2 |
| Laravel framework | 13.37.0 |
| Livewire | 4.4.7 |
| PHPUnit | 12.5.40 |
| Pint | 1.32.1 |
| Boost | 2.10.3, development dependency |
| Node | 22.22.3 |
| npm | 10.9.8 |
| Tailwind / Tailwind Vite plugin | 4.3.3 |
| Vite | 8.3.4 |
| Laravel Vite plugin | 3.2.0 |
| Concurrently | 10.0.6 |
| Playwright, local extraneous test install | 1.64.1 |
| MariaDB supplied by XAMPP | 10.4.32 |
| XAMPP directory | `D:\xampp` |
| MySQL CLI | `D:\xampp\mysql\bin\mysql.exe` |
| Database host / port | `127.0.0.1:3306` |
| Application DB | `summit_2026` |
| Test DB | `summit_2026_test` |

Do not substitute XAMPP's bundled PHP without checking compatibility. The lock file requires **PHP 8.4.1+**. PHP 8.4.22 is the runtime actually used to build and verify this app.

Composer's `composer` launcher at `D:\php84\composer` is a Unix shell script and was unusable directly in PowerShell. Use:

```powershell
php D:\php84\composer.phar install
```

Optimized autoload generation stalled during initial scaffolding. `composer.json` currently has `"optimize-autoloader": false`. Standard autoload generation works. The original interrupted scaffold-path autoload failure was resolved by regeneration in the project root.

`.npmrc` sets `ignore-scripts=true` and `audit=true`. The normal frontend build works with this configuration. Do not indiscriminately enable package installation scripts.

`npm list` currently reports Playwright packages as extraneous because they were installed only for local QA without changing the lock file. It also reports `@laravel/multiplex` as an unmet **optional** dependency; this did not block installation/build or app operation.

### Verified non-secret runtime configuration

| Config | Current value |
|---|---|
| `app.name` | SUMMIT 2026 |
| `app.env` | local |
| `app.debug` | true |
| `app.url` | http://localhost:8000 |
| `app.timezone` | UTC |
| `database.default` | mysql |
| Local DB username | root |
| `session.driver` | database |
| `session.lifetime` | 120 minutes |
| `session.encrypt` | true |
| `session.secure` | null for local HTTP |
| `queue.default` | database |
| `cache.default` | database |
| `filesystems.default` | local |
| `mail.default` | log |

The real `.env` already exists and has a generated application key. Read credentials locally only when necessary. Do not paste secrets, reset links, hashes or the application key into logs/reports.

### Runtime snapshot

Observed while preparing this file:

| Process | Observed PID | Purpose |
|---|---:|---|
| `php artisan serve ... --port=8000 --no-reload` | 26324 | Web-server parent |
| PHP built-in server on `127.0.0.1:8000` | 1364 | Web serving |
| `php artisan queue:work database --queue=imports ...` | 26700 | Application import worker |

These are **not permanent identifiers**. Reinspect before stopping or restarting a process.

`GET http://127.0.0.1:8000/login` returned **200** during handoff preparation.

### Application data snapshot

Read-only query at handoff:

- One active staff account.
- User ID 1, name **Chris**, email **chrisniger101@gmail.com**, role **super_admin**.
- Zero Info Com attendees.
- Zero Guest Service guests.
- Eight conference sessions.
- Zero enabled conference sessions.
- Every real session has null start/end boundaries.
- No generic test accounts remain.

The zero counts are a snapshot, **not an instruction to clear data**. Preserve anything the owner enters later.

## 6. Startup, restart and recovery commands

### Existing installation: normal use

```powershell
Set-Location -LiteralPath 'D:\Apps\summit2026_plus'

# Only if the web server is not already running:
php artisan serve --host=127.0.0.1 --port=8000 --no-reload

# In another terminal, only if the imports worker is not already running:
php artisan queue:work database --queue=imports --sleep=1 --tries=3 --timeout=120
```

Use the `imports` queue, not only Laravel's default queue. A worker listening only to `default` will not process these jobs.

### Installing dependencies in an existing checkout

```powershell
php D:\php84\composer.phar install --no-interaction --prefer-dist
npm ci
php artisan migrate --no-interaction
npm run build
```

Inspect migrations before applying them. Do not run fresh migrations or regenerate the application key on this existing installation.

### Truly new machine / empty application database only

1. Install compatible PHP, Composer, Node and MariaDB/MySQL.
2. Copy the source plus lock files.
3. Install dependencies.
4. Create a **new** application database and a separate disposable test database.
5. Create `.env` from `.env.example` only if `.env` does not exist.
6. Set database configuration.
7. Generate a new application key **only for a genuinely new installation without existing encrypted data**.
8. Run ordinary `migrate --seed`.
9. Build assets.
10. Bootstrap the initial administrator only if none exists.

```powershell
php artisan summit:bootstrap-admin --name="Your Name" --email="you@example.com"
```

The command prompts privately for password and confirmation and refuses when a Super Admin already exists. **It is expected to refuse on the current installation.** Use Staff & roles for additional accounts.

### Background operation on Windows

If launching background services programmatically, use `Start-Process -WindowStyle Hidden` and the project working directory. Quote the absolute artisan argument because the workspace path contains spaces. Redirect stdout/stderr to the app's private log directory. Do not open visible windows unless the user requests them.

Current logs:

- `storage/logs/local-server.out.log`
- `storage/logs/local-server.err.log`
- `storage/logs/import-worker.out.log`
- `storage/logs/import-worker.err.log`
- `storage/logs/laravel.log`

### Worker restarts after code changes

Queue workers are long-lived PHP processes and may keep old code loaded.

- Identify the worker belonging to this project.
- Gracefully stop/restart it after application/job changes.
- `php artisan queue:restart` asks workers to exit; **there is no supervisor installed here to relaunch them automatically**.
- Relaunch the imports worker afterward.
- Do not kill all PHP processes; another process may be the web server or an unrelated app.
- Do not stop XAMPP MariaDB merely to restart Laravel.

### Key and encrypted data preservation

`APP_KEY` protects encrypted session values and encrypted CSV originals. Replacing it can make those files unreadable and invalidate sessions. Back it up privately along with the database and private uploads.

## 7. Authentication, roles and permissions

### Implemented authentication

- Staff-only login, no public registration route.
- Lowercase/trimmed email input.
- Laravel password hashing and session guard.
- Login session ID rotation.
- Logout invalidation and CSRF-token regeneration.
- Active-user checks on login and every authenticated web request.
- Last-login timestamp and authentication audit.
- Failed-login throttle: five attempts per normalized email/IP key over 60 seconds.
- Password request/reset throttle: five requests/minute per IP.
- Generic reset-request response avoids explicit account existence disclosure.
- Inactive accounts do not receive reset links.
- Password-reset token validation and confirmation.
- Database sessions revoked after successful password reset.
- Remember token rotated on reset.
- `AuthenticateSession` middleware protects authenticated sessions after password changes.
- Current password policy is registered centrally in `AppServiceProvider`: `Password::min(8)`.
- No mixed-case, letter, number or symbol requirement.
- Plaintext passwords are not stored in source or audit logs.

Reset functionality is built, but `MAIL_MAILER=log` means messages are written to private local logs instead of delivered. SMTP/provider configuration remains required for real email.

### Role keys, labels and landing routes

| Role key | Display label | Named landing route | Path |
|---|---|---|---|
| `super_admin` | Super Admin | `admin` | `/admin` |
| `info_com` | Info Com Team | `info` | `/info-com` → check-in screen |
| `follow_up_admin` | Follow-Up Admin | `follow-up` | `/follow-up` |
| `data_entry` | Follow-Up Data Entry | `guest-entry` | `/guest-service` |
| `officer` | Follow-Up Officer | `my-guests` | `/my-guests` |

`User::workspace()` checks roles in this order. An account with no recognized role lands on the denied page.

### Permission implementation

Native tables:

- `roles`
- `permissions`
- `role_user`
- `permission_role`
- `permission_user`

`User::hasPermission()` allows active Super Admin, explicit individual grants or grants through roles.

`AppServiceProvider`:

- Denies inactive users first in `Gate::before`.
- Allows active Super Admin globally.
- Registers every permission in `config/summit.php`.
- Registers role-workspace gates.
- Maps Info Attendee, Attendance, Guest Record and Assignment policies.

Seeded permission names:

```text
platform.manage
info.access
info.attendee.create
info.attendance.mark
info.import
info.report.view
guest.access
guest.create
guest.assign
guest.contact
guest.report.view
guest.import
```

Default role grants:

| Role | Default grants |
|---|---|
| Super Admin | All listed permissions plus Gate override |
| Info Com | info.access, info.attendee.create, info.attendance.mark, info.report.view |
| Follow-Up Admin | guest.access, guest.create, guest.assign, guest.contact, guest.report.view |
| Data Entry | guest.access, guest.create |
| Officer | guest.access, guest.contact |

Imports are **not automatically granted** to Info Com, Follow-Up Admin, Data Entry or Officer. Individual permission pivots exist, but there is **no grant/revoke-permission editor** in the staff UI yet.

Info Com personal-data exports currently use `platform.manage`, effectively Super Admin only, not a separate export permission.

### Staff management behavior

- Super Admin creates individual accounts with a selected role.
- Password and confirmation use the shared eight-character minimum.
- UI assigns one selected role; database pivots technically support multiple roles.
- Role/account changes revoke the target's database sessions.
- An admin cannot change their own role/active status through this screen.
- Keep at least one active Super Admin.
- Changes are transactional and audited.
- Follow-Up Admin account-management delegation is not enabled.
- No staff hard-delete UI is exposed.
- Reset email delivery still depends on mail setup.

### Guest ownership policy already present

`GuestRecord::visibleTo($user)`:

1. No `guest.access`: empty query.
2. Super Admin / Follow-Up Admin: all guest records.
3. Data Entry: records where `created_by_user_id = current user`.
4. Other guest-access users, including officers: only records with an active assignment to that user.

`GuestRecordPolicy`:

- `viewAny` requires guest.access.
- `view` checks the scoped query.
- `create` requires guest.create.
- `update` requires guest.create and visibility.
- `contact` requires guest.contact and visibility.

Important: `visibleTo` does **not** itself exclude archived records or filter conference year. Current dashboards add those restrictions. New guest controllers/Livewire components must apply the appropriate archival/event scope too.

## 8. Conference sessions and time handling

### Authoritative 2026 inventory

| Code | Day | Local date | Name | Label |
|---|---:|---|---|---|
| 2026-10-27-G | 1 | 2026-10-27 | General | Day 1 |
| 2026-10-28-G | 2 | 2026-10-28 | General | Day 2 |
| 2026-10-29-G | 3 | 2026-10-29 | General | Day 3 |
| 2026-10-30-G | 4 | 2026-10-30 | General | Day 4 |
| 2026-10-31-AM | 5 | 2026-10-31 | Morning | Day 5 Morning |
| 2026-10-31-PM | 5 | 2026-10-31 | Evening | Day 5 Evening |
| 2026-11-01-AM | 6 | 2026-11-01 | Morning | Day 6 Morning |
| 2026-11-01-PM | 6 | 2026-11-01 | Evening | Day 6 Evening |

- Seeded event: SUMMIT 2026, year 2026, starts_on 2026-10-27, ends_on 2026-11-01, timezone Africa/Lagos.
- All eight real sessions currently have null times and `enabled=false`.
- The owner/admin must enter actual clock times.
- **Never invent conference times.**
- Reseeding sessions uses `firstOrCreate` and does not reset configured boundaries.

### Schedule editor and resolver

Files: `app/Livewire/SessionManager.php` and `app/Services/SessionSchedule.php`.

- Admin enters same-day local `H:i` start/end.
- Converted to UTC and stored in `starts_at_utc` / `ends_at_utc`.
- Both values are required before enabling.
- End must be later than start on the session's local date.
- Enabled windows cannot overlap.
- Adjacent windows are permitted.
- Schedule changes lock the parent conference-event row, then the session row.
- Database CHECK also rejects invalid/half-configured enabled intervals.
- Active interval: `start <= instant < end`.
- Resolver returns a session only when **exactly one** enabled, unlocked interval matches.
- Explicit `attendance_locked_at` excludes a session.
- Current resolver queries sessions; Info Com write services additionally require the matched event's year to be 2026.
- Manual check-in checks current server time, not a client-supplied timestamp.
- CSV automatic attendance checks the original registration instant, not upload/worker time.

For Phase 3, a guest entered after an event must be allowed to select its **actual historical session**. Do not require the chosen guest session to be currently active, enabled or inside its clock window just because attendance has those rules.

## 9. Database schema and invariants

The six migration files are:

```text
0001_01_01_000000_create_users_table.php
0001_01_01_000001_create_cache_table.php
0001_01_01_000002_create_jobs_table.php
2026_10_18_121200_create_platform_tables.php
2026_10_18_121201_create_independent_domain_tables.php
2026_10_18_124840_extend_info_import_progress.php
```

All six are applied to the application database. The first five were migration batch 1; the final additive import-progress migration was batch 2 at snapshot.

Use new, additive migrations for future changes. Do not rewrite applied migrations to change existing installed schema.

### 9.1 Shared identity/infrastructure tables

| Table | Relevant columns and rules |
|---|---|
| `users` | id, name, unique email, email_verified_at, password, remember_token, timestamps; added is_active default true and nullable last_login_at |
| `password_reset_tokens` | Laravel standard email/token/created timestamp |
| `sessions` | Laravel session ID, nullable user ID, IP, user-agent, payload, last_activity |
| `roles` | id, unique name, label, timestamps |
| `permissions` | id, unique name, timestamps |
| `role_user` | role/user FKs, composite PK, cascade on related role/user deletion |
| `permission_role` | permission/role FKs, composite PK |
| `permission_user` | permission/user FKs, composite PK |
| `conference_events` | id, name, unique year, starts_on, ends_on, timezone, timestamps |
| `conference_sessions` | event FK, unique code, day_number, date_local, enum name, label, nullable UTC boundaries, enabled false, nullable attendance lock, timestamps |
| `audit_logs` | actor nullable FK, domain enum, action, subject_type/id, before_json, after_json, source_ip, user_agent, occurred_at |
| `app_settings` | unique key, JSON value, nullable updating-user FK, timestamps; reserved for future allowed settings |
| `cache` / `cache_locks` | Laravel database cache |
| `jobs` / `job_batches` / `failed_jobs` | Laravel queue infrastructure |

Audit actor references may become null when a user is removed. Operational/history FKs generally restrict deletion. There is no normal history-deletion UI.

### 9.2 Info Com tables

#### `info_attendees`

- id
- required first_name, last_name
- required phone_raw (max DB length 50)
- nullable indexed phone_normalized (length 30)
- nullable email
- nullable booleans: member, commits_to_invite, first_time, bus_shuttle, consent
- nullable strings: summit_centre, location, abuja_area, bus_route, attendance_preference, heard_about, source_external_id
- nullable unsigned invite_count
- nullable text feedback
- nullable indexed registration_original_at_utc
- registration_source enum: onsite / csv / other; default onsite
- nullable raw_source_json
- nullable creator user FK
- timestamps and soft deletes
- name/phone/time indexes
- **No unique phone constraint**

Model casts member, first_time, consent, raw_source_json and original registration datetime. Do not assume every boolean-looking field is already cast on the model; inspect `casts()` when adding UI.

#### `info_registration_events`

- attendee FK
- source enum onsite / csv
- indexed registered_at_utc
- nullable FK to `info_import_rows`
- nullable creator FK
- nullable **unique** 64-character source_fingerprint
- timestamps

An attendee can have multiple original-time registration events after explicit in-domain review. A source event is not the same thing as a unique participant.

#### `info_attendance_records`

- attendee FK
- required `conference_session_id` FK
- checked_in_at_utc
- source enum manual / registration_auto / csv_auto / admin_adjustment
- nullable marker, registration-event and Info Com import-row FKs
- nullable voided_at and void_reason
- timestamps
- **UNIQUE(info_attendee_id, conference_session_id)**

Void/restore updates the same row. Ordinary writes refuse to silently restore a voided record. Do not create persisted absent rows.

#### `info_import_batches`

- event FK, filename, sha256 index
- status enum uploaded / previewed / processing / completed / failed
- uploader FK
- counters: rows_total, rows_valid, rows_invalid, rows_created, rows_matched, auto_attendance_created
- summary_json, mapping_json, timestamps
- Phase 2 additions: private_path, rows_processed, rows_skipped, rows_review, failure_message

The batch SHA is indexed, not globally unique; repeated uploads may create separate audit batches while identical source events are skipped.

#### `info_import_rows`

- batch FK
- nullable worksheet_name
- row_number
- raw_json and nullable normalized_json
- indexed row_fingerprint
- nullable classification and error_json
- processing_state
- nullable attendee FK
- timestamps
- **UNIQUE(batch_id, row_number)**

### 9.3 Guest Service tables

#### `guest_records`

- required `conference_session_id` FK
- required full_name and phone_raw
- nullable first_name, last_name and indexed phone_normalized
- optional gender, home_address, heard_about
- optional captured_at_original_utc and entry_notes
- current_status enum with **NOT CONTACTED default**
- nullable creator FK
- nullable FK to `guest_import_rows`
- legacy_officer_name
- raw_source_json
- nullable archived_at
- timestamps
- name/phone indexes and composite session/status index
- **No Info Com FK**
- **No unique phone constraint**

The Eloquent model intentionally excludes current_status and archived_at from its fillable input list. New controllers must not blindly pass user input into provenance, creator, import or raw-data fields even though some are model-fillable for trusted services.

`UNASSIGNED` is **derived**, not a current_status enum value or stored assignment flag.

`guest_records` uses `archived_at`, not the `SoftDeletes` trait.

#### `follow_up_assignments`

- guest FK
- officer user FK
- assigning-user FK
- assigned_at
- nullable unassigned_at and reason
- timestamps
- generated stored `active_guest_id`:
  - guest_record_id when unassigned_at is null
  - null otherwise
- **UNIQUE(active_guest_id)**
- officer / unassigned_at composite index
- CHECK: unassigned_at is null or not before assigned_at

Never write the generated column directly. Future bulk assignment services must also lock affected guest rows in a consistent order and validate an active officer role.

#### `follow_up_activities`

- guest FK
- nullable assignment FK
- actor user FK
- method enum call / sms / whatsapp / visit / other
- indexed attempted_at_utc
- outcome enum CONTACTED / UNREACHABLE / NOTE_ONLY
- nullable comment
- previous_status
- nullable new_status
- timestamps

A schema does not implement the contact workflow. Future services must check that the referenced assignment belongs to the same guest, is the authorized active assignment and belongs to the actor where required.

#### `follow_up_status_history`

- guest FK
- nullable from_status
- to_status
- nullable changing-user FK
- changed_at_utc
- nullable reason
- nullable activity FK

The model explicitly uses table `follow_up_status_history` and disables standard timestamps. Future activity references must belong to the same guest; ordinary FKs alone do not establish that cross-column invariant.

#### `guest_import_batches` / `guest_import_rows`

Already-created independent provenance tables analogous to the initial Info Com import schema:

- Batch: event, filename, SHA, processing status, uploader, original counters, mapping_json, summary_json and timestamps.
- Row: batch, worksheet_name, row_number, raw/normalized JSON, fingerprint, classification, errors, processing_state, nullable guest FK and timestamps.
- Unique batch/row-number key.

**Guest imports do not yet have the Phase 2 Info Com progress/path extension columns.** Add appropriate new guest-specific fields through a migration if the Phase 3 pipeline needs them. Do not assume both schemas match or route guest data through Info Com models.

### 9.4 Portability

The actual installation and tests use MariaDB/MySQL. `InfoReports` uses MySQL `YEAR(COALESCE(...))`, and migrations use MySQL-compatible enum/generated-column/check behavior. The original requirements permitted PostgreSQL as an alternative, but **PostgreSQL portability has not been implemented or verified**.

## 10. Application source map

### Entry points and configuration

| File | Responsibility |
|---|---|
| `bootstrap/app.php` | Web/console/health routing, active-user and authenticated-session middleware, guest redirect, JSON error preference |
| `bootstrap/providers.php` | Application provider registration |
| `app/Providers/AppServiceProvider.php` | Password policy, gates, policy mapping and password-route rate limiter |
| `config/summit.php` | Conference timezone, referral options, five role labels and seeded permission lists |
| `routes/web.php` | Actual protected web routes |
| `routes/console.php` | Console route definitions |
| `public/index.php` | Laravel public entry point |
| `vite.config.js` | Tailwind + Laravel Vite build, no external font configuration |
| `resources/js/app.js` | Deliberately minimal; Livewire supplies Alpine |

### Controllers

| File in `app/Http/Controllers/` | Responsibility |
|---|---|
| `Controller.php` | Base |
| `AuthController.php` | Login/logout/reset request/reset completion |
| `DashboardController.php` | Admin and follow-up workspace counts; Info Com root opens check-in |
| `StaffController.php` | Staff listing/create/update, session revocation and audit |
| `InfoAttendeeController.php` | Registration form/save, record detail, edit/update |
| `InfoAttendanceController.php` | Manual check-in and admin void/restore |
| `InfoImportController.php` | Upload, preview view, row decisions, commit, template/results download |
| `InfoReportController.php` | Filter validation, reports, matrix/summary streaming export |

### Requests, middleware and command

- `app/Http/Requests/LoginRequest.php` — credentials, normalized email, failed-login limiter.
- `app/Http/Requests/StaffRequest.php` — authorized staff create/access-change validation.
- `app/Http/Requests/InfoAttendeeRequest.php` — shared attendee field rules plus submission UUID.
- `app/Http/Middleware/EnsureActiveUser.php` — rejects/revokes inactive staff sessions.
- `app/Console/Commands/BootstrapAdmin.php` — initial admin only, hidden password prompts.

### Services

| File in `app/Services/` | Public surface / purpose |
|---|---|
| `Audit.php` | `record(domain, action, subject, actor, before, after)` with metadata allowlist |
| `SessionSchedule.php` | `activeAt(instant)` and authorized `configure(...)` |
| `PhoneNumber.php` | `normalize(phone)` and `searchPrefix(search)` |
| `InfoRegistration.php` | Transactional `register(...)`, source idempotency and optional auto attendance |
| `InfoAttendance.php` | `lockConference()`, `manual(...)`, `record(...)` and `correct(...)` |
| `InfoCsvImport.php` | `candidates`, `preview`, `resolve`, `commit` and `summarize` |
| `InfoReports.php` | `base`, `cutoff`, `eligible` and `build`; shared query definitions |
| `CsvExport.php` | Spreadsheet-safe `cell` and streamed `download` |

### Jobs and Livewire

- `app/Jobs/CommitInfoImport.php` — one import chunk, next-chunk dispatch, paused-state handling.
- `app/Livewire/SessionManager.php` — admin time editor; authorizes every action.
- `app/Livewire/CheckInDesk.php` — paginated debounced search, explicit identity selection and confirmation; polls every 30 seconds.

### Models

All under `app/Models/`:

```text
User.php
Role.php
Permission.php
ConferenceEvent.php
ConferenceSession.php
AuditLog.php
InfoAttendee.php
InfoRegistrationEvent.php
InfoAttendanceRecord.php
InfoImportBatch.php
InfoImportRow.php
GuestRecord.php
FollowUpAssignment.php
FollowUpActivity.php
FollowUpStatusHistory.php
```

Guest import models and a general AppSetting model/service do not exist yet.

### Policies

All under `app/Policies/`:

- `InfoAttendeePolicy.php`
- `AttendancePolicy.php`
- `GuestRecordPolicy.php`
- `FollowUpAssignmentPolicy.php`
- `ReportPolicy.php`

Some current report routes use permission gates directly rather than invoking `ReportPolicy` methods.

### Views

```text
resources/views/
  components/
    layout.blade.php
    auth-layout.blade.php
    icon.blade.php
  auth/
    login.blade.php
    forgot-password.blade.php
    reset-password.blade.php
  admin/
    sessions.blade.php
    staff.blade.php
    audit.blade.php
  info/
    check-in.blade.php
    attendee-form.blade.php
    attendee-detail.blade.php
    imports.blade.php
    import-detail.blade.php
    reports.blade.php
  livewire/
    session-manager.blade.php
    check-in-desk.blade.php
  errors/
    403.blade.php
  dashboard.blade.php
  welcome.blade.php
```

`welcome.blade.php` is the unused Laravel starter view. It is not an implemented screen or the design-system reference.

### Seeders and factories

- `database/seeders/DatabaseSeeder.php` runs RolePermissionSeeder and ConferenceSeeder.
- `RolePermissionSeeder.php` updates roles and synchronizes their configured role grants.
- `ConferenceSeeder.php` creates event/session rows idempotently without resetting configured times.
- Factories: `UserFactory.php`, `InfoAttendeeFactory.php`, `GuestRecordFactory.php`.

Reseeding can resynchronize role permission pivots to `config/summit.php`; preserve intentional future changes in configuration, not only ad hoc role-table edits.

## 11. Route inventory

All app mutations below are web routes with CSRF protection. Unless otherwise stated, protected routes require authenticated active staff. Native form actions usually redirect with session feedback rather than returning a REST resource.

### Public / guest authentication

| Method | Path | Route name |
|---|---|---|
| ANY redirect | `/` | unnamed → /dashboard |
| GET | `/login` | login |
| POST | `/login` | login.store |
| GET | `/forgot-password` | password.request |
| POST | `/forgot-password` | password.email |
| GET | `/reset-password/{token}` | password.reset |
| POST | `/reset-password` | password.update |
| GET | `/up` | Framework health endpoint |

There is no public `/register` route.

### Shared / admin / role workspaces

| Method | Path | Route name | Gate / behavior |
|---|---|---|---|
| POST | `/logout` | logout | Authenticated |
| GET | `/dashboard` | dashboard | Redirects by role |
| GET | `/admin` | admin | platform.manage |
| GET | `/info-com` | info | workspace.info_com; Super Admin override |
| GET | `/follow-up` | follow-up | workspace.follow_up_admin; shell |
| GET | `/guest-service` | guest-entry | workspace.data_entry; shell |
| GET | `/my-guests` | my-guests | workspace.officer; shell |
| GET | `/access-denied` | access-denied | 403 view |
| GET | `/admin/sessions` | admin.sessions | platform.manage |
| GET | `/admin/staff` | admin.staff | platform.manage |
| POST | `/admin/staff` | admin.staff.store | platform.manage |
| PATCH | `/admin/staff/{user}` | admin.staff.update | platform.manage |
| GET | `/admin/audit` | admin.audit | platform.manage |

### Info Com

The prefix requires `info.access`; additional gates/policies apply.

| Method | Path | Route name | Additional protection |
|---|---|---|---|
| GET | `/info-com/check-in` | info.check-in | Component/action gates |
| GET | `/info-com/attendees/create` | info.attendees.create | Attendee create policy |
| POST | `/info-com/attendees` | info.attendees.store | info.attendee.create |
| GET | `/info-com/attendees/{attendee}` | info.attendees.show | Attendee view policy |
| GET | `/info-com/attendees/{attendee}/edit` | info.attendees.edit | Attendee update policy |
| PATCH | `/info-com/attendees/{attendee}` | info.attendees.update | Update policy / request |
| POST | `/info-com/attendees/{attendee}/check-in` | info.attendance.store | info.attendance.mark |
| POST | `/info-com/attendance/{record}/correct` | info.attendance.correct | platform.manage |
| GET | `/info-com/reports` | info.reports | info.report.view |
| GET | `/info-com/reports/export` | info.reports.export | platform.manage |
| GET | `/info-com/imports` | info.imports | info.import |
| GET | `/info-com/imports/template` | info.imports.template | info.import |
| POST | `/info-com/imports` | info.imports.store | info.import |
| GET | `/info-com/imports/{batch}` | info.imports.show | info.import |
| POST | `/info-com/imports/{batch}/rows/{row}` | info.imports.resolve | info.import; row scoped to batch |
| POST | `/info-com/imports/{batch}/commit` | info.imports.commit | info.import + explicit confirm |
| GET | `/info-com/imports/{batch}/results` | info.imports.results | info.import + audit |

`route:list --except-vendor` showed **37 app routes** at handoff; framework/vendor routes such as Livewire and health may be excluded from that filtered count. Livewire endpoint paths are generated by the installed package; do not hardcode an assumed legacy update URL.

No full Guest Service CRUD, guest import, assignment or activity endpoints currently exist. Do not mistake the workspace root paths for those completed features.

## 12. Info Com registration and editing

### Form and validation

Required:

- first_name: string, max 255
- last_name: string, max 255
- phone_raw: string, max 50, valid normalizer result
- submission_id: UUID

Optional:

- email: valid email, max 255
- member / first_time / consent / commits_to_invite / bus_shuttle: nullable booleans
- attendance_preference: In Person / Virtual / null
- heard_about / summit_centre / location / abuja_area / bus_route: nullable max 255
- invite_count: nullable integer 0–100000
- feedback: nullable text max 5000

The mobile form shows core fields first; extra invitation/shuttle/location/feedback fields are under native expandable details. Referral suggestions come from `config/summit.php` and accept an appropriate custom text value.

Blank answers remain null, not false.

### Registration transaction

`InfoRegistration::register(actor, data, instant, fingerprint, source='onsite', rowId=null, existingId=null)`:

1. Authorize source-appropriate permission.
2. Lock the 2026 conference-event row.
3. Look for an existing registration event by source fingerprint.
4. Return an idempotent replay result if present.
5. Create an attendee, or lock the explicitly reviewed existing attendee.
6. Retain raw phone and compute normalized phone.
7. Save original registration instant/provenance and registration event.
8. For an explicitly matched older event, update the earliest original registration time if earlier.
9. Resolve the unique enabled/unlocked session at that original instant.
10. Do not auto-mark Virtual preference as physically present.
11. Otherwise record one session attendance if eligible.
12. Audit registration and its auto-attendance/no-attendance reason.
13. Return attendee, attendance result, reason, replay flag and created flag.

Onsite fingerprint: SHA256 of `onsite:{actor_id}:{submission_uuid}`. Reusing the same form UUID for the same actor does not create a second event.

### Editing

- Validates and authorizes independently.
- Changes whitelisted profile fields and normalized phone.
- Does not accept user-supplied source/creator/import metadata.
- Does not modify attendance or add another registration event.
- Audits **names of changed fields**, not plaintext contact details.

## 13. Phone lookup and check-in

### Normalization

`PhoneNumber::normalize`:

- Permits digits plus conventional phone punctuation/spacing.
- Converts an 11-digit Nigerian local number starting 0 to country prefix 234.
- Converts a 10-digit Nigerian-looking number starting 7/8/9 to 234.
- Retains other acceptable international digit sequences with a leading plus.
- Requires 7–15 digits overall.
- This is a practical search normalizer, not a full external telephony validation library.

`searchPrefix` supports partial local/country-prefix searches. `InfoAttendee::scopeSearch`:

- Searches concatenated first/last name with bound LIKE input.
- Escapes wildcard characters.
- Adds normalized phone-prefix matching for phone-shaped input.
- Never searches Guest Service.

### Check-in desk

- 300 ms debounced search.
- 12-result pagination.
- 30-second polling for refreshed session state.
- Current server-resolved session banner.
- Distinguishable name/phone/attendee-ID cards.
- Explicit select, then confirm present.
- Confirmation revalidates the selected attendee and the displayed session ID.
- A stale session returns a safe conflict rather than marking a different session.
- A successful action clears search/selection and dispatches `check-in-saved` to restore focus.
- Duplicate attempts display “Already present” without another row.
- No active session: registration/search still work; check-in is closed.

### Write safety

`InfoAttendance::manual`:

- Authorize attendance marking.
- Transaction with up to three attempts.
- Lock conference event before attendee.
- Re-resolve active session at current server time inside the transaction.
- Require the active session to equal the displayed expected session.
- Require the 2026 event.
- Record or return the existing unique attendance pair.

`record` is shared by registration/import/manual services. Its callers establish the surrounding transaction/lock order. It checks the relevant permission, refuses implicit restoration of a voided row, writes provenance and audits actual new attendance.

### Admin corrections

- Endpoint accepts only void / restore.
- Required reason length 5–1000.
- Super Admin only.
- Event/attendance row locks and transaction.
- Same row is updated; unique pair remains.
- Reason and before/after correction metadata are audited.
- General backdated creation and transfer UI is not provided.

## 14. CSV import contract and processing

### Exact 18 named headers

```text
Created
First Name
Surname
Phone
Email
Member
Summit Centre
Commits to Invite
Invite Count
Location
Abuja Area
First Time
Bus Shuttle
Bus Route
Attendance
Heard About
Feedback
Consent
```

Header order may vary, but all names must be present once. UTF-8 BOM is stripped. An empty file or file without data rows is rejected.

| Source header | Destination |
|---|---|
| Created | Original registration instant / registration event |
| First Name | first_name |
| Surname | last_name |
| Phone | phone_raw + normalized search value |
| Email | email |
| Member | member |
| Summit Centre | summit_centre |
| Commits to Invite | commits_to_invite |
| Invite Count | invite_count |
| Location | location |
| Abuja Area | abuja_area |
| First Time | first_time |
| Bus Shuttle | bus_shuttle |
| Bus Route | bus_route |
| Attendance | attendance_preference, not proof of attendance |
| Heard About | heard_about |
| Feedback | feedback |
| Consent | consent |

### Upload and row validation

- File extension: CSV.
- Content/MIME validation permits typical CSV/plain-text MIME detection.
- 5 MB upload limit.
- 10,000 non-empty source records per batch.
- UTF-8 required; binary null content rejected.
- Exactly 18 cells per non-empty record.
- Rows counted as logical CSV records, including parser handling of quoted multiline content.
- Shared attendee-field validation.
- Yes/True/1 → true; No/False/0 → false, case-insensitive.
- Blank optional values → null.
- Unknown boolean answers → invalid row.
- Original Created must be a valid 2026 ISO timestamp with timezone, optional fractional seconds and valid calendar date.
- Future timestamps and timestamps without explicit offset are invalid.
- Source examples include `2026-10-18T06:30:49.227Z`.
- Exact original text survives in raw provenance; SQL datetime fields use the migration's precision.

### Preview algorithm

- Source file read/validated before operational records are written.
- Canonical raw row dictionary sorted by header key.
- Fingerprint: SHA256 of `info_csv:` plus canonical raw JSON.
- Invalid rows become invalid.
- Exact repeated fingerprints already seen in the file or saved registration events become duplicate.
- Existing phone/full-name candidates or repeated in-file normalized phone/full-name combinations become review.
- Remaining rows become new.
- None of these decisions merges a participant automatically.
- Source CSV encrypted with Laravel Crypt and stored at `storage/app/private/info-imports/{uuid}.csv.enc`.
- Batch and row preview records are persisted transactionally.
- Uploaded file is deleted if preview database persistence fails.
- No participant/attendance rows are created until confirmation.

### Row classifications and states

Classification values currently used:

`new`, `review`, `match`, `skip`, `duplicate`, `invalid`.

Processing states currently used:

`pending`, `created`, `matched`, `skipped`, `duplicate`, `invalid`.

These are string columns; future code should not assume a separate database enum.

- Invalid/duplicate rows can already contribute to processed/skipped counters during preview.
- Therefore processed > 0 does **not** necessarily mean participant records have been imported.
- New/matched rows are valid accepted rows.
- Unresolved review count blocks confirmation.
- Review actions: create separate attendee, append to a reviewed attendee ID, or skip.
- Matching an existing attendee does not overwrite trusted profile fields.
- Candidate display is limited to 10 suggestions per row; an explicitly reviewed valid ID can be entered.
- Rows on different preview pages must all be resolved.
- Decisions are mutable only while the batch is previewed/failed and the row remains pending.
- A row ID from another batch is rejected by batch-scoped lookup.
- Invalid source data is corrected by uploading a corrected file, not editing raw payload in this UI.

### Commit and job behavior

`InfoCsvImport::commit`:

1. Authorize info.import.
2. Lock batch row.
3. No-op if already processing/completed.
4. Require previewed/failed state.
5. Reject remaining review rows.
6. Set processing and clear failure message.
7. Audit commitment.
8. Dispatch `CommitInfoImport` on `imports` using before-commit dispatch.

The **database queue** is intentional: job insertion shares database transactional behavior. Review dispatch/transaction guarantees before switching queue drivers.

`CommitInfoImport`:

- Public parameters: batchId and actorId.
- Declared tries 3 and timeout 120.
- Lock 2026 event, then batch.
- Stop if batch is no longer processing.
- Reload actor and recheck info.import/active state.
- Read at most 100 pending rows in ID order with locks.
- Explicit skip → skipped.
- Accepted new/match → original-time registration service call.
- Source replay → duplicate.
- New attendee → created.
- Reviewed existing attendee → matched.
- Recompute counters.
- Dispatch next chunk if pending rows remain.
- Otherwise mark completed and audit final counters.

Most chunk exceptions are caught inside `handle`:

- Entire interrupted chunk rolls back.
- Batch becomes failed with a safe message.
- Log records batch ID and exception class, not raw row contents.
- The job normally finishes from Laravel's perspective after setting the paused batch.
- **Do not assume these business failures will appear in failed_jobs or automatically retry three times.**
- UI retry is the supported resumption path.
- The `failed` hook also marks batches failed for worker-level failures.
- Verify actual timeout/process-supervisor support on the production platform; Windows CLI lacks some Unix process facilities.

### UI and results

- Import history: paginated batches and counts.
- Preview: 25 rows/page.
- Possible duplicates open their review controls.
- Ordinary new-row controls are optional expandable details.
- Commitment requires an accepted confirmation checkbox.
- Progress refresh is manual via Refresh progress.
- History shows total/processed/created/matched/skipped/invalid/review/automatic attendance.
- Row-results CSV contains source row, classification, processing result, attendee ID and validation errors.
- Result download is authorized and audited.
- No raw-file download route exists.
- Retention cleanup is not yet implemented.

### Actual source data availability

The handoff package documents:

- `registrations-2026-10-18.csv`: 1,749 rows, 18 columns, 1,638 distinct raw phone strings, 1,565 In Person and 184 Virtual choices.
- **The real personal source file is not in this workspace.**
- Only synthetic header templates and source audits are supplied.
- These counts are reference metadata, not data loaded into the app and not production dashboard values.

## 15. Reports and exports

### Filters

- session_id
- day (1–6)
- search (max 100)
- status: all / present / absent
- attendance_preference
- registration_source
- member
- first_time

Selected session must belong to the current 2026 conference. A conflicting day/session selection is rejected.

### Shared cohort definition

`InfoReports::base`:

- Info Attendee records only, excluding soft-deleted records.
- Year 2026 from `COALESCE(registration_original_at_utc, created_at)`.
- Name/phone/source/preference/member/first-time filters.

For each selected session:

- Cutoff = earlier of now and configured session end.
- Eligible = filtered attendees whose original registration (or creation fallback) is no later than cutoff.
- Present = eligible people with a valid unvoided attendance row for that session.
- Absent = eligible minus present, only when a session has configured end.
- Rate = present / eligible × 100, rounded to one decimal; N/A for zero denominator or unconfigured window.

A future/in-progress session report is as-of the labelled cutoff, not a final post-event absence statement.

### Totals

- Registered: number of distinct attendee profiles in filtered cohort.
- Unique present: distinct attendee IDs in unvoided checks across selected session/day scope.
- Session check-ins: number of unvoided attendee/session records in that scope.
- Daily count: people with at least one valid check-in on that day, deduplicated across morning/evening.
- Status filter changes the attendee list; headline/summary cohort remains as labelled.
- Breakdown tables group filtered registrations by preference, member, first_time, source and heard_about.
- Breakdowns show up to 20 groups and label unanswered values; they are not exhaustive charts when >20 groups exist.
- Admin dashboard Info Com counts use the same base cohort.
- No Guest Service counts enter any of these definitions.

### Exports

Super Admin only:

- Attendance matrix: attendee ID, first name, surname, phone, email, preference and one column for each authoritative current-conference session.
- Matrix cells: Present / Absent / Not eligible / Not configured, accounting for voids and registration cutoff.
- Session summary: session, local date, eligible, present, absent, rate and WAT cutoff.

`CsvExport`:

- Streams rows.
- UTF-8 BOM and explicit CSV escaping.
- Formula-risk values beginning with optional whitespace/control characters followed by =, +, -, @ are prefixed with an apostrophe.
- Applies even to plus-prefixed phones for spreadsheet safety.
- Private no-store response headers.
- Same filtered query definitions as report lists.
- Export actions audited.

No XLSX report export or interactive chart library is implemented.

## 16. Guest Service foundations and missing workflows

### Already available for reuse

- Required authoritative session FK.
- Guest fields/default status.
- Own-entry / active-assignment query scope.
- GuestRecordPolicy.
- FollowUpAssignmentPolicy and ReportPolicy foundations.
- Guest → session / assignments / activeAssignment / activities / statusHistory relationships.
- Assignment → guest / officer relationships.
- Activity → guest / assignment relationships.
- StatusHistory → guest relationship.
- DB enum constraints, required session FK, active-owner unique index.
- Independent guest import provenance tables.
- Existing guest factory and model/policy tests.
- Branded layout and role-specific workspace shells.

### Do not overstate what exists

The application cannot yet use an ordinary UI to:

- Create, list, inspect or edit operational guest records.
- Save a session-retaining batch of guest entries.
- Preview/commit Guest Service CSV/XLSX.
- Provision historical guest event/session mappings.
- Bulk assign/reassign/unassign guests.
- Record contact attempts or enforce contact/status history atomically.
- Display full officer lists, contact timelines or follow-up reports.

The model/state tests prove foundations, not those missing workflows.

### Status / assignment business validation still needed

The DB constrains allowed status strings, but does not automatically:

- Create initial status history.
- Require a successful activity for CONTACTED.
- Require a failed activity for UNREACHABLE.
- Keep current status and status history synchronized.
- Verify an assignment/activity/history row belongs to the same guest.
- Validate that a selected assignee is an active Follow-Up Officer.
- Apply scoped guest access to new endpoints automatically.
- Revoke historical officer detail/export access if a controller bypasses the current-assignment scope.

Implement these in authorized transactional services and tests.

## 17. Phase 3 implementation plan

Build **Guest Service capture and imports** next when authorized.

### 17.1 Entry and batch entry

- Dedicated Guest Service controller/requests/services and/or Livewire components.
- Required full name, phone and authoritative conference session.
- Optional names, gender, address, referral source, original capture timestamp and notes.
- Server sets creator; do not trust a posted creator/import ID.
- Default NOT CONTACTED and no active assignment.
- Session selection reflects actual encounter, not data-entry instant.
- Batch entry preserves the selected session across successive guests.
- Post-session entry remains valid even after a session ends or is disabled.
- Use safe retry/idempotency behavior for double submissions.
- Preserve raw phone and normalized search phone.
- Guests with the same name/phone may remain distinct encounters.
- Detect likely duplicates **within Guest Service only** and ask for an explicit in-domain decision.
- No Info Com search, auto-population, matching or writes.
- Reuse branding and fast mobile input patterns.
- Decide/document any batch-tracking schema additions rather than overloading Info Com imports.

### 17.2 Guest browse, detail and editing

- Guest-only search and paginated list.
- Session/day/status/assignment filters appropriate to each role.
- Super Admin and Follow-Up Admin: authorized full Guest Service scope.
- Data Entry: own records under current policy.
- Officer: active assigned records only.
- Server-side policy authorization on every guessed ID.
- Explicit archival/event-year filtering.
- Creation timestamps separate from encounter session.
- Safe correction/audit of guest profile details.
- Ordinary Data Entry cannot assign officers or post current contact status.
- Do not expose assignment/contact actions before their Phase 4 services are implemented.

### 17.3 Guest CSV

Template supplied:

```text
Event Date,Event Session,Full Name,Phone,Gender,Home Address,Heard About,Comment
```

Resolve date/session into the authoritative session FK; reject ambiguity rather than guessing. Maintain a guest-specific private upload, dry-run preview, duplicate review, commit, progress, retry and results pipeline. Add guest import models/jobs and new guest migration fields as needed.

Reuse low-level utilities such as phone normalization and safe CSV cell formatting; do not reuse Info Com registration/attendance services for guest records.

### 17.4 Historical 2025 XLSX

Source documented as `SUMMIT 2025 GUEST DATA.xlsx`, not included with personal rows.

Reference audit:

- Nine worksheets, one empty `Sheet2`.
- Eight dated worksheets.
- Mixed header aliases and column ordering.
- Original dates/session labels may differ from later capture timestamps.
- Broken formula/result values such as `#NAME?`.
- Contradictory status/comment values.
- Repeated rows across sheets may be legitimate distinct encounters.

Header aliases:

| Source | Meaning |
|---|---|
| Timestamp / DATE | Original capture/source timestamp, timezone reviewed |
| EVENTS / EVENT | Encounter date/session descriptor, explicitly mapped |
| NAME | full_name |
| PHONE | raw/normalized phone |
| FOLLOW UP OFFICER | legacy text, not automatic user/account creation |
| GENDER | gender |
| HOME ADDRESS | home_address |
| FOLLOW UP STATUS | reviewed exact status mapping |
| COMMENT | preserved source note |
| HOW DID YOU HEAR ABOUT THE CONFERENCE | heard_about |

Requirements:

- Select worksheets and map headers by name, never fixed Excel column letters.
- Ignore genuinely blank sheets/rows.
- Reject/quarantine formula errors and invalid names.
- Preserve raw officer/status/comment text and source worksheet/row provenance.
- Explicitly configure/map **2025** event sessions.
- Do not invent 2025 clock times or convert 2025 encounters into 2026.
- Flag unknown officers and contradictory statuses.
- Never infer CONTACTED from “SMS sent,” “not picking,” or an unanswered attempt.
- Canonical legacy assignments/statuses require explicit reviewed import mode and valid account mapping.
- Default to preserving unverified legacy metadata rather than inventing successful contact activities or creating staff.
- Repeat source imports must be idempotent.
- No Info Com writes.

An XLSX parser package is **not installed**. Inspect requirements and existing dependencies before choosing a supported package. Do not fake XLSX support by reading it as CSV. Follow the current workspace's dependency authorization rules.

### 17.5 Phase 3 verification

Add tests for:

- Real guest create/list/detail/edit endpoints and Livewire actions.
- Required session selection and FK.
- Session-retaining batch input.
- Entry after encounter date.
- Default status/unassigned state.
- Own-entry policy and officer IDOR denial.
- Profile changes cannot post status/assignment/provenance.
- Same phone in both modules produces no cross-writes.
- Same phone within Guest Service warns but does not silently merge.
- Event-year/session filter integrity.
- Guest CSV dry-run/commit/replay/invalid rows.
- XLSX header order/sheets/formula errors/legacy conflicts.
- Historical 2025 data excluded from 2026 reports.
- Mobile screenshots and keyboard/validation behavior.
- Transaction rollback and queue retry with independent guest provenance.

## 18. Phase 4 implementation plan

Build follow-up operations after capture is verified.

### Assignment service

- Admin filters queue by session/day/status/assignment.
- Select many guests, choose one active officer, explicitly confirm selection.
- Validate every guest and assignee server-side.
- Lock guest rows in consistent ID order.
- Use an all-or-nothing transaction or a clearly documented partial-result contract.
- Only one active primary assignment per guest.
- Reassignment ends the prior assignment and creates a new one; do not overwrite history.
- Support unassignment and required administrative reasons where policy requires.
- Immediately revoke former officer access through the current-assignment scope.
- Audit actor, time, guest and officer changes.
- Do not imply a Guest Service assignment exists because of source spreadsheet text.

### Officer workflow

- My assigned guests only.
- Session label on every guest.
- Guest detail and safe tap-to-call.
- Contact attempt: method, actual attempt timestamp, outcome, comment.
- Recheck active assignment inside the same transaction as the write.
- Append activity and status history, update current status atomically.
- NOTE_ONLY does not imply successful contact.
- CONTACTED requires actual two-way contact.
- UNREACHABLE requires a failed attempt.
- Preserve earlier attempts on subsequent successful contact.
- Test reassignment racing with an officer update.
- Never expose another officer's current/historical private records through detail, timeline or export endpoints.

### Follow-up reporting

- Current total / assigned / unassigned.
- Current NOT CONTACTED / CONTACTED / UNREACHABLE counts.
- Attempted guests vs number of attempts.
- Contact success: distinct successfully contacted guests divided by distinct attempted guests within consistently labelled scope.
- Separate attempt history from current status.
- Officer workload uses **current active** assignments.
- Reassignment history must not double-count current workload.
- Filter encounter session/date, event year, officer, state and activity period.
- Guest roster, officer detail, status summary, activity timeline and safe exports.
- Clearly define historical/as-of views before presenting them.

## 19. Phase 5 and launch work

- Complete remaining acceptance scenarios.
- Physical low-end Android checks, Safari/iOS equivalent, keyboard navigation, touch targets and accessibility review.
- Intermittent network, stale sessions, duplicate submissions and recovery UX.
- Sustained multi-operator concurrency and representative-volume imports.
- Reconcile reports and exported row-level data.
- Real outbound mail/provider configuration.
- Production database account with least privilege.
- Supported production database/server versions.
- HTTPS, secure cookies, APP_ENV=production and APP_DEBUG=false.
- Public document root only; never serve project root.
- Worker supervision/restart policy, queue monitoring and failed/paused import alerts.
- Encrypted offsite backups of database, private originals and APP_KEY; tested restoration.
- Approved retention/deletion/archival policies and upload cleanup.
- Operational session schedule, staff onboarding and correction policies.
- Audit search/domain filters if required.
- General allowed-key settings editor if required.
- Optional read-only role/dark mode only if requested and validated.
- No production deployment has occurred in this chat.

## 20. Design system and frontend conventions

Reference: `docs/DESIGN_SYSTEM.md` and `prompt3.md`.

### Official identity

- Original source: `docs/logo.png`.
- Unchanged production copy: `public/brand/summit-logo.png`.
- Both hashes matched when verified.
- Only one logo asset was supplied.
- No additional website reference images or alternate logo versions are present.
- White lettering requires the deep navy treatment.
- Preserve proportions. Never regenerate, recolor or stretch the official logo.

### Colors

| Token / purpose | Value |
|---|---|
| Official primary red | #F20D19 |
| Deep navy | #111A3D |
| White | #FFFFFF |
| Ink / black | #101114 |
| Accessible primary button red | #CE101C |
| Surface | #F6F7FB |
| Border | #E2E5EE |
| Muted text | #5D6475 |

Use semantic CSS variables in `resources/css/app.css`. The default is light. A dark theme toggle/UI has not been delivered.

### Interaction/layout

- System sans-serif stack; remote font integration removed.
- Responsive sidebar/header and shared component layouts.
- Minimum 44px controls/touch targets.
- 4px spacing rhythm and restrained borders/shadows.
- Desktop/tablet/mobile handling with actual CSS breakpoints; inspect CSS rather than assuming every design-document breakpoint was used literally.
- Tables scroll within `.table-wrap`, which is positioned relative so absolute screen-reader labels do not overflow the document.
- Main flex frame/content have min-width 0.
- Labels, explicit errors, status text, skip link, focus rings and reduced-motion support.
- Status cannot be color-only.
- Use the existing `<x-layout>`, `<x-auth-layout>` and `<x-icon>`.
- Livewire supplies Alpine. **Do not import a second Alpine instance.**
- Rebuild assets after CSS/template-class changes.
- Empty states must be honest and use actual counts, never fabricated operational numbers.
- Keep implementation jargon/phase numbers out of ordinary product flows where it does not help staff.

## 21. Tests, browser checks and evidence

### Last completed full implementation verification

**97 tests passed, 536 assertions.**  
**11 browser/concurrency scenario groups passed.**

Also passed:

- Full-project Pint formatting.
- Blade view compilation.
- Vite production build.
- Composer strict validation.
- Application migration/status checks.
- Local /login HTTP 200.
- Previous dependency/platform/advisory checks during foundation verification.

This handoff task did not rerun the full suite or change application code. Re-run appropriate tests after future implementation changes.

### Test isolation

`tests/TestCase.php`:

- Extends Laravel testing base.
- Uses RefreshDatabase.
- Seeds configured role/conference seeders.
- Refuses to proceed unless default connection is mysql and configured database is exactly `summit_2026_test`.
- Provides `userWithRole` helper.

`phpunit.xml`:

- APP_ENV testing.
- DB_CONNECTION mysql forced.
- DB_DATABASE summit_2026_test forced.
- Test DB URL empty.
- Low bcrypt cost.
- Array cache/mail/session.
- Sync queue by default.
- Tests do not rely on the real application staff account.

Do not bypass this guard in new test classes. Do not point tests at the application database.

**Run PHPUnit and browser smoke sequentially.** They share the disposable test database; parallel execution can destroy each other's fixtures/migrations and create misleading failures.

### Commands

```powershell
php artisan test --compact

# Narrow examples:
php artisan test --compact tests/Feature/InfoImportTest.php
php artisan test --compact tests/Feature/InfoOperationsTest.php

# Formatter:
php vendor/laravel/pint/builds/pint --format agent

# Templates/assets:
php artisan view:cache
npm run build

# Dependencies:
php D:\php84\composer.phar validate --strict
php D:\php84\composer.phar check-platform-reqs
php D:\php84\composer.phar audit --no-interaction
npm audit
```

AGENTS asks for Pint --dirty, but the folder currently has no Git repository, so that invocation cannot run. Full-project formatting was used successfully. Do not describe the failed dirty-only invocation as passed.

### Test inventory

| Test file | Last cases | Focus |
|---|---:|---|
| AuthenticationTest.php | 11 | Login, hashing, throttling, reset, inactive users, logout, CSRF |
| AuthorizationTest.php | 10 | Role redirects, route isolation, Super Admin, own scopes, assignment policy/revocation |
| BootstrapAdminTest.php | 3 | Initial hidden-password setup, existing-admin refusal, too-short password |
| DatabaseIntegrityTest.php | 8 | FK/enum/default/unique/check constraints and append-only/redacted audit |
| FoundationTest.php | 7 | Eight-session seed, domain independence, guest defaults/session relationships |
| PasswordPolicyTest.php | 15 | Lowercase/uppercase/numeric/symbol eight-character values and seven-character rejection across three flows |
| SessionScheduleTest.php | 7 | Unconfigured, eight boundaries, WAT/UTC, overlap, adjacency, Livewire save, locked session |
| StaffManagementTest.php | 4 | Provisioning, permission denial, deactivate/self-change guard, validation |
| InfoOperationsTest.php | 11 | Registration, auto/manual attendance, idempotency, search, policies, corrections, edit/audit, screen rendering |
| InfoImportTest.php | 13 | UTC/date boundary/gap, earliest reviewed event, actor revocation, dry run/encryption, original time, replay, fields, review, chunks, rollback/retry, scope, confirmation |
| InfoReportsTest.php | 6 | Eligibility, daily uniqueness, N/A, filtered safe exports, permission denial, voids |
| Feature/ExampleTest.php | 1 | Root/protected-dashboard redirect |
| Unit/ExampleTest.php | 1 | Basic scaffold sanity |
| **Total** | **97** | **536 assertions on last full run** |

Some tests still use old method names containing “weak password”; actual rules and data-provider tests enforce the current eight-character-only minimum.

### Browser smoke

Files:

- `tests/Browser/smoke.mjs`
- `tests/Browser/fixtures.php`

Install the QA dependency if npm ci removed its extraneous local install:

```powershell
npm install --no-save --package-lock=false --ignore-scripts playwright
node tests/Browser/smoke.mjs
```

Uses Chrome at:

`C:\Program Files\Google\Chrome\Application\chrome.exe`

Adapt explicitly for another machine; do not assume this Windows path exists elsewhere.

Smoke setup:

- Hard-restricted to testing environment and summit_2026_test.
- Random test-only account prefix/password.
- Five test role accounts.
- Synthetic current test session, never a real application session.
- Pending/race attendees.
- Separate PHP web server at port 8001.
- Separate database-queue imports worker using the test database.
- Two separately spawned PHP processes attempt one check-in simultaneously.
- A real 101-row CSV exercises 100-row chunk handoff and final completion.
- Fixture cleanup removes its attendees/import data/files/session/accounts.
- Browser worker/server terminated afterward.
- Application web server/worker and database are not used as fixtures.

Verified groups:

1. Real simultaneous PHP/database check-in yields one row.
2. Admin login/navigation/desktop overview.
3. Livewire session editor validation/cancel.
4. CSV upload/dry-run/actual queue completion/result download.
5. Attendance reports/matrix download on mobile.
6. 360px navigation, staff/session tables, overview.
7. Onsite forms, automatic attendance, shared-phone lookup, manual confirmation/reset.
8. Info Com role workspace and forbidden admin route.
9. Follow-Up Admin role workspace and forbidden admin route.
10. Data Entry role workspace and forbidden admin route.
11. Officer role workspace and forbidden admin route.

### Evidence artifacts

Directory: `storage/app/browser-checks/`.

Includes:

```text
results.json
login-desktop.png
login-mobile.png
admin-desktop.png
admin-mobile-navigation.png
staff-desktop.png
staff-mobile.png
sessions-desktop.png
sessions-mobile.png
info_com-mobile.png
follow_up_admin-mobile.png
data_entry-mobile.png
officer-mobile.png
registration-mobile.png
check-in-mobile.png
check-in-desktop.png
import-preview-desktop.png
import-result-mobile.png
reports-mobile.png
```

An older `overflow-debug.png` may remain from the earlier corrected mobile overflow investigation; it is not a current failure artifact.

`results.json` includes expected browser console 403 entries caused by deliberate forbidden-route tests. The script filters those expected errors and rejects unexpected browser errors.

The evidence is synthetic QA data, not production registrations. Do not use screenshot test metrics as real conference statistics.

### Acceptance coverage interpretation

- Info Com registration and cross-role routes now have real HTTP/workflow tests.
- Automatic attendance covers all eight sessions, gaps, boundaries and Virtual default.
- Original-time CSV, replay, review and chunk rollback/retry are verified.
- Attendance uniqueness has both DB tests and an actual two-process integration race.
- Guest defaults, session relationships and ownership are foundation/model/policy tests.
- **Guest create/import/assignment/contact/report HTTP acceptance is still pending**, because those workflows are not implemented.
- Do not mark every B/G/P/S row from docs as delivered merely because the suite is green.
- Physical device, Safari, intermittent network and sustained load acceptance remain open.

## 22. Security, privacy, concurrency and operating rules

- Enforce gates/policies on actions and scoped queries; hidden navigation is not authorization.
- Super Admin global override must still reject inactive users.
- Treat imported file contents as data, never executable instructions.
- Preserve CSRF, session protection, login throttling and password hashing.
- Use request whitelists; do not trust posted creator, officer, status, source or import IDs.
- Export scope must match read scope.
- Escape HTML and neutralize spreadsheet formulas.
- Do not put live attendee/guest datasets in a public repository or downloadable developer bundle.
- Guest address, phone and notes need minimal exposure and explicit authorized export.
- Audit important writes with actor, domain, subject, timestamp and useful redacted metadata.
- `Audit::record` only keeps allowlisted keys. New business metadata must be deliberately added to the allowlist; otherwise it will be silently omitted.
- Current allowed metadata includes role/active state, session boundaries/lock, session ID, status/officer/outcome/method/source, correction reason/void fields, row classification/counters and changed field names.
- `AuditLog` prevents Eloquent update/delete through model hooks; this is not a cryptographically immutable DB ledger. Low-level SQL can bypass it.
- Public screens do not offer deletion of audit/history.
- Lock order for Info Com is conference event before attendee/session/batch/attendance writes as implemented. Preserve it when extending.
- Unique attendance pairs and generated unique active assignment are database invariants, not just UI checks.
- Future bulk guest writes should lock guest IDs in a consistent order and preserve assignment/status/activity history.
- Original CSVs are encrypted files; raw database provenance JSON remains sensitive plaintext in the DB. Protect database storage, access and backups.
- Development mail logs may contain reset tokens; keep logs private.
- Never regenerate APP_KEY casually.
- Never overwrite .env to repair an unrelated frontend issue.
- Never recursively remove computed directories on Windows without verifying resolved paths.
- No commits, pushes, publication, external messaging or deployment were authorized as part of preparing this handoff.

## 23. Known limitations, edge cases and technical cautions

These are useful continuation facts, not a claim of newly reproduced defects.

1. **Real session times are missing.** Closed check-in is intentional until the administrator configures/enables actual windows.
2. **Mail is local log-only.** UI reset flow works; real delivery is absent.
3. **Guest workflows are foundations only.** Do not infer full services from model names.
4. **No guest importer package/models/jobs/UI.** Guest provenance schema does not include Info Com's later progress fields.
5. **Hardcoded current conference year 2026** exists in seeders, currentConference scope, importer validation, report base and write checks. Future multi-event support needs deliberate refactoring, not a global unreviewed string replacement.
6. **Database portability is not established.** MySQL/MariaDB-specific reporting/generated-column behavior is used.
7. **Preview is synchronous/in-memory with queries per row.** Limits bound the input, but maximum 10k-row performance has not been benchmarked. Real personal 1,749-row source was not supplied.
8. **Chunk jobs serialize through the event lock.** Correctness was preferred over maximum throughput; measure contention before changing it.
9. **Caught import failures pause the batch rather than necessarily entering failed_jobs.** Use batch state/log class and safe UI retry.
10. **No worker supervisor/autostart.** The local process stops on machine/process termination and must be relaunched.
11. **No automatic original-file retention cleanup.**
12. **Progress includes skipped/invalid preview rows.** Do not equate processed count with new participants.
13. **Candidate preview is a snapshot, not global identity certainty.** Concurrently changed data may warrant a future re-review policy; no automatic phone-based identity claim is made.
14. **Source fingerprints are exact canonical raw rows.** Whitespace/text edits can produce a new source event and possible review, not automatic semantic equivalence.
15. **Only basic original-time void/restore corrections exist.** General backdate/transfer needs approved policy/UI.
16. **No general settings UI or allowlisted settings service.**
17. **No permission-grant editor or delegated Follow-Up Admin account management.**
18. **Data Entry defaults to own guest entries.** Do not broaden to all guests without explicit policy direction.
19. **visibleTo does not itself apply archival/year filtering.**
20. **Model fillability is not authorization.** Contact/status/creator/provenance operations need services and request whitelists.
21. **Activity/assignment/status FKs need same-guest semantic validation in future services.**
22. **No initial/status-history workflow is automatically triggered by ordinary guest model creation.** The default status exists, but history/state-machine services remain to build.
23. **No physical/virtual attendance modality workflow.** Virtual preference never automatically proves physical presence.
24. **No chart library, XLSX reporting export, dark-mode UI or optional Report Viewer.**
25. **No sustained production-scale concurrency/device/network proof.** The two-process check-in and browser viewport checks are narrower evidence.
26. **No source-control history/diff.** Reinspect files/status before assuming a clean baseline.
27. **Some older docs are historical/future tense.** Phase 2 completion/source supersede their still-pending Info Com descriptions.
28. **Unused Laravel welcome view remains.** It is not part of app navigation.
29. **Browser port 8001 and test DB are shared QA resources.** Do not start duplicate smoke runs or parallel migration tests.
30. **Soft-deleted attendee/source-event replay edge behavior has not been given a full admin UX.** There is no ordinary attendee delete UI now; if adding archival, review idempotent replay and provenance behavior first.

## 24. Troubleshooting

| Symptom | Check / action |
|---|---|
| composer command fails/hangs in PowerShell | Use `php D:\php84\composer.phar ...` |
| Composer optimized class map stalls | Current standard autoload mode is intentional; regenerate standard autoload and inspect before optimizing |
| PHP requirement error | Use PHP 8.4.1+; current tested executable is D:\php84\php.exe |
| Database connection fails | Confirm XAMPP MariaDB, loopback host/3306, application DB and .env without printing secrets |
| Wrong database about to be refreshed | Stop; tests must use summit_2026_test, never application DB |
| /login unavailable | Check port 8000 and project PHP server processes |
| New CSS/classes absent | npm run build; check public/build manifest; clear/recompile Blade views if needed |
| Unable to locate Vite manifest | npm ci then npm run build |
| Alpine duplicate/erratic behavior | Remove duplicate Alpine imports; Livewire already supplies it |
| No active session | Expected until real windows configured/enabled; also check UTC conversion and lock state |
| Session expired during selection | Refresh/current server session; retry against displayed session, do not force backdated manual write |
| Already present | Idempotent success state, not a reason to add another attendance row |
| Voided row refuses ordinary check-in | Super Admin must explicitly restore with reason |
| CSV rejected before preview | UTF-8 CSV, correct extension, 18 named headers, size and content checks |
| Preview review count >0 | Resolve every candidate row, including other pages |
| CSV invalid Created | Explicit timezone, valid 2026 ISO date, no future instant |
| Import stuck processing/queued | Worker must listen to imports queue and application DB; inspect process and logs |
| Import batch failed but failed_jobs empty | Expected for caught chunk failure; batch pauses, log has exception class, UI retry |
| Worker uses old code | Restart the correct project worker; no supervisor auto-relaunches it |
| Password reset email not received | MAIL_MAILER=log; configure outbound mail |
| Another role sees forbidden page | Expected least privilege; investigate grants only if authorized |
| Officer sees old assignment unexpectedly | New endpoint likely missed visibleTo/current-active assignment scope |
| Mobile table overflows entire page | Preserve min-width constraints and relative .table-wrap; inspect absolute screen-reader labels |
| Browser QA cannot import playwright | Reinstall no-save test dependency after npm ci |
| Browser test cannot start | Port 8001, Chrome executable path and test DB state; run sequentially with PHPUnit |
| Pint --dirty fails | No Git repository; run full Pint formatter |
| Encrypted CSV cannot decrypt after key change | Restore original application key through authorized recovery; do not discard data |

## 25. Documentation and file inventory

### Root control/setup files

```text
AGENTS.md
CLAUDE.md
README.md
handoff.md
prompt1.md
prompt2.md
prompt3.md
composer.json
composer.lock
package.json
package-lock.json
phpunit.xml
vite.config.js
artisan
boost.json
.env                 # private, already configured; do not copy into handoff
.env.example
.editorconfig
.gitattributes
.gitignore
.npmrc
.phpunit.result.cache # generated/ignored
```

`vendor/` and `node_modules/` are installed/generated dependencies. `public/build/` and compiled framework caches are generated output, not source edits.

### Documentation guide

| Path | Contents |
|---|---|
| `docs/README.md` | Requirement package index and boundaries |
| `docs/01_PRODUCT_REQUIREMENTS.md` | Product and nonfunctional scope |
| `docs/02_WORKFLOWS.md` | Independent operational workflows |
| `docs/03_ROLES_PERMISSIONS.md` | Permission matrix and ownership |
| `docs/04_DATABASE_DESIGN.md` | Logical/physical schema guidance |
| `docs/05_ATTENDANCE_RULES.md` | Exact-session attendance and corrections |
| `docs/06_IMPORT_SPECIFICATIONS.md` | Source headers, mapping, historical caveats |
| `docs/07_UI_SCREEN_SPECIFICATIONS.md` | Role-specific screens and states |
| `docs/08_API_CONTRACTS.md` | Illustrative action/JSON contracts and errors |
| `docs/09_ANALYTICS_REPORTS.md` | Separate metric/denominator definitions |
| `docs/10_SECURITY_OPERATIONS.md` | Security/privacy/reliability/release checklist |
| `docs/11_ACCEPTANCE_TESTS.md` | B/A/G/P/S scenarios |
| `docs/12_DEVELOPMENT_PLAN.md` | Phase sequence and go-live decisions |
| `docs/CODEX_IMPLEMENTATION_PROMPT.md` | Original implementation handoff instructions |
| `docs/ARCHITECTURE_DECISIONS.md` | Resolved conflicts, password and Phase 2 decisions |
| `docs/IMPLEMENTATION_PROGRESS.md` | Latest status at top, historical Phase 1 below |
| `docs/PHASE_1_COMPLETION_REPORT.md` | Foundation evidence at Phase 1 completion |
| `docs/PHASE_2_COMPLETION_REPORT.md` | Delivered Info Com contract and evidence |
| `docs/DESIGN_SYSTEM.md` | Logo, tokens and component/accessibility guidance |
| `docs/SUMMIT_2026_Laravel_Requirements_v2.md` | Legacy v2, superseded on conflicts |
| `docs/reference/Requirements_v2.md` | Identical legacy copy |
| `docs/reference/SOURCE_FILE_AUDIT.md` | Actual source metadata, no live rows |
| `docs/schemas/allowed_values.json` | Status/method/session code reference |
| `docs/schemas/info_com_registration_template.csv` | Synthetic/header-only Info Com template |
| `docs/schemas/guest_service_2026_template.csv` | Synthetic/header-only guest template |
| `docs/logo.png` | Official original logo |

### Remaining configuration directories

Existing config files:

```text
config/app.php
config/auth.php
config/cache.php
config/database.php
config/filesystems.php
config/logging.php
config/mail.php
config/queue.php
config/services.php
config/session.php
config/summit.php
```

Public assets/entry files:

```text
public/index.php
public/brand/summit-logo.png
public/favicon.ico
public/robots.txt
public/build/                     # compiled by `npm run build`, committed to git
```

Generated/private operational locations:

```text
bootstrap/cache/
storage/app/private/              # private local disk
storage/app/private/info-imports/ # encrypted CSV originals
storage/app/browser-checks/       # synthetic QA evidence
storage/framework/               # session/view/cache runtime files
storage/logs/                    # private app/server/worker/mail logs
```

### Local agent guidelines

- Read `AGENTS.md` before edits.
- Boost's generated guidelines require applicable `.ai/rules` if present; **that directory is currently absent**.
- The mentioned `testing-best-practices` skill was not present in the checked local skills path; do not falsely claim it was read.
- Use current installed API versions.
- Use existing Laravel conventions and artisan generators.
- Use typed PHP methods and braces.
- Authorize/validate Livewire actions as server requests.
- Use meaningful tests, appropriate formatting/build checks and actual results.
- User instructions take precedence over generic local boilerplate; existing authorization to implement a requested phase should not trigger repeated permission questions for routine work.
- Do not spawn/delegate agents unless the current user's instructions or applicable local rules explicitly authorize it.
- Do not manufacture progress, test results or source-control actions.

## 26. Decisions still needed

These items should not block independent Phase 3 implementation where current safe defaults suffice, but must be resolved before the relevant launch behavior is finalized.

| Decision | Current safe behavior |
|---|---|
| Actual start/end times for all eight 2026 sessions | Null, disabled; administrator configures |
| Virtual registration attendance policy | No automatic physical presence |
| Additional mandatory guest fields | Name, phone and session are the confirmed baseline; other fields optional unless approved |
| Data Entry cross-user editing | Own entries only under current policy |
| Historical 2025 migration scope | No live source file loaded; preserve separate year and explicit mapping |
| Legacy officer/status contradictions | Review/quarantine; no guessed account or successful contact |
| Guest profile reconciliation across encounters | One independent guest encounter per row; no automatic merge |
| Import grants beyond Super Admin | Not automatically granted |
| Delegated team account administration | Not enabled |
| Optional Report Viewer | Not implemented |
| Retention / consent / archive / cleanup periods | Not invented; organization policy required |
| General attendance backdate/transfer window | Not implemented; approved policy needed |
| Production hosting/database/mail/backup targets | Not configured |
| Dark-mode release | Tokens structured only, no toggle or validated dark palette |

The owner prefers action over repeated confirmations. Choose and document ordinary reversible implementation details within confirmed scope. Ask only where missing business input actually determines correctness; do not invent conference times or historical identity/status facts.

## 27. Acceptance checklist and first continuation prompt

### Before the next coding turn

- [ ] Read this handoff, AGENTS and the controlling requirements.
- [ ] Verify cwd and actual source state.
- [ ] Recheck Git presence instead of assuming it.
- [ ] Confirm existing app/account/data are preserved.
- [ ] Verify application/test DB separation.
- [ ] Inspect running server/import worker; avoid duplicates.
- [ ] Establish the latest relevant test baseline without racing browser fixtures.
- [ ] Inspect GuestRecord scope/policies and guest import schema before designing Phase 3.
- [ ] Keep Info Com and Guest Service completely independent.

### Phase 3 definition of done

- [ ] Real authorized guest entry/list/detail/edit workflows.
- [ ] Required actual session selection, including after-event entry.
- [ ] Batch entry retains selected session.
- [ ] New guests default NOT CONTACTED and UNASSIGNED.
- [ ] Own-entry Data Entry scope, active-assignment officer scope and IDOR tests.
- [ ] Guest-only duplicate warnings/review, no silent merges.
- [ ] Independent guest CSV/XLSX preview/confirm/commit/retry/results.
- [ ] Historical 2025 sessions/year preserved, ambiguous mappings reviewed.
- [ ] No accidental Info Com writes in all new workflows.
- [ ] Existing 97-test baseline still passes, plus meaningful new tests.
- [ ] New browser/device-scope checks and screenshots reviewed.
- [ ] Migration/build/formatter/authorization checks accurately reported.
- [ ] Docs/progress/handoff updated with actual results and honest remaining work.
- [ ] No deployment, commit or push unless separately authorized.

### Suggested prompt to give the next AI

> Historical Phase 3 kickoff prompt (completed; consult the current update above): Read handoff.md, AGENTS.md, prompt1.md, prompt2.md, prompt3.md and the relevant docs. Continue the existing SUMMIT 2026 Laravel application with Phase 3: independent Guest Service capture, session-retaining batch entry, own-scope guest search/detail/edit, duplicate review, guest CSV and historical 2025 XLSX imports. Reuse the existing schemas, permissions, branding and services where appropriate without linking guests to Info Com. Preserve the existing administrator, application key, database and completed Phases 1–2. Use the running XAMPP MariaDB, keep tests restricted to summit_2026_test, verify actual behavior and report results. Do not invent session times, merge by phone, create guests from attendance, or claim assignment/contact workflows are complete before implementing them. Work through the authorized phase and document any genuinely unresolved configuration.

### Final reminders

The next AI should be able to continue from the **current working app**, not from the original blank-project prompt. Preserve the owner account, eight-session authority, eight-character password policy, database isolation, audit trail and independent operational domains. A green foundation suite does not imply Phase 3 or Phase 4 exists. Phase 3 Guest Service capture/import is now implemented. Phase 4 assignment/contact operations are now implemented. The next meaningful implementation work is **Phase 5 stabilization and release**.

