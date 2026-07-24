<?php
/**
 * Payment allocations against bills/invoices and amount_due rollups.
 */

/**
 * @return 'bills'|'invoices'|null
 */
function itm_finance_payment_parent_table(string $table): ?string
{
    if ($table === 'bills' || $table === 'invoices') {
        return $table;
    }

    return null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function itm_finance_load_payment_allocations(mysqli $conn, int $companyId, string $parentTable, int $parentId): array
{
    $parentTable = itm_finance_payment_parent_table($parentTable);
    if ($parentTable === null || $companyId <= 0 || $parentId <= 0) {
        return [];
    }
    $fkCol = $parentTable === 'bills' ? 'bill_id' : 'invoice_id';
    $sql = 'SELECT * FROM finance_payment_allocations WHERE company_id = ? AND `' . $fkCol . '` = ? AND deleted_at IS NULL ORDER BY payment_date DESC, id DESC';
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

function itm_finance_sum_allocated_amount(mysqli $conn, int $companyId, string $parentTable, int $parentId): float
{
    $parentTable = itm_finance_payment_parent_table($parentTable);
    if ($parentTable === null || $companyId <= 0 || $parentId <= 0) {
        return 0.0;
    }
    $fkCol = $parentTable === 'bills' ? 'bill_id' : 'invoice_id';
    $sql = 'SELECT COALESCE(SUM(amount), 0) AS s FROM finance_payment_allocations WHERE company_id = ? AND `' . $fkCol . '` = ? AND deleted_at IS NULL';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0.0;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $parentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (float) ($row['s'] ?? 0);
}

function itm_finance_recompute_amount_due(mysqli $conn, int $companyId, string $parentTable, int $parentId): bool
{
    $parentTable = itm_finance_payment_parent_table($parentTable);
    if ($parentTable === null || $companyId <= 0 || $parentId <= 0) {
        return false;
    }
    if (!itm_is_safe_identifier($parentTable)) {
        return false;
    }
    $hdrSql = 'SELECT total_amount FROM `' . $parentTable . '` WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1';
    $hdr = mysqli_prepare($conn, $hdrSql);
    if (!$hdr) {
        return false;
    }
    mysqli_stmt_bind_param($hdr, 'ii', $parentId, $companyId);
    mysqli_stmt_execute($hdr);
    $res = mysqli_stmt_get_result($hdr);
    $header = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($hdr);
    if (!$header) {
        return false;
    }
    $total = (float) ($header['total_amount'] ?? 0);
    $paid = itm_finance_sum_allocated_amount($conn, $companyId, $parentTable, $parentId);
    $due = max(0, round($total - $paid, 2));
    $upd = mysqli_prepare($conn, 'UPDATE `' . $parentTable . '` SET amount_due = ? WHERE id = ? AND company_id = ? LIMIT 1');
    if (!$upd) {
        return false;
    }
    mysqli_stmt_bind_param($upd, 'dii', $due, $parentId, $companyId);
    $ok = mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    return (bool) $ok;
}

/**
 * @return array{ok:bool, error?:string}
 */
function itm_finance_save_payment_allocation_from_post(
    mysqli $conn,
    int $companyId,
    string $parentTable,
    int $parentId,
    int $employeeId
): array {
    $parentTable = itm_finance_payment_parent_table($parentTable);
    if ($parentTable === null || $companyId <= 0 || $parentId <= 0) {
        return ['ok' => false, 'error' => 'Invalid payment context.'];
    }
    $amountRaw = trim((string) ($_POST['payment_amount'] ?? ''));
    $amount = (float) str_replace(',', '.', $amountRaw);
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Payment amount must be greater than zero.'];
    }
    $paymentDate = trim((string) ($_POST['payment_date'] ?? ''));
    if ($paymentDate === '') {
        $paymentDate = date('Y-m-d');
    }
    $bankId = (int) ($_POST['payment_bank_account_id'] ?? 0);
    $bankId = $bankId > 0 ? $bankId : null;
    $modeId = (int) ($_POST['payment_mode_id'] ?? 0);
    $modeId = $modeId > 0 ? $modeId : null;
    $reference = trim((string) ($_POST['payment_reference'] ?? ''));
    $memo = trim((string) ($_POST['payment_memo'] ?? ''));

    $billId = $parentTable === 'bills' ? $parentId : null;
    $invoiceId = $parentTable === 'invoices' ? $parentId : null;

    $sql = 'INSERT INTO finance_payment_allocations (company_id, bank_account_id, bill_id, invoice_id, payment_mode_id, amount, payment_date, reference, memo, active, created_by) VALUES (?,?,?,?,?,?,?,?,?,1,?)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => mysqli_error($conn)];
    }
    $billIdStr = $billId === null ? null : (string) $billId;
    $invoiceIdStr = $invoiceId === null ? null : (string) $invoiceId;
    $bankStr = $bankId === null ? null : (string) $bankId;
    $modeStr = $modeId === null ? null : (string) $modeId;
    mysqli_stmt_bind_param(
        $stmt,
        'issssdsssi',
        $companyId,
        $bankStr,
        $billIdStr,
        $invoiceIdStr,
        $modeStr,
        $amount,
        $paymentDate,
        $reference,
        $memo,
        $employeeId
    );
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return ['ok' => false, 'error' => $err];
    }
    mysqli_stmt_close($stmt);
    itm_finance_recompute_amount_due($conn, $companyId, $parentTable, $parentId);

    return ['ok' => true];
}

/**
 * @return array{ok:bool, error?:string}
 */
function itm_finance_soft_delete_payment_allocation(
    mysqli $conn,
    int $companyId,
    string $parentTable,
    int $parentId,
    int $allocationId,
    int $employeeId
): array {
    $parentTable = itm_finance_payment_parent_table($parentTable);
    if ($parentTable === null || $companyId <= 0 || $parentId <= 0 || $allocationId <= 0) {
        return ['ok' => false, 'error' => 'Invalid delete context.'];
    }
    $fkCol = $parentTable === 'bills' ? 'bill_id' : 'invoice_id';
    $sql = 'UPDATE finance_payment_allocations SET active = 0, deleted_by = ?, deleted_at = NOW() WHERE id = ? AND company_id = ? AND `' . $fkCol . '` = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => mysqli_error($conn)];
    }
    mysqli_stmt_bind_param($stmt, 'iiii', $employeeId, $allocationId, $companyId, $parentId);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return ['ok' => false, 'error' => $err];
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($affected < 1) {
        return ['ok' => false, 'error' => 'Payment not found.'];
    }
    itm_finance_recompute_amount_due($conn, $companyId, $parentTable, $parentId);

    return ['ok' => true];
}

function itm_finance_payment_fk_label(mysqli $conn, int $companyId, string $table, $id): string
{
    $id = (int) $id;
    if ($id <= 0 || !itm_is_safe_identifier($table)) {
        return '';
    }
    if ($table === 'bank_accounts') {
        $sql = 'SELECT account_name FROM bank_accounts WHERE id = ? AND company_id = ? LIMIT 1';
    } elseif ($table === 'payment_modes') {
        $sql = 'SELECT name FROM payment_modes WHERE id = ? AND company_id = ? LIMIT 1';
    } else {
        return '';
    }
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return $row ? (string) (array_values($row)[0] ?? '') : '';
}

/**
 * @param array<int, array<string, mixed>> $allocations
 */
function itm_finance_render_payment_allocations_view(mysqli $conn, int $companyId, array $allocations, float $amountDue): void
{
    echo '<div class="card" style="margin-top:16px;"><h3 title="Payments">💳</h3>';
    echo '<p><strong>Amount due:</strong> ' . htmlspecialchars(number_format($amountDue, 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</p>';
    if (empty($allocations)) {
        echo '<p>No payments recorded.</p></div>';

        return;
    }
    echo '<table class="table"><thead><tr><th>Date</th><th>Amount</th><th>Bank</th><th>Mode</th><th>Reference</th></tr></thead><tbody>';
    foreach ($allocations as $row) {
        $bankLabel = itm_finance_payment_fk_label($conn, $companyId, 'bank_accounts', $row['bank_account_id'] ?? 0);
        $modeLabel = itm_finance_payment_fk_label($conn, $companyId, 'payment_modes', $row['payment_mode_id'] ?? 0);
        echo '<tr>';
        echo '<td>' . htmlspecialchars(itm_format_cell_scalar_display($row['payment_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars(number_format((float) ($row['amount'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($bankLabel, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($modeLabel, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['reference'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function itm_finance_render_payment_allocations_editor(
    mysqli $conn,
    int $companyId,
    string $parentTable,
    int $parentId,
    array $allocations,
    float $amountDue
): void {
    itm_finance_render_payment_allocations_view($conn, $companyId, $allocations, $amountDue);
    $banks = [];
    $bRes = mysqli_query($conn, 'SELECT id, account_name FROM bank_accounts WHERE company_id = ' . (int) $companyId . ' AND deleted_at IS NULL ORDER BY account_name');
    while ($bRes && ($b = mysqli_fetch_assoc($bRes))) {
        $banks[] = $b;
    }
    $modes = [];
    $mRes = mysqli_query($conn, 'SELECT id, name FROM payment_modes WHERE company_id = ' . (int) $companyId . ' AND deleted_at IS NULL ORDER BY name');
    while ($mRes && ($m = mysqli_fetch_assoc($mRes))) {
        $modes[] = $m;
    }
    echo '<div class="card" style="margin-top:16px;"><h3 title="Record payment">➕</h3>';
    echo '<input type="hidden" name="finance_payment_parent_table" value="' . htmlspecialchars($parentTable, ENT_QUOTES, 'UTF-8') . '">';
    echo '<div class="form-group"><label>Payment date</label><input type="date" name="payment_date" value="' . htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') . '"></div>';
    echo '<div class="form-group"><label>Amount</label><input type="text" name="payment_amount" value=""></div>';
    echo '<div class="form-group"><label>Bank account</label><select name="payment_bank_account_id"><option value="">-- Select --</option>';
    foreach ($banks as $b) {
        echo '<option value="' . (int) $b['id'] . '">' . htmlspecialchars((string) $b['account_name'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group"><label>Payment mode</label><select name="payment_mode_id"><option value="">-- Select --</option>';
    foreach ($modes as $m) {
        echo '<option value="' . (int) $m['id'] . '">' . htmlspecialchars((string) $m['name'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group"><label>Reference</label><input type="text" name="payment_reference" value=""></div>';
    echo '<div class="form-group"><label>Memo</label><textarea name="payment_memo" rows="2"></textarea></div>';
    echo '<button type="submit" class="btn btn-primary" name="save_payment_allocation" value="1" title="Save payment">💾</button>';
    if (!empty($allocations)) {
        echo '<h4 title="Remove payment">🗑️</h4><table class="table"><thead><tr><th>Date</th><th>Amount</th><th></th></tr></thead><tbody>';
        foreach ($allocations as $row) {
            echo '<tr><td>' . htmlspecialchars(itm_format_cell_scalar_display($row['payment_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars(number_format((float) ($row['amount'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td><button type="submit" class="btn btn-sm btn-danger" name="delete_payment_allocation_id" value="' . (int) ($row['id'] ?? 0) . '" title="Delete">🗑️</button></td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}
