<?php
/**
 * Static audit: flattened CRUD index.php delete/create/edit handlers must call RBAC guards.
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

putenv('ITM_SKIP_DB_TESTS=1');
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('CRUD RBAC Coverage Check');

$nl = itm_script_output_nl();
$missing = [];

/**
 * @return string[]
 */
function itm_crud_rbac_alternate_guard_markers(): array
{
    return [
        'itm_require_crud_role_module_permission(',
        'itm_require_role_module_permission(',
        'itm_require_admin(',
    ];
}

function itm_crud_rbac_block_has_guard(string $block): bool
{
    foreach (itm_crud_rbac_alternate_guard_markers() as $marker) {
        if (strpos($block, $marker) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @return string|null
 */
function itm_crud_rbac_extract_braced_block(string $content, int $openBracePos)
{
    $depth = 0;
    $len = strlen($content);
    for ($i = $openBracePos; $i < $len; $i++) {
        $ch = $content[$i];
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($content, $openBracePos, $i - $openBracePos + 1);
            }
        }
    }

    return null;
}

foreach (glob(dirname(__DIR__) . '/modules/*/index.php') as $path) {
    $slug = basename(dirname($path));
    if (in_array($slug, itm_crud_rbac_exempt_module_slugs(), true)) {
        continue;
    }

    $content = (string)file_get_contents($path);
    $rel = 'modules/' . $slug . '/index.php';

    if (preg_match('/if\s*\(\$crud_action\s*===\s*[\'"]delete[\'"]\s*\)\s*\{/', $content, $m, PREG_OFFSET_CAPTURE)) {
        $block = itm_crud_rbac_extract_braced_block($content, (int)$m[0][1] + strlen($m[0][0]) - 1);
        if (!is_string($block) || !itm_crud_rbac_block_has_guard($block)) {
            $missing[] = $rel . ' — delete handler missing RBAC guard';
        }
    }

    if (preg_match("/if\s*\(\$_SERVER\['REQUEST_METHOD'\]\s*===\s*'POST'\s*&&\s*in_array\(\$crud_action,\s*\['create',\s*'edit'\],\s*true\)[^{]*\{/", $content, $m, PREG_OFFSET_CAPTURE)) {
        $block = itm_crud_rbac_extract_braced_block($content, (int)$m[0][1] + strlen($m[0][0]) - 1);
        if (!is_string($block) || !itm_crud_rbac_block_has_guard($block)) {
            $missing[] = $rel . ' — create/edit POST handler missing RBAC guard';
        }
    }
}

if ($missing === []) {
    echo colorText('[PASS] CRUD RBAC guards present on all in-scope flattened index.php handlers.', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

echo colorText('[FAIL] Missing CRUD RBAC guards:', 'fail') . $nl;
foreach ($missing as $line) {
    echo '  - ' . $line . $nl;
}
echo colorText('Repair: php scripts/apply_crud_rbac_guards.php', 'fail') . $nl;
itm_script_output_end();
exit(1);
