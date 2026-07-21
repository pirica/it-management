<?php
/**
 * Insert itm_require_crud_role_module_permission() on flattened CRUD index.php handlers.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply CRUD RBAC Guards');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$guardDelete = "    // Why: Server-side RBAC before CSRF/delete SQL (UI-only hiding is not enough).\n    itm_require_crud_role_module_permission(\$conn, 'delete', '%s');\n\n";
$guardCreateEdit = "    // Why: Server-side RBAC before CSRF persistence (UI-only hiding is not enough).\n    itm_require_crud_role_module_permission(\$conn, \$crud_action, '%s');\n";

$changed = [];
$exempt = [];

foreach (glob($root . '/modules/*/index.php') as $path) {
    $slug = basename(dirname($path));
    if (in_array($slug, itm_crud_rbac_exempt_module_slugs(), true)) {
        $exempt[] = $slug;
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $original = $content;

    $deleteNeedle = "itm_require_crud_role_module_permission(\$conn, 'delete', '{$slug}')";
    if (strpos($content, $deleteNeedle) === false
        && preg_match('/if\s*\(\$crud_action\s*===\s*[\'"]delete[\'"]\s*\)/', $content)
        && strpos($content, 'itm_require_role_module_permission(') === false
        && strpos($content, 'itm_require_crud_role_module_permission(') === false
    ) {
        $patterns = [
            '/(\if\s*\(\$crud_action\s*===\s*[\'"]delete[\'"]\s*\)\s*\{[^{}]*?if\s*\(\$_SERVER\[[\'"]REQUEST_METHOD[\'"]\]\s*!==\s*[\'"]POST[\'"]\s*\)\s*\{[^{}]*?\}\s*\n)(\s*)(cr_require_valid_csrf_token\(\);)/s',
            '/(\if\s*\(\$crud_action\s*===\s*[\'"]delete[\'"]\s*\)\s*\{[^{}]*?if\s*\(\$_SERVER\[[\'"]REQUEST_METHOD[\'"]\]\s*!==\s*[\'"]POST[\'"]\s*\)\s*\{[^{}]*?\}\s*\n)(\s*)(itm_require_post_csrf\(\);)/s',
            '/(\if\s*\(\$crud_action\s*===\s*[\'"]delete[\'"]\s*\)\s*\{\s*\n)(\s*)(itm_require_post_csrf\(\);)/s',
        ];
        foreach ($patterns as $pattern) {
            $replacement = '$1' . sprintf($guardDelete, $slug) . '$2$3';
            $updated = preg_replace($pattern, $replacement, $content, 1, $count);
            if ($count === 1 && is_string($updated)) {
                $content = $updated;
                break;
            }
        }
    }

    $createEditGuard = sprintf($guardCreateEdit, $slug);
    $createEditNeedle = "itm_require_crud_role_module_permission(\$conn, \$crud_action, '{$slug}')";
    if (strpos($content, $createEditNeedle) === false
        && preg_match("/if\s*\(\$_SERVER\['REQUEST_METHOD'\]\s*===\s*'POST'\s*&&\s*in_array\(\$crud_action,\s*\['create',\s*'edit'\],\s*true\)/", $content)
    ) {
        $createPatterns = [
            "/(if\s*\(\$_SERVER\['REQUEST_METHOD'\]\s*===\s*'POST'\s*&&\s*in_array\(\$crud_action,\s*\['create',\s*'edit'\],\s*true\)\s*\)\s*\{\s*\n)(\s*)(cr_require_valid_csrf_token\(\);)/",
            "/(if\s*\(\$_SERVER\['REQUEST_METHOD'\]\s*===\s*'POST'\s*&&\s*in_array\(\$crud_action,\s*\['create',\s*'edit'\],\s*true\)\s*&&\s*\$crud_table\s*!==\s*'floor_plans'\s*\)\s*\{\s*\n)(\s*)(cr_require_valid_csrf_token\(\);)/",
            "/(if\s*\(\$_SERVER\['REQUEST_METHOD'\]\s*===\s*'POST'\s*&&\s*in_array\(\$crud_action,\s*\['create',\s*'edit'\],\s*true\)\s*\)\s*\{\s*\n)(\s*)(itm_require_post_csrf\(\);)/",
        ];
        foreach ($createPatterns as $pattern) {
            $replacement = '$1' . $createEditGuard . "\n$2$3";
            $updated = preg_replace($pattern, $replacement, $content, 1, $count);
            if ($count === 1 && is_string($updated)) {
                $content = $updated;
                break;
            }
        }
    }

    if ($content !== $original) {
        if ($apply) {
            file_put_contents($path, $content);
        }
        $changed[] = 'modules/' . $slug . '/index.php';
    }
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' module index.php file(s).' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' files', $changed);
itm_apply_script_echo_list('RBAC exempt (skipped)', $exempt);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_crud_rbac_guards.php');

itm_script_output_end();
exit(0);
