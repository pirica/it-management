<?php
/**
 * Why: Shared bill/invoice line-item load, save, rollup, and simple edit/view grids.
 */

function itm_finance_line_meta_for_parent(string $parentTable): ?array
{
    if ($parentTable === 'bills') {
        return [
            'line_table' => 'bill_line_items',
            'parent_fk' => 'bill_id',
        ];
    }
    if ($parentTable === 'invoices') {
        return [
            'line_table' => 'invoice_line_items',
            'parent_fk' => 'invoice_id',
        ];
    }
    return null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function itm_finance_load_document_lines(mysqli $conn, int $companyId, string $parentTable, int $parentId): array
{
    $meta = itm_finance_line_meta_for_parent($parentTable);
    if ($meta === null || $parentId <= 0 || $companyId <= 0) {
        return [];
    }
    $lineTable = $meta['line_table'];
    $parentFk = $meta['parent_fk'];
    if (!itm_is_safe_identifier($lineTable) || !itm_is_safe_identifier($parentFk)) {
        return [];
    }
    $sql = 'SELECT * FROM `' . $lineTable . '` WHERE company_id = ? AND `' . $parentFk . '` = ? AND deleted_at IS NULL ORDER BY line_number ASC, id ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $parentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * @param array<string, mixed> $totals
 */
function itm_finance_rollup_line_totals(array $lines): array
{
    $sub = 0.0;
    $tax = 0.0;
    $discount = 0.0;
    $total = 0.0;
    foreach ($lines as $line) {
        $sub += (float) ($line['sub_total'] ?? 0);
        $tax += (float) ($line['tax_amount'] ?? 0);
        $discount += (float) ($line['total_discount'] ?? 0);
        $total += (float) ($line['total_amount'] ?? 0);
    }
    return [
        'sub_total' => number_format($sub, 2, '.', ''),
        'tax_amount' => number_format($tax, 2, '.', ''),
        'total_discount' => number_format($discount, 2, '.', ''),
        'total_amount' => number_format($total, 2, '.', ''),
    ];
}

function itm_finance_normalize_line_decimal($value, int $scale = 2): string
{
    $raw = str_replace(',', '.', trim((string) $value));
    if ($raw === '' || !is_numeric($raw)) {
        return number_format(0, $scale, '.', '');
    }
    return number_format((float) $raw, $scale, '.', '');
}

/**
 * @return array{ok: bool, error: string}
 */
function itm_finance_save_document_lines_from_post(
    mysqli $conn,
    int $companyId,
    string $parentTable,
    int $parentId,
    int $employeeId
): array {
    $meta = itm_finance_line_meta_for_parent($parentTable);
    if ($meta === null || $parentId <= 0 || $companyId <= 0) {
        return ['ok' => false, 'error' => 'Invalid document line context.'];
    }
    $lineTable = $meta['line_table'];
    $parentFk = $meta['parent_fk'];
    if (!itm_is_safe_identifier($lineTable) || !itm_is_safe_identifier($parentFk)) {
        return ['ok' => false, 'error' => 'Invalid line table.'];
    }

    $descriptions = $_POST['line_description'] ?? [];
    if (!is_array($descriptions)) {
        $descriptions = [];
    }

    mysqli_begin_transaction($conn);
    $softDeleteSql = 'UPDATE `' . $lineTable . '` SET active = 0, deleted_by = ?, deleted_at = NOW() WHERE company_id = ? AND `' . $parentFk . '` = ? AND deleted_at IS NULL';
    $delStmt = mysqli_prepare($conn, $softDeleteSql);
    if (!$delStmt) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => mysqli_error($conn)];
    }
    mysqli_stmt_bind_param($delStmt, 'iii', $employeeId, $companyId, $parentId);
    mysqli_stmt_execute($delStmt);
    mysqli_stmt_close($delStmt);

    $savedLines = [];
    $lineNum = 0;
    foreach ($descriptions as $idx => $desc) {
        $desc = trim((string) $desc);
        $qty = itm_finance_normalize_line_decimal($_POST['line_quantity'][$idx] ?? '1', 4);
        $unit = itm_finance_normalize_line_decimal($_POST['line_unit_amount'][$idx] ?? '0', 2);
        $sub = itm_finance_normalize_line_decimal($_POST['line_sub_total'][$idx] ?? '0', 2);
        $taxAmt = itm_finance_normalize_line_decimal($_POST['line_tax_amount'][$idx] ?? '0', 2);
        $lineTotal = itm_finance_normalize_line_decimal($_POST['line_total_amount'][$idx] ?? '0', 2);
        $lineDiscount = itm_finance_normalize_line_decimal($_POST['line_total_discount'][$idx] ?? '0', 2);
        if ($desc === '' && (float) $lineTotal <= 0 && (float) $sub <= 0) {
            continue;
        }
        $lineNum++;
        $taxRateId = (int) ($_POST['line_tax_rate_id'][$idx] ?? 0);
        $taxRateId = $taxRateId > 0 ? $taxRateId : null;
        $taxSnapshot = null;
        if ($taxRateId !== null && function_exists('itm_expenses_stamp_tax_rate_snapshot')) {
            $taxSnapshot = itm_expenses_stamp_tax_rate_snapshot($conn, $companyId, $taxRateId);
        }
        $integrationAccountId = (int) ($_POST['line_integration_account_id'][$idx] ?? 0);
        $integrationAccountId = $integrationAccountId > 0 ? $integrationAccountId : null;
        $glAccountId = (int) ($_POST['line_gl_account_id'][$idx] ?? 0);
        $glAccountId = $glAccountId > 0 ? $glAccountId : null;
        $platformItemId = trim((string) ($_POST['line_platform_item_id'][$idx] ?? ''));

        $insertSql = 'INSERT INTO `' . $lineTable . '` (company_id, `' . $parentFk . '`, line_number, platform_item_id, tax_rate_id, tax_rate_snapshot, integration_account_id, gl_account_id, description, quantity, unit_amount, sub_total, total_amount, tax_amount, total_discount, active, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)';
        $stmt = mysqli_prepare($conn, $insertSql);
        if (!$stmt) {
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => mysqli_error($conn)];
        }
        $taxRateIdStr = $taxRateId === null ? null : (string) $taxRateId;
        $iaStr = $integrationAccountId === null ? null : (string) $integrationAccountId;
        $glStr = $glAccountId === null ? null : (string) $glAccountId;
        $qtyF = (float) $qty;
        $unitF = (float) $unit;
        $subF = (float) $sub;
        $taxF = (float) $taxAmt;
        $totalF = (float) $lineTotal;
        $discF = (float) $lineDiscount;
        mysqli_stmt_bind_param(
            $stmt,
            'iiisssssddddddi',
            $companyId,
            $parentId,
            $lineNum,
            $platformItemId,
            $taxRateIdStr,
            $taxSnapshot,
            $iaStr,
            $glStr,
            $desc,
            $qtyF,
            $unitF,
            $subF,
            $totalF,
            $taxF,
            $discF,
            $employeeId
        );
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            mysqli_rollback($conn);
            return ['ok' => false, 'error' => $err];
        }
        mysqli_stmt_close($stmt);
        $savedLines[] = [
            'sub_total' => $sub,
            'tax_amount' => $taxAmt,
            'total_discount' => $lineDiscount,
            'total_amount' => $lineTotal,
        ];
    }

    $totals = itm_finance_rollup_line_totals($savedLines);
    $updateParent = 'UPDATE `' . $parentTable . '` SET sub_total = ?, tax_amount = ?, total_discount = ?, total_amount = ? WHERE id = ? AND company_id = ? LIMIT 1';
    $uStmt = mysqli_prepare($conn, $updateParent);
    if (!$uStmt) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => mysqli_error($conn)];
    }
    $subF = (float) $totals['sub_total'];
    $taxF = (float) $totals['tax_amount'];
    $discF = (float) $totals['total_discount'];
    $totalF = (float) $totals['total_amount'];
    mysqli_stmt_bind_param($uStmt, 'ddddii', $subF, $taxF, $discF, $totalF, $parentId, $companyId);
    if (!mysqli_stmt_execute($uStmt)) {
        $err = mysqli_stmt_error($uStmt);
        mysqli_stmt_close($uStmt);
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => $err];
    }
    mysqli_stmt_close($uStmt);
    mysqli_commit($conn);
    if (function_exists('itm_finance_recompute_amount_due')) {
        itm_finance_recompute_amount_due($conn, $companyId, $parentTable, $parentId);
    }
    return ['ok' => true, 'error' => ''];
}

function itm_finance_render_document_lines_editor(mysqli $conn, int $companyId, string $parentTable, array $lines): void
{
    if (empty($lines)) {
        $lines = [['description' => '', 'quantity' => '1', 'unit_amount' => '0', 'sub_total' => '0', 'tax_amount' => '0', 'total_amount' => '0', 'total_discount' => '0', 'tax_rate_id' => '', 'integration_account_id' => '', 'gl_account_id' => '', 'platform_item_id' => '']];
    }
    echo '<div class="card" style="margin-top:16px;"><h3 title="Line items">📋</h3>';
    echo '<table class="table" id="itm-finance-lines-table"><thead><tr>';
    echo '<th>Description</th><th>Qty</th><th>Unit</th><th>Sub</th><th>Tax</th><th>Total</th><th></th></tr></thead><tbody>';
    foreach ($lines as $i => $line) {
        echo '<tr class="itm-finance-line-row">';
        echo '<td><input type="text" name="line_description[]" value="' . htmlspecialchars((string) ($line['description'] ?? ''), ENT_QUOTES, 'UTF-8') . '"></td>';
        echo '<td><input type="text" name="line_quantity[]" style="width:70px" value="' . htmlspecialchars((string) ($line['quantity'] ?? '1'), ENT_QUOTES, 'UTF-8') . '"></td>';
        echo '<td><input type="text" name="line_unit_amount[]" style="width:90px" value="' . htmlspecialchars((string) ($line['unit_amount'] ?? '0'), ENT_QUOTES, 'UTF-8') . '"></td>';
        echo '<td><input type="text" name="line_sub_total[]" style="width:90px" value="' . htmlspecialchars((string) ($line['sub_total'] ?? '0'), ENT_QUOTES, 'UTF-8') . '"></td>';
        echo '<td><input type="text" name="line_tax_amount[]" style="width:80px" value="' . htmlspecialchars((string) ($line['tax_amount'] ?? '0'), ENT_QUOTES, 'UTF-8') . '"></td>';
        echo '<td><input type="text" name="line_total_amount[]" style="width:90px" value="' . htmlspecialchars((string) ($line['total_amount'] ?? '0'), ENT_QUOTES, 'UTF-8') . '"></td>';
        echo '<td><input type="hidden" name="line_total_discount[]" value="' . htmlspecialchars((string) ($line['total_discount'] ?? '0'), ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="line_tax_rate_id[]" value="' . htmlspecialchars((string) ($line['tax_rate_id'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="line_integration_account_id[]" value="' . htmlspecialchars((string) ($line['integration_account_id'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="line_gl_account_id[]" value="' . htmlspecialchars((string) ($line['gl_account_id'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
        echo '<input type="hidden" name="line_platform_item_id[]" value="' . htmlspecialchars((string) ($line['platform_item_id'] ?? ''), ENT_QUOTES, 'UTF-8') . '">';
        echo '<button type="button" class="btn btn-sm itm-finance-line-remove" title="Remove row">🗑️</button></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<button type="button" class="btn btn-sm" id="itm-finance-line-add" title="Add line">➕</button></div>';
    echo '<script>
document.getElementById("itm-finance-line-add")?.addEventListener("click", function () {
  const tbody = document.querySelector("#itm-finance-lines-table tbody");
  const row = document.querySelector(".itm-finance-line-row");
  if (!tbody || !row) return;
  tbody.appendChild(row.cloneNode(true));
});
document.addEventListener("click", function (e) {
  const btn = e.target.closest(".itm-finance-line-remove");
  if (!btn) return;
  const tr = btn.closest("tr");
  const tbody = tr?.parentElement;
  if (tr && tbody && tbody.querySelectorAll("tr").length > 1) tr.remove();
});
</script>';
}

function itm_finance_render_document_lines_view(array $lines): void
{
    if ($lines === []) {
        echo '<p><em>No line items.</em></p>';
        return;
    }
    echo '<table class="table"><thead><tr><th>#</th><th>Description</th><th>Qty</th><th>Unit</th><th>Sub</th><th>Tax</th><th>Total</th></tr></thead><tbody>';
    foreach ($lines as $line) {
        echo '<tr>';
        echo '<td>' . (int) ($line['line_number'] ?? 0) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($line['description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($line['quantity'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($line['unit_amount'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($line['sub_total'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($line['tax_amount'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($line['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
