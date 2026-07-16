<?php
/**
 * Index table compliance audit for standard module list screens.
 *
 * Why: AGENTS.md requires index list tables to expose hooks for table-tools.js import,
 * layout engine action-column mapping, and POST CSRF on state-changing handlers. This
 * script provides a single CI-friendly gate similar to check_csrf_coverage.php.
 *
 * Intentional non-failures:
 *   - data-itm-no-import-excel="1" → Import Excel / data-itm-db-import-endpoint not required
 *     (e.g. backup_tape_log, birthdays, contacts, resignations, ops_report).
 *   - No Actions column → data-itm-actions-origin / itm-actions-cell not required
 *     (e.g. birthdays, contacts read-only directories).
 *
 * Browser output is HTML-escaped inside <pre> so violation text cannot inject tags.
 *
 * Usage (repository root):
 *   php scripts/check_index_table_compliance.php
 *   php scripts/check_index_table_compliance.php --write-baseline
 *   php scripts/check_index_table_compliance.php --strict
 *
 * Baseline file (one module slug per line) grandfathers known legacy gaps until fixed.
 * Remove a module from scripts/data/index_table_compliance_baseline.txt when it passes.
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Index table compliance check');
$nl = itm_script_output_nl();

$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';
$baselinePath = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'index_table_compliance_baseline.txt';

$argvCopy = $argv ?? [];
$writeBaseline = in_array('--write-baseline', $argvCopy, true);
$strictMode = in_array('--strict', $argvCopy, true);

// Bespoke screens that do not follow the standard index table contract.
$excludeModules = [
    'equipment',
    'idfs',
    'idf_links',
    'idf_positions',
    'idf_ports',
    'audit_logs',
    'employees',
    'settings',
    'employee_companies',
    'employee_system_access',
    'cable_colors',
    'ui_configuration',
    'rack_planner',
];

$csrfGuardPatterns = [
    'itm_require_post_csrf',
    'itm_validate_csrf_token',
    'cr_require_valid_csrf_token',
    'so_require_valid_csrf_token',
    'idf_require_csrf',
];

/**
 * @return array<int, string>
 */
function itc_list_modules(string $modulesDir, array $excludeModules): array
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
        $path = $modulesDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $modules[] = $item;
        }
    }

    sort($modules);
    return $modules;
}

/**
 * @return array<int, string>
 */
function itc_load_baseline(string $baselinePath): array
{
    if (!is_file($baselinePath)) {
        return [];
    }

    $lines = file($baselinePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $modules = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $modules[] = $line;
    }

    return array_values(array_unique($modules));
}

/**
 * @param array<int, string> $modules
 */
function itc_write_baseline(string $baselinePath, array $modules)
{
    $dir = dirname($baselinePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!is_dir($dir)) {
        throw new RuntimeException("Unable to create baseline directory: {$dir}");
    }

    sort($modules);
    $header = "# Modules grandfathered until index table compliance is implemented.\n"
        . "# Regenerate with: php scripts/check_index_table_compliance.php --write-baseline\n";
    $body = $modules === [] ? '' : implode("\n", $modules) . "\n";
    if (file_put_contents($baselinePath, $header . $body) === false) {
        throw new RuntimeException("Unable to write baseline file: {$baselinePath}");
    }

    return;
}

/**
 * Collect index.php plus module-local PHP includes for static analysis.
 *
 * @return array{content:string,files:array<int,string>}
 */
function itc_collect_module_sources(string $moduleDir, string $indexPath): array
{
    $seen = [];
    $queue = [realpath($indexPath) ?: $indexPath];
    $chunks = [];
    $files = [];

    while ($queue !== []) {
        $file = array_shift($queue);
        if (!is_string($file) || isset($seen[$file]) || !is_file($file)) {
            continue;
        }

        $resolved = realpath($file);
        if ($resolved === false) {
            continue;
        }

        if (strpos($resolved, $moduleDir) !== 0) {
            continue;
        }

        $seen[$resolved] = true;
        $files[] = str_replace('\\', '/', substr($resolved, strlen(dirname($moduleDir)) + 1));

        $source = file_get_contents($resolved);
        if ($source === false) {
            continue;
        }

        $chunks[] = $source;

        $patterns = [
            '/(?:require|include)(?:_once)?\s*\(?\s*[\'"]([^\'"]+\.php)[\'"]/',
            '/(?:require|include)(?:_once)?\s*\(?\s*__DIR__\s*\.\s*[\'"]([^\'"]+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            $matchCount = preg_match_all($pattern, $source, $matches);
            if ($matchCount === false || $matchCount === 0) {
                continue;
            }
            foreach ($matches[1] as $relative) {
                $candidate = $moduleDir . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
                if (is_file($candidate)) {
                    $queue[] = $candidate;
                    continue;
                }
                $candidate = dirname($resolved) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
                if (is_file($candidate)) {
                    $queue[] = $candidate;
                }
            }
        }
    }

    return [
        'content' => implode("\n\n", $chunks),
        'files' => $files,
    ];
}

function itc_has_csrf_guard(string $content): bool
{
    global $csrfGuardPatterns;
    foreach ($csrfGuardPatterns as $pattern) {
        if (strpos($content, $pattern . '(') !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Whether index sources intentionally opt out of Excel import (table-tools hide Import).
 */
function itc_has_import_opt_out(string $content): bool
{
    return preg_match('/data-itm-no-import-excel\s*=\s*(["\'])1\1/i', $content) === 1;
}

/**
 * Whether the index markup includes an Actions column that layout mapping must mark.
 */
function itc_has_actions_column(string $content): bool
{
    if (preg_match('/<th\b[^>]*>[^<]*\bActions\b[^<]*<\/th>/i', $content) === 1) {
        return true;
    }
    if (preg_match('/\bitm-actions-cell\b/i', $content) === 1) {
        return true;
    }

    return preg_match('/data-itm-actions-origin\s*=/i', $content) === 1;
}

/**
 * Echo one report line. Browser output stays inside <pre>: use real newlines and escape
 * HTML so messages mentioning th/td attributes cannot break the page layout.
 */
function itc_echo_line(string $text): void
{
    global $nl;
    if (itm_script_cli_is_cli()) {
        echo $text . $nl;
        return;
    }

    echo htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
}

/**
 * @return array<int, string>
 */
function itc_check_module(string $module, string $content): array
{
    $violations = [];

    if (strpos($content, 'index-table-compliance: skip') !== false) {
        return [];
    }

    if (stripos($content, '<table') === false) {
        return [];
    }

    $hasImportEndpoint = preg_match(
        '/data-itm-db-import-endpoint\s*=\s*(["\'])([^"\']+)\1/i',
        $content
    ) === 1;

    // Why: bespoke grids (backup_tape_log, birthdays, resignations, …) opt out of Import Excel.
    if (!$hasImportEndpoint && !itc_has_import_opt_out($content)) {
        $violations[] = 'missing data-itm-db-import-endpoint on index list table';
    }

    if (itc_has_actions_column($content)) {
        $hasActionsHeader = preg_match(
            '/<th[^>]*data-itm-actions-origin\s*=\s*(["\'])1\1[^>]*>/i',
            $content
        ) === 1;

        if (!$hasActionsHeader) {
            $violations[] = 'missing data-itm-actions-origin="1" on Actions header (th)';
        }

        $hasActionsCell = preg_match(
            '/<td[^>]*data-itm-actions-origin\s*=\s*(["\'])1\1[^>]*>/i',
            $content
        ) === 1;

        if (!$hasActionsCell) {
            $violations[] = 'missing data-itm-actions-origin="1" on Actions body cell (td)';
        }

        if (stripos($content, 'itm-actions-cell') === false) {
            $violations[] = 'Actions column present but missing itm-actions-cell class';
        }
    }

    $postSurface = preg_match(
        '/REQUEST_METHOD\s*[\"\']?\]\s*={2,3}\s*[\"\']POST[\"\']|\$_POST\s*\[/i',
        $content
    ) === 1;

    $stateMutation = preg_match(
        '/\b(INSERT|UPDATE|DELETE)\b|\b(mysqli_query|mysqli_prepare|mysqli_stmt_execute)\s*\(/i',
        $content
    ) === 1;

    if ($postSurface && $stateMutation && !itc_has_csrf_guard($content)) {
        $violations[] = 'POST mutation surface without known CSRF guard';
    }

    if ($postSurface && preg_match_all('/<form\b[^>]*method\s*=\s*(["\'])post\1/i', $content, $formMatches) > 0) {
        $formCount = count($formMatches[0]);
        $tokenCount = preg_match_all('/name\s*=\s*(["\'])csrf_token\1/i', $content);
        if ($tokenCount < $formCount) {
            $violations[] = 'POST form(s) detected without enough csrf_token hidden fields';
        }
    }

    if (stripos($content, 'add_sample_data') !== false && $postSurface && !itc_has_csrf_guard($content)) {
        $violations[] = 'add_sample_data handler without CSRF guard';
    }

    return $violations;
}

if (!is_dir($modulesDir)) {
    fwrite(STDERR, "Modules directory not found: {$modulesDir}\n");
    exit(2);
}

$modules = itc_list_modules($modulesDir, $excludeModules);
$baseline = itc_load_baseline($baselinePath);

$violationsByModule = [];
$skippedNoIndex = 0;
$skippedNoTable = 0;
$scanned = 0;

foreach ($modules as $module) {
    $indexPath = $modulesDir . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'index.php';
    if (!is_file($indexPath)) {
        $skippedNoIndex++;
        continue;
    }

    $sources = itc_collect_module_sources($modulesDir . DIRECTORY_SEPARATOR . $module, $indexPath);
    $content = $sources['content'];
    if (stripos($content, '<table') === false) {
        $skippedNoTable++;
        continue;
    }

    $scanned++;
    $violations = itc_check_module($module, $content);
    if ($violations !== []) {
        $violationsByModule[$module] = $violations;
    }
}

if ($writeBaseline) {
    try {
        itc_write_baseline($baselinePath, array_keys($violationsByModule));
    } catch (RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(2);
    }
    echo "Wrote baseline with " . count($violationsByModule) . " module(s) to {$baselinePath}\n";
    exit(0);
}

$newViolations = [];
$staleBaseline = [];

foreach ($violationsByModule as $module => $violations) {
    if ($strictMode || !in_array($module, $baseline, true)) {
        $newViolations[$module] = $violations;
    }
}

foreach ($baseline as $module) {
    if (!isset($violationsByModule[$module])) {
        $staleBaseline[] = $module;
    }
}

itc_echo_line('Index table compliance audit');
itc_echo_line('Modules scanned (with list table): ' . $scanned);
itc_echo_line('Excluded modules: ' . implode(', ', $excludeModules));
itc_echo_line('Baseline entries: ' . count($baseline) . ($strictMode ? ' (ignored — --strict)' : ''));
itc_echo_line('Skipped (no index.php): ' . $skippedNoIndex);
itc_echo_line('Skipped (no table markup): ' . $skippedNoTable);
itc_echo_line('Import endpoint not required when data-itm-no-import-excel="1" is present.');
itc_echo_line('Actions markers not required when the index has no Actions column.');
itc_echo_line('');

if ($newViolations === [] && $staleBaseline === []) {
    itc_echo_line('Index table compliance check passed.');
    exit(0);
}

$exitCode = 0;

if ($newViolations !== []) {
    $exitCode = 1;
    itc_echo_line('Compliance violations' . ($strictMode ? ' (--strict, all modules):' : ' (not in baseline):'));
    foreach ($newViolations as $module => $violations) {
        itc_echo_line(' - ' . $module);
        foreach ($violations as $violation) {
            itc_echo_line('     * ' . $violation);
        }
    }
    itc_echo_line('');
}

if ($staleBaseline !== [] && !$strictMode) {
    itc_echo_line('Baseline entries with no remaining violations (safe to remove):');
    foreach ($staleBaseline as $module) {
        itc_echo_line(' - ' . $module);
    }
    itc_echo_line('');
}

if ($exitCode !== 0) {
    itc_echo_line('Fix violations or run --write-baseline only when intentionally grandfathering legacy modules.');
    itc_echo_line('Remove fixed modules from scripts/data/index_table_compliance_baseline.txt.');
}

exit($exitCode);
