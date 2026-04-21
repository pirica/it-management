<?php
/**
 * Audits module UI structure against UI Configuration capabilities.
 *
 * Why: the application exposes per-company layout toggles (table actions,
 * new buttons, export toolbar, and back/save alignment). This script provides
 * a single verification pass across modules so regressions are easy to spot.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$modulesDir = $root . '/modules';

if (!is_dir($modulesDir)) {
    fwrite(STDERR, "Modules directory not found: {$modulesDir}\n");
    exit(1);
}

$excludeModules = ['idfs'];

/**
 * @return array<int, string>
 */
function itm_list_modules(string $modulesDir, array $excludeModules): array
{
    $items = scandir($modulesDir) ?: [];
    $modules = [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        if (in_array($item, $excludeModules, true)) {
            continue;
        }

        $path = $modulesDir . '/' . $item;
        if (is_dir($path)) {
            $modules[] = $item;
        }
    }

    sort($modules);
    return $modules;
}

function itm_read_file_or_empty(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    $content = file_get_contents($path);
    return is_string($content) ? $content : '';
}

/**
 * @return array{status:string,details:string}
 */
function itm_check_table_actions(string $indexContent): array
{
    if ($indexContent === '' || stripos($indexContent, '<table') === false) {
        return ['status' => 'n/a', 'details' => 'No table in index.php'];
    }

    $hasActionHeader = preg_match('/<th[^>]*>\s*(Actions?|Table\s+Actions|Options)\s*<\/th>/i', $indexContent) === 1;
    $hasMappedCell = stripos($indexContent, 'data-itm-actions-origin="1"') !== false || stripos($indexContent, 'data-itm-actions-origin=\'1\'') !== false;

    if ($hasActionHeader || $hasMappedCell) {
        return ['status' => 'pass', 'details' => 'Action column discoverable'];
    }

    return ['status' => 'fail', 'details' => 'Table exists but action column marker/header was not detected'];
}

/**
 * @return array{status:string,details:string}
 */
function itm_check_new_button(string $indexContent, bool $hasCreateFile): array
{
    if (!$hasCreateFile) {
        return ['status' => 'n/a', 'details' => 'Module has no create.php'];
    }

    $hasCreateLink = preg_match('/<a[^>]*href\s*=\s*["\'][^"\']*(create\.php|new|add)[^"\']*["\'][^>]*>/i', $indexContent) === 1
        && preg_match('/<a[^>]*class\s*=\s*["\'][^"\']*btn[^"\']*btn-primary[^"\']*["\'][^>]*href\s*=\s*["\'][^"\']*(create\.php|new|add)[^"\']*["\'][^>]*>|<a[^>]*href\s*=\s*["\'][^"\']*(create\.php|new|add)[^"\']*["\'][^>]*class\s*=\s*["\'][^"\']*btn[^"\']*btn-primary[^"\']*["\'][^>]*>/i', $indexContent) === 1;

    if ($hasCreateLink) {
        return ['status' => 'pass', 'details' => 'Primary create/add control detected'];
    }

    return ['status' => 'fail', 'details' => 'create.php exists but index.php has no detectable primary create/add action'];
}

/**
 * @return array{status:string,details:string}
 */
function itm_check_export_toolbar_support(string $indexContent): array
{
    if ($indexContent === '' || stripos($indexContent, '<table') === false) {
        return ['status' => 'n/a', 'details' => 'No table in index.php'];
    }

    $hasCard = preg_match('/class\s*=\s*["\'][^"\']*card[^"\']*["\']/i', $indexContent) === 1;
    if ($hasCard) {
        return ['status' => 'pass', 'details' => 'Card wrapper present for table-tools.js'];
    }

    return ['status' => 'fail', 'details' => 'Table exists but no .card wrapper was detected'];
}

/**
 * @return array{status:string,details:string}
 */
function itm_check_back_save(string $formContent, string $filename): array
{
    if ($formContent === '' || stripos($formContent, '<form') === false) {
        return ['status' => 'n/a', 'details' => "No form in {$filename}"];
    }

    $hasSubmit = stripos($formContent, 'type="submit"') !== false || stripos($formContent, "type='submit'") !== false;
    $hasBack = stripos($formContent, 'index.php') !== false || stripos($formContent, 'history.back') !== false || stripos($formContent, 'javascript:history.back') !== false;

    if ($hasSubmit && $hasBack) {
        return ['status' => 'pass', 'details' => "Back + submit controls detected in {$filename}"];
    }

    return ['status' => 'fail', 'details' => "Could not detect paired back/save controls in {$filename}"];
}

$modules = itm_list_modules($modulesDir, $excludeModules);
$totals = ['pass' => 0, 'fail' => 0, 'n/a' => 0];
$moduleFailures = [];

echo "UI Configuration Coverage Audit\n";
echo "Root: {$modulesDir}\n";
echo "Excluded modules: " . implode(', ', $excludeModules) . "\n\n";

foreach ($modules as $module) {
    $modulePath = $modulesDir . '/' . $module;
    $indexPath = $modulePath . '/index.php';
    $createPath = $modulePath . '/create.php';
    $editPath = $modulePath . '/edit.php';

    $indexContent = itm_read_file_or_empty($indexPath);
    $createContent = itm_read_file_or_empty($createPath);
    $editContent = itm_read_file_or_empty($editPath);

    $checks = [
        'Table Actions' => itm_check_table_actions($indexContent),
        '+ New Button' => itm_check_new_button($indexContent, is_file($createPath)),
        'Export Buttons' => itm_check_export_toolbar_support($indexContent),
        'Back & Save (create.php)' => itm_check_back_save($createContent, 'create.php'),
        'Back & Save (edit.php)' => itm_check_back_save($editContent, 'edit.php'),
    ];

    foreach ($checks as $checkName => $result) {
        $status = $result['status'];
        $totals[$status]++;

        $label = str_pad($status, 4, ' ', STR_PAD_RIGHT);
        echo "[{$label}] {$module} :: {$checkName} - {$result['details']}\n";

        if ($status === 'fail') {
            $moduleFailures[$module][] = "{$checkName}: {$result['details']}";
        }
    }

    echo "\n";
}

echo "==== Summary ====\n";
echo 'PASS: ' . $totals['pass'] . "\n";
echo 'FAIL: ' . $totals['fail'] . "\n";
echo 'N/A : ' . $totals['n/a'] . "\n";

if ($totals['fail'] > 0) {
    echo "\nModules with failures:\n";
    foreach ($moduleFailures as $module => $failures) {
        echo "- {$module}\n";
        foreach ($failures as $failure) {
            echo "    * {$failure}\n";
        }
    }
    exit(2);
}

exit(0);
