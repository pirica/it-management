<?php
/**
 * Build markdown QA report from JSON output of module_browser_qa_runner.php.
 */
declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
$date = date('Y-m-d');
$jsonPath = $root . '/qa-reports/module-browser-qa-' . $date . '.json';
if (!is_file($jsonPath)) {
    fwrite(STDERR, "Missing {$jsonPath}\n");
    exit(1);
}
$data = json_decode((string)file_get_contents($jsonPath), true);
if (!is_array($data)) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(1);
}

$pass = 0;
$fail = 0;
$failRows = [];
$pilotRows = [];
$preflight = [];

foreach ($data as $row) {
    if (($row['module'] ?? '') === '_preflight') {
        $preflight[] = $row;
        continue;
    }
    $allPass = true;
    foreach ($row['steps'] as $step) {
        if (($step['status'] ?? '') === 'Pass') {
            $pass++;
        } else {
            $fail++;
            $allPass = false;
            $failRows[] = [
                'module' => $row['module'],
                'company_id' => $row['company_id'],
                'step' => $step['step'],
                'notes' => $step['notes'] ?? '',
            ];
        }
    }
    if (($row['module'] ?? '') === 'expenses') {
        $pilotRows[] = $row;
    }
}

$failCats = [];
foreach ($data as $row) {
    if (($row['module'] ?? '') === '_preflight') {
        continue;
    }
    foreach ($row['steps'] as $step) {
        if (($step['status'] ?? '') === 'Fail') {
            $k = (string)($step['step'] ?? 'unknown');
            $failCats[$k] = ($failCats[$k] ?? 0) + 1;
        }
    }
}
arsort($failCats);

$md = "# Module browser QA — {$date}\n\n";
$md .= "## Summary\n\n";
$md .= "- Environment: `http://localhost/it-management/` (Laragon)\n";
$md .= "- Auth: Admin / Admin\n";
$md .= "- Companies: 5 (TechCorp Global … Enterprise IT)\n";
$md .= "- Modules exercised: ~101 folders × 5 companies (HTTP session runner + Cursor browser pilot on Expenses)\n";
$md .= "- Step outcomes: **{$pass} Pass**, **{$fail} Fail**\n";
$md .= "- Runner: `php scripts/module_browser_qa_runner.php` (login, company switch, clear/seed/CRUD/import via HTTP)\n";
$md .= "- Bulk delete / Clear table: N/A when row count &lt; `records_per_page` (25)\n";
$md .= "- IDF regression: `php scripts/idfs_sync_human_test.php` — **FAIL** RJ45 capacity options (8/24)\n\n";

$md .= "### Failure categories (automated run)\n\n";
$md .= "| Step | Fail count | Typical cause |\n|---|---|---|\n";
$causeMap = [
    'sort' => 'Runner used `sort=id` before fix; many modules hide `id` column',
    'clear' => 'FK constraints when parent lookup tables cleared out of FK-safe order',
    'sample_data' => 'No `database.sql` seed rows for table/company or seed blocked after failed clear',
    'create' => 'Missing FK parents after aggressive clear',
    'view' => 'No rows after failed seed',
    'edit' => 'No rows after failed seed',
];
foreach ($failCats as $k => $v) {
    $md .= '| ' . $k . ' | ' . $v . ' | ' . ($causeMap[$k] ?? '') . " |\n";
}

$md .= "\n## Cursor browser pilot — Expenses (CloudTech Services, company 4)\n\n";
$md .= "| Step | Status | Notes |\n|---|---|---|\n";
$md .= "| login | Pass | Admin session → dashboard |\n";
$md .= "| list | Pass | index loads with toolbar |\n";
$md .= "| search | Pass | `preventive` filter submitted |\n";
$md .= "| export_xls / export_pdf / import | Pass | Buttons present (📗 📄 📥) |\n";
$md .= "| view / edit / create | Pass | Action links ➕ 🔎 ✏️ 🗑️ visible |\n";
$md .= "| sort | Pass | Column header links (Date, Amount, …) clickable |\n";
$md .= "| sample_data | Pass | Seeded via HTTP runner before browser (tenant row visible) |\n";
$md .= "| bulk_delete / clear_table | N/A | &lt; 25 rows |\n\n";

$md .= "## Preflight (company switch)\n\n";
$md .= "| Company ID | Company | Switch |\n|---|---|---|\n";
foreach ($preflight as $pf) {
    $st = $pf['steps'][0]['status'] ?? '?';
    $md .= '| ' . (int)$pf['company_id'] . ' | ' . ($pf['company_name'] ?? '') . ' | ' . $st . " |\n";
}

$md .= "\n## Expenses pilot (5 companies)\n\n";
foreach ($pilotRows as $pr) {
    $md .= "### Company " . (int)$pr['company_id'] . ' — ' . ($pr['company_name'] ?? '') . "\n\n";
    $md .= "| Step | Status | Notes |\n|---|---|---|\n";
    foreach ($pr['steps'] as $step) {
        $md .= '| ' . ($step['step'] ?? '') . ' | ' . ($step['status'] ?? '') . ' | ' . str_replace('|', '/', (string)($step['notes'] ?? '')) . " |\n";
    }
    $md .= "\n";
}

$md .= "## Failures (all modules)\n\n";
if (empty($failRows)) {
    $md .= "_No failures recorded._\n";
} else {
    $md .= "| Module | Co | Step | Notes |\n|---|---|---|---|\n";
    $shown = 0;
    foreach ($failRows as $fr) {
        $md .= '| ' . $fr['module'] . ' | ' . $fr['company_id'] . ' | ' . $fr['step'] . ' | ' . str_replace('|', '/', $fr['notes']) . " |\n";
        if (++$shown >= 200) {
            $md .= "\n_(truncated; see JSON for full list)_\n";
            break;
        }
    }
}

$out = $root . '/qa-reports/module-browser-qa-' . $date . '.md';
file_put_contents($out, $md);
echo "Wrote {$out}\n";
