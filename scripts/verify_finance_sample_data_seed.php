<?php
/**
 * Regression: Add sample data for finance modules on a disposable tenant.
 *
 * CLI: php scripts/verify_finance_sample_data_seed.php
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_sample_data_seed.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Finance sample data seed verification');

$nl = itm_script_output_nl();
$failures = 0;

function vfs_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function vfs_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    vfs_fail('No database connection.');
    itm_script_output_end();
    exit(1);
}

$companyName = 'FinanceSampleVerify-' . bin2hex(random_bytes(4));
$incode = strtoupper(substr(md5($companyName), 0, 6));
$stmt = mysqli_prepare($conn, 'INSERT INTO companies (company, incode, active) VALUES (?, ?, 1)');
mysqli_stmt_bind_param($stmt, 'ss', $companyName, $incode);
mysqli_stmt_execute($stmt);
$companyId = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if ($companyId <= 0) {
    vfs_fail('Could not create disposable company.');
    itm_script_output_end();
    exit(1);
}

mysqli_query($conn, 'SET @app_company_id = ' . $companyId);

// Soft-deleted rows must not block Add sample data when the list is empty.
$softCompanyName = 'FinanceSampleSoftDel-' . bin2hex(random_bytes(3));
$softIncode = strtoupper(substr(md5($softCompanyName), 0, 6));
$stmtSoftCo = mysqli_prepare($conn, 'INSERT INTO companies (company, incode, active) VALUES (?, ?, 1)');
mysqli_stmt_bind_param($stmtSoftCo, 'ss', $softCompanyName, $softIncode);
mysqli_stmt_execute($stmtSoftCo);
$softCompanyId = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmtSoftCo);

if ($softCompanyId > 0) {
  $taxName = 'SoftDeleted VAT Gate';
  $rate = '23.00';
  $delBy = 1;
  $insSoft = mysqli_prepare(
      $conn,
      'INSERT INTO tax_rates (company_id, name, rate_percent, active, deleted_by, deleted_at) VALUES (?, ?, ?, 0, ?, NOW())'
  );
  mysqli_stmt_bind_param($insSoft, 'isdi', $softCompanyId, $taxName, $rate, $delBy);
  mysqli_stmt_execute($insSoft);
  mysqli_stmt_close($insSoft);

  $rawCountRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM tax_rates WHERE company_id = ' . $softCompanyId);
  $rawCount = $rawCountRes ? (int) (mysqli_fetch_assoc($rawCountRes)['c'] ?? 0) : 0;
  $liveCount = itm_seed_tenant_row_count($conn, 'tax_rates', $softCompanyId);

  if ($rawCount < 1 || $liveCount !== 0) {
      vfs_fail('tax_rates soft-delete fixture: expected raw>0 and live=0 (raw=' . $rawCount . ', live=' . $liveCount . ').');
  } else {
      $softErr = '';
      $softInserted = itm_seed_table_from_database_sql($conn, 'tax_rates', $softCompanyId, $softErr);
      $afterLive = itm_seed_tenant_row_count($conn, 'tax_rates', $softCompanyId);
      if ($softInserted < 1 || $afterLive < 1) {
          vfs_fail('tax_rates sample seed with only soft-deleted rows: ' . ($softErr !== '' ? $softErr : 'no live row after seed'));
      } else {
          vfs_pass('tax_rates Add sample data allowed when only soft-deleted rows exist.');
      }
  }
  mysqli_query($conn, 'DELETE FROM companies WHERE id = ' . $softCompanyId);
}

$financeTables = [
    'tax_rates',
    'paid_statuses',
    'payment_modes',
    'expense_recurrence',
    'customer_statuses',
    'customers',
    'budget_categories',
    'cost_centers',
    'gl_accounts',
    'integration_accounts',
    'bank_accounts',
    'suppliers',
    'bills',
    'bill_line_items',
    'invoices',
    'invoice_line_items',
    'annual_budgets',
    'monthly_budgets',
    'expenses',
];

foreach ($financeTables as $table) {
    $err = '';
    $inserted = itm_seed_table_from_database_sql($conn, $table, $companyId, $err);
    if ($inserted < 1) {
        vfs_fail($table . ' sample seed: ' . ($err !== '' ? $err : 'no rows inserted'));
        continue;
    }
    vfs_pass($table . ' seeded (' . $inserted . ' row(s)).');
}

$currencyCheck = mysqli_query(
    $conn,
    "SELECT currency_code, CHAR_LENGTH(currency_code) AS len FROM expenses WHERE company_id = {$companyId} LIMIT 1"
);
$currencyRow = $currencyCheck ? mysqli_fetch_assoc($currencyCheck) : null;
if (!is_array($currencyRow) || (int) ($currencyRow['len'] ?? 0) > 3) {
    vfs_fail('expenses.currency_code must be at most 3 characters after sample seed.');
} else {
    vfs_pass('expenses.currency_code length OK (' . ($currencyRow['currency_code'] ?? '') . ').');
}

foreach (['bills', 'invoices', 'bank_accounts'] as $currencyTable) {
    $res = mysqli_query(
        $conn,
        "SELECT currency_code, CHAR_LENGTH(currency_code) AS len FROM `{$currencyTable}` WHERE company_id = {$companyId} LIMIT 1"
    );
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!is_array($row) || (int) ($row['len'] ?? 0) > 3) {
        vfs_fail($currencyTable . '.currency_code invalid after sample seed.');
    } else {
        vfs_pass($currencyTable . '.currency_code OK.');
    }
}

require_once ROOT_PATH . 'includes/ui_config.php';
$financeSidebarSlugs = [
    'expenses',
    'bills',
    'invoices',
    'customers',
    'bank_accounts',
    'suppliers',
    'supplier_statuses',
    'tax_rates',
    'paid_statuses',
    'payment_modes',
    'expense_recurrence',
    'customer_statuses',
    'integration_accounts',
];
$parentMap = itm_sidebar_default_item_parent_map();
foreach ($financeSidebarSlugs as $slug) {
    $section = (string) ($parentMap[$slug] ?? '');
    if ($section !== 'finance') {
        vfs_fail('Sidebar: ' . $slug . ' must be under finance section (got ' . ($section !== '' ? $section : 'missing') . ').');
    } else {
        vfs_pass('Sidebar finance section includes ' . $slug . '.');
    }
}

mysqli_query($conn, 'DELETE FROM companies WHERE id = ' . $companyId);

if ($failures > 0) {
    echo $nl . colorText('Failures: ' . $failures, 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('All finance sample data seed checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
