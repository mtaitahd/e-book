<?php

/**
 * Safe drop-in front controller for shared hosting where the domain document
 * root IS the Laravel project root (for example cPanel's public_html) and the
 * host does not allow pointing the document root at public/.
 *
 * This is the LAST RESORT fallback (Option B in DEPLOYMENT.md). The
 * recommended deployment points the domain document root at public/ instead;
 * in that case this file lives OUTSIDE the document root and is never served.
 *
 * It loads Laravel's real entry point (public/index.php) with all relative
 * paths intact, so the framework, storage, vendor and source directories stay
 * inside the project tree and are kept out of the web root by the companion
 * root .htaccess. Do not replace this with a hand-copied public/index.php.
 */
require __DIR__ . '/public/index.php';