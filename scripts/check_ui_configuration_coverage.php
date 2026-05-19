<?php
/**
 * Audits module UI structure against UI Configuration capabilities.
 *
 * Why: the application exposes per-company layout toggles (table actions,
 * new buttons, export toolbar, and back/save alignment). This script provides
 * a single verification pass across modules so regressions are easy to spot.
 *
 * Entry wrappers: create.php, edit.php, and view.php are checked for CRUD delegation.
 * delete.php and list_all.php use separate rules (wrapper OR standalone entry file).
 * Entry helpers
 * reports module helpers, cross-module, and shared includes with found/MISSING status.
 *
 * Exemptions:
 *   - scripts/data/ui_configuration_excluded_modules.txt (explicit module slugs)
 *   - scripts/data/ui_configuration_excluded_prefixes.txt (e.g. is_* equipment façades)
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$modulesDir = $root . '/modules';

if (!is_dir($modulesDir)) {
    fwrite(STDERR, "Modules directory not found: {$modulesDir}\n");
    exit(1);
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('UI configuration coverage check');

$excludeModulesFile = __DIR__ . '/data/ui_configuration_excluded_modules.txt';
$excludePrefixesFile = __DIR__ . '/data/ui_configuration_excluded_prefixes.txt';

/**
 * @return array<int, string>
 */
function itm_ui_config_load_excluded_list(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $entries = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $entries[] = $line;
    }

    return $entries;
}

/**
 * @return array<int, string>
 */
function itm_list_modules(string $modulesDir, array $excludeModules, array $excludeModulePrefixes): array
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

        $skipByPrefix = false;
        foreach ($excludeModulePrefixes as $prefix) {
            if ($prefix !== '' && strpos($item, $prefix) === 0) {
                $skipByPrefix = true;
                break;
            }
        }
        if ($skipByPrefix) {
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
 * @return array<int, string>
 */
function itm_ui_entry_crud_targets(): array
{
    return ['create.php', 'edit.php', 'index.php', 'list_all.php', 'view.php', 'delete.php'];
}

/**
 * @return array<int, string>
 */
function itm_ui_entry_terminal_bases(): array
{
    return ['index.php', 'list_all.php', 'view.php', 'delete.php'];
}

/**
 * @return array{relative:string,resolved_path:string,exists:bool,category:string}
 */
function itm_ui_resolve_require_path(string $modulePath, string $relative, bool $fromRepoRoot = false): array
{
    $relative = trim(str_replace('\\', '/', $relative), '/');
    $basePath = $fromRepoRoot ? dirname($modulePath, 2) : $modulePath;
    $candidate = $basePath . '/' . $relative;
    $resolved = realpath($candidate);

    return [
        'relative' => $relative,
        'resolved_path' => $resolved !== false ? $resolved : $candidate,
        'exists' => $resolved !== false && is_file($resolved),
        'category' => itm_ui_classify_require($relative, $fromRepoRoot),
    ];
}

/**
 * @return string config|crud_entry|module_helper|shared_include|cross_module|other
 */
function itm_ui_classify_require(string $relative, bool $fromRepoRoot = false): string
{
    if (preg_match('#(^|/)config/config\.php$#i', $relative)) {
        return 'config';
    }

    $base = basename($relative);
    if (in_array($base, itm_ui_entry_crud_targets(), true)) {
        return 'crud_entry';
    }

    if ($fromRepoRoot) {
        if (strpos($relative, 'includes/') === 0) {
            return 'shared_include';
        }
        if (strpos($relative, 'modules/') === 0) {
            return 'cross_module';
        }
    }

    if (preg_match('#(^|/)(\.\./)+includes/#', $relative)) {
        return 'shared_include';
    }

    if (preg_match('#(^|/)includes/#', $relative)
        || preg_match('#_(helpers|handlers|sync|schema)\.php$#i', $relative)
        || preg_match('#_view\.php$#i', $relative)
        || preg_match('#\.php$#i', $relative)
    ) {
        return 'module_helper';
    }

    return 'other';
}

/**
 * @return array<int, array{relative:string,resolved_path:string,exists:bool,category:string}>
 */
function itm_ui_parse_entry_requires(string $entryContent, string $modulePath): array
{
    if ($entryContent === '') {
        return [];
    }

    $requires = [];
    $patterns = [
        '/\b(?:require|include)(?:_once)?\s*(?:\(\s*__DIR__\s*\.\s*[\'"]([^\'"]+)[\'"]\s*\)|__DIR__\s*\.\s*[\'"]([^\'"]+)[\'"]|[\'"]([^\'"]+\.php)[\'"])/i',
        '/\b(?:require|include)(?:_once)?\s*ROOT_PATH\s*\.\s*[\'"]([^\'"]+)[\'"]/i',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $entryContent, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $relative = '';
            for ($i = 1; $i < count($match); $i++) {
                if (isset($match[$i]) && $match[$i] !== '') {
                    $relative = (string)$match[$i];
                    break;
                }
            }
            if ($relative === '') {
                continue;
            }

            $fromRepoRoot = strpos($pattern, 'ROOT_PATH') !== false;
            $resolved = itm_ui_resolve_require_path($modulePath, $relative, $fromRepoRoot);
            $requires[$resolved['relative']] = $resolved;
        }
    }

    return array_values($requires);
}

/**
 * Why: create.php and edit.php are often thin delegates (index.php or a shared
 * create.php in another module) while the real form lives in another entry file.
 *
 * @return array{is_wrapper:bool,target_label:string,target_exists:bool,resolved_path:string}
 */
function itm_ui_detect_entry_wrapper(string $entryContent, string $modulePath): array
{
    $none = [
        'is_wrapper' => false,
        'target_label' => '',
        'target_exists' => false,
        'resolved_path' => '',
    ];

    foreach (itm_ui_parse_entry_requires($entryContent, $modulePath) as $require) {
        if ($require['category'] !== 'crud_entry') {
            continue;
        }

        if ($require['exists']) {
            return [
                'is_wrapper' => true,
                'target_label' => $require['relative'],
                'target_exists' => true,
                'resolved_path' => $require['resolved_path'],
            ];
        }

        return [
            'is_wrapper' => true,
            'target_label' => $require['relative'],
            'target_exists' => false,
            'resolved_path' => $require['resolved_path'],
        ];
    }

    return $none;
}

/**
 * @return array{content:string,chain:array<int,string>}
 */
function itm_ui_resolve_entry_form_source(string $modulePath, string $entryContent, string $recurseTargetBase, int $depth = 0): array
{
    if ($depth > 5) {
        return ['content' => $entryContent, 'chain' => []];
    }

    $wrapper = itm_ui_detect_entry_wrapper($entryContent, $modulePath);
    if (!$wrapper['is_wrapper'] || !$wrapper['target_exists']) {
        return ['content' => $entryContent, 'chain' => []];
    }

    $targetContent = itm_read_file_or_empty($wrapper['resolved_path']);
    $targetBase = basename(str_replace('\\', '/', $wrapper['target_label']));

    if (in_array($targetBase, itm_ui_entry_terminal_bases(), true)) {
        return [
            'content' => $targetContent,
            'chain' => [$wrapper['target_label']],
        ];
    }

    if ($targetBase === $recurseTargetBase) {
        $targetModulePath = dirname($wrapper['resolved_path']);
        $inner = itm_ui_resolve_entry_form_source($targetModulePath, $targetContent, $recurseTargetBase, $depth + 1);

        return [
            'content' => $inner['content'],
            'chain' => array_merge([$wrapper['target_label']], $inner['chain']),
        ];
    }

    return [
        'content' => $targetContent,
        'chain' => [$wrapper['target_label']],
    ];
}

/**
 * Whether the module exposes delete (file and/or index.php actions pointing at delete.php).
 */
function itm_module_uses_delete(string $indexContent, string $deletePath): bool
{
    if (is_file($deletePath)) {
        return true;
    }

    if ($indexContent === '') {
        return false;
    }

    return preg_match('#delete\.php#i', $indexContent) === 1;
}

/**
 * Delete entry files are usually standalone POST handlers, not index.php wrappers.
 *
 * @return array{status:string,details:string}
 */
function itm_check_delete_entry(string $modulePath, string $indexContent, string $deletePath, string $deleteContent): array
{
    if (!itm_module_uses_delete($indexContent, $deletePath)) {
        return ['status' => 'n/a', 'details' => 'Delete not used (no delete.php and no delete.php action in index.php)'];
    }

    if (!is_file($deletePath)) {
        return ['status' => 'fail', 'details' => 'index.php references delete.php but delete.php is missing on disk'];
    }

    $wrapper = itm_ui_detect_entry_wrapper($deleteContent, $modulePath);
    if ($wrapper['is_wrapper']) {
        if (!$wrapper['target_exists']) {
            return [
                'status' => 'fail',
                'details' => 'Wrapper references ' . $wrapper['target_label'] . ' but that file was not found on disk',
            ];
        }

        $chain = itm_ui_resolve_entry_form_source($modulePath, $deleteContent, 'delete.php')['chain'];
        $via = empty($chain) ? $wrapper['target_label'] : implode(' → ', $chain);

        return ['status' => 'pass', 'details' => 'Wrapper found — delete.php delegates to ' . $via];
    }

    $signals = [];
    if (stripos($deleteContent, 'itm_require_post_csrf') !== false) {
        $signals[] = 'CSRF';
    }
    if (preg_match('#\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]#i', $deleteContent) === 1) {
        $signals[] = 'POST guard';
    }
    if (stripos($deleteContent, 'bulk') !== false || stripos($deleteContent, 'DELETE') !== false) {
        $signals[] = 'delete logic';
    }

    $hint = $signals === [] ? 'standalone handler' : implode(', ', $signals);

    return ['status' => 'pass', 'details' => 'Delete handler present (' . $hint . '; not a wrapper)'];
}

/**
 * Whether the module exposes list-all (file and/or index.php links to list_all.php).
 */
function itm_module_uses_list_all(string $indexContent, string $listAllPath): bool
{
    if (is_file($listAllPath)) {
        return true;
    }

    if ($indexContent === '') {
        return false;
    }

    return preg_match('#list_all\.php#i', $indexContent) === 1;
}

/**
 * list_all.php is often a full list screen (shared CRUD copy), not require index.php.
 *
 * @return array{status:string,details:string}
 */
function itm_check_list_all_entry(string $modulePath, string $indexContent, string $listAllPath, string $listAllContent): array
{
    if (!itm_module_uses_list_all($indexContent, $listAllPath)) {
        return ['status' => 'n/a', 'details' => 'List-all not used (no list_all.php and no list_all.php link in index.php)'];
    }

    if (!is_file($listAllPath)) {
        return ['status' => 'fail', 'details' => 'index.php references list_all.php but list_all.php is missing on disk'];
    }

    $wrapper = itm_ui_detect_entry_wrapper($listAllContent, $modulePath);
    if ($wrapper['is_wrapper']) {
        if (!$wrapper['target_exists']) {
            return [
                'status' => 'fail',
                'details' => 'Wrapper references ' . $wrapper['target_label'] . ' but that file was not found on disk',
            ];
        }

        $chain = itm_ui_resolve_entry_form_source($modulePath, $listAllContent, 'list_all.php')['chain'];
        $via = empty($chain) ? $wrapper['target_label'] : implode(' → ', $chain);

        return ['status' => 'pass', 'details' => 'Wrapper found — list_all.php delegates to ' . $via];
    }

    $signals = [];
    if (preg_match('#\$crud_action\s*=\s*[\'"]list_all[\'"]#i', $listAllContent) === 1) {
        $signals[] = 'crud_action=list_all';
    }
    if (stripos($listAllContent, '<table') !== false) {
        $signals[] = 'table UI';
    }
    if (stripos($listAllContent, 'data-itm-db-import-endpoint') !== false) {
        $signals[] = 'import endpoint';
    }

    $hint = $signals === [] ? 'standalone list screen' : implode(', ', $signals);

    return ['status' => 'pass', 'details' => 'List-all entry present (' . $hint . '; not a wrapper)'];
}

/**
 * @return array{status:string,details:string}
 */
function itm_check_entry_wrapper(string $entryPath, string $entryContent, string $modulePath, string $entryBasename): array
{
    if (!is_file($entryPath)) {
        return ['status' => 'n/a', 'details' => 'No ' . $entryBasename];
    }

    $wrapper = itm_ui_detect_entry_wrapper($entryContent, $modulePath);
    if (!$wrapper['is_wrapper']) {
        return ['status' => 'n/a', 'details' => 'Wrapper not found'];
    }

    if (!$wrapper['target_exists']) {
        return [
            'status' => 'fail',
            'details' => 'Wrapper references ' . $wrapper['target_label'] . ' but that file was not found on disk',
        ];
    }

    $chain = itm_ui_resolve_entry_form_source($modulePath, $entryContent, $entryBasename)['chain'];
    $via = empty($chain) ? $wrapper['target_label'] : implode(' → ', $chain);

    return ['status' => 'pass', 'details' => 'Wrapper found — ' . $entryBasename . ' delegates to ' . $via];
}

/**
 * @return array{status:string,details:string}
 */
function itm_check_entry_helpers(string $entryPath, string $entryContent, string $modulePath, string $entryBasename, string $indexContent = ''): array
{
    if ($entryBasename === 'delete.php' && !itm_module_uses_delete($indexContent, $entryPath)) {
        return ['status' => 'n/a', 'details' => 'Delete not used'];
    }

    if ($entryBasename === 'list_all.php' && !itm_module_uses_list_all($indexContent, $entryPath)) {
        return ['status' => 'n/a', 'details' => 'List-all not used'];
    }

    if (!is_file($entryPath)) {
        return ['status' => 'n/a', 'details' => 'No ' . $entryBasename];
    }

    $requires = itm_ui_parse_entry_requires($entryContent, $modulePath);
    if ($requires === []) {
        return ['status' => 'n/a', 'details' => 'Wrapper not found; no require/include targets'];
    }

    $groups = [
        'crud_entry' => [],
        'module_helper' => [],
        'cross_module' => [],
        'shared_include' => [],
        'other' => [],
    ];
    $hasMissing = false;

    foreach ($requires as $require) {
        if ($require['category'] === 'config') {
            continue;
        }

        $label = $require['relative'] . ($require['exists'] ? ' (found)' : ' (MISSING)');
        if (!$require['exists']) {
            $hasMissing = true;
        }

        $bucket = $require['category'];
        if (!isset($groups[$bucket])) {
            $bucket = 'other';
        }
        $groups[$bucket][] = $label;
    }

    $parts = [];
    if ($groups['crud_entry'] !== []) {
        $parts[] = 'CRUD: ' . implode(', ', $groups['crud_entry']);
    }
    if ($groups['module_helper'] !== []) {
        $parts[] = 'Module helpers: ' . implode(', ', $groups['module_helper']);
    }
    if ($groups['cross_module'] !== []) {
        $parts[] = 'Cross-module: ' . implode(', ', $groups['cross_module']);
    }
    if ($groups['shared_include'] !== []) {
        $parts[] = 'Shared includes: ' . implode(', ', $groups['shared_include']);
    }
    if ($groups['other'] !== []) {
        $parts[] = 'Other: ' . implode(', ', $groups['other']);
    }

    if ($parts === []) {
        return ['status' => 'n/a', 'details' => 'Wrapper not found; only config bootstrap requires'];
    }

    $details = implode('; ', $parts);
    if ($hasMissing) {
        return ['status' => 'fail', 'details' => $details];
    }

    return ['status' => 'pass', 'details' => $details];
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

/**
 * @return array{status:string,details:string}
 */
function itm_check_back_save_entry(string $modulePath, string $entryPath, string $entryContent, string $entryBasename): array
{
    if (!is_file($entryPath)) {
        return ['status' => 'n/a', 'details' => 'No ' . $entryBasename];
    }

    $wrapperCheck = itm_check_entry_wrapper($entryPath, $entryContent, $modulePath, $entryBasename);
    if ($wrapperCheck['status'] === 'fail') {
        return $wrapperCheck;
    }

    $resolved = itm_ui_resolve_entry_form_source($modulePath, $entryContent, $entryBasename);
    $viaLabel = empty($resolved['chain'])
        ? $entryBasename
        : $entryBasename . ' via ' . implode(' → ', $resolved['chain']);

    $result = itm_check_back_save($resolved['content'], $viaLabel);
    if ($wrapperCheck['status'] === 'pass' && $result['status'] === 'n/a' && stripos($result['details'], 'No form') !== false) {
        $result['details'] = 'Wrapper found but delegated file has no detectable form (' . $viaLabel . ')';
        $result['status'] = 'fail';
    }

    if ($wrapperCheck['status'] === 'n/a' && $result['status'] === 'n/a' && stripos($result['details'], 'No form') !== false) {
        $result['details'] = 'Wrapper not found — no form in ' . $entryBasename;
    }

    return $result;
}

$excludeModules = itm_ui_config_load_excluded_list($excludeModulesFile);
$excludeModulePrefixes = itm_ui_config_load_excluded_list($excludePrefixesFile);
$modules = itm_list_modules($modulesDir, $excludeModules, $excludeModulePrefixes);
$totals = ['pass' => 0, 'fail' => 0, 'n/a' => 0];
$moduleFailures = [];

echo "UI Configuration Coverage Audit\n";
echo "Root: {$modulesDir}\n";
echo "Excluded modules: " . (empty($excludeModules) ? '(none)' : implode(', ', $excludeModules));
echo " (from " . basename($excludeModulesFile) . ")\n";
echo "Excluded prefixes: " . (empty($excludeModulePrefixes) ? '(none)' : implode(', ', $excludeModulePrefixes));
echo " (from " . basename($excludePrefixesFile) . ")\n\n";

foreach ($modules as $module) {
    $modulePath = $modulesDir . '/' . $module;
    $indexPath = $modulePath . '/index.php';
    $createPath = $modulePath . '/create.php';
    $editPath = $modulePath . '/edit.php';
    $viewPath = $modulePath . '/view.php';
    $listAllPath = $modulePath . '/list_all.php';
    $deletePath = $modulePath . '/delete.php';

    $indexContent = itm_read_file_or_empty($indexPath);
    $createContent = itm_read_file_or_empty($createPath);
    $editContent = itm_read_file_or_empty($editPath);
    $viewContent = itm_read_file_or_empty($viewPath);
    $listAllContent = itm_read_file_or_empty($listAllPath);
    $deleteContent = itm_read_file_or_empty($deletePath);

    $checks = [
        'Table Actions' => itm_check_table_actions($indexContent),
        '+ New Button' => itm_check_new_button($indexContent, is_file($createPath)),
        'Export Buttons' => itm_check_export_toolbar_support($indexContent),
        'Create wrapper (create.php)' => itm_check_entry_wrapper($createPath, $createContent, $modulePath, 'create.php'),
        'Edit wrapper (edit.php)' => itm_check_entry_wrapper($editPath, $editContent, $modulePath, 'edit.php'),
        'View wrapper (view.php)' => itm_check_entry_wrapper($viewPath, $viewContent, $modulePath, 'view.php'),
        'List-all entry (list_all.php)' => itm_check_list_all_entry($modulePath, $indexContent, $listAllPath, $listAllContent),
        'Delete entry (delete.php)' => itm_check_delete_entry($modulePath, $indexContent, $deletePath, $deleteContent),
        'Entry helpers (create.php)' => itm_check_entry_helpers($createPath, $createContent, $modulePath, 'create.php'),
        'Entry helpers (edit.php)' => itm_check_entry_helpers($editPath, $editContent, $modulePath, 'edit.php'),
        'Entry helpers (delete.php)' => itm_check_entry_helpers($deletePath, $deleteContent, $modulePath, 'delete.php', $indexContent),
        'Entry helpers (list_all.php)' => itm_check_entry_helpers($listAllPath, $listAllContent, $modulePath, 'list_all.php', $indexContent),
        'Back & Save (create.php)' => itm_check_back_save_entry($modulePath, $createPath, $createContent, 'create.php'),
        'Back & Save (edit.php)' => itm_check_back_save_entry($modulePath, $editPath, $editContent, 'edit.php'),
    ];

    foreach ($checks as $checkName => $result) {
        $status = $result['status'];
        $totals[$status]++;

        $label = str_pad($status, 4, ' ', STR_PAD_RIGHT);
        $moduleLabel = itm_script_format_module_link($module);
        echo "[{$label}] {$moduleLabel} :: {$checkName} - {$result['details']}\n";

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
        echo '- ' . itm_script_format_module_link($module) . "\n";
        foreach ($failures as $failure) {
            echo "    * {$failure}\n";
        }
    }
    exit(2);
}

exit(0);
