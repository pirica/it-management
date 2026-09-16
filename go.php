<?php
/**
 * Public short URL redirect (app root alias) — no login.
 *
 * Enables shorter links such as {BASE_URL}go.php?c={code}.
 * Delegates to modules/short-url/go.php (legacy path still supported).
 */
require __DIR__ . '/modules/short-url/go.php';
