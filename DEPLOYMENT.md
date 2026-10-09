# Deployment guide — Gajoki Books (Laravel 12)

This document fixes the production outage on **https://gajokibooks.co.tz**: the
live domain returned `404 Not Found — LiteSpeed Web Server` on every URL.

## Root cause

The domain's document root is the **project root** `/home/gajokibo/public_html`.
Laravel's real entry point is `public/index.php`, so there is no `index.php`
(or `.htaccess`) in `/home/gajokibo/public_html`. As a result LiteSpeed never
starts the application and answers with its own "LiteSpeed Web Server" 404 page.
This is a server-level 404 — Laravel is never reached, so a code-only change on
the GitHub repo cannot fix the live site by itself.

## Required hosting change (Option A — recommended)

Point the domain's document root at the Laravel `public/` directory:

1. Log in to **cPanel** for `gajokibooks` (e.g. `https://<host>:2083`).
2. **Domains → Manage** (row for `gajokibooks.co.tz`) → **Document Root**.
3. Set it to `/home/gajokibo/public_html/public` (i.e. `public_html/public`).
4. Save. No `.htaccess` change is needed — `public/.htaccess` already contains
   the standard Laravel rewrite rules and is inside the new document root.

Why this is the correct, secure fix:

- Only `public/` becomes the web root. `.env`, `.git`, `vendor/`, `storage/`,
  `routes/`, `app/`, etc. move **outside** the document root and are unreachable
  over HTTP by default — no deny rules needed.
- `public/assets/*` is served at `/assets/*`, `public/storage/*` (the public
  disk symlink) at `/storage/*`, exactly the URLs `asset()` emits in the app.
- Canonical names resolve as-is (no `/public` in URLs) and HTTPS/AutoSSL keeps
  working as cPanel manages the vhost.
- Existing root-level `.htaccess` rules (if any, e.g. a Force-HTTPS redirect)
  become inert because they are no longer in the document root; re-apply any
  site-level redirect inside `public/.htaccess` or via cPanel's Force HTTPS.

## Option B — fallback when the document root cannot be changed

Only if the host refuses to change the document root to `public/`. Copy the two
prepared files into `/home/gajokibo/public_html`:

```bash
cp /path/to/repo/deploy/shared-host/index.php /home/gajokibo/public_html/index.php
cp /path/to/repo/deploy/shared-host/.htaccess /home/gajokibo/public_html/.htaccess
```

- `index.php` is a three-line loader that `require`s `public/index.php` — never
  a hand-adjusted copy, so relative paths are always correct.
- `.htaccess` hard-denies dot-files, `vendor/`, the app source, dumps/logs and
  the `public/` tree, rewrites `/assets/*` and `/storage/*` into `public/`, and
  routes everything else through the loader.
- **Merge** any existing server-side `.htaccess` (cPanel "Force HTTPS") into the
  copy instead of overwriting it, and keep the HTTPS section commented out if
  the host already redirects on its own.
- See `deploy/shared-host/README.md` for details.

## Production deployment steps

From the project root on the server (PHP CLI is `/opt/alt/php83/usr/bin/php`):

```bash
# Your shell will usually use an older default PHP; always use the alt PHP 8.3.
export PATH="/opt/alt/php83/usr/bin:$PATH"

composer install --no-dev --optimize-autoloader

# Only the FIRST time: expose the public disk for /storage/* cover images.
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --status   # inspect ONLY - do not auto-migrate in production
```

Do **not** re-run `key:generate`, overwrite `.env`, or run `php artisan migrate`
unattended on the live site. Keep `APP_ENV=production` and `APP_DEBUG=false`.

## Post-deploy verification

Replace `https://gajokibooks.co.tz` with the live URL:

```bash
curl -s -o /dev/null -w 'root: %{http_code}\n'          https://gajokibooks.co.tz/
curl -s -o /dev/null -w 'health: %{http_code}\n'        https://gajokibooks.co.tz/up
curl -s -o /dev/null -w 'login: %{http_code}\n'         https://gajokibooks.co.tz/login
curl -s -o /dev/null -w 'book: %{http_code}\n'          https://gajokibooks.co.tz/books
curl -s -o /dev/null -w 'asset: %{http_code}\n'         https://gajokibooks.co.tz/assets/e_book-removebg-preview.png

# Security spot checks (Option A) or denial checks (Option B):
curl -s -o /dev/null -w 'env: %{http_code}\n'           https://gajokibooks.co.tz/.env
curl -s -o /dev/null -w 'vendor: %{http_code}\n'        https://gajokibooks.co.tz/vendor/autoload.php
curl -s -o /dev/null -w 'composer: %{http_code}\n'      https://gajokibooks.co.tz/composer.json
curl -s -o /dev/null -w 'log: %{http_code}\n'           https://gajokibooks.co.tz/storage/logs/laravel.log
curl -s -o /dev/null -w 'git: %{http_code}\n'           https://gajokibooks.co.tz/.git/config
```

Expected: `root`, `health`, `login`, `book`, `asset` → `200` (as long as the
database is reachable and migrations are applied); `env`, `vendor`, `composer`,
`git` → `403`; `log` → `404`.

## Out of scope (pre-existing, unrelated to this outage)

- `php artisan test` is red because tests reference the removed constant
  `Payment::PROVIDER_SNIPPE` (the model now exposes only `PROVIDER_ABLINER`);
  the failures start in `tests/Feature/Purchase/PurchaseTestCase.php:59`.
- `admin/settings/payments/reveal` references `asset('css/app.css')`, but there
  is no `public/css/app.css` in `public/` — that one asset 404s on the admin
  reveal page under both options.