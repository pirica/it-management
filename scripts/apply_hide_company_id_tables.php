<?php
/**
 * Add module table names to $hideCompanyIdTables on every scaffold PHP file in scope.
 *
 * CLI: php scripts/apply_hide_company_id_tables.php [--module=slug] [--prefix=hotel_] [--apply]
 * Browser: scripts/apply_hide_company_id_tables.php?run=1 (dry-run) / ?run=1&apply=1 (Admin)
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_company_id_ui_column_audit.php';
require_once __DIR__ . '/lib/itm_fields_missing_report.php';

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/apply_hide_company_id_tables.php</code> — dry-run: lists PHP files that would gain the module table in <code>$hideCompanyIdTables</code>.<br>
<code>php scripts/apply_hide_company_id_tables.php --apply</code> — writes changes (Admin + <code>?apply=1</code> in browser).<br>
Optional filters: <code>--module=appointment_type</code> (repeatable) or <code>--prefix=hotel_</code> (all module folders matching).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$boot = itm_apply_script_bootstrap('Hide company_id UI tables', [
    'usage_gate_title' => 'Hide company_id from list/view',
]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/\\');

$argvLocal = $boot['argv'] ?? [];
if (PHP_SAPI !== 'cli') {
    if (isset($_GET['module'])) {
        $raw = (string) $_GET['module'];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '') {
                $argvLocal[] = '--module=' . $part;
            }
        }
    }
    if (isset($_GET['prefix'])) {
        $argvLocal[] = '--prefix=' . (string) $_GET['prefix'];
    }
}

$moduleSlugs = [];
$prefix = '';
foreach ($argvLocal as $arg) {
    if (strpos((string) $arg, '--module=') === 0) {
        $slug = trim(substr((string) $arg, 9));
        if ($slug !== '') {
            $moduleSlugs[$slug] = true;
        }
    } elseif (strpos((string) $arg, '--prefix=') === 0) {
        $prefix = trim(substr((string) $arg, 9));
    }
}

if ($prefix !== '') {
    foreach (itm_company_id_ui_column_discover_module_slugs($root) as $slug) {
        if (strpos($slug, $prefix) === 0) {
            $moduleSlugs[$slug] = true;
        }
    }
}

if ($moduleSlugs === []) {
    echo colorText('[FAIL] Specify --module=slug and/or --prefix=hotel_.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

ksort($moduleSlugs);

$changed = [];
$skipped = [];

foreach (array_keys($moduleSlugs) as $slug) {
    $filesByPath = itm_company_id_ui_column_collect_module_php_files($slug, $root);
    if ($filesByPath === []) {
        $skipped[] = $slug . ' (missing module folder)';
        continue;
    }

    $table = itm_company_id_ui_column_parse_crud_table_from_files($filesByPath);
    if ($table === null || $table === '') {
        $skipped[] = $slug . ' (no $crud_table)';
        continue;
    }

    $hideFiles = itm_company_id_ui_column_files_with_hide_list($filesByPath);
    if ($hideFiles === []) {
        $skipped[] = $slug . ' (no $hideCompanyIdTables scaffold files)';
        continue;
    }

    foreach ($hideFiles as $path => $content) {
        if (itm_company_id_ui_column_scaffold_table_in_hide_list($content, $table)) {
            continue;
        }

        $updated = preg_replace(
            '/\$hideCompanyIdTables\s*=\s*\[/',
            "\$hideCompanyIdTables = ['" . $table . "', ",
            $content,
            1
        );
        if (!is_string($updated) || $updated === $content) {
            $skipped[] = $path . ' (regex patch failed)';
            continue;
        }

        if ($apply) {
            file_put_contents($root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path), $updated);
        }
        $changed[] = $path . ' +' . $table;
    }
}

$mode = $apply ? 'Updated' : 'Would update';
echo $mode . ' ' . count($changed) . ' file(s).' . $nl;
foreach ($changed as $line) {
    echo '  ' . $line . $nl;
}
if ($skipped !== []) {
    echo $nl . 'Skipped:' . $nl;
    foreach ($skipped as $line) {
        echo '  ' . $line . $nl;
    }
}

itm_script_output_end();
exit(0);
