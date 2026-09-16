<?php
/**
 * Static audit: flattened CRUD mutation RBAC must use the single chokepoint.
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/check_crud_rbac_coverage.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

putenv('ITM_SKIP_DB_TESTS=1');
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_crud_rbac_mutation_apply.php';

itm_script_output_begin('CRUD RBAC Coverage Check');

$nl = itm_script_output_nl();
$missing = [];

foreach (glob(dirname(__DIR__) . '/modules/*/index.php') as $path) {
    $slug = basename(dirname($path));
    if (in_array($slug, itm_crud_rbac_exempt_module_slugs(), true)) {
        continue;
    }

    $content = (string)file_get_contents($path);
    $rel = 'modules/' . $slug . '/index.php';

    $hasDeleteHandler = (bool)preg_match('/if\s*\(\$crud_action\s*===\s*[\'"]delete[\'"]\s*\)\s*\{/', $content);
    $hasCreateEditHandler = (bool)preg_match(
        "/if\s*\(\$_SERVER\['REQUEST_METHOD'\]\s*===\s*'POST'\s*&&\s*in_array\(\$crud_action,\s*\['create',\s*'edit'\],\s*true\)[^{]*\{/",
        $content
    );

    if (($hasDeleteHandler || $hasCreateEditHandler) && !itm_crud_rbac_mutation_index_has_early_gate($content)) {
        $missing[] = $rel . ' — missing early POST mutation chokepoint itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table)';
    }
}

foreach (['create.php', 'edit.php', 'delete.php'] as $entryFile) {
    foreach (glob(dirname(__DIR__) . '/modules/*/' . $entryFile) as $path) {
        $slug = basename(dirname($path));
        if (in_array($slug, itm_crud_rbac_exempt_module_slugs(), true)) {
            continue;
        }

        $content = (string)file_get_contents($path);
        $rel = 'modules/' . $slug . '/' . $entryFile;

        if (itm_crud_rbac_mutation_entry_is_index_wrapper($content)) {
            continue;
        }

        if (strpos($content, 'itm_crud_mutation_guard_entry(') === false
            && strpos($content, 'itm_crud_enforce_mutation_access(') === false
            && strpos($content, 'itm_require_crud_role_module_permission(') === false
            && strpos($content, 'itm_require_role_module_permission(') === false
            && strpos($content, 'itm_require_admin(') === false
            && strpos($content, 'aps_require_permission(') === false
        ) {
            $missing[] = $rel . ' — standalone entry missing itm_crud_mutation_guard_entry()';
        }
    }
}

if ($missing === []) {
    echo colorText('[PASS] CRUD RBAC chokepoint present on all in-scope mutation entry paths.', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

echo colorText('[FAIL] Missing CRUD RBAC chokepoint:', 'fail') . $nl;
foreach ($missing as $line) {
    echo '  - ' . $line . $nl;
}
echo colorText('Repair: php scripts/apply_crud_rbac_guards.php --apply && php scripts/apply_crud_mutation_bootstrap.php --apply', 'fail') . $nl;
itm_script_output_end();
exit(1);
