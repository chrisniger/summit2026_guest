# SUMMIT 2026

Laravel conference operations platform for Summit Bible Church. Info Com registration/attendance and Guest Service/follow-up are independent domains. Phases 1–5 are implemented for the local internal app, including release checks, operations monitoring and verified encrypted backups. Production hosting is deferred at the owner's request.

## Local setup with XAMPP

Requirements: PHP 8.4.1+ (this workspace uses D:\php84\php.exe 8.4.22), Composer, Node 22.12+ and the running XAMPP MariaDB server. The locked dependencies require PHP 8.4.1 or newer. Use PHP 8.4's development server here; XAMPP supplies the database.

```powershell
php D:\php84\composer.phar install
npm ci
Copy-Item .env.example .env
php artisan key:generate
```

Skip copying .env/key generation when already configured. Create a new `summit_2026` database in phpMyAdmin, then set DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env. The current local installation already uses XAMPP at 127.0.0.1:3306. Do not expose or commit .env.

```powershell
php artisan migrate --seed
npm run build
php artisan summit:bootstrap-admin --name="Your Name" --email="you@example.com"
.\start-local.ps1
```

Open [the local application](http://127.0.0.1:8000). Bootstrap prompts privately for a password and confirmation (at least 8 characters, with no character-type requirements). It refuses to run after any Super Admin exists. No default account/password is seeded. Use Staff & roles after signing in to provision the remaining team.

For this existing installation, preserve `.env`, APP_KEY and the existing administrator. Run `.\start-local.ps1 -RestartWorkers` after updates to start/reuse the local server and restart this checkout's imports worker and scheduler. Super Admin → Operations and `php artisan summit:doctor` show local readiness. See [local operations runbook](docs/LOCAL_OPERATIONS_RUNBOOK.md) for backups, restore rehearsal and recovery.

This Windows installation's `composer` shell launcher is Unix-only; invoke its PHP phar explicitly as shown. Composer standard autoloading is enabled because optimized scanning stalled during setup. For production, build optimized autoloading on the deployment host after verifying that scan completes.

## Conference configuration

Super Admin → Conference sessions: enter actual WAT start/end for all eight sessions and explicitly enable them. All start/end times are initially null; sessions are disabled. Start is inclusive and end exclusive. Overlapping enabled windows are rejected. Reseeding does not reset saved times.

The default light design uses the official supplied logo and brand colors. See [design system](docs/DESIGN_SYSTEM.md) and [architecture decisions](docs/ARCHITECTURE_DECISIONS.md).

## Info Com operations and import worker

Use Attendance check-in to search by name or Nigerian 0/234/+234 phone variants, select the correct attendee, and confirm presence for the currently active session. Onsite registration uses its server timestamp for automatic attendance; Virtual registrations remain registered without automatic physical presence.

Super Admin → Registration CSV imports: download the 18-column template, upload for a dry-run preview, resolve every possible duplicate, and explicitly confirm. Invalid rows are skipped and reported. You may create a separate person, match a reviewed Info Com ID or skip a candidate; nothing automatically merges by phone/name. CSV timestamps must have explicit timezones, belong to 2026 and not be in the future.

The database queue needs a running worker for imports:

```powershell
php artisan queue:work --queue=imports --sleep=1 --tries=3 --timeout=120
```

Keep this running alongside the web server. Jobs process 100 rows per transaction and expose progress on the import page. Retry resumes pending rows without duplicating completed source events. Restart workers after application updates. Original CSVs are encrypted under the private local storage disk; configure an approved retention policy and protect the database and backups.

Attendance reports use explicit session/day/cohort filters, distinguish unique people from total check-ins, and show the eligibility cutoff. Super Admin may download a filtered attendance matrix, session summary or import row results. Shared phone numbers remain separate attendees until an explicit in-domain review.

## Guest Service operations

Use Enter guest to select the actual encounter session, name and phone. Save & add another retains the selected session. Browse guests provides role-scoped search and event-year/session/status/assignment filters. Matching phone/name records require an explicit separate-encounter decision; nothing is matched to Info Com.

Super Admin can use Guest CSV / XLSX imports. Other staff require explicit guest.import permission plus guest.create. Upload, select worksheets, map each source event descriptor to its verified session, confirm source timezone, review the dry run, resolve flagged rows and explicitly commit. The existing imports queue worker handles both independent import pipelines. For 2025 XLSX, configure verified historical sessions using the Super Admin form first. Legacy text is preserved; new guests remain NOT CONTACTED and unassigned. See docs/PHASE_3_COMPLETION_REPORT.md for exact formats, limits and remaining launch checks.

## Follow-Up operations

Follow-Up Admin uses Guest records to select guests, choose Assign/Reassign/Unassign and an active officer, and enter a reason. Review the exact selection and explicitly confirm. Changes save atomically; a stale confirmation requires review. Reassignment preserves history and immediately revokes former officer access.

Officers use My assigned guests, open an encounter, and record the actual WAT attempt, method, comment and outcome. CONTACTED requires actual two-way contact; UNREACHABLE requires a failed attempt. Notes leave status unchanged. Follow-up reports distinguish current ownership/status from activity period and offer scoped CSV exports. See docs/PHASE_4_COMPLETION_REPORT.md for exact definitions and verification.

## Testing and browser verification

Create a separate `summit_2026_test` MariaDB database; test connection credentials inherit the local configuration. PHPUnit forces this database, and TestCase refuses any other database before migrations. Tests refresh only that dedicated database.

```powershell
php artisan test --compact
php vendor/laravel/pint/builds/pint --format agent
php artisan view:cache
npm run build
```

Browser smoke checks use test-only staff fixtures, a synthetic test session, a test queue worker and a separate loopback server at port 8001. They check desktop/360px layouts, all five roles, registration, auto/manual check-in, a real 101-row queued CSV import, exports, Alpine, Livewire validation and server denials. Two concurrent PHP processes verify database check-in idempotency. Fixtures are removed afterward. Chrome is used from its standard Windows install path. Screenshots and results are written under storage/app/browser-checks (private, ignored).

```powershell
npm install --no-save --package-lock=false --ignore-scripts playwright
node tests/Browser/smoke.mjs
```

## Phase boundaries

Implemented: login/logout/reset, throttling, session protection, deactivation, roles/permissions/policies, staff administration, audit, eight-session configuration, independent domains, onsite registration/editing, normalized name/phone search, automatic/manual attendance, void/restore corrections, CSV preview/review/queue/retry, attendance reports and safe exports; guest capture, scoped browsing/editing, duplicate review and independent guest CSV/XLSX imports.

Phase 5 local stabilization is delivered: private response headers, failure recovery, offline/double-submit guards, session locks, operations checks, queue/scheduler heartbeats, guarded import maintenance and encrypted backup/isolated restore verification. Configure actual session times and real staff before operational use. Review the [Phase 5 completion report](docs/PHASE_5_COMPLETION_REPORT.md) for evidence and remaining owner decisions.

## Production requirements

Use a least-privilege database account, supported database release, HTTPS, secure session cookies, APP_ENV=production and APP_DEBUG=false; configure outbound mail, backup/restore, retention and queue operations. MAIL_MAILER=log is local-only: reset messages are recorded in private storage/logs/laravel.log instead of being delivered. Reset tokens in development logs must remain private. Web servers must use the public/ directory as document root; never expose the project root. Set SESSION_SECURE_COOKIE=true when HTTPS is enabled.

Setup commands do not deploy. The release source includes Phases 3–5 and is maintained on `main` at [GitHub](https://github.com/chrisniger/summit2026_guest). Private configuration, database data, backups and recovery passwords are excluded. See `handoff.md` and the phase completion reports for verified scope and remaining work.




Phase 3 Chrome verification: node tests/Browser/guest-smoke.mjs (set PLAYWRIGHT_MODULE to an externally installed Playwright module if needed). It uses port 8002 and summit_2026_test only. Run browser checks and PHPUnit sequentially because both use the disposable database.

Phase 4 browser/concurrency verification: node tests/Browser/follow-up-smoke.mjs (same optional PLAYWRIGHT_MODULE). Uses port 8003 and test-only fixtures; run sequentially with PHPUnit and other browser suites.

Phase 5: `node tests/Browser/release-smoke.mjs` uses port 8004 and the same disposable database. Its volume/concurrency/restore rehearsal is destructive only to that test database. All four suites must run sequentially. Final release verification: 165 automated tests / 1007 assertions and 30 Chrome/concurrency scenario groups. Browser evidence is private and ignored; no operational participant data is committed.
