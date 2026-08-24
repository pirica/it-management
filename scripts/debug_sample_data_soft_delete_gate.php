<?php
/**
 * Debug Add sample data vs soft-delete list mismatch across CRUD modules.
 *
 * Reproduces the idf_device_type symptom: list shows "No records found" (deleted_at IS NULL)
 * but Add sample data is blocked because the gate still counts soft-deleted rows.
 *
 * CLI:
 *   php scripts/debug_sample_data_soft_delete_gate.php
 *   php scripts/debug_sample_data_soft_delete_gate.php --company=4
 *   php scripts/debug_sample_data_soft_delete_gate.php --company=4 --module=idf_device_type
 *   php scripts/debug_sample_data_soft_delete_gate.php --only-drift --company=4
 *
 * Browser (Administrator): scripts/debug_sample_data_soft_delete_gate.php?run=1&company=4
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Static audit: finds module <code>index.php</code> files whose <strong>Add sample data</strong> gate still uses raw <code>COUNT(*)</code> instead of <code>itm_seed_tenant_row_count()</code> (live rows with <code>deleted_at IS NULL</code>).<br>
Live repro (optional): pass <code>company=4</code> (or another tenant id) to compare raw vs live row counts per table. Rows marked <code>REPRO</code> match the bug: list empty, gate blocked by soft-deleted rows.<br>
CLI examples:<br>
<code>php scripts/debug_sample_data_soft_delete_gate.php --only-repro --company=4</code><br>
<code>php scripts/debug_sample_data_soft_delete_gate.php --module=idf_device_type --company=4</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_sample_data_soft_delete_gate_audit.php';

itm_script_output_begin('Debug sample data soft-delete gate');

$isCli = itm_script_cli_is_cli();
$nl = itm_script_output_nl();
$root = rtrim(ROOT_PATH, '/\\');

$companyId = 0;
$moduleFilter = '';
$onlyDrift = false;
$onlyRepro = false;

if ($isCli) {
  foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--company=') === 0) {
      $companyId = (int) substr($arg, 10);
    } elseif (strpos($arg, '--module=') === 0) {
      $moduleFilter = trim((string) substr($arg, 9));
    } elseif ($arg === '--only-drift') {
      $onlyDrift = true;
    } elseif ($arg === '--only-repro') {
      $onlyRepro = true;
    }
  }
} else {
  $companyId = isset($_GET['company']) ? (int) $_GET['company'] : 0;
  $moduleFilter = isset($_GET['module']) ? trim((string) $_GET['module']) : '';
  $onlyDrift = isset($_GET['only_drift']) && (string) $_GET['only_drift'] === '1';
  $onlyRepro = isset($_GET['only_repro']) && (string) $_GET['only_repro'] === '1';
}

$rows = itm_sample_data_gate_audit_run([
  'root' => $root,
  'company_id' => $companyId,
  'module' => $moduleFilter,
  'only_drift' => $onlyDrift,
  'only_repro' => $onlyRepro,
  'conn' => $conn,
]);

$driftCount = 0;
$reproCount = 0;
$okCount = 0;

foreach ($rows as $row) {
  $status = (string) ($row['status'] ?? 'ok');
  if ($status === 'drift') {
    $driftCount++;
  } elseif ($status === 'repro') {
    $reproCount++;
    $driftCount++;
  } elseif ($status === 'ok') {
    $okCount++;
  }
}

$exitCode = ($driftCount > 0 || $reproCount > 0) ? 1 : 0;

if (!$isCli) {
  itm_script_output_close_pre();

  echo '<h1>Sample data soft-delete gate audit</h1>';
  echo '<p><strong>Root:</strong> <code>' . sanitize($root) . '</code></p>';
  if ($companyId > 0) {
    echo '<p><strong>Live company_id:</strong> ' . (int) $companyId . '</p>';
  }
  if ($moduleFilter !== '') {
    echo '<p><strong>Module filter:</strong> <code>' . sanitize($moduleFilter) . '</code></p>';
  }
  if ($onlyDrift) {
    echo '<p><strong>Filter:</strong> only drift modules</p>';
  }
  if ($onlyRepro) {
    echo '<p><strong>Filter:</strong> only live REPRO rows</p>';
  }

  if ($rows === []) {
    echo '<p>' . colorText('[INFO] No matching modules.', 'info') . '</p>';
    itm_script_output_end();
    exit(0);
  }

  echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:16px 0;width:100%;max-width:100%;font-size:13px;">';
  echo '<thead><tr>';
  echo '<th>Status</th><th>Module</th><th>Table</th><th>Gate</th><th>Raw</th><th>Live</th><th>SQL sample</th><th>Soft</th><th>Notes</th>';
  echo '</tr></thead><tbody>';

  foreach ($rows as $row) {
    $status = (string) ($row['status'] ?? 'ok');
    $statusLabel = strtoupper($status);
    if (!empty($row['repro'])) {
      $statusLabel = 'REPRO';
    }

    $statusColor = '#1a7f37';
    if ($statusLabel === 'REPRO' || $status === 'repro') {
      $statusColor = '#cf222e';
    } elseif ($status === 'drift') {
      $statusColor = '#bf8700';
    }

    $raw = $row['raw'];
    $live = $row['live'];
    $sqlSample = $row['sql_sample'] ?? null;
    $soft = $row['soft_deleted'];
    $slug = (string) ($row['slug'] ?? '');
    $tableName = (string) ($row['table'] ?? '');
    $gate = (string) ($row['gate'] ?? '');
    $notes = (string) ($row['notes'] ?? '');

    $moduleCell = sanitize($slug);
    if ($slug !== '' && function_exists('itm_script_format_modules_file_local_dev_link')) {
      $moduleCell = itm_script_format_modules_file_local_dev_link('modules/' . $slug . '/index.php', $slug);
    }

    echo '<tr>';
    echo '<td style="color:' . $statusColor . ';font-weight:600;">' . sanitize($statusLabel) . '</td>';
    echo '<td>' . $moduleCell . '</td>';
    echo '<td><code>' . sanitize($tableName) . '</code></td>';
    echo '<td><code>' . sanitize($gate) . '</code></td>';
    echo '<td>' . sanitize($raw === null ? '-' : (string) $raw) . '</td>';
    echo '<td>' . sanitize($live === null ? '-' : (string) $live) . '</td>';
    echo '<td>' . sanitize($sqlSample === null ? '-' : (string) $sqlSample) . '</td>';
    echo '<td>' . sanitize($soft === null ? '-' : (string) $soft) . '</td>';
    echo '<td>' . sanitize($notes) . '</td>';
    echo '</tr>';
  }

  echo '</tbody></table>';

  echo '<p><strong>Summary:</strong> ok=' . (int) $okCount . ' drift=' . (int) $driftCount . ' repro=' . (int) $reproCount . '</p>';

  echo '<div style="margin:16px 0;padding:12px;border:1px dashed #d0d7de;border-radius:6px;font-size:13px;">';
  echo '<p><strong>Gate legend:</strong> <code>live_rows</code> = <code>itm_seed_tenant_row_count()</code>; <code>raw_count</code> = legacy <code>COUNT(*)</code> includes soft-deleted rows.</p>';
  echo '<p><strong>SQL sample</strong> = row count for that table in <code>db/02_data_sample.sql</code> (templates inserted per Add sample data click).</p>';
  echo '<p><strong>REPRO</strong> = list would be empty (<code>live=0</code>) but legacy gate still sees rows (<code>raw&gt;0</code>).</p>';
  echo '<p><strong>Fix pattern:</strong> <code>modules/bank_accounts/index.php</code> — <code>itm_seed_tenant_row_count()</code> + <code>itm_seed_table_from_database_sql()</code>.</p>';
  echo '<p><strong>Bulk repair:</strong> <code>php scripts/apply_crud_sample_data_live_row_gate.php --apply</code></p>';
  echo '</div>';

  if ($exitCode === 1) {
    echo '<p>' . colorText('[FAIL] Drift or live REPRO rows found.', 'fail') . '</p>';
  } else {
    echo '<p>' . colorText('[PASS] No drift modules in scope.', 'pass') . '</p>';
  }

  itm_script_output_end();
  exit($exitCode);
}

echo colorText('Sample data gate audit', 'info') . $nl;
echo 'Root: ' . $root . $nl;
if ($companyId > 0) {
  echo 'Live company_id: ' . $companyId . $nl;
}
if ($moduleFilter !== '') {
  echo 'Module filter: ' . $moduleFilter . $nl;
}
echo $nl;

if ($rows === []) {
  echo colorText('[INFO] No matching modules.', 'info') . $nl;
  itm_script_output_end();
  exit(0);
}

echo str_pad('Status', 8) . ' '
  . str_pad('Module', 34) . ' '
  . str_pad('Table', 28) . ' '
  . str_pad('Gate', 12) . ' '
  . str_pad('Raw', 6) . ' '
  . str_pad('Live', 6) . ' '
  . str_pad('SQL', 6) . ' '
  . str_pad('Soft', 6) . ' '
  . 'Notes' . $nl;
echo str_repeat('-', 120) . $nl;

foreach ($rows as $row) {
  $status = (string) ($row['status'] ?? 'ok');
  $statusLabel = strtoupper($status);
  if (!empty($row['repro'])) {
    $statusLabel = 'REPRO';
  }

  $raw = $row['raw'];
  $live = $row['live'];
  $sqlSample = $row['sql_sample'] ?? null;
  $soft = $row['soft_deleted'];

  echo str_pad($statusLabel, 8) . ' '
    . str_pad((string) ($row['slug'] ?? ''), 34) . ' '
    . str_pad((string) ($row['table'] ?? ''), 28) . ' '
    . str_pad((string) ($row['gate'] ?? ''), 12) . ' '
    . str_pad($raw === null ? '-' : (string) $raw, 6) . ' '
    . str_pad($live === null ? '-' : (string) $live, 6) . ' '
    . str_pad($sqlSample === null ? '-' : (string) $sqlSample, 6) . ' '
    . str_pad($soft === null ? '-' : (string) $soft, 6) . ' '
    . (string) ($row['notes'] ?? '') . $nl;
}

echo $nl;
echo 'Summary: ok=' . $okCount . ' drift=' . $driftCount . ' repro=' . $reproCount . $nl;
echo $nl;
echo 'Gate legend: live_rows = itm_seed_tenant_row_count(); raw_count = legacy COUNT(*) includes soft-deleted rows.' . $nl;
echo 'SQL sample = row count for that table in db/02_data_sample.sql (templates per Add sample data click).' . $nl;
echo 'REPRO = list would be empty (live=0) but legacy gate still sees rows (raw>0).' . $nl;
echo 'Fix pattern: modules/bank_accounts/index.php — itm_seed_tenant_row_count() + itm_seed_table_from_database_sql().' . $nl;
echo 'Bulk repair: php scripts/apply_crud_sample_data_live_row_gate.php --apply' . $nl;

if ($exitCode === 1) {
  echo colorText('[FAIL] Drift or live REPRO rows found.', 'fail') . $nl;
} else {
  echo colorText('[PASS] No drift modules in scope.', 'pass') . $nl;
}

itm_script_output_end();
exit($exitCode);
