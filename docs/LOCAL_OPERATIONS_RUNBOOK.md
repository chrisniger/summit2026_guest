# Local internal app operations

Release scope: local Windows/XAMPP, 18 October 2026. No production host or domain is configured. Workspace is `D:\Apps\summit2026_plus`; PHP is `D:\php84\php.exe`; XAMPP provides MariaDB at 127.0.0.1:3306. Preserve the existing `.env`, APP_KEY, database and owner account. Never run `migrate:fresh`, test fixtures or database restoration against `summit_2026`.

## Start and inspect

From the workspace in PowerShell:

```powershell
.\start-local.ps1 -RestartWorkers
php artisan summit:doctor
```

Open http://127.0.0.1:8000. The script runs hidden local web/import-worker/scheduler processes; it reuses a matching workspace listener and refuses a foreign process on the selected port. `-Php` and `-Port` override defaults. `-RestartWorkers` restarts only this workspace's identified import/scheduler commands. The private PID receipt is `storage/app/local-processes.json`; PIDs are transient, always inspect process command lines before stopping them. Do not terminate all PHP processes or the XAMPP database. For shutdown, stop only verified matching workspace web/queue/scheduler processes; the web launcher may have a child listener, so inspect both.

Super Admin → Operations is read-only. `summit:doctor --json` is suitable for a local status check. Worker and scheduler heartbeats expire after three minutes. The scheduler updates a heartbeat only; it does not prune files or schedule automatic backups. Warnings about real staff, unconfigured sessions and the local database version remain until administrators resolve them. `--production` intentionally fails this local setup; it is not a deployment command.

The import worker uses `--queue=imports --sleep=1 --tries=3 --timeout=120`. Database reservation `retry_after` defaults to 300 seconds and must remain greater than the job timeout. Restart workers after source/config updates. This follows the [Laravel queue timeout guidance](https://laravel.com/framework/docs/13.x/queues).

## Encrypted backup and isolated restore rehearsal

A private random recovery-password file has been generated at `storage/app/private/backup-recovery-key.txt`. Its contents are never printed or committed. Securely copy the password to a separate protected recovery location; losing it makes the archives unusable. Keep an off-device protected archive copy separately. The app does not automate off-device transfer or backup rotation.

```powershell
php artisan summit:backup --password-file=storage/app/private/backup-recovery-key.txt --dump-binary=D:\xampp\mysql\bin\mysqldump.exe
$archive = Get-ChildItem -LiteralPath storage/app/private/backups/summit_2026 -Filter '*.zip' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
php artisan summit:backup:verify $archive.FullName --password-file=storage/app/private/backup-recovery-key.txt --mysql-binary=D:\xampp\mysql\bin\mysql.exe
```

The backup briefly enters maintenance mode, preserves an existing maintenance state, refuses outstanding imports, and creates an AES-256 encrypted ZIP containing SQL, the existing APP_KEY, encrypted originals and a SHA-256 manifest. The full `.env` is excluded; keep a separate protected configuration record. Temporary SQL/key/database-client credential files are removed. Complete or safely recover imports and stop other external writers before taking a coordinated snapshot. The local tool supports snapshots up to **32 MiB expanded**, with a 128 MiB PHP runtime; larger datasets need a reviewed streaming backup solution. ZIP metadata/entry names are visible; contents are encrypted.

Verification decrypts and checks every entry and original, restores into a newly created `summit_2026_restore_check` database, compares 21 critical table counts and staff identity/password hashes, and removes only the rehearsal database it created. It refuses an existing rehearsal target, an active application connection pointing there, or a dump that switches/manages databases. The configured local DB user needs create/drop permission for this rehearsal; this is not the future production app-user privilege model. Wrong-password or failed checks return failure without selecting the live DB as a restore target.

Archives and `latest-verified.json` are scoped beneath `storage/app/private/backups/{source_database}`. Main/test backup receipts are independent. A doctor backup warning clears after a verification less than 24 hours old. An early unscoped backup may also remain in the private backups root; current commands use scoped directories. Nothing is automatically deleted.

Disaster recovery is a separate deliberate operation: stop writers; retain a copy of the damaged installation; use a trusted matching source revision and locked dependencies; verify/decrypt a known-good archive on a separate recovery machine/database; restore SQL into a new database and originals into private storage; restore the archived APP_KEY with protected configuration; compare counts, credential hashes, original decryptability and authorized workflows; then deliberately change the app connection and rebuild caches/assets before resuming service. The shipped verify command performs a rehearsal, not a live database replacement. Do not regenerate the APP_KEY or blindly import an archive over live data.

## Failed or interrupted imports

Check the authorized import screen and `php artisan queue:failed`. A stale processing batch means no progress for ten minutes. Review first:

```powershell
php artisan summit:imports:maintain recover
php artisan summit:imports:maintain recover --apply
```

Recovery marks eligible batches failed under locks; it does not insert rows. Retry from the authorized import screen after resolving the worker/storage cause. Pending-row retries preserve already committed rows. Delayed job-failure callbacks cannot regress completed batches. Review failed queue job IDs individually; do not bulk retry blindly.

Original retention requires an owner-approved age. For an approved example of 30 days:

```powershell
php artisan summit:imports:maintain prune --days=30
php artisan summit:imports:maintain prune --days=30 --apply
```

Default is dry-run. Apply deletes only old completed import originals with guarded domain paths, clears their file references and audits the action. It preserves imported people, source rows and provenance. Incomplete imports are excluded. No retention duration has been approved or applied to the application database. Backups may retain older copies; manage those separately under the agreed policy.

## Staff, sessions and errors

The existing owner signs in with their configured email/password; there are no seeded operational staff passwords. Provision real accounts and independent permissions through Staff & roles. Passwords require at least eight characters without composition rules; saved-password verification remains mandatory. `MAIL_MAILER=log` records reset mail locally and does not deliver email; keep logs private.

Enter verified WAT start/end times for all eight 2026 sessions and enable them. Do not invent times. The session manager offers audited attendance lock/unlock; locks preserve clock boundaries and privileged correction requires the existing audited workflow. Configure real 2025 historical sessions before importing historical source files.

409 means changed/stale data: reload and review before saving. 419 means the session expired: sign in/reopen the form. 429 requires waiting before retry. 500/503 use generic recovery pages without raw exception output. Offline native forms retain current page values and block submission; reconnect before saving. Values are not persisted offline across navigation, reload or browser closure. Do not promise offline operation. Private no-store headers reduce browser caching; they do not prevent an authorized user copying/exporting data.

Inspect private `storage/logs/laravel.log`, `web-server-error.log`, `imports-worker-error.log` and `scheduler-error.log`. Avoid sharing logs containing reset tokens or personal information. Keep disk space available and review Operations after updates. XAMPP MariaDB 10.4 is local-only; choose a maintained release before future production hosting. See the [MariaDB maintenance policy](https://mariadb.org/about/).

## Test safely

Use `summit_2026_test` only. Run PHPUnit, the four browser suites and backup rehearsals sequentially; they share the disposable DB and maintenance marker. Browser ports are 8001–8004, separate from the local app. `PLAYWRIGHT_MODULE` can point to an external Playwright installation; no runtime dependency was added for browser checks. Test artifacts and fixtures stay private and are cleaned up. Never change test guards to point to the application database.
