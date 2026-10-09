# Option B - shared-hosting fallback files

Copy both files into the domain document root **only if** the host cannot point
the document root at `public/`:

```bash
cp index.php /home/gajokibo/public_html/index.php
cp .htaccess /home/gajokibo/public_html/.htaccess
```

Prerequisites before copying (run from the project root on the server):

```bash
/opt/alt/php83/usr/bin/php artisan storage:link
/opt/alt/php83/usr/bin/php artisan config:cache
```

Warnings:

- If the document root already contains a `.htaccess` (e.g. a cPanel "Force
  HTTPS" redirect), **merge** its rules into the copy - do not overwrite it.
- This fallback intentionally keeps the entire project inside the web root, so
  the deny rules in `.htaccess` must stay intact. They block `.env`, `.git`,
  `vendor/`, the application source and `storage/` internals.
- Read `DEPLOYMENT.md` in the repository root. **Option A** (pointing the
  document root at `public/`) is strongly preferred: it exposes only the public
  directory, needs no rewrite layer, and keeps every secret outside the web
  root automatically.