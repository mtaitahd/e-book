# Project Handover — Gajoki Books (E-Book Store)

**Prepared for:** System Owner
**Prepared by:** _[your name / development team]_
**Date:** _[handover date]_
**Repository:** https://github.com/mtaitahd/e-book
**Live site:** https://gajokibooks.co.tz

---

## 1. What this system is

Gajoki Books is an online bookstore and digital reading platform. Customers
browse and buy books (as downloadable PDFs or as online, chapter-by-chapter
e-books), pay via Mobile Money through the **Abliner** payment provider, and read
or download what they own. Administrators manage the catalogue, orders,
customers, sales reporting, the store's wallet and their own account.

The application is a custom **Laravel 12** web app. There is no off-the-shelf
e-commerce product behind it — it is purpose-built code that lives entirely in
the repository above.

---

## 2. Feature summary

### Public storefront
- Home page, book catalogue, trending books, categories, per-book detail pages.
- Free chapter previews for books the admin has marked as previewable.
- Shopping cart and checkout.
- Free books can be claimed in one click (no payment).

### Payments (Abliner — Mobile Money)
- Customer pays by Mobile Money; a USSD push is sent to their phone.
- Inbound payment callbacks are signature-verified and re-verified against the
  Abliner API before an order is trusted as paid.
- Store wallet: live balance, transaction history and payouts (withdrawals).

### Customer account
- Register / sign in (sign-in happens from a storefront modal).
- Order history, payment status.
- Personal library; secure PDF download and two online readers
  (PDF.js reader and a native HTML chapter reader) with progress + bookmarks.

### Admin panel (`/admin`, login at `/login`)
- Dashboard with catalogue and sales counts.
- Books (with chapters), Authors, Categories — create/edit/archive/restore/delete.
- Orders (all / pending / paid), bulk actions, sales reports with CSV export.
- Customers list (delete only when the account has no order/purchase history).
- Subscribers (newsletter list).
- Payment Settings (Abliner credentials, stored encrypted, connection test).
- Wallet & Withdrawals.
- **My Profile** — the signed-in admin updates their own username, email, phone,
  password and profile photo.

---

## 3. Technology stack

| Layer | Technology |
|---|---|
| Backend framework | Laravel 12 (PHP **8.2+**, production runs PHP 8.3) |
| Database | MySQL / MariaDB |
| Front-end | Blade templates + Bootstrap (admin theme "RuangAdmin"); Vite + Tailwind available but the app is served pre-built static assets under `public/assets/` |
| Payments | Abliner (Mobile Money) REST API + signed webhooks |
| Mail | Laravel mail (SMTP in production; `log` driver is the safe default) |
| Sessions / cache / queue | file sessions, file cache, `sync` queue (no worker required) |
| Storage | Local disk: public covers in `storage/app/public`, private e-book files in `storage/app/private` |

Key PHP packages: `laravel/framework`, `laravel/tinker`. Dev-only: PHPUnit 11,
Laravel Pint, Faker, Pail, Sail, Mockery.

---

## 4. Repository layout (where things live)

```
app/                         Application code
  Http/Controllers/          Storefront, Account, Auth and Admin controllers
    Admin/                   Every admin screen (Books, Orders, Profile, ...)
  Http/Requests/             Form validation (Admin/BookRequest, Admin/ProfileRequest, ...)
  Http/Middleware/           EnsureUserIsAdmin (the only custom middleware)
  Models/                    Eloquent models (User, Book, Order, Payment, ...)
  Services/                  Business logic (Cart, Purchase, Abliner integration, ...)
  Settings/                  Encrypted payment settings handling
  Reports/                   Sales reporting
  Mail/ , Listeners/         Transactional email (receipts, payment failures)
config/                      Laravel config
database/migrations/         All schema changes (see section 8)
database/seeders/            AdminSeeder (dev accounts), CatalogSeeder
resources/views/             Blade templates (admin/, account/, auth/, storefront/, layouts/)
routes/web.php               The ONLY route file (all HTTP routes)
public/                      Web root (index.php, assets/, storage symlink)
storage/app/public/covers/   Uploaded book covers
storage/app/private/ebooks/  Uploaded (private) e-book PDFs
deploy/shared-host/          Fallback index.php + .htaccess for shared hosting
ebs.sql                      Full database dump (schema + data snapshot)
DEPLOYMENT.md                Production deployment / outage runbook
```

Git remote: `origin → https://github.com/mtaitahd/e-book.git` (branch `main`).

---

## 5. Accounts, access and credentials to transfer

The system owner should receive control of **all** of the following. No real
passwords or secret keys are written in this document — collect them through a
secure channel.

| Item | Where | Notes |
|---|---|---|
| Domain `gajokibooks.co.tz` | Domain registrar | Enable auto-renew; confirm nameservers |
| DNS / nameservers | Registrar or hosting provider | |
| Hosting / cPanel (`gajokibo`) | Hosting provider | cPanel URL typically `https://<host>:2083` |
| GitHub repository | https://github.com/mtaitahd/e-book | Transfer ownership or add owner as admin |
| Abliner merchant account | https://abliner.net | Holds the API key + webhook secret and the payout destination |
| SMTP mailbox (customer emails) | Mail provider | Only needed once receipts are enabled |
| Application `APP_KEY` | Server `.env` only | **Never rotate** without a plan — rotating breaks encrypted payment credentials and sessions |
| Admin login | `/login` on the live site | Manage the account in **Admin → My Profile** |

**Admin login:** the administrator signs in at `https://gajokibooks.co.tz/login`
(the same `/login` page doubles as the admin entry point; customers sign in from
a modal on the storefront). Admin accounts are rows in the `users` table with
`role = 'admin'`. There is no admin-user management screen — new admins are
created directly in the database. The signed-in admin can change their own
name, email, phone, password and photo from **My Profile**.

> `database/seeders/AdminSeeder.php` creates `admin@ebs.local` /
> `admin-dev-password` — these are **local development only** and must never be
> used in production.

---

## 6. Local development setup

Prerequisites: PHP 8.2+, Composer, MySQL/MariaDB (XAMPP is fine), Node (only if
you touch the Vite assets).

```bash
git clone https://github.com/mtaitahd/e-book.git
cd e-book
composer install
copy .env.example .env        # Windows
php artisan key:generate
# set DB_* in .env, then:
php artisan migrate
php artisan db:seed --class=AdminSeeder   # optional local accounts
php artisan serve
```

- The public disk symlink (`public/storage`) is normally absent on Windows; the
  app serves `/storage/{path}` through a fallback route in `routes/web.php`, so
  covers still load without `storage:link`.
- Run the test suite with `php artisan test` (uses in-memory SQLite).
  See section 11 for known, pre-existing test failures.

---

## 7. Production deployment

Full runbook: **`DEPLOYMENT.md`**. Summary:

- The domain's **document root must point at `public/`**
  (`/home/gajokibo/public_html/public`). If the host will not allow this, use the
  fallback files in `deploy/shared-host/` (see that folder's README).
- Deploy from the project root on the server, using the alt PHP 8.3 binary:

```bash
export PATH="/opt/alt/php83/usr/bin:$PATH"
composer install --no-dev --optimize-autoloader
php artisan storage:link        # FIRST deploy only
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- **Never** overwrite `.env` or run `key:generate` on the live site.
- **Never** run `php artisan migrate` unattended in production; inspect with
  `php artisan migrate --status` and apply approved migrations deliberately.
- Keep `APP_ENV=production` and `APP_DEBUG=false`.
- Storage permissions: `storage/` and `bootstrap/cache/` must be writable by the
  web user.
- After any code change: clear/rebuild caches
  (`php artisan optimize:clear` then re-cache).
- Security check: `https://gajokibooks.co.tz/.env`, `/vendor/...`,
  `/composer.json`, `/.git/config` must return **403/404**, never the file.

A practical release loop is: commit → push to `main` → on the server
`git pull` → `composer install --no-dev` (if dependencies changed) →
`php artisan migrate --force` (only when a migration is approved) →
`php artisan optimize:clear` → re-cache.

---

## 8. Database

- **Engine:** MySQL/MariaDB. Database name/credentials live in the server `.env`
  (currently `gajokibo_kajokidb`).
- **Schema is managed by migrations** in `database/migrations/`. Never edit the
  database by hand except for the documented one-off below.
- **Reference snapshot:** `ebs.sql` at the repo root (a full dump you can import
  into an empty database via phpMyAdmin if you need a starting point).
- **Seeders:** `AdminSeeder` (local dev accounts), `CatalogSeeder` (sample
  catalogue). Do not run seeders on production.

### Latest schema change (admin account management)

To let administrators manage their own photo, one column was added. Run this in
phpMyAdmin **once** on the live database (it is also captured in migration
`2026_10_14_000001_add_avatar_to_users_table.php`):

```sql
ALTER TABLE `users`
  ADD COLUMN `avatar` VARCHAR(255) NULL DEFAULT NULL AFTER `email`;
```

### Main tables

`users`, `authors`, `categories`, `books`, `book_chapters`, `author_book`,
`book_category`, `orders`, `order_items`, `payments`, `purchases`,
`download_logs`, `reading_progress`, `reading_bookmarks`, `withdrawals`,
`payment_settings`, `email_notifications`, `subscribers`, `webhook_events`,
`sessions`, `cache`, `jobs`/`failed_jobs`.

---

## 9. Configuration reference (server `.env`)

Only the non-secret names are listed; values stay on the server.

- `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `APP_KEY`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `SESSION_DRIVER=file`, `SESSION_LIFETIME`, `SESSION_DOMAIN` (e.g. `.gajokibooks.co.tz`)
- `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`, `FILESYSTEM_DISK=local`
- `SHOP_CURRENCY=TZS` (must match the priced currency in the storefront)
- `ABLINER_*`: `BASE_URL`, `API_KEY`, `WEBHOOK_SECRET`, `WEBHOOK_URL`,
  `ENABLED`, `VERIFY_ON_WEBHOOK` (keep `true` in production)
- Mail: `MAIL_MAILER` (`log` by default; set `smtp` + host/user/pass to send
  real receipts), `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- `APP_MAINTENANCE_DRIVER=file`

> The Abliner API key and webhook secret can also be set from
> **Admin → Payment Settings**, where they are stored **encrypted** with
> `APP_KEY`. A value saved there overrides the `.env`. The page only ever shows
> a masked hint; a "reveal" action shows the value once on a no-store page.
> Losing/rotating `APP_KEY` makes those stored credentials unreadable.

---

## 10. Routine maintenance

| Frequency | Task |
|---|---|
| Every deploy | `php artisan optimize:clear` then re-cache (`config:cache`, `route:cache`, `view:cache`) |
| Daily (or via host cron) | Back up the database; check `storage/logs/laravel.log` for errors |
| Weekly | Check `/admin` dashboard, pending orders, and the Wallet balance/payouts |
| On Abliner key change | Rotate in **Payment Settings**; update the webhook URL if the domain changes |
| Monthly | Review disk usage of `storage/app/public/covers` and `storage/app/private/ebooks` |
| As needed | Import `ebs.sql` or apply approved migrations |

There is **no queue worker to run** (`QUEUE_CONNECTION=sync`). There is no
required scheduler for the core flows.

---

## 11. Backups & recovery

- **Database:** schedule a `mysqldump` of the production database (cPanel →
  Backup, or a cron job). Keep off-server copies.
- **Uploaded files:** back up `storage/app/public/covers` and
  `storage/app/private/ebooks` — these are **not** in Git (except the covers
  historically committed) and cannot be regenerated.
- **`.env`:** store an encrypted copy in a password manager; it is not in Git.
- **Recovery:** restore the database dump, restore `storage/`, confirm `.env`
  and `APP_KEY`, then clear caches.

### Known, pre-existing issues (not introduced by recent work)

- `php artisan test` has a small number of **pre-existing** failures, including
  two in `tests/Feature/Admin/AdminFormModalTest.php` (author create/update via
  the JSON modal path) and tests referencing a removed
  `Payment::PROVIDER_SNIPPE` constant (see `DEPLOYMENT.md`, "Out of scope").
  The new admin-profile tests all pass.
- `admin/settings/payments/reveal` references `asset('css/app.css')`, which does
  not exist in `public/`; that single asset 404s on the reveal page.

---

## 12. Security notes

- Keep `.env`, `/vendor`, `/.git` and app source **outside** the web root
  (document-root = `public/`), per `DEPLOYMENT.md`.
- `APP_DEBUG=false` in production; never expose stack traces.
- All admin routes require authentication **and** the admin role
  (`EnsureUserIsAdmin` middleware, `admin` route group).
- The admin profile endpoints can only ever act on the **signed-in** account
  (the controller reads the user from the session, never from the request).
- Password changes require the correct **current password**; passwords are
  hashed with bcrypt and never stored in plain text.
- Uploads are validated by type and size (images JPG/PNG/WebP ≤ 2 MB); e-book
  PDFs are stored on the private disk and served through authorized,
  ownership-checked routes.
- Webhooks are CSRF-exempt but **signature-verified** and re-checked against the
  Abliner API. Keep `ABLINER_VERIFY_ON_WEBHOOK=true`.
- Rotate the Abliner API key and webhook secret if they are ever exposed.

---

## 13. Handover acceptance checklist

- [ ] GitHub repo ownership transferred / owner added as admin
- [ ] cPanel + hosting access handed over
- [ ] Domain registrar + DNS access handed over
- [ ] Abliner merchant account access handed over (API key, webhook secret,
      payout bank/wallet details)
- [ ] SMTP mailbox access handed over (if receipts are enabled)
- [ ] Server `.env` and `APP_KEY` securely transferred
- [ ] Confirmed `avatar` column exists in the live database
- [ ] Admin can log in at `/login` and update their account at **My Profile**
- [ ] Backup schedule in place (database + uploaded files)
- [ ] Owner walked through: catalogue, orders, customers, payment settings,
      wallet, sales reports

---

_For deployment specifics see `DEPLOYMENT.md`. For the database starting point
see `ebs.sql`._
