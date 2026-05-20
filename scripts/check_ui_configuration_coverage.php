<?php
/**
 * Audits module UI structure against UI Configuration capabilities.
 *
 * Why: the application exposes per-company layout toggles (table actions,
 * new buttons, export toolbar, and back/save alignment). This script provides
 * a single verification pass across modules so regressions are easy to spot.
 *
 * CRUD entry files (create/edit/view/list_all/delete): wrapper to index.php OR standalone
 * screen/handler on disk — not "Wrapper not found" when the file is a full CRUD copy.
 * Require/include audits run only when a missing CRUD delegate is reported (no per-file
 * duplicate sidebar/header lines).
 *
 * List table contract (index.php, or list_all.php when index has no table): Search,
 * column sort (ASC/DESC), pagination via Settings records_per_page, and bulk
 * Select to Delete / Clear Table.
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
 * @return array{content:string,source:string}
 */
function itm_ui_resolve_list_table_screen(string $indexContent, string $listAllContent): array
{
    if ($indexContent !== '' && stripos($indexContent, '<table') !== false) {
        return ['content' => $indexContent, 'source' => 'index.php'];
    }

    if ($listAllContent !== '' && stripos($listAllContent, '<table') !== false) {
        return ['content' => $listAllContent, 'source' => 'list_all.php'];
    }

    return ['content' => $indexContent, 'source' => 'index.php'];
}

/**
 * @return array{status:string,details:string}
 */
function itm_check_search(string $listContent, string $sourceLabel): array
{
    if ($listContent === '' || stripos($listContent, '<table') === false) {
        return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
    }

    $hasSearchParam = preg_match('#\$_GET\s*\[\s*[\'"]search[\'"]\s*\]#', $listContent) === 1
        || stripos($listContent, '$searchRaw') !== false
        || preg_match('#\$search\s*=.*\$_GET\s*\[\s*[\'"]search[\'"]\s*\]#s', $listContent) === 1;
    $hasSearchInput = preg_match('#name\s*=\s*["\']search["\']#i', $listContent) === 1;
    $hasSearchQuery = stripos($listContent, 'searchConditions') !== false
        || (stripos($listContent, 'LIKE') !== false && preg_match('#search(Raw|Pattern|Value|Esc|Like)|\$search\s*!==\s*[\'"][\s]*[\'"]#i', $listContent) === 1);

    if ($hasSearchParam && $hasSearchInput && $hasSearchQuery) {
        return ['status' => 'pass', 'details' => 'Search input and server-side query detected in ' . $sourceLabel];
    }

    $missing = [];
    if (!$hasSearchParam) {
        $missing[] = 'GET/search variable';
    }
    if (!$hasSearchInput) {
        $missing[] = 'search input';
    }
    if (!$hasSearchQuery) {
        $missing[] = 'server-side search conditions';
    }

    return [
        'status' => 'fail',
        'details' => 'Table in ' . $sourceLabel . ' missing search wiring: ' . implode(', ', $missing),
    ];
}

/**
 * @return array{status:string,details:string}
 */
function itm_check_sort(string $listContent, string $sourceLabel): array
{
    if ($listContent === '' || stripos($listContent, '<table') === false) {
        return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
    }

    $hasSortParam = preg_match('#\$_GET\s*\[\s*[\'"]sort[\'"]\s*\]#', $listContent) === 1
        || preg_match('#\$sort\s*=.*\$_GET\s*\[\s*[\'"]sort[\'"]\s*\]#s', $listContent) === 1;
    $hasDirParam = preg_match('#\$_GET\s*\[\s*[\'"]dir[\'"]\s*\]#', $listContent) === 1
        || preg_match('#\$dir\s*=.*\$_GET\s*\[\s*[\'"]dir[\'"]\s*\]#s', $listContent) === 1
        || preg_match('#\[\'ASC\'\s*,\s*\'DESC\'\]#', $listContent) === 1;
    $hasOrderBySort = preg_match('#ORDER\s+BY[^\n;]*(\$sortSql|sortableColumns|\$sort\b)#i', $listContent) === 1;
    $hasSortUi = strpos($listContent, '▲') !== false
        || strpos($listContent, '▼') !== false
        || preg_match('#\$nextDir\s*=#', $listContent) === 1;

    if ($hasSortParam && $hasDirParam && $hasOrderBySort && $hasSortUi) {
        return [
            'status' => 'pass',
            'details' => 'Column sort (ASC/DESC) detected in ' . $sourceLabel,
        ];
    }

    $missing = [];
    if (!$hasSortParam) {
        $missing[] = 'GET/sort variable';
    }
    if (!$hasDirParam) {
        $missing[] = 'ASC/DESC direction';
    }
    if (!$hasOrderBySort) {
        $missing[] = 'ORDER BY sort wiring';
    }
    if (!$hasSortUi) {
        $missing[] = 'sortable header indicators (▲/▼ or $nextDir)';
    }

    return [
        'status' => 'fail',
        'details' => 'Table in ' . $sourceLabel . ' missing sort wiring: ' . implode(', ', $missing),
    ];
}

/**
 * @return array{status:string,details:string}
 */
function itm_check_pagination(string $listContent, string $sourceLabel): array
{
    if ($listContent === '' || stripos($listContent, '<table') === false) {
        return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
    }

    $usesRecordsPerPage = stripos($listContent, 'itm_resolve_records_per_page') !== false;
    $hasPerPageVar = preg_match('#\$perPage\s*=#', $listContent) === 1;
    $hasLimitPaging = preg_match('#LIMIT\s+[^\n;]*\$perPage#i', $listContent) === 1
        || (stripos($listContent, 'LIMIT') !== false && stripos($listContent, '$offset') !== false);
    $hasPageState = preg_match('#\$_GET\s*\[\s*[\'"]page[\'"]\s*\]#', $listContent) === 1
        || preg_match('#\$page\s*=#', $listContent) === 1;
    $hasPageNav = (stripos($listContent, 'Previous') !== false
            || stripos($listContent, 'Prev') !== false
            || stripos($listContent, '«') !== false)
        && stripos($listContent, 'Next') !== false;
    $hasRowTotal = stripos($listContent, '$totalPages') !== false || stripos($listContent, '$totalRows') !== false;

    if ($usesRecordsPerPage && $hasPerPageVar && $hasLimitPaging && $hasPageState && $hasPageNav && $hasRowTotal) {
        return [
            'status' => 'pass',
            'details' => 'Pagination uses itm_resolve_records_per_page() in ' . $sourceLabel,
        ];
    }

    $missing = [];
    if (!$usesRecordsPerPage) {
        $missing[] = 'itm_resolve_records_per_page()';
    }
    if (!$hasPerPageVar) {
        $missing[] = '$perPage';
    }
    if (!$hasLimitPaging) {
        $missing[] = 'LIMIT/OFFSET paging';
    }
    if (!$hasPageState) {
        $missing[] = 'page query state';
    }
    if (!$hasPageNav) {
        $missing[] = 'page navigation controls';
    }
    if (!$hasRowTotal) {
        $missing[] = 'total row/page count';
    }

    return [
        'status' => 'fail',
        'details' => 'Table in ' . $sourceLabel . ' missing pagination wiring: ' . implode(', ', $missing),
    ];
}

/**
 * Bulk actions must only be visible when row count meets Settings records_per_page.
 *
 * Why: reject inverted comparisons (perPage >= totalRows) so the audit cannot pass
 * modules that show destructive bulk controls on small tables.
 *
 * @return bool
 */
function itm_has_bulk_actions_records_per_page_gate(string $listContent): bool
{
    if (preg_match(
        '#\$showBulkActions\s*=.*\$perPage\s*(>=|>)\s*\$totalRows#is',
        $listContent
    ) === 1) {
        return false;
    }

    if (preg_match(
        '#if\s*\(\s*\$perPage\s*(>=|>)\s*\$totalRows[^)]*\)[\s\S]{0,2500}bulk[-_]delete#i',
        $listContent
    ) === 1) {
        return false;
    }

    if (preg_match(
        '#\$showBulkActions\s*=.*\$totalRows\s*(>=|>)\s*\$perPage#is',
        $listContent
    ) === 1) {
        return true;
    }

    if (preg_match(
        '#if\s*\(\s*\$totalRows\s*(>=|>)\s*\$perPage[^)]*\)[\s\S]{0,2500}bulk[-_]delete#i',
        $listContent
    ) === 1) {
        return true;
    }

    return false;
}

/**
 * When bulk UI is gated by records_per_page, tbody checkbox cells must use the same gate.
 *
 * Why: hiding only thead/bulk form but leaving ids[] cells breaks column alignment and orphans
 * checkboxes that reference bulk-delete-form when it is not rendered.
 *
 * @return bool
 */
function itm_bulk_row_checkbox_cells_gated(string $listContent): bool
{
    if (!itm_has_bulk_actions_records_per_page_gate($listContent)) {
        return true;
    }

    if (stripos($listContent, 'form="bulk-delete-form"') === false
        || stripos($listContent, 'name="ids[]"') === false) {
        return true;
    }

    $lines = preg_split('/\R/', $listContent) ?: [];
    $lineCount = count($lines);
    for ($i = 0; $i < $lineCount; $i++) {
        $line = $lines[$i];
        $probe = $line;
        if (stripos($probe, 'name="ids[]"') === false && $i > 0 && stripos($lines[$i - 1], '<td') !== false) {
            $probe = $lines[$i - 1] . ' ' . $line;
        }
        if (stripos($probe, 'name="ids[]"') === false || stripos($probe, 'bulk-delete-form') === false) {
            continue;
        }
        if (stripos($probe, '<td') === false) {
            continue;
        }
        // Hidden checkbox columns (e.g. switch_ports) use a separate layout; not a visible misalignment case.
        if (stripos($probe, 'display:none') !== false) {
            continue;
        }
        if (stripos($probe, 'showBulkActions') !== false) {
            continue;
        }
        for ($j = max(0, $i - 6); $j < $i; $j++) {
            if (stripos($lines[$j], 'if ($showBulkActions)') !== false) {
                continue 2;
            }
        }
        return false;
    }

    return true;
}

/**
 * @return array{status:string,details:string}
 */
function itm_check_bulk_delete_actions(string $listContent, string $sourceLabel, bool $hasDeleteFile): array
{
    if ($listContent === '' || stripos($listContent, '<table') === false) {
        return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
    }

    if (!$hasDeleteFile) {
        return ['status' => 'n/a', 'details' => 'Module has no delete.php'];
    }

    $hasBulkDelete = stripos($listContent, 'bulk_delete') !== false;
    $hasClearTable = stripos($listContent, 'clear_table') !== false;
    $hasSelectControl = stripos($listContent, 'Select to Delete') !== false
        || stripos($listContent, 'bulk-delete-toggle') !== false;
    $hasRecordsPerPageGate = itm_has_bulk_actions_records_per_page_gate($listContent);
    $hasGatedRowCheckboxes = itm_bulk_row_checkbox_cells_gated($listContent);
    $hasProgressiveBulkSelection = stripos($listContent, 'setSelectionVisibility') !== false
        && stripos($listContent, 'bulk-delete-toggle') !== false
        && stripos($listContent, 'Delete Selected') !== false;

    if ($hasBulkDelete && $hasClearTable && $hasSelectControl && $hasRecordsPerPageGate
        && ($hasGatedRowCheckboxes || $hasProgressiveBulkSelection)) {
        return [
            'status' => 'pass',
            'details' => 'Select to Delete and Clear Table controls gated when count >= records_per_page in ' . $sourceLabel,
        ];
    }

    $missing = [];
    if (!$hasBulkDelete) {
        $missing[] = 'bulk_delete action';
    }
    if (!$hasClearTable) {
        $missing[] = 'clear_table action';
    }
    if (!$hasSelectControl) {
        $missing[] = 'Select to Delete control';
    }
    if (!$hasRecordsPerPageGate) {
        $missing[] = 'records_per_page visibility gate (showBulkActions = $totalRows >= $perPage or equivalent)';
    }
    if (!$hasGatedRowCheckboxes && !$hasProgressiveBulkSelection) {
        $missing[] = 'tbody ids[] checkbox cells gated with showBulkActions (column alignment) or progressive bulk selection UI';
    }

    return [
        'status' => 'fail',
        'details' => 'Table in ' . $sourceLabel . ' missing bulk actions: ' . implode(', ', $missing),
    ];
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
 * Whether the module uses a CRUD entry file (exists on disk and/or linked from index.php).
 */
function itm_module_uses_entry_file(string $indexContent, string $entryPath, string $entryBasename): bool
{
    if (is_file($entryPath)) {
        return true;
    }

    if ($indexContent === '') {
        return false;
    }

    return preg_match('#' . preg_quote($entryBasename, '#') . '#i', $indexContent) === 1;
}

/**
 * @return string
 */
function itm_entry_screen_label(string $entryBasename): string
{
    $labels = [
        'create.php' => 'Create',
        'edit.php' => 'Edit',
        'view.php' => 'View',
        'list_all.php' => 'List-all',
        'delete.php' => 'Delete',
    ];

    return $labels[$entryBasename] ?? ucfirst(str_replace('.php', '', $entryBasename));
}

/**
 * @return array<int, string>
 */
function itm_standalone_entry_signals(string $entryBasename, string $entryContent): array
{
    $signals = [];
    $actionMap = [
        'create.php' => 'create',
        'edit.php' => 'edit',
        'view.php' => 'view',
        'list_all.php' => 'list_all',
        'delete.php' => 'delete',
    ];

    if (isset($actionMap[$entryBasename])
        && preg_match(
            '#\$crud_action\s*=\s*[\'"]' . preg_quote($actionMap[$entryBasename], '#') . '[\'"]#i',
            $entryContent
        ) === 1
    ) {
        $signals[] = 'crud_action=' . $actionMap[$entryBasename];
    }

    if (in_array($entryBasename, ['create.php', 'edit.php'], true) && stripos($entryContent, '<form') !== false) {
        $signals[] = 'form UI';
    }

    if ($entryBasename === 'view.php') {
        if (preg_match('#view\.php\?id=#i', $entryContent) === 1 || stripos($entryContent, 'read-only') !== false) {
            $signals[] = 'detail view';
        }
    }

    if ($entryBasename === 'list_all.php') {
        if (stripos($entryContent, '<table') !== false) {
            $signals[] = 'table UI';
        }
        if (stripos($entryContent, 'data-itm-db-import-endpoint') !== false) {
            $signals[] = 'import endpoint';
        }
    }

    if ($entryBasename === 'delete.php') {
        if (stripos($entryContent, 'itm_require_post_csrf') !== false
            || stripos($entryContent, 'cr_require_valid_csrf_token') !== false
            || stripos($entryContent, 'require_valid_csrf') !== false) {
            $signals[] = 'CSRF';
        }
        if (preg_match('#\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]#i', $entryContent) === 1) {
            $signals[] = 'POST guard';
        }
        if (stripos($entryContent, 'bulk') !== false
            || stripos($entryContent, 'single_delete') !== false
            || stripos($entryContent, 'clear_table') !== false
            || preg_match('#\bDELETE\s+FROM\b#i', $entryContent) === 1) {
            $signals[] = 'delete logic';
        }
    }

    return $signals;
}

/**
 * Standalone delete.php must expose handler wiring, not merely exist on disk.
 *
 * @param array<int, string> $signals
 * @return array{status:string,details:string}|null
 */
function itm_check_delete_standalone_handler(array $signals)
{
    if ($signals === []) {
        return [
            'status' => 'fail',
            'details' => 'Delete handler missing expected indicators (POST guard, CSRF, delete logic, or crud_action=delete)',
        ];
    }

    $hasPost = in_array('POST guard', $signals, true);
    $hasCsrf = in_array('CSRF', $signals, true);
    $hasDelete = in_array('delete logic', $signals, true);

    if (!$hasPost) {
        return [
            'status' => 'fail',
            'details' => 'Delete handler missing POST guard ($_SERVER REQUEST_METHOD check)',
        ];
    }

    if (!$hasCsrf && !$hasDelete) {
        return [
            'status' => 'fail',
            'details' => 'Delete handler missing CSRF protection and delete logic',
        ];
    }

    return null;
}

/**
 * Wrapper delegate OR standalone CRUD entry screen (shared master-template copies).
 *
 * @return array{status:string,details:string}
 */
function itm_check_module_entry_file(
    string $modulePath,
    string $indexContent,
    string $entryPath,
    string $entryContent,
    string $entryBasename
): array {
    $screenLabel = itm_entry_screen_label($entryBasename);

    if (!itm_module_uses_entry_file($indexContent, $entryPath, $entryBasename)) {
        return [
            'status' => 'n/a',
            'details' => $screenLabel . ' not used (no ' . $entryBasename . ' and no ' . $entryBasename . ' link in index.php)',
        ];
    }

    if (!is_file($entryPath)) {
        return [
            'status' => 'fail',
            'details' => 'index.php references ' . $entryBasename . ' but ' . $entryBasename . ' is missing on disk',
        ];
    }

    $wrapper = itm_ui_detect_entry_wrapper($entryContent, $modulePath);
    if ($wrapper['is_wrapper']) {
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

    $signals = itm_standalone_entry_signals($entryBasename, $entryContent);

    if ($entryBasename === 'delete.php') {
        $reject = itm_check_delete_standalone_handler($signals);
        if ($reject !== null) {
            return $reject;
        }

        return [
            'status' => 'pass',
            'details' => 'Delete handler present (' . implode(', ', $signals) . '; not a wrapper)',
        ];
    }

    $hint = $signals === [] ? 'standalone entry file' : implode(', ', $signals);

    return ['status' => 'pass', 'details' => $screenLabel . ' entry present (' . $hint . '; not a wrapper)'];
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

    $entryCheck = itm_check_module_entry_file($modulePath, '', $entryPath, $entryContent, $entryBasename);
    if ($entryCheck['status'] === 'fail') {
        return $entryCheck;
    }

    $resolved = itm_ui_resolve_entry_form_source($modulePath, $entryContent, $entryBasename);
    $viaLabel = empty($resolved['chain'])
        ? $entryBasename
        : $entryBasename . ' via ' . implode(' → ', $resolved['chain']);

    $result = itm_check_back_save($resolved['content'], $viaLabel);
    if ($result['status'] !== 'n/a' || stripos($result['details'], 'No form') === false) {
        return $result;
    }

    if (itm_ui_detect_entry_wrapper($entryContent, $modulePath)['is_wrapper']) {
        $result['details'] = 'Wrapper found but delegated file has no detectable form (' . $viaLabel . ')';
        $result['status'] = 'fail';

        return $result;
    }

    if (in_array($entryBasename, ['create.php', 'edit.php'], true)) {
        $result['details'] = itm_entry_screen_label($entryBasename) . ' entry has no detectable form in ' . $entryBasename;
        $result['status'] = 'fail';

        return $result;
    }

    return ['status' => 'n/a', 'details' => 'No form expected in ' . $entryBasename];
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
    $listScreen = itm_ui_resolve_list_table_screen($indexContent, $listAllContent);
    $listContent = $listScreen['content'];
    $listSource = $listScreen['source'];

    $checks = [
        'Table Actions' => itm_check_table_actions($indexContent),
        '+ New Button' => itm_check_new_button($indexContent, is_file($createPath)),
        'Export Buttons' => itm_check_export_toolbar_support($indexContent),
        'Search' => itm_check_search($listContent, $listSource),
        'Column sort (ASC/DESC)' => itm_check_sort($listContent, $listSource),
        'Pagination (records per page)' => itm_check_pagination($listContent, $listSource),
        'Bulk delete actions' => itm_check_bulk_delete_actions($listContent, $listSource, is_file($deletePath)),
        'Create entry (create.php)' => itm_check_module_entry_file($modulePath, $indexContent, $createPath, $createContent, 'create.php'),
        'Edit entry (edit.php)' => itm_check_module_entry_file($modulePath, $indexContent, $editPath, $editContent, 'edit.php'),
        'View entry (view.php)' => itm_check_module_entry_file($modulePath, $indexContent, $viewPath, $viewContent, 'view.php'),
        'List-all entry (list_all.php)' => itm_check_module_entry_file($modulePath, $indexContent, $listAllPath, $listAllContent, 'list_all.php'),
        'Delete entry (delete.php)' => itm_check_module_entry_file($modulePath, $indexContent, $deletePath, $deleteContent, 'delete.php'),
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
