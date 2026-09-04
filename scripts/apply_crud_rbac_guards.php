<?php
/**
 * Insert single POST mutation RBAC chokepoint on flattened CRUD index.php handlers.
 *
 * Browser + CLI. Default dry-run; --apply / ?apply=1 (Admin) writes files.
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="apply_crud_rbac_guards.php">dry-run</a> / <a href="apply_crud_rbac_guards.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_crud_rbac_guards.php</code> then <code>php scripts/apply_crud_rbac_guards.php --apply</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_crud_rbac_mutation_apply.php';

$boot = itm_apply_script_bootstrap('Apply CRUD RBAC Mutation Chokepoint');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$changed = [];
$exempt = [];
$unchanged = [];

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

    $updated = itm_crud_rbac_mutation_apply_index_content($content, $slug);
    if ($updated === $content) {
        $unchanged[] = 'modules/' . $slug . '/index.php';
        continue;
    }

    if ($apply) {
        file_put_contents($path, $updated);
    }
    $changed[] = 'modules/' . $slug . '/index.php';
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' module index.php file(s).' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' files', $changed);
itm_apply_script_echo_list('RBAC exempt (skipped)', $exempt);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_crud_rbac_guards.php');

itm_script_output_end();
exit(0);
