<?php
/**
 * Repair catalogs rows that store FK ids from another tenant (seed copy issue).
 *
 * Why: database.sql seeds company 2–5 catalogs with company-1 equipment_types/manufacturers ids.
 *
 * Usage (PHP 7.4+, repository root):
 *   php scripts/repair_catalogs_cross_tenant_fks.php           # dry-run
 *   php scripts/repair_catalogs_cross_tenant_fks.php --apply    # execute UPDATEs
 *   php scripts/repair_catalogs_cross_tenant_fks.php --company=4
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

require_once $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'detect_fk_dropdown_ui_risk_lib.php';

$apply = false;
$companyFilter = 0;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
        continue;
    }
    if (preg_match('/^--company=(\d+)$/', $arg, $m)) {
        $companyFilter = (int)$m[1];
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php scripts/repair_catalogs_cross_tenant_fks.php [--apply] [--company=N]\n");
        exit(0);
    }
    fwrite(STDERR, "Unknown argument: {$arg}\n");
    exit(2);
}

$conn = itm_detect_fk_connect();
if (!$conn) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(2);
}

$schema = getenv('DB_NAME') !== false ? (string)getenv('DB_NAME') : 'itmanagement';
$allIssues = itm_detect_fk_data_issues($conn, $schema, $companyFilter);
$issues = array_values(array_filter($allIssues, static function ($row) {
    return ($row['child_table'] ?? '') === 'catalogs'
        && ($row['risk'] ?? '') === 'duplicate_dropdown_risk'
        && (int)($row['tenant_equivalent_id'] ?? 0) > 0
        && (int)($row['tenant_equivalent_id'] ?? 0) !== (int)($row['stored_fk_id'] ?? 0);
}));

if ($issues === []) {
    fwrite(STDOUT, "[OK] No catalogs cross-tenant FK rows need repair.\n");
    mysqli_close($conn);
    exit(0);
}

fwrite(STDOUT, ($apply ? 'Applying' : 'Dry-run') . ' ' . count($issues) . " catalogs FK correction(s).\n\n");

$updated = 0;
$errors = 0;

foreach ($issues as $issue) {
    $catalogId = (int)$issue['child_id'];
    $companyId = (int)$issue['child_company_id'];
    $column = (string)$issue['fk_column'];
    $newId = (int)$issue['tenant_equivalent_id'];
    $oldId = (int)$issue['stored_fk_id'];

    if (!itm_detect_fk_safe_identifier($column) || $catalogId <= 0 || $companyId <= 0 || $newId <= 0) {
        continue;
    }

    $sql = 'UPDATE `catalogs` SET `' . $column . '`=' . $newId
        . ' WHERE `id`=' . $catalogId . ' AND `company_id`=' . $companyId
        . ' AND `' . $column . '`=' . $oldId;

    fwrite(STDOUT, $sql . "  -- {$issue['business_key']}\n");

    if ($apply) {
        if (mysqli_query($conn, $sql)) {
            $updated += (int)mysqli_affected_rows($conn);
        } else {
            fwrite(STDERR, '  ERROR: ' . mysqli_error($conn) . "\n");
            $errors++;
        }
    }
}

mysqli_close($conn);

if (!$apply) {
    fwrite(STDOUT, "\nRe-run with --apply to persist. No rows were changed.\n");
    exit(0);
}

fwrite(STDOUT, "\nUpdated rows (affected_rows): {$updated}\n");
exit($errors > 0 ? 2 : 0);
