# Coolify deployment runbook

SUMMIT 2026 on [Coolify](https://coolify.io) using the repository `Dockerfile`.
One application container runs nginx + PHP-FPM 8.4, the `imports` queue worker
and the scheduler. MySQL is a separate Coolify database resource.

Branch deployed here: `codex/info-com-identity-deduplication`.
Local parity check: `docker compose up --build` (compose file is for local testing
only; Coolify ignores it).

---

## 1. What you need before starting

| Item | Notes |
| --- | --- |
| Coolify server | Docker Engine reachable, proxy configured, a domain pointed at it |
| Git repository access | `https://github.com/chrisniger/summit2026_guest` (add a deploy key if private) |
| MySQL 8.0+ | Coolify database resource in the same project/network |
| Domain | e.g. `summit2026.summitdata.one`, A record → Coolify server IP |
| `APP_KEY` | One value, generated once: `php artisan key:generate --show` |

MariaDB works too, but must be 10.11 or newer — the Operations page flags older
MariaDB builds.

---

## 2. Create the database

1. Coolify → your project → **+ New** → **Database** → **MySQL** (8.x).
2. Set `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` to real values and deploy.
3. Open the resource and note the **internal hostname** (e.g. `mysql-abc123`), the
   port (3306), the database name, user and password. You will paste these into the
   application's environment variables — the two resources share a Docker network,
   so the internal hostname is what the app must use, not `127.0.0.1`.

If you are migrating the existing Hostinger database, import the dump into this
database first and **reuse the original `APP_KEY`** (encrypted session data and any
encrypted columns depend on it).

---

## 3. Create the application

Coolify → project → **+ New** → **Application** → **Git repository**.

| Setting | Value |
| --- | --- |
| Repository / branch | `chrisniger/summit2026_guest` → `codex/info-com-identity-deduplication` |
| Build pack | **Dockerfile** |
| Dockerfile location | `/Dockerfile` |
| Base directory | `/` |
| Ports exposes | `8080` |
| Domain | `https://your-domain` with **HTTPS enabled** |
| Healthcheck | enabled, path `/up`, expected status 200 |

The image already declares a `HEALTHCHECK` on `http://127.0.0.1:8080/up`, sets
`EXPOSE 8080`, and serves on 8080 — do not change the port to 80 or 8000.

### Persistent storage (required)

Add a volume on the application's **Persistent Storage** tab:

| Name | Mount path |
| --- | --- |
| `storage` | `/var/www/html/storage` |

This single volume holds imported spreadsheets (`storage/app/private`), the public
disk uploads (`storage/app/public`), compiled Blade views, file/database cache
payloads and `storage/logs`. Without it every redeploy wipes uploads and logged
import files.

Do **not** mount `/var/www/html` (it would hide the application code) or
`bootstrap/cache` (the container writes the config/route cache there at boot).

The entrypoint runs `chown -R www-data:www-data storage` on every start, so a fresh
volume does not need manual permission fixes.

---

## 4. Environment variables

Set these on the application's **Environment Variables** tab (Coolify injects them
at container start; they override every default baked into the image). Non-secret
defaults already exist in the Dockerfile for anything not listed as required.

### Required

| Variable | Value |
| --- | --- |
| `APP_KEY` | `base64:...` from `php artisan key:generate --show` — keep it stable across deploys |
| `APP_URL` | `https://your-domain` (must be the public HTTPS URL) |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `DB_HOST` | MySQL resource internal hostname, e.g. `mysql-abc123` |
| `DB_PORT` | `3306` |
| `DB_DATABASE` | database name from step 2 |
| `DB_USERNAME` | database user from step 2 (not `root`) |
| `DB_PASSWORD` | database password from step 2 |

### Mail (required for password-reset mail)

| Variable | Value |
| --- | --- |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | provider host, default in image `smtp.hostinger.com` |
| `MAIL_PORT` | `465` for implicit TLS, `587` for STARTTLS |
| `MAIL_SCHEME` | `smtps` (port 465) or `smtp` (port 587) |
| `MAIL_USERNAME` | mailbox login, default `noreply@summitdata.one` |
| `MAIL_PASSWORD` | mailbox password — **not** stored in the image |
| `MAIL_FROM_ADDRESS` | sending address, e.g. `noreply@summitdata.one` |

### First-deploy switches

| Variable | Value | Why |
| --- | --- | --- |
| `RUN_MIGRATIONS` | `true` (default) | entrypoint runs `php artisan migrate --force` before starting services |
| `RUN_SEEDERS` | `true` on the first deploy, then `false` | the database needs roles/permissions before a Super Admin can exist; seeders are idempotent (`updateOrCreate`/`firstOrCreate`) |
| `STORAGE_LINK` | `true` (default) | recreates the `public/storage` symlink in the container filesystem |

### Worth overriding

| Variable | Default in image | Notes |
| --- | --- | --- |
| `SESSION_SECURE_COOKIE` | `true` | keep `true` behind Coolify's HTTPS proxy |
| `SESSION_DOMAIN` | unset | set only if serving from a subdomain cookie scope is needed |
| `DB_QUEUE_RETRY_AFTER` | `300` | must stay above the 120-second import timeout |
| `LOG_LEVEL` | `warning` | use `debug` temporarily when diagnosing |
| `BCRYPT_ROUNDS` | `12` | lower only for load testing |
| `APP_NAME` | `SUMMIT 2026` | shown in the UI |

`QUEUE_CONNECTION`, `CACHE_STORE` and `SESSION_DRIVER` are already `database`, and
`FILESYSTEM_DISK=local` — the app needs all four to stay on `database`/`local`
because the Operations page and the import pipeline depend on shared state.

---

## 5. First deploy

1. Click **Deploy**. Coolify builds the `node:22-alpine` assets stage, then the
   `php:8.4-fpm-alpine` runtime, then starts the container.
2. Watch **Logs**. A healthy boot prints, in order:
   - `Running database migrations`
   - `Caching configuration, routes and views`
   - `Starting supervisord`
   - nginx and `php-fpm` startup lines
3. If the container exits with
   `FATAL: APP_KEY is empty` → set `APP_KEY` and redeploy. Any migration or
   config error also stops the container before nginx starts, which is intentional:
   the proxy will show 502 until it is fixed.
4. Once the deployment is healthy, set `RUN_SEEDERS=false` and redeploy (or leave
   it; the seeders are idempotent, but there is no reason to re-run them).

---

## 6. Bootstrap the Super Admin

Roles come from `RolePermissionSeeder`, not from migrations, so seed before
bootstrapping if you skipped `RUN_SEEDERS`.

In Coolify → application → **Terminal**:

```bash
php artisan db:seed --class=RolePermissionSeeder --force   # only if roles are missing
php artisan summit:bootstrap-admin --name="Your Name" --email=you@example.com
```

`summit:bootstrap-admin` prompts twice for a hidden password (minimum 8
characters). It refuses to run if a `super_admin` already exists — afterwards,
manage staff from **Admin → Staff**. Create additional staff accounts in the UI
(`info_com`, `officer`, `data_entry`, `follow_up_admin` roles) rather than by
seeding.

---

## 7. Verify the deployment

| Check | Expected |
| --- | --- |
| `https://your-domain/up` | HTTP 200 |
| `https://your-domain/` | login page, CSS/JS loaded (assets are built into the image) |
| Sign in as the Super Admin | dashboard loads; no redirect loop |
| **Admin → Operations** (`/admin/operations`) | all required checks green: PHP extensions, application key, built assets, private uploads, queue reservation, database connection, migrations, Super Admin |
| Upload a small guest/INFO CSV | batch completes; the Operations page's *Imports worker* heartbeat stays green |

Expected **warnings** (non-blocking) on a fresh deployment: *Staff onboarding*,
*Attendance schedule* (until the eight session times are entered),
*Verified backup* (a local backup receipt from the standalone install),
*Database lifecycle* (only if MariaDB < 10.11).

The Operations page renders `Operations::checks()` **without** the production
flag, so the four production-only checks are not shown there. Verify them by hand:

- `APP_URL` starts with `https://` and `SESSION_SECURE_COOKIE=true`;
- database user is not `root`;
- mailer is not `log`/`array`, and a test password reset actually arrives;
- `$APP_ENV=production`, `APP_DEBUG=false` (the boot banner says `production`).

---

## 8. Backups

The volume and the database are separate failure domains:

| Data | How to protect it |
| --- | --- |
| MySQL | Coolify scheduled backups on the MySQL resource, plus an off-server copy |
| `/var/www/html/storage` | Coolify volume backup, or a scheduled `tar` of `storage/app` |

A database-only backup loses imported spreadsheets and uploaded files; a
volume-only backup loses all records. Restore drill: import the SQL dump into a
scratch database, restore the volume into a second application instance, and log
in — do this at least once before the event.

---

## 9. Day-two operations

| Task | Action |
| --- | --- |
| Deploy a code change | Push to the branch (redeploy automatically or hit **Deploy**); the image is rebuilt, migrations run, supervisors restart |
| Change env vars | Edit in Coolify and redeploy; config is cached at boot, so a restart alone is not always enough |
| Restart queue worker | Restart the container (supervisord manages the worker); no `queue:restart` needed |
| Stuck / failed import | `php artisan queue:failed`, then `php artisan summit:imports:maintain recover --apply` from the terminal after reviewing the batch |
| Inspect the app | `php artisan about`, `php artisan config:show database`, `php artisan route:list` in the Coolify terminal |
| Logs | Coolify **Logs** shows entrypoint, nginx, php-fpm, worker and scheduler output on one stream |
| Rollback | Redeploy the previous image/deployment in Coolify; do **not** roll back a migration blindly |

Scaling: keep the application at **one replica**. The entrypoint runs migrations
on boot, so two starting replicas can race; if you scale, set
`RUN_MIGRATIONS=false` on the extra replicas.

---

## 10. Troubleshooting

| Symptom | Likely cause / fix |
| --- | --- |
| Container exits, `FATAL: APP_KEY is empty` | `APP_KEY` missing — set it, redeploy |
| 502 Bad Gateway | Container crashed before nginx started; check Logs for a migration/DB error |
| 419 *Page Expired* or login loop | `APP_URL` not the HTTPS URL, or secure cookies served over http; fix `APP_URL`/`SESSION_SECURE_COOKIE`, disable `SESSION_DOMAIN` unless needed |
| Unstyled pages, missing CSS/JS | `public/build` missing (image built without the assets stage) — rebuild, don't patch the container |
| Migrations fail: `Connection refused` / access denied | `DB_HOST` still `127.0.0.1`, wrong credentials, or the app and database are in different networks |
| 500 on CSV upload | volume not mounted, or file over the 12 MB limit (`client_max_body_size`/PHP `upload_max_filesize`); raise both together |
| Imports stuck in *processing* | Worker logs, `failed_jobs`, then `summit:imports:maintain recover --apply` |
| Operations: *Imports worker* stale | Queue worker not running — check supervisord output in Logs |
| Operations: *Scheduler* stale | Scheduler process down, or `CACHE_STORE` changed away from `database` (heartbeats are cache entries) |
| Password reset mail never arrives | `MAIL_*` incomplete, or `MAIL_MAILER=log`; send a test reset and read the app logs |
| Storage resets after deploy | Mount missing: `/var/www/html/storage` must be a Coolify volume |
