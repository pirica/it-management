<?php
/**
 * Browser alias for the fast-create account UI (same as scripts/fast_create_acc.php).
 *
 * Why: fast_create_acc_browser.php is the shared include under modules/employees/ — not a public URL.
 * Use this entry when linking to scripts/fast_create_acc_browser.php directly.
 */

declare(strict_types=1);

require __DIR__ . '/fast_create_acc.php';
