<?php
// Inline notes save from IP list (AJAX).
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($crud_action, ['index', 'list_all'], true)
    && isset($_POST['inline_notes_save'])
) {
    header('Content-Type: application/json');
    cr_require_valid_csrf_token();

    if (!$hasCompany || $company_id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Active company is required.']);
        exit;
    }

    $inlineAddressId = (int)($_POST['id'] ?? 0);
    $inlineNotes = trim((string)($_POST['notes'] ?? ''));
    if ($inlineAddressId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid IP address record.']);
        exit;
    }

    $inlineSaved = function_exists('itm_ipam_save_address_notes')
        && itm_ipam_save_address_notes($conn, (int)$company_id, $inlineAddressId, $inlineNotes);
    if (!$inlineSaved) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Unable to save notes.']);
        exit;
    }

    echo json_encode(['ok' => true, 'notes' => $inlineNotes]);
    exit;
}

// Handle Excel/CSV database import requests from table-tools.js.
$requestContentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
$isJsonImportRequest = false;
$rawBody = '';
$jsonBody = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true)) {
    $rawBody = (string)file_get_contents('php://input');
    $jsonBody = json_decode($rawBody, true);
    $hasImportRows = is_array($jsonBody) && isset($jsonBody['import_excel_rows']);

    // Why: table-tools.js may send JSON payloads with non-JSON content-type headers.
    $bodyMentionsImportRows = strpos($rawBody, '"import_excel_rows"') !== false;
    $isJsonImportRequest = strpos($requestContentType, 'application/json') !== false || $hasImportRows || $bodyMentionsImportRows;
}
if ($isJsonImportRequest) {
        header('Content-Type: application/json');

        if (!is_array($jsonBody)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON import payload.']);
            exit;
        }

        if (!isset($jsonBody['import_excel_rows'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing import rows payload.']);
            exit;
        }

        $requestToken = (string)($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($requestToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        if (!$hasCompany || $company_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Import requires an active company.']);
            exit;
        }

        $importRows = $jsonBody['import_excel_rows'];
        if (!is_array($importRows) || count($importRows) < 2) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'The uploaded file has no data rows.']);
            exit;
        }

        $headerRow = array_map('trim', array_map('strval', (array)($importRows[0] ?? [])));
        $columnKeys = [];
        foreach ($headerRow as $headerValue) {
            $columnKeys[] = strtolower(preg_replace('/\s+/', ' ', $headerValue));
        }

        $fieldByLabel = [];
        foreach ($fieldColumns as $col) {
            $fieldName = (string)$col['Field'];
            $fieldByLabel[strtolower((string)cr_humanize_field($fieldName))] = $col;
            $fieldByLabel[strtolower(str_replace('_', ' ', $fieldName))] = $col;
        }
        $fieldByLabel['id'] = null;

        $importColumns = [];
        foreach ($columnKeys as $labelKey) {
            $importColumns[] = $fieldByLabel[$labelKey] ?? null;
        }

        $insertedRows = 0;
        for ($rowIndex = 1; $rowIndex < count($importRows); $rowIndex++) {
            $sourceRow = (array)$importRows[$rowIndex];
            if (empty(array_filter($sourceRow, function ($v) { return trim((string)$v) !== ''; }))) {
                continue;
            }

            $rowData = [];
            foreach ($fieldColumns as $col) {
                $rowData[$col['Field']] = 'NULL';
            }

            foreach ($importColumns as $idx => $columnMeta) {
                if (!is_array($columnMeta)) {
                    continue;
                }

                $fieldName = (string)$columnMeta['Field'];
                $rawValue = trim((string)($sourceRow[$idx] ?? ''));
                if ($rawValue === '' || $rawValue === '—') {
                    continue;
                }

                if ($fieldName === 'company_id' || $fieldName === 'id') {
                    continue;
                }

                $isTinyInt = (bool)preg_match('/^tinyint(\(\d+\))?/i', (string)$columnMeta['Type']);
                if ($isTinyInt) {
                    $normalizedBool = strtolower($rawValue);
                    if (in_array($normalizedBool, ['1', 'active', 'yes', 'true', 'on', '✅'], true)) {
                        $rowData[$fieldName] = '1';
                    } elseif (in_array($normalizedBool, ['0', 'inactive', 'no', 'false', 'off', '❌'], true)) {
                        $rowData[$fieldName] = '0';
                    }
                    continue;
                }

                if (isset($fkMap[$fieldName])) {
                    $fk = $fkMap[$fieldName];
                    $options = cr_fk_options($conn, $fk, (int)$company_id, $fieldName);
                    $resolvedId = 0;
                    foreach ($options as $option) {
                        if (strcasecmp((string)$option['label'], $rawValue) === 0) {
                            $resolvedId = (int)$option['id'];
                            break;
                        }
                    }
                    if ($resolvedId <= 0 && ctype_digit($rawValue)) {
                        $resolvedId = (int)$rawValue;
                    }
                    $rowData[$fieldName] = $resolvedId > 0 ? (string)$resolvedId : 'NULL';
                    continue;
                }

                if (preg_match('/int|decimal|float|double/', (string)$columnMeta['Type'])) {
                    $normalizedNumeric = null; $numericError = '';
                    if (cr_validate_numeric_value($rawValue, $columnMeta, $fieldName, $normalizedNumeric, $numericError)) {
                        $rowData[$fieldName] = $normalizedNumeric;
                    }
                    continue;
                }

                $rowData[$fieldName] = "'" . mysqli_real_escape_string($conn, $rawValue) . "'";
            }

            if ($hasCompany) {
                $rowData['company_id'] = (string)(int)$company_id;
            }

            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = (string)$col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $rowData[$name] ?? 'NULL';
            }

            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
            $dbErrorCode = 0; $dbErrorMessage = '';
            if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
                $insertedRows++;
            }
        }

        echo json_encode(['ok' => true, 'inserted' => $insertedRows]);
        exit;
    }

