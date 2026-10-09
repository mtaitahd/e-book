<?php

/**
 * Shop-wide configuration.
 *
 * currency: single source of truth for the store currency code. Change it
 * here if the store operates in a different currency; orders snapshot the
 * code at creation time so historical orders never change.
 */
return [
    'currency' => env('SHOP_CURRENCY', 'TZS'),
];