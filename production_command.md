# SUMMIT 2026 — production deploy

Hostinger, account `u188660189`, domain `summit2026.summitdata.one`. Document root cannot change,
so code lives in `summit/` beside `public_html/`, and `public_html/` holds only public files plus a
3-line front controller.

Every PHP call on the server uses the absolute binary `/opt/alt/php84/usr/bin/php`. Nothing relies on
`php` or `composer` from `PATH`, which on this host may point at an older PHP.

`public/build` is committed to git, so compiled assets arrive with `git clone` / `git pull` and no
file transfer from your PC is needed. After changing CSS or JS, run `npm run build` locally and commit
`public/build` before pushing.

**Paste block B as one piece** over SSH after editing the three `CHANGE_ME` values. Nothing prompts.

---

## Block B — SSH / hPanel → Advanced → Terminal (single paste)

```bash
# =====================================================================
# SUMMIT 2026 production deploy — paste this whole block once.
# PHP is always /opt/alt/php84/usr/bin/php (PHP 8.4), never bare "php".
# Before pasting, replace the four CHANGE_ME values marked EDIT below.
# =====================================================================
set -euo pipefail
set +o history                     # keeps the passwords out of ~/.bash_history

[ -x /opt/alt/php84/usr/bin/php ] \
  || { echo "STOP: /opt/alt/php84/usr/bin/php not found — check PHP 8.4 in hPanel"; exit 1; }
/opt/alt/php84/usr/bin/php -v | head -1

BASE=/home/u188660189/domains/summit2026.summitdata.one
cd "$BASE"

# ---------- 1. code ----------
git clone https://github.com/chrisniger/summit2026_guest.git summit 2>/dev/null \
  || (cd summit && git pull --ff-only)

# ---------- 2. compiled assets (committed to git) ----------
test -f summit/public/build/manifest.json \
  || { echo "STOP: public/build missing from the checkout — build it locally, commit it, then re-pull"; exit 1; }

# ---------- 3. environment ----------
if [ -f summit/.env ] && grep -q '^APP_KEY=base64:' summit/.env; then
  echo "Existing .env kept (APP_KEY present)."
else
  cat > summit/.env <<'ENV'
APP_NAME="SUMMIT 2026"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://summit2026.summitdata.one
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DEPRECATIONS_CHANNEL=null

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u188660189_summit
DB_USERNAME=u188660189_summit
# EDIT 1 — database password from hPanel → Databases
DB_PASSWORD="CHANGE_ME_DB_PASSWORD"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PATH=/
SESSION_DOMAIN=null

FILESYSTEM_DISK=local
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=300
BROADCAST_CONNECTION=log

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=noreply@summitdata.one
# EDIT 2 — mailbox password from hPanel → Emails
MAIL_PASSWORD="CHANGE_ME_MAIL_PASSWORD"
MAIL_FROM_ADDRESS="noreply@summitdata.one"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
ENV
fi

# ---------- 4. dependencies (composer.phar run through PHP 8.4) ----------
cd summit
if [ ! -f composer.phar ]; then
  curl -sS https://getcomposer.org/installer \
    | /opt/alt/php84/usr/bin/php -- --install-dir=. --filename=composer.phar
fi
/opt/alt/php84/usr/bin/php composer.phar install --no-dev --optimize-autoloader --no-interaction

# ---------- 5. key, schema, seed ----------
grep -q '^APP_KEY=base64:' .env || /opt/alt/php84/usr/bin/php artisan key:generate --force
/opt/alt/php84/usr/bin/php artisan migrate --force --seed --no-interaction

# ---------- 6. first Super Admin ----------
printf '%s\n%s\n' 'CHANGE_ME_ADMIN_PASSWORD' 'CHANGE_ME_ADMIN_PASSWORD' \
  | /opt/alt/php84/usr/bin/php artisan summit:bootstrap-admin --name="Chris" --email="chrisniger101@gmail.com" \
  || echo "Super Admin already exists — skipped."
# EDIT 3 and 4 — replace CHANGE_ME_ADMIN_PASSWORD above (both copies, same value)

# ---------- 7. caches and permissions ----------
/opt/alt/php84/usr/bin/php artisan config:cache
/opt/alt/php84/usr/bin/php artisan route:cache
/opt/alt/php84/usr/bin/php artisan view:cache
chmod -R 775 storage bootstrap/cache

# ---------- 8. publish the web root ----------
cd "$BASE"
rm -rf public_html/build
cp -R summit/public/build summit/public/brand summit/public/favicon.ico summit/public/robots.txt public_html/

cat > public_html/.htaccess <<'HTACCESS'
Options -Indexes -MultiViews
DirectoryIndex index.php

RewriteEngine On

# Block hidden paths, preserving certificate-validation requests.
RewriteRule (^|/)\.(?!well-known(?:/|$)) - [F,L]

# Preserve headers used by Laravel authentication and CSRF protection.
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteCond %{HTTP:x-xsrf-token} .
RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

# Remove trailing slashes on routes, leaving real directories alone.
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} (.+)/$
RewriteRule ^ %1 [L,R=301]

# Serve existing public assets; route everything else through Laravel.
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
HTACCESS

cat > public_html/index.php <<'PHP'
<?php
require __DIR__.'/../summit/public/index.php';
PHP

# ---------- 9. verify ----------
cd "$BASE/summit"
curl -sI https://summit2026.summitdata.one/login | head -5 || true
/opt/alt/php84/usr/bin/php artisan summit:doctor --production \
  || echo "doctor reported failures — read the rows above"

set -o history

# ---------- 10. cron: paste both lines into hPanel → Advanced → Cron Jobs ----------
# * * * * * /opt/alt/php84/usr/bin/php /home/u188660189/domains/summit2026.summitdata.one/summit/artisan schedule:run >> /dev/null 2>&1
# * * * * * /opt/alt/php84/usr/bin/php /home/u188660189/domains/summit2026.summitdata.one/summit/artisan queue:work --queue=imports --stop-when-empty --max-time=50 --tries=3 --timeout=120 >> /dev/null 2>&1
```

---

## Repair — the repository was cloned into `public_html/`

Tell-tale sign: log lines or stack traces contain `…/public_html/vendor/…` or `…/public_html/artisan`.
The whole checkout is then reachable over HTTP (`/vendor/`, `/database/`, `/storage/logs/laravel.log`),
and the front controller sits at `public_html/public/index.php` instead of `public_html/index.php`.

This moves the checkout to `summit/` beside the web root. `.env` and `storage/` travel with it, so the
`APP_KEY` and the encrypted import originals survive. Run it once, from the hPanel terminal:

```bash
set -euo pipefail
BASE=/home/u188660189/domains/summit2026.summitdata.one
cd "$BASE/public_html"

# ---------- 1. bring the committed assets into the current checkout ----------
git status --porcelain
git pull --ff-only

# ---------- 2. guards — nothing moves unless all four pass ----------
[ -f artisan ] || { echo "Nothing to repair: public_html/artisan not found."; exit 0; }
[ -f .env ] || { echo "STOP: no .env inside public_html — create it first (block B, section 3), then rerun"; exit 1; }
[ -f public/build/manifest.json ] \
  || { echo "STOP: public/build missing — push from your PC with git push origin main, then rerun"; exit 1; }
[ ! -f "$BASE/summit/artisan" ] \
  || { echo "STOP: $BASE/summit already holds a checkout — compare the two before merging anything"; exit 1; }

# ---------- 3. move the checkout out of the web root ----------
mkdir -p "$BASE/summit"
shopt -s dotglob
mv "$BASE"/public_html/* "$BASE/summit/"
shopt -u dotglob

# ---------- 4. rebuild caches from the new location ----------
cd "$BASE/summit"
/opt/alt/php84/usr/bin/php artisan config:cache
/opt/alt/php84/usr/bin/php artisan route:cache
/opt/alt/php84/usr/bin/php artisan view:cache
chmod -R 775 storage bootstrap/cache

# ---------- 5. republish the web root ----------
cd "$BASE"
rm -rf public_html/build
cp -R summit/public/build summit/public/brand summit/public/favicon.ico summit/public/robots.txt public_html/
```

Then paste **sections 8 and 9 of block B** (they rewrite `public_html/.htaccess` and
`public_html/index.php`, and re-run the checks). Finally edit both cron jobs to point at
`…/summit/artisan` and delete any cron entry that still calls `public_html/artisan`.

Do **not** run `key:generate` during this repair — the existing `.env` is moved, not recreated.

---

## After the block finishes

1. **Cron** — add the two lines from section 10 of the block in hPanel → Advanced → Cron Jobs.
   Without them imports never process and the Operations page reports stale heartbeats.
2. **Sign in** at `https://summit2026.summitdata.one` with `chrisniger101@gmail.com` and the password
   used for EDIT 3/4.
3. **Conference sessions** — Super Admin → Conference sessions: enter the real WAT times and enable
   each of the eight sessions before any check-in.
4. **Staff** — Super Admin → Staff & roles: create the real team accounts. No demo accounts exist.
5. **Back up `APP_KEY`** — copy `.env` (or just `APP_KEY`) into a password manager. It decrypts stored
   import originals; regenerating it makes them unreadable and logs everyone out.

## Redeploy (repeat this after code changes, no hPanel work)

```bash
set -euo pipefail
BASE=/home/u188660189/domains/summit2026.summitdata.one
cd "$BASE/summit"
git pull --ff-only
if [ ! -f composer.phar ]; then
  curl -sS https://getcomposer.org/installer \
    | /opt/alt/php84/usr/bin/php -- --install-dir=. --filename=composer.phar
fi
/opt/alt/php84/usr/bin/php composer.phar install --no-dev --optimize-autoloader --no-interaction
/opt/alt/php84/usr/bin/php artisan migrate --force
/opt/alt/php84/usr/bin/php artisan config:cache
/opt/alt/php84/usr/bin/php artisan route:cache
/opt/alt/php84/usr/bin/php artisan view:cache
# assets arrive with git pull; refresh the copies inside the web root:
rm -rf "$BASE"/public_html/build
cp -R "$BASE"/summit/public/build "$BASE"/public_html/
```

Cron-driven workers pick up new code on the next minute; no manual worker restart is needed.

## If something breaks

| Symptom | Fix |
|---|---|
| `STOP: /opt/alt/php84/usr/bin/php not found` | PHP 8.4 is not provisioned for this account; select 8.4 in hPanel → Advanced → PHP Configuration, then re-run. |
| `500` everywhere, log says `Options not allowed here` | Delete the `Options -Indexes -MultiViews` line from `public_html/.htaccess`, re-run section 8. |
| `There are no commands defined in the "permission" namespace` | You ran a `permission:*` command (from spatie/laravel-permission). This app ships its own roles and permissions, has no spatie package and no such commands — the error is harmless and nothing needs clearing. |
| Log paths contain `public_html/vendor/…` | The checkout is inside the web root: run the **Repair** section above, then fix the cron paths. |
| `Vite manifest not found` | `public/build` is missing from the checkout (build locally with `npm run build` and commit it) or never reached `public_html/`; re-check section 2 and re-run section 8. |
| Login bounces back to `/login` | SSL inactive or `APP_URL` not `https://…`; confirm the certificate, then re-run `config:cache`. |
| `419 Page Expired` | Re-run `config:cache`, then hard-refresh (stale CSRF/session). |
| `Byte order mark`/parse error in `.env` | `.env` was saved with a BOM; rewrite section 3 and regenerate. |
| Imports stuck at "uploaded" | Cron lines from section 10 missing, or they use a different PHP path; they must use `/opt/alt/php84/usr/bin/php`. |
| `doctor` fails on mail | EDIT 2 still wrong, or the mailbox password changed; re-run `config:cache`. |
| `doctor` fails on database privilege | `DB_USERNAME`/`DB_PASSWORD` are the hosting login instead of the prefixed DB user. |
| `doctor` warns "Attendance schedule" | Expected until real WAT times are entered and the eight sessions enabled. |
