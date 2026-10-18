# SUMMIT 2026

Laravel conference operations platform for Summit Bible Church. Info Com registration/attendance and Guest Service/follow-up are independent domains. Phases 1–2 supply the secure staff foundation and working Info Com registration, attendance, CSV imports and reports.

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
php artisan serve --host=127.0.0.1 --port=8000
```

Open [the local application](http://127.0.0.1:8000). Bootstrap prompts privately for a password and confirmation (at least 8 characters, with no character-type requirements). It refuses to run after any Super Admin exists. No default account/password is seeded. Use Staff & roles after signing in to provision the remaining team.

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

Implemented: login/logout/reset, throttling, session protection, deactivation, roles/permissions/policies, staff administration, audit, eight-session configuration, independent domains, onsite registration/editing, normalized name/phone search, automatic/manual attendance, void/restore corrections, CSV preview/review/queue/retry, attendance reports and safe exports.

Next: Phase 3 guest batch entry and historical XLSX; Phase 4 assignment/contact operations; Phase 5 remaining reports, exports and launch verification. Guest Service dashboard shells explain their unavailable workflows. General attendance backdate/transfer controls await an approved correction policy.

## Production requirements

Use a least-privilege database account, supported database release, HTTPS, secure session cookies, APP_ENV=production and APP_DEBUG=false; configure outbound mail, backup/restore, retention and queue operations. MAIL_MAILER=log is local-only: reset messages are recorded in private storage/logs/laravel.log instead of being delivered. Reset tokens in development logs must remain private. Web servers must use the public/ directory as document root; never expose the project root. Set SESSION_SECURE_COOKIE=true when HTTPS is enabled.

No commit, deployment or push is performed by setup. See docs/IMPLEMENTATION_PROGRESS.md, docs/PHASE_1_COMPLETION_REPORT.md and docs/PHASE_2_COMPLETION_REPORT.md for verified scope and remaining work.



