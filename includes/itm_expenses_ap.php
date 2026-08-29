<?php
/**
 * Why: AP field normalization for expenses (import header aliases, EUR defaults, budget actuals).
 */

require_once __DIR__ . '/itm_date_format.php';

/**
 * @return array<int>
 */
function itm_expenses_paid_status_ids_for_actuals(mysqli $conn, int $companyId): array
{
    $ids = [];
    $sql = 'SELECT id FROM paid_statuses WHERE company_id = ? AND name IN (\'Posted\', \'Paid\') AND deleted_at IS NULL';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $ids;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $ids[] = (int) $row['id'];
    }
    mysqli_stmt_close($stmt);
    return $ids;
}

function itm_expenses_resolve_default_paid_status_id(mysqli $conn, int $companyId, string $preferName = 'Draft'): ?int
{
    $sql = 'SELECT id FROM paid_statuses WHERE company_id = ? AND name = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $preferName);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? (int) $row['id'] : null;
}

function itm_expenses_resolve_paid_status_name(mysqli $conn, int $companyId, int $paidStatusId): string
{
    if ($paidStatusId <= 0) {
        return '';
    }
    $sql = 'SELECT name FROM paid_statuses WHERE company_id = ? AND id = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $paidStatusId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return trim((string) ($row['name'] ?? ''));
}

function itm_expenses_is_approved_paid_status_id(mysqli $conn, int $companyId, int $paidStatusId): bool
{
    if ($paidStatusId <= 0) {
        return false;
    }
    $name = itm_expenses_resolve_paid_status_name($conn, $companyId, $paidStatusId);
    $normalized = strtolower(trim($name));

    return in_array($normalized, ['posted', 'paid'], true);
}

function itm_expenses_stamp_tax_rate_snapshot(mysqli $conn, int $companyId, ?int $taxRateId): ?string
{
    if ($taxRateId === null || $taxRateId <= 0) {
        return null;
    }
    $sql = 'SELECT rate_percent FROM tax_rates WHERE company_id = ? AND id = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $taxRateId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }
    return number_format((float) $row['rate_percent'], 2, '.', '');
}

function itm_expenses_sync_legacy_date(array $row): string
{
    $posting = trim((string) ($row['posting_date'] ?? ''));
    if ($posting !== '') {
        return $posting;
    }
    $invoice = trim((string) ($row['invoice_date'] ?? ''));
    if ($invoice !== '') {
        return $invoice;
    }
    return trim((string) ($row['date'] ?? ''));
}

function itm_expenses_normalize_decimal(?string $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }
    $raw = str_replace(',', '.', $raw);
    if (!is_numeric($raw)) {
        return null;
    }
    return number_format((float) $raw, 2, '.', '');
}

/**
 * Merge normalized AP fields into scaffold $data / $sqlValues after generic POST loop.
 *
 * @param array<int, array<string, mixed>> $fieldColumns
 * @param array<string, mixed> $data
 * @param array<string, string> $sqlValues
 * @param array<int, string> $errors
 */
function itm_expenses_ap_apply_post_normalization(
    mysqli $conn,
    int $companyId,
    string $crudAction,
    array $fieldColumns,
    array &$data,
    array &$sqlValues,
    array &$errors
): void {
    if ($companyId <= 0) {
        return;
    }

    $dateFields = ['invoice_date', 'posting_date', 'payment_date', 'due_date'];
    foreach ($dateFields as $df) {
        if (!array_key_exists($df, $data) && !isset($_POST[$df])) {
            continue;
        }
        $parsed = itm_parse_date_input((string) ($_POST[$df] ?? $data[$df] ?? ''));
        if ($parsed === null && in_array($df, ['posting_date'], true) && $crudAction === 'create') {
            $errors[] = 'Posting date is required.';
            continue;
        }
        if ($parsed !== null) {
            $data[$df] = $parsed;
            $sqlValues[$df] = "'" . mysqli_real_escape_string($conn, $parsed) . "'";
        } elseif (array_key_exists($df, $sqlValues)) {
            $data[$df] = '';
            $sqlValues[$df] = 'NULL';
        }
    }

    if (isset($_POST['currency_code']) || isset($data['currency_code'])) {
        $cc = strtoupper(trim((string) ($_POST['currency_code'] ?? $data['currency_code'] ?? 'EUR')));
        if ($cc === '') {
            $cc = 'EUR';
        }
        $data['currency_code'] = $cc;
        $sqlValues['currency_code'] = "'" . mysqli_real_escape_string($conn, $cc) . "'";
    }

    if (isset($_POST['exchange_rate']) || isset($data['exchange_rate'])) {
        $fx = str_replace(',', '.', trim((string) ($_POST['exchange_rate'] ?? $data['exchange_rate'] ?? '1')));
        if ($fx === '' || !is_numeric($fx)) {
            $fx = '1.000000';
        }
        $data['exchange_rate'] = $fx;
        $sqlValues['exchange_rate'] = "'" . mysqli_real_escape_string($conn, number_format((float) $fx, 6, '.', '')) . "'";
    }

    foreach (['net_amount', 'vat_amount', 'total_discount', 'amount'] as $moneyField) {
        if (!isset($_POST[$moneyField]) && !array_key_exists($moneyField, $data)) {
            continue;
        }
        $norm = itm_expenses_normalize_decimal((string) ($_POST[$moneyField] ?? $data[$moneyField] ?? ''));
        if ($norm === null) {
            if ($moneyField === 'amount') {
                $errors[] = 'Amount is required.';
            } else {
                $data[$moneyField] = '';
                $sqlValues[$moneyField] = 'NULL';
            }
            continue;
        }
        $data[$moneyField] = $norm;
        $sqlValues[$moneyField] = $norm;
    }

    $taxRateId = null;
    if (isset($data['tax_rate_id']) && $data['tax_rate_id'] !== '' && $data['tax_rate_id'] !== 'NULL') {
        $taxRateId = (int) $data['tax_rate_id'];
    }
    $snapshot = itm_expenses_stamp_tax_rate_snapshot($conn, $companyId, $taxRateId);
    if ($snapshot !== null) {
        $data['tax_rate_snapshot'] = $snapshot;
        $sqlValues['tax_rate_snapshot'] = $snapshot;
    }

    if ($crudAction === 'create') {
        $paidStatus = $data['paid_status_id'] ?? '';
        if ($paidStatus === '' || $paidStatus === 'NULL') {
            $defaultPaid = itm_expenses_resolve_default_paid_status_id($conn, $companyId, 'Draft');
            if ($defaultPaid !== null) {
                $data['paid_status_id'] = (string) $defaultPaid;
                $sqlValues['paid_status_id'] = (string) $defaultPaid;
            }
        }
    }

    $legacyDate = itm_expenses_sync_legacy_date($data);
    if ($legacyDate !== '') {
        $data['date'] = $legacyDate;
        $sqlValues['date'] = "'" . mysqli_real_escape_string($conn, $legacyDate) . "'";
    }

    $isRecursive = !empty($_POST['is_recursive']) || (isset($data['is_recursive']) && (int) $data['is_recursive'] === 1);
    $data['is_recursive'] = $isRecursive ? '1' : '0';
    $sqlValues['is_recursive'] = $isRecursive ? '1' : '0';
    foreach (['next_run_date', 'recurrence_end_date'] as $recDateField) {
        if (!array_key_exists($recDateField, $data) && !isset($_POST[$recDateField])) {
            continue;
        }
        $parsedRec = itm_parse_date_input((string) ($_POST[$recDateField] ?? $data[$recDateField] ?? ''));
        if ($parsedRec !== null) {
            $data[$recDateField] = $parsedRec;
            $sqlValues[$recDateField] = "'" . mysqli_real_escape_string($conn, $parsedRec) . "'";
        } elseif (array_key_exists($recDateField, $sqlValues)) {
            $data[$recDateField] = '';
            $sqlValues[$recDateField] = 'NULL';
        }
    }
    if ($isRecursive) {
        $recId = (int) ($data['expense_recurrence_id'] ?? $_POST['expense_recurrence_id'] ?? 0);
        if ($recId <= 0) {
            $errors[] = 'Recurrence interval is required when recurring expense is enabled.';
        }
        $nextRun = $data['next_run_date'] ?? '';
        if ($nextRun === '' || $nextRun === 'NULL') {
            $errors[] = 'Next run date is required for recurring expenses.';
        }
        $endRun = $data['recurrence_end_date'] ?? '';
        if ($endRun !== '' && $endRun !== 'NULL' && $nextRun !== '' && $endRun < $nextRun) {
            $errors[] = 'Recurrence end date must be on or after next run date.';
        }
    } else {
        if (array_key_exists('expense_recurrence_id', $sqlValues)) {
            $data['expense_recurrence_id'] = '';
            $sqlValues['expense_recurrence_id'] = 'NULL';
        }
        if (array_key_exists('next_run_date', $sqlValues)) {
            $data['next_run_date'] = '';
            $sqlValues['next_run_date'] = 'NULL';
        }
        if (array_key_exists('recurrence_end_date', $sqlValues)) {
            $data['recurrence_end_date'] = '';
            $sqlValues['recurrence_end_date'] = 'NULL';
        }
    }
}

/**
 * RootFi / Excel header aliases → expenses column names.
 *
 * @return array<string, string> normalized header key => field name
 */
function itm_expenses_import_header_aliases(): array
{
    return [
        'posted date' => 'posting_date',
        'posted_date' => 'posting_date',
        'document number' => 'invoice_number',
        'document_number' => 'invoice_number',
        'invoice number' => 'invoice_number',
        'contact' => '__supplier_contact__',
        'supplier' => '__supplier_contact__',
        'supplier name' => '__supplier_contact__',
        'net amount' => 'net_amount',
        'vat amount' => 'vat_amount',
        'tax amount' => 'vat_amount',
        'total amount' => 'amount',
        'gross amount' => 'amount',
        'currency' => 'currency_code',
        'currency code' => 'currency_code',
        'exchange rate' => 'exchange_rate',
        'payment mode' => 'payment_mode_id',
        'paid status' => 'paid_status_id',
        'tax rate' => 'tax_rate_id',
        'purchase order' => 'purchase_order',
        'purchase_order' => 'purchase_order',
        'quotation order' => 'quotation_order',
        'quotation_order' => 'quotation_order',
    ];
}

function itm_expenses_resolve_supplier_id_by_contact_label(mysqli $conn, int $companyId, string $label): ?int
{
    $label = trim($label);
    if ($label === '' || $companyId <= 0) {
        return null;
    }
    $sql = 'SELECT id FROM suppliers WHERE company_id = ? AND deleted_at IS NULL AND (name = ? OR supplier_code = ?) LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'iss', $companyId, $label, $label);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? (int) $row['id'] : null;
}

/**
 * Apply AP defaults to one import row (SQL fragment values in $rowData).
 *
 * @param array<string, string> $rowData
 */
function itm_expenses_ap_normalize_import_row(mysqli $conn, int $companyId, array &$rowData): void
{
    if ($companyId <= 0) {
        return;
    }

    if (($rowData['posting_date'] ?? 'NULL') === 'NULL' && ($rowData['date'] ?? 'NULL') !== 'NULL') {
        $rowData['posting_date'] = $rowData['date'];
    }
    if (($rowData['date'] ?? 'NULL') === 'NULL' && ($rowData['posting_date'] ?? 'NULL') !== 'NULL') {
        $rowData['date'] = $rowData['posting_date'];
    }

    if (($rowData['currency_code'] ?? 'NULL') === 'NULL') {
        $rowData['currency_code'] = "'EUR'";
    }
    if (($rowData['exchange_rate'] ?? 'NULL') === 'NULL') {
        $rowData['exchange_rate'] = '1.000000';
    }

    if (($rowData['paid_status_id'] ?? 'NULL') === 'NULL') {
        $draftId = itm_expenses_resolve_default_paid_status_id($conn, $companyId, 'Draft');
        if ($draftId !== null) {
            $rowData['paid_status_id'] = (string) $draftId;
        }
    }

    $taxRateId = null;
    if (($rowData['tax_rate_id'] ?? 'NULL') !== 'NULL') {
        $taxRateId = (int) $rowData['tax_rate_id'];
    }
    $snapshot = itm_expenses_stamp_tax_rate_snapshot($conn, $companyId, $taxRateId);
    if ($snapshot !== null) {
        $rowData['tax_rate_snapshot'] = $snapshot;
    }

    $legacyParts = [];
    foreach (['posting_date', 'invoice_date', 'date'] as $df) {
        $v = $rowData[$df] ?? 'NULL';
        if ($v !== 'NULL' && $v !== '') {
            $legacyParts[$df] = trim($v, "'");
        }
    }
    if (!empty($legacyParts)) {
        $synced = itm_expenses_sync_legacy_date($legacyParts);
        if ($synced !== '') {
            $rowData['date'] = "'" . mysqli_real_escape_string($conn, $synced) . "'";
        }
    }
}

/**
 * Plan field order for expenses create/edit forms (AP header flow).
 *
 * @return list<string>
 */
function itm_expenses_ap_form_field_order(): array
{
    return [
        'supplier_id',
        'invoice_date',
        'posting_date',
        'payment_date',
        'due_date',
        'purchase_order',
        'purchase_order_accepted',
        'quotation_order',
        'quotation_order_accepted',
        'net_amount',
        'vat_amount',
        'total_discount',
        'amount',
        'tax_rate_id',
        'tax_rate_snapshot',
        'currency_code',
        'exchange_rate',
        'payment_mode_id',
        'paid_status_id',
        'cost_center_id',
        'gl_account_id',
        'description',
        'invoice_number',
        'bill_id',
        'invoice_id',
        'expense_recurrence_id',
        'is_recursive',
        'next_run_date',
        'recurrence_end_date',
        'date',
        'active',
    ];
}

/**
 * @param array<int, array<string, mixed>> $columns DESCRIBE-style column rows
 * @return array<int, array<string, mixed>>
 */
function itm_expenses_reorder_form_field_columns(array $columns): array
{
    $rank = array_flip(itm_expenses_ap_form_field_order());
    usort($columns, static function ($a, $b) use ($rank) {
        $fa = (string) ($a['Field'] ?? '');
        $fb = (string) ($b['Field'] ?? '');
        $ra = $rank[$fa] ?? 9999;
        $rb = $rank[$fb] ?? 9999;
        if ($ra === $rb) {
            return strcmp($fa, $fb);
        }
        return $ra <=> $rb;
    });

    return array_values($columns);
}

/**
 * Live expense row already linked to this bill (soft-delete aware).
 */
function itm_expenses_find_id_by_bill_id(mysqli $conn, int $companyId, int $billId): ?int
{
    if ($companyId <= 0 || $billId <= 0) {
        return null;
    }
    $sql = 'SELECT id FROM expenses WHERE company_id = ? AND bill_id = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $billId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }

    return (int) $row['id'];
}

/**
 * Create one budget expense from a bill header (Posted status, bill_id link).
 *
 * @return array{ok: bool, expense_id?: int, error?: string}
 */
function itm_expenses_post_from_bill(mysqli $conn, int $companyId, int $billId, int $employeeId): array
{
    if ($companyId <= 0 || $billId <= 0) {
        return ['ok' => false, 'error' => 'Invalid company or bill.'];
    }

    $existingId = itm_expenses_find_id_by_bill_id($conn, $companyId, $billId);
    if ($existingId !== null) {
        return ['ok' => false, 'error' => 'This bill was already posted to expenses.', 'expense_id' => $existingId];
    }

    $sql = 'SELECT * FROM bills WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not load bill.'];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $billId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $bill = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$bill) {
        return ['ok' => false, 'error' => 'Bill not found.'];
    }

    $costCenterId = (int) ($bill['cost_center_id'] ?? 0);
    $glAccountId = (int) ($bill['gl_account_id'] ?? 0);
    if ($costCenterId <= 0 || $glAccountId <= 0) {
        return ['ok' => false, 'error' => 'Set cost center and GL account on the bill before posting to expenses.'];
    }

    $postedStatusId = itm_expenses_resolve_default_paid_status_id($conn, $companyId, 'Posted');
    if ($postedStatusId === null) {
        return ['ok' => false, 'error' => 'Posted paid status is not configured for this company.'];
    }

    $postingDate = (string) ($bill['posted_date'] ?? '');
    if ($postingDate === '' || $postingDate === '0000-00-00') {
        $postingDate = date('Y-m-d');
    }
    $invoiceDate = (string) ($bill['posted_date'] ?? '');
    if ($invoiceDate === '' || $invoiceDate === '0000-00-00') {
        $invoiceDate = $postingDate;
    }
    $legacyDate = itm_expenses_sync_legacy_date([
        'posting_date' => $postingDate,
        'invoice_date' => $invoiceDate,
    ]);

    $invoiceNumber = trim((string) ($bill['document_number'] ?? ''));
    if ($invoiceNumber === '') {
        return ['ok' => false, 'error' => 'Bill document number is required to post to expenses.'];
    }

    $amount = (string) ($bill['total_amount'] ?? '0');
    $netAmount = isset($bill['sub_total']) ? (string) $bill['sub_total'] : null;
    $vatAmount = isset($bill['tax_amount']) ? (string) $bill['tax_amount'] : null;
    $totalDiscount = (string) ($bill['total_discount'] ?? '0');
    $currencyCode = strtoupper(trim((string) ($bill['currency_code'] ?? 'EUR')));
    if ($currencyCode === '') {
        $currencyCode = 'EUR';
    }
    $exchangeRate = (string) ($bill['exchange_rate'] ?? '1.000000');
    $supplierId = isset($bill['supplier_id']) && (int) $bill['supplier_id'] > 0 ? (int) $bill['supplier_id'] : null;
    $description = trim((string) ($bill['memo'] ?? ''));
    $dueDate = (string) ($bill['due_date'] ?? '');
    if ($dueDate === '' || $dueDate === '0000-00-00') {
        $dueDate = null;
    }

    $taxRateId = null;
    $taxSnapshot = null;
    $lineSql = 'SELECT tax_rate_id FROM bill_line_items WHERE company_id = ? AND bill_id = ? AND deleted_at IS NULL ORDER BY line_number ASC LIMIT 1';
    $lineStmt = mysqli_prepare($conn, $lineSql);
    if ($lineStmt) {
        mysqli_stmt_bind_param($lineStmt, 'ii', $companyId, $billId);
        mysqli_stmt_execute($lineStmt);
        $lineRes = mysqli_stmt_get_result($lineStmt);
        $lineRow = $lineRes ? mysqli_fetch_assoc($lineRes) : null;
        mysqli_stmt_close($lineStmt);
        if ($lineRow && (int) ($lineRow['tax_rate_id'] ?? 0) > 0) {
            $taxRateId = (int) $lineRow['tax_rate_id'];
            $taxSnapshot = itm_expenses_stamp_tax_rate_snapshot($conn, $companyId, $taxRateId);
        }
    }

    $insertCols = [
        'company_id', 'cost_center_id', 'gl_account_id', 'date', 'posting_date', 'invoice_date',
        'total_discount', 'paid_status_id', 'bill_id', 'currency_code', 'exchange_rate', 'amount',
        'description', 'invoice_number', 'purchase_order_accepted', 'quotation_order_accepted', 'active',
    ];
    $insertTypes = 'iiisssdiisddssiii';
    $insertParams = [
        $companyId,
        $costCenterId,
        $glAccountId,
        $legacyDate,
        $postingDate,
        $invoiceDate,
        $totalDiscount,
        $postedStatusId,
        $billId,
        $currencyCode,
        $exchangeRate,
        $amount,
        $description,
        $invoiceNumber,
        1,
        1,
        1,
    ];

    if ($dueDate !== null) {
        array_splice($insertCols, 6, 0, ['due_date']);
        $insertTypes = substr($insertTypes, 0, 6) . 's' . substr($insertTypes, 6);
        array_splice($insertParams, 6, 0, [$dueDate]);
    }
    if ($netAmount !== null && $netAmount !== '') {
        $insertCols[] = 'net_amount';
        $insertTypes .= 'd';
        $insertParams[] = $netAmount;
    }
    if ($vatAmount !== null && $vatAmount !== '') {
        $insertCols[] = 'vat_amount';
        $insertTypes .= 'd';
        $insertParams[] = $vatAmount;
    }
    if ($taxRateId !== null && $taxRateId > 0) {
        $insertCols[] = 'tax_rate_id';
        $insertCols[] = 'tax_rate_snapshot';
        $insertTypes .= 'id';
        $insertParams[] = $taxRateId;
        $insertParams[] = $taxSnapshot;
    }
    if ($supplierId !== null && $supplierId > 0) {
        $insertCols[] = 'supplier_id';
        $insertTypes .= 'i';
        $insertParams[] = $supplierId;
    }
    if ($employeeId > 0) {
        $insertCols[] = 'created_by';
        $insertCols[] = 'updated_by';
        $insertTypes .= 'ii';
        $insertParams[] = $employeeId;
        $insertParams[] = $employeeId;
    }

    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $colList = implode(',', array_map(static function ($c) {
        return '`' . str_replace('`', '``', $c) . '`';
    }, $insertCols));
    $insertSql = 'INSERT INTO expenses (' . $colList . ') VALUES (' . $placeholders . ')';

    $ins = mysqli_prepare($conn, $insertSql);
    if (!$ins) {
        return ['ok' => false, 'error' => 'Could not prepare expense insert.'];
    }

    $bind = [$insertTypes];
    foreach ($insertParams as $key => $value) {
        $bind[] = &$insertParams[$key];
    }
    call_user_func_array([$ins, 'bind_param'], $bind);

    if (!mysqli_stmt_execute($ins)) {
        $err = itm_format_db_constraint_error(mysqli_stmt_errno($ins), mysqli_stmt_error($ins));
        mysqli_stmt_close($ins);

        return ['ok' => false, 'error' => $err];
    }

    $expenseId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($ins);

    return ['ok' => true, 'expense_id' => $expenseId];
}

/**
 * Live expense row already linked to this invoice (soft-delete aware).
 */
function itm_expenses_find_id_by_invoice_id(mysqli $conn, int $companyId, int $invoiceId): ?int
{
    if ($companyId <= 0 || $invoiceId <= 0) {
        return null;
    }
    $sql = 'SELECT id FROM expenses WHERE company_id = ? AND invoice_id = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $invoiceId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }

    return (int) $row['id'];
}

/**
 * Create one budget expense from an invoice header (Posted status, invoice_id + invoice_number link).
 *
 * @return array{ok: bool, expense_id?: int, error?: string}
 */
function itm_expenses_post_from_invoice(mysqli $conn, int $companyId, int $invoiceId, int $employeeId): array
{
    if ($companyId <= 0 || $invoiceId <= 0) {
        return ['ok' => false, 'error' => 'Invalid company or invoice.'];
    }

    $existingId = itm_expenses_find_id_by_invoice_id($conn, $companyId, $invoiceId);
    if ($existingId !== null) {
        return ['ok' => false, 'error' => 'This invoice was already posted to expenses.', 'expense_id' => $existingId];
    }

    $sql = 'SELECT * FROM invoices WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not load invoice.'];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $invoiceId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $invoice = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$invoice) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }

    $costCenterId = (int) ($invoice['cost_center_id'] ?? 0);
    $glAccountId = (int) ($invoice['gl_account_id'] ?? 0);
    if ($costCenterId <= 0 || $glAccountId <= 0) {
        return ['ok' => false, 'error' => 'Set cost center and GL account on the invoice before posting to expenses.'];
    }

    $postedStatusId = itm_expenses_resolve_default_paid_status_id($conn, $companyId, 'Posted');
    if ($postedStatusId === null) {
        return ['ok' => false, 'error' => 'Posted paid status is not configured for this company.'];
    }

    $postingDate = (string) ($invoice['posted_date'] ?? '');
    if ($postingDate === '' || $postingDate === '0000-00-00') {
        $postingDate = date('Y-m-d');
    }
    $invoiceDate = (string) ($invoice['posted_date'] ?? '');
    if ($invoiceDate === '' || $invoiceDate === '0000-00-00') {
        $invoiceDate = $postingDate;
    }
    $legacyDate = itm_expenses_sync_legacy_date([
        'posting_date' => $postingDate,
        'invoice_date' => $invoiceDate,
    ]);

    $invoiceNumber = trim((string) ($invoice['document_number'] ?? ''));
    if ($invoiceNumber === '') {
        return ['ok' => false, 'error' => 'Invoice document number is required to post to expenses.'];
    }

    $amount = (string) ($invoice['total_amount'] ?? '0');
    $netAmount = isset($invoice['sub_total']) ? (string) $invoice['sub_total'] : null;
    $vatAmount = isset($invoice['tax_amount']) ? (string) $invoice['tax_amount'] : null;
    $totalDiscount = (string) ($invoice['total_discount'] ?? '0');
    $currencyCode = strtoupper(trim((string) ($invoice['currency_code'] ?? 'EUR')));
    if ($currencyCode === '') {
        $currencyCode = 'EUR';
    }
    $exchangeRate = (string) ($invoice['exchange_rate'] ?? '1.000000');
    $supplierId = isset($invoice['supplier_id']) && (int) $invoice['supplier_id'] > 0 ? (int) $invoice['supplier_id'] : null;
    $description = trim((string) ($invoice['memo'] ?? ''));
    $dueDate = (string) ($invoice['due_date'] ?? '');
    if ($dueDate === '' || $dueDate === '0000-00-00') {
        $dueDate = null;
    }

    $taxRateId = null;
    $taxSnapshot = null;
    $lineSql = 'SELECT tax_rate_id FROM invoice_line_items WHERE company_id = ? AND invoice_id = ? AND deleted_at IS NULL ORDER BY line_number ASC LIMIT 1';
    $lineStmt = mysqli_prepare($conn, $lineSql);
    if ($lineStmt) {
        mysqli_stmt_bind_param($lineStmt, 'ii', $companyId, $invoiceId);
        mysqli_stmt_execute($lineStmt);
        $lineRes = mysqli_stmt_get_result($lineStmt);
        $lineRow = $lineRes ? mysqli_fetch_assoc($lineRes) : null;
        mysqli_stmt_close($lineStmt);
        if ($lineRow && (int) ($lineRow['tax_rate_id'] ?? 0) > 0) {
            $taxRateId = (int) $lineRow['tax_rate_id'];
            $taxSnapshot = itm_expenses_stamp_tax_rate_snapshot($conn, $companyId, $taxRateId);
        }
    }

    $insertCols = [
        'company_id', 'cost_center_id', 'gl_account_id', 'date', 'posting_date', 'invoice_date',
        'total_discount', 'paid_status_id', 'invoice_id', 'currency_code', 'exchange_rate', 'amount',
        'description', 'invoice_number', 'purchase_order_accepted', 'quotation_order_accepted', 'active',
    ];
    $insertTypes = 'iiisssdiisddssiii';
    $insertParams = [
        $companyId,
        $costCenterId,
        $glAccountId,
        $legacyDate,
        $postingDate,
        $invoiceDate,
        $totalDiscount,
        $postedStatusId,
        $invoiceId,
        $currencyCode,
        $exchangeRate,
        $amount,
        $description,
        $invoiceNumber,
        1,
        1,
        1,
    ];

    if ($dueDate !== null) {
        array_splice($insertCols, 6, 0, ['due_date']);
        $insertTypes = substr($insertTypes, 0, 6) . 's' . substr($insertTypes, 6);
        array_splice($insertParams, 6, 0, [$dueDate]);
    }
    if ($netAmount !== null && $netAmount !== '') {
        $insertCols[] = 'net_amount';
        $insertTypes .= 'd';
        $insertParams[] = $netAmount;
    }
    if ($vatAmount !== null && $vatAmount !== '') {
        $insertCols[] = 'vat_amount';
        $insertTypes .= 'd';
        $insertParams[] = $vatAmount;
    }
    if ($taxRateId !== null && $taxRateId > 0) {
        $insertCols[] = 'tax_rate_id';
        $insertCols[] = 'tax_rate_snapshot';
        $insertTypes .= 'id';
        $insertParams[] = $taxRateId;
        $insertParams[] = $taxSnapshot;
    }
    if ($supplierId !== null && $supplierId > 0) {
        $insertCols[] = 'supplier_id';
        $insertTypes .= 'i';
        $insertParams[] = $supplierId;
    }
    if ($employeeId > 0) {
        $insertCols[] = 'created_by';
        $insertCols[] = 'updated_by';
        $insertTypes .= 'ii';
        $insertParams[] = $employeeId;
        $insertParams[] = $employeeId;
    }

    $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
    $colList = implode(',', array_map(static function ($c) {
        return '`' . str_replace('`', '``', $c) . '`';
    }, $insertCols));
    $insertSql = 'INSERT INTO expenses (' . $colList . ') VALUES (' . $placeholders . ')';

    $ins = mysqli_prepare($conn, $insertSql);
    if (!$ins) {
        return ['ok' => false, 'error' => 'Could not prepare expense insert.'];
    }

    $bind = [$insertTypes];
    foreach ($insertParams as $key => $value) {
        $bind[] = &$insertParams[$key];
    }
    call_user_func_array([$ins, 'bind_param'], $bind);

    if (!mysqli_stmt_execute($ins)) {
        $err = itm_format_db_constraint_error(mysqli_stmt_errno($ins), mysqli_stmt_error($ins));
        mysqli_stmt_close($ins);

        return ['ok' => false, 'error' => $err];
    }

    $expenseId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($ins);

    return ['ok' => true, 'expense_id' => $expenseId];
}

/**
 * Advance recurrence schedule from interval code.
 */
function itm_expense_recurrence_advance_date(string $code, string $fromYmd): ?string
{
    $code = strtolower(trim($code));
    if ($fromYmd === '') {
        return null;
    }
    $ts = strtotime($fromYmd);
    if ($ts === false) {
        return null;
    }
    switch ($code) {
        case 'hourly':
            return date('Y-m-d', strtotime('+1 hour', $ts));
        case 'daily':
            return date('Y-m-d', strtotime('+1 day', $ts));
        case 'weekly':
            return date('Y-m-d', strtotime('+1 week', $ts));
        case 'monthly':
            return date('Y-m-d', strtotime('+1 month', $ts));
        case 'quarterly':
            return date('Y-m-d', strtotime('+3 months', $ts));
        case 'yearly':
            return date('Y-m-d', strtotime('+1 year', $ts));
        case 'every_2_years':
            return date('Y-m-d', strtotime('+2 years', $ts));
        case 'every_3_years':
            return date('Y-m-d', strtotime('+3 years', $ts));
        case 'every_4_years':
            return date('Y-m-d', strtotime('+4 years', $ts));
        case 'every_5_years':
            return date('Y-m-d', strtotime('+5 years', $ts));
        default:
            return null;
    }
}

/**
 * Human-readable label for recurrence source expense FK (list/view/dropdown).
 */
function itm_expenses_format_recurrence_source_label(array $row): string
{
    $id = (int) ($row['id'] ?? 0);
    $invoiceNumber = trim((string) ($row['invoice_number'] ?? ''));
    $description = trim((string) ($row['description'] ?? ''));
    $postingDate = trim((string) ($row['posting_date'] ?? ''));

    if ($invoiceNumber !== '') {
        $label = $invoiceNumber;
    } elseif ($description !== '') {
        $label = $description;
    } else {
        $label = 'Expense #' . $id;
    }

    if ($postingDate !== '') {
        $dateLabel = function_exists('itm_format_date_display')
            ? itm_format_date_display($postingDate)
            : $postingDate;
        if ($dateLabel !== '') {
            $label .= ' (' . $dateLabel . ')';
        }
    }

    return $label;
}

/**
 * Dropdown rows for expenses.recurrence_source_expense_id (self-FK).
 *
 * @return array<int, array{id:int, label:string}>
 */
function itm_expenses_recurrence_source_option_rows(mysqli $conn, int $companyId, int $excludeExpenseId = 0): array
{
    if ($companyId <= 0) {
        return [];
    }

    $sql = 'SELECT id, invoice_number, posting_date, description
            FROM expenses
            WHERE company_id = ? AND deleted_at IS NULL';
    if ($excludeExpenseId > 0) {
        $sql .= ' AND id <> ?';
    }
    $sql .= ' ORDER BY posting_date DESC, id DESC';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    if ($excludeExpenseId > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $excludeExpenseId);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'label' => itm_expenses_format_recurrence_source_label($row),
        ];
    }
    mysqli_stmt_close($stmt);

    return $out;
}

function itm_expenses_recurrence_source_label_by_id(mysqli $conn, int $companyId, int $expenseId): string
{
    $expenseId = (int) $expenseId;
    if ($expenseId <= 0) {
        return '';
    }

    $sql = 'SELECT id, invoice_number, posting_date, description
            FROM expenses
            WHERE id = ? AND deleted_at IS NULL';
    if ($companyId > 0) {
        $sql .= ' AND company_id = ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }

    if ($companyId > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $expenseId, $companyId);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $expenseId);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        return '';
    }

    return itm_expenses_format_recurrence_source_label($row);
}

/**
 * Run due recurring expense templates for one company (CLI).
 *
 * @return array{created:int, skipped:int, errors:list<string>}
 */
function itm_expense_recurrence_run_for_company(mysqli $conn, int $companyId, int $employeeId): array
{
    $created = 0;
    $skipped = 0;
    $errors = [];
    if ($companyId <= 0) {
        return ['created' => 0, 'skipped' => 0, 'errors' => ['Invalid company.']];
    }
    $today = date('Y-m-d');
    $sql = 'SELECT e.*, er.code AS recurrence_code FROM expenses e INNER JOIN expense_recurrence er ON er.id = e.expense_recurrence_id AND er.company_id = e.company_id WHERE e.company_id = ? AND e.is_recursive = 1 AND e.deleted_at IS NULL AND e.next_run_date IS NOT NULL AND e.next_run_date <= ?';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['created' => 0, 'skipped' => 0, 'errors' => [mysqli_error($conn)]];
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $today);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $templates = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $templates[] = $row;
    }
    mysqli_stmt_close($stmt);

    foreach ($templates as $template) {
        $templateId = (int) ($template['id'] ?? 0);
        $runDate = (string) ($template['next_run_date'] ?? '');
        $endDate = (string) ($template['recurrence_end_date'] ?? '');
        if ($endDate !== '' && $runDate > $endDate) {
            $skipped++;
            continue;
        }
        $dupSql = 'SELECT id FROM expenses WHERE company_id = ? AND recurrence_source_expense_id = ? AND posting_date = ? AND deleted_at IS NULL LIMIT 1';
        $dup = mysqli_prepare($conn, $dupSql);
        if ($dup) {
            mysqli_stmt_bind_param($dup, 'iis', $companyId, $templateId, $runDate);
            mysqli_stmt_execute($dup);
            $dupRes = mysqli_stmt_get_result($dup);
            $exists = $dupRes && mysqli_fetch_assoc($dupRes);
            mysqli_stmt_close($dup);
            if ($exists) {
                $skipped++;
                continue;
            }
        }
        $child = $template;
        unset($child['recurrence_code']);
        $child['is_recursive'] = 0;
        $child['recurrence_source_expense_id'] = $templateId;
        $child['expense_recurrence_id'] = null;
        $child['next_run_date'] = null;
        $child['recurrence_end_date'] = null;
        $child['posting_date'] = $runDate;
        $child['date'] = $runDate;
        $child['invoice_date'] = $runDate;
        $child['created_by'] = $employeeId > 0 ? $employeeId : ($template['created_by'] ?? null);

        $insertCols = [];
        $placeholders = [];
        $types = '';
        $params = [];
        $skipCols = ['id', 'created_at', 'updated_at', 'updated_by', 'deleted_by', 'deleted_at'];
        foreach ($child as $col => $val) {
            if (in_array($col, $skipCols, true)) {
                continue;
            }
            if (!itm_is_safe_identifier($col)) {
                continue;
            }
            $insertCols[] = '`' . $col . '`';
            $placeholders[] = '?';
            if ($val === null || $val === '') {
                $types .= 's';
                $params[] = null;
            } elseif (is_int($val) || (is_string($val) && ctype_digit($val))) {
                $types .= 'i';
                $params[] = (int) $val;
            } elseif (is_float($val) || is_numeric($val)) {
                $types .= 'd';
                $params[] = (float) $val;
            } else {
                $types .= 's';
                $params[] = (string) $val;
            }
        }
        $insSql = 'INSERT INTO expenses (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $placeholders) . ')';
        $ins = mysqli_prepare($conn, $insSql);
        if (!$ins) {
            $errors[] = 'Insert failed for template ' . $templateId;
            continue;
        }
        $bind = [$types];
        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }
        call_user_func_array([$ins, 'bind_param'], $bind);
        if (!mysqli_stmt_execute($ins)) {
            $errors[] = 'Template ' . $templateId . ': ' . mysqli_stmt_error($ins);
            mysqli_stmt_close($ins);
            continue;
        }
        mysqli_stmt_close($ins);
        $created++;

        $code = (string) ($template['recurrence_code'] ?? '');
        $next = itm_expense_recurrence_advance_date($code, $runDate);
        if ($next === null) {
            $errors[] = 'Could not advance recurrence for template ' . $templateId;
            continue;
        }
        $upd = mysqli_prepare($conn, 'UPDATE expenses SET next_run_date = ? WHERE id = ? AND company_id = ? LIMIT 1');
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'sii', $next, $templateId, $companyId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }
    }

    return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
}
