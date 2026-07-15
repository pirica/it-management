<?php
/**
 * Company View Redirect Helper
 *
 * Redirects legacy or shorthand "view company" requests to the standardized
 * company view module. It dynamically calculates the base directory to ensure
 * the redirect works from any location.
 */

require_once __DIR__ . '/itm_script_entry_guard.php';
if (itm_skip_http_entry_unless_direct(__FILE__)) {
    return;
}

$id = (int)($_GET['id'] ?? 0);

// Calculate the absolute URL path to the company view module
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/includes/companies_view_redirect.php'));
$currentDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$baseDir = preg_replace('#/includes$#', '', $currentDir);
$target = ($baseDir === '' ? '' : $baseDir) . '/modules/companies/view.php';

// Append the specific company ID if provided
if ($id > 0) {
    $target .= '?id=' . $id;
}

// Perform the temporary redirect
header('Location: ' . $target, true, 302);
exit;
