<?php
/**
 * Insert single POST mutation RBAC chokepoint on standalone create.php / edit.php / delete.php.
 *
 * Skips index.php wrappers (require index.php). Browser + CLI dry-run by default.
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="apply_crud_mutation_bootstrap.php">dry-run</a> / <a href="apply_crud_mutation_bootstrap.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_crud_mutation_bootstrap.php</code> then <code>php scripts/apply_crud_mutation_bootstrap.php --apply</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_crud_rbac_mutation_apply.php';

$boot = itm_apply_script_bootstrap('Apply CRUD Mutation Bootstrap');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$changed = [];
$skippedWrapper = [];
$exempt = [];

foreach (['create.php', 'edit.php', 'delete.php'] as $entryFile) {
    foreach (glob($root . '/modules/*/' . $entryFile) as $path) {
        $slug = basename(dirname($path));
        if (in_array($slug, itm_crud_rbac_exempt_module_slugs(), true)) {
            $exempt[] = $slug . '/' . $entryFile;
            continue;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }

        if (itm_crud_rbac_mutation_entry_is_index_wrapper($content)) {
            $skippedWrapper[] = 'modules/' . $slug . '/' . $entryFile;
            continue;
        }

        $updated = itm_crud_rbac_mutation_apply_entry_content($content);
        if ($updated === $content) {
            continue;
        }

        if ($apply) {
            file_put_contents($path, $updated);
        }
        $changed[] = 'modules/' . $slug . '/' . $entryFile;
    }
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' standalone entry file(s).' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' files', $changed);
itm_apply_script_echo_list('Index wrappers (skipped)', $skippedWrapper);
itm_apply_script_echo_list('RBAC exempt (skipped)', $exempt);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_crud_mutation_bootstrap.php');

itm_script_output_end();
exit(0);
