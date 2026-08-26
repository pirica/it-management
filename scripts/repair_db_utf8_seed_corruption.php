<?php
/**
 * Repair UTF-8 seed corruption in live MySQL (question marks instead of emoji).
 *
 * Cause: importing db/*.sql via PowerShell Get-Content without -Encoding utf8
 * before piping to mysql.exe replaces multibyte emoji with ASCII '?'.
 *
 * CLI: php scripts/repair_db_utf8_seed_corruption.php [--apply]
 * Browser: scripts/repair_db_utf8_seed_corruption.php?run=1&apply=1 (Admin)
 *
 * Default is dry-run (reports rows that would be fixed).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/repair_db_utf8_seed_corruption.php</code> (dry-run) or <code>php scripts/repair_db_utf8_seed_corruption.php --apply</code>. Repairs <code>ui_configuration.app_name</code> and <code>configuration_item_types.icon</code> when a non-UTF-8 PowerShell SQL pipe stored <code>????</code> instead of emoji. Prefer <code>php scripts/import_database_split.php</code> on Windows instead of <code>Get-Content db/*.sql | mysql</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
} else {
    define('ITM_CLI_SCRIPT', true);
    require_once dirname(__DIR__) . '/config/config.php';
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Repair DB UTF-8 seed corruption');

$nl = itm_script_output_nl();
$apply = false;
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if ($arg === '--apply') {
            $apply = true;
        }
    }
} else {
    itm_script_require_admin_script_or_exit($conn);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Repair DB UTF-8</title></head><body><pre>';
    $apply = !empty($_REQUEST['apply']);
    if (!$apply) {
        echo colorText(
            '[INFO] DRY-RUN (detect only) — ?run=1 does not write MySQL. To repair: repair_db_utf8_seed_corruption.php?run=1&apply=1 (Admin).',
            'info'
        ) . $nl . $nl;
    }
}

if (!($conn instanceof mysqli)) {
    echo itm_script_format_status_line('[SKIP] Database connection unavailable — dry-run skipped (tier2 CI has no MySQL).') . $nl;
    itm_script_output_end();
    exit(0);
}

require_once ROOT_PATH . 'includes/itm_cmdb.php';
require_once ROOT_PATH . 'includes/ui_config.php';

/**
 * True when a seed string was reduced to ASCII question marks by a bad import pipe.
 */
function itm_repair_db_utf8_is_question_mark_corruption(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    return (bool)preg_match('/^\?+(\s|$)/', $value) || (bool)preg_match('/^\?+$/', $value);
}

$canonicalAppName = (string)(itm_ui_config_defaults()['app_name'] ?? '⚙️ IT Controls');
$uiRows = 0;
$uiStmt = mysqli_prepare(
    $conn,
    'SELECT id, company_id, employee_id, app_name FROM ui_configuration WHERE deleted_at IS NULL'
);
if ($uiStmt) {
    mysqli_stmt_execute($uiStmt);
    $res = mysqli_stmt_get_result($uiStmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $appName = (string)($row['app_name'] ?? '');
        if (!itm_repair_db_utf8_is_question_mark_corruption($appName)) {
            continue;
        }
        $uiRows++;
        $label = 'ui_configuration id=' . (int)($row['id'] ?? 0) . ' company=' . (int)($row['company_id'] ?? 0);
        if ($apply) {
            $upd = mysqli_prepare($conn, 'UPDATE ui_configuration SET app_name = ? WHERE id = ? AND company_id = ? LIMIT 1');
            if ($upd) {
                $id = (int)($row['id'] ?? 0);
                $companyId = (int)($row['company_id'] ?? 0);
                mysqli_stmt_bind_param($upd, 'sii', $canonicalAppName, $id, $companyId);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
                echo itm_script_format_status_line('[PASS] Fixed ' . $label) . $nl;
            }
        } else {
            echo itm_script_format_status_line('[WARN] Would fix ' . $label . ' — ' . $appName) . $nl;
        }
    }
    mysqli_stmt_close($uiStmt);
}

$ciTypeRows = 0;
$equipmentTypeRows = 0;
$seeds = itm_cmdb_builtin_type_seeds();
$companyRes = mysqli_query($conn, 'SELECT id FROM companies ORDER BY id');
$companyIds = [];
while ($companyRes && ($cRow = mysqli_fetch_assoc($companyRes))) {
    $companyIds[] = (int)($cRow['id'] ?? 0);
}

foreach ($companyIds as $companyId) {
    if ($companyId <= 0) {
        continue;
    }
    foreach ($seeds as $seed) {
        $sourceSlug = (string)($seed['source_slug'] ?? '');
        $icon = (string)($seed['icon'] ?? '');
        if ($sourceSlug === '' || $icon === '') {
            continue;
        }
        $sel = mysqli_prepare(
            $conn,
            'SELECT id, icon FROM configuration_item_types
             WHERE company_id = ? AND source_slug = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$sel) {
            continue;
        }
        mysqli_stmt_bind_param($sel, 'is', $companyId, $sourceSlug);
        mysqli_stmt_execute($sel);
        $typeRes = mysqli_stmt_get_result($sel);
        $typeRow = $typeRes ? mysqli_fetch_assoc($typeRes) : null;
        mysqli_stmt_close($sel);
        if (!$typeRow) {
            continue;
        }
        $currentIcon = (string)($typeRow['icon'] ?? '');
        if ($currentIcon === $icon) {
            continue;
        }
        if (!itm_repair_db_utf8_is_question_mark_corruption($currentIcon)) {
            continue;
        }
        $ciTypeRows++;
        $label = 'configuration_item_types id=' . (int)($typeRow['id'] ?? 0)
            . ' company=' . $companyId . ' slug=' . $sourceSlug;
        if ($apply) {
            $upd = mysqli_prepare(
                $conn,
                'UPDATE configuration_item_types SET icon = ? WHERE id = ? AND company_id = ? LIMIT 1'
            );
            if ($upd) {
                $typeId = (int)($typeRow['id'] ?? 0);
                mysqli_stmt_bind_param($upd, 'sii', $icon, $typeId, $companyId);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
                echo itm_script_format_status_line('[PASS] Fixed ' . $label . ' → ' . $icon) . $nl;
            }
        } else {
            echo itm_script_format_status_line('[WARN] Would fix ' . $label . ' — ' . $currentIcon . ' → ' . $icon) . $nl;
        }
    }
}

if (function_exists('itm_table_has_column') && itm_table_has_column($conn, 'equipment_types', 'field_edit_emoji')) {
    $eqRes = mysqli_query(
        $conn,
        'SELECT id, company_id, name, field_edit_emoji FROM equipment_types WHERE deleted_at IS NULL'
    );
    while ($eqRes && ($eqRow = mysqli_fetch_assoc($eqRes))) {
        $typeName = (string)($eqRow['name'] ?? '');
        $currentEmoji = (string)($eqRow['field_edit_emoji'] ?? '');
        if ($typeName === '') {
            continue;
        }
        $canonical = itm_equipment_type_resolve_field_edit_emoji($typeName, '');
        if ($canonical === '' || $currentEmoji === $canonical) {
            continue;
        }
        if (!itm_repair_db_utf8_is_question_mark_corruption($currentEmoji) && trim($currentEmoji) !== '') {
            continue;
        }
        $equipmentTypeRows++;
        $label = 'equipment_types id=' . (int)($eqRow['id'] ?? 0)
            . ' company=' . (int)($eqRow['company_id'] ?? 0) . ' name=' . $typeName;
        if ($apply) {
            $upd = mysqli_prepare(
                $conn,
                'UPDATE equipment_types SET field_edit_emoji = ? WHERE id = ? AND company_id = ? LIMIT 1'
            );
            if ($upd) {
                $typeId = (int)($eqRow['id'] ?? 0);
                $companyId = (int)($eqRow['company_id'] ?? 0);
                mysqli_stmt_bind_param($upd, 'sii', $canonical, $typeId, $companyId);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
                echo itm_script_format_status_line('[PASS] Fixed ' . $label . ' → ' . $canonical) . $nl;
            }
        } else {
            echo itm_script_format_status_line('[WARN] Would fix ' . $label . ' — ' . $currentEmoji . ' → ' . $canonical) . $nl;
        }
    }
}

if ($uiRows === 0 && $ciTypeRows === 0 && $equipmentTypeRows === 0) {
    echo itm_script_format_status_line('[PASS] No UTF-8 question-mark corruption detected.') . $nl;
    $exitCode = 0;
} elseif (!$apply) {
    echo itm_script_format_status_line('[FAIL] Corruption detected — dry-run only; use --apply or browser ?run=1&apply=1 to repair.') . $nl;
    $exitCode = 1;
} else {
    echo itm_script_format_status_line('[PASS] Repair complete — ui_configuration=' . $uiRows . ', configuration_item_types=' . $ciTypeRows . ', equipment_types=' . $equipmentTypeRows) . $nl;
    $exitCode = 0;
}

if (PHP_SAPI !== 'cli') {
    echo '</pre></body></html>';
}

itm_script_output_end();
exit($exitCode);
