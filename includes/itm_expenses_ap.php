<?php
/**
 * Why: AP field normalization for expenses (RootFi-shaped import headers, EUR defaults, budget actuals).
 * No inbound RootFi/webhook sync — platform_* DB columns elsewhere are optional manual metadata only.
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
