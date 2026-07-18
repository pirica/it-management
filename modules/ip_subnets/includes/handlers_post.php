<?php
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
        $importErrors = [];
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
                if ($rawValue === '' || strcasecmp($rawValue, 'null') === 0 || in_array($rawValue, ['-', '–', '—', '—'], true)) {
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

            $importPost = [];
            if (($crud_table ?? '') === 'ip_subnets') {
                foreach ($importColumns as $idx => $columnMeta) {
                    if (!is_array($columnMeta)) {
                        continue;
                    }
                    $importPost[(string)$columnMeta['Field']] = trim((string)($sourceRow[$idx] ?? ''));
                }
                if (function_exists('itm_ipam_apply_derived_sql_to_data')) {
                    itm_ipam_apply_derived_sql_to_data($conn, $crud_table, $rowData, $importPost);
                }
                $rowErrors = [];
                if (function_exists('itm_ipam_assert_subnet_save_ready')) {
                    itm_ipam_assert_subnet_save_ready($rowData, $importPost, $rowErrors);
                }
                if (!empty($rowErrors)) {
                    if (count($importErrors) < 5) {
                        $importErrors[] = 'Row ' . ($rowIndex + 1) . ': ' . implode(' ', array_map('strval', $rowErrors));
                    }
                    continue;
                }
            }

            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = (string)$col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $rowData[$name] ?? 'NULL';
            }
            if (($crud_table ?? '') === 'ip_subnets') {
                foreach (['network_ip', 'prefix_length'] as $derivedField) {
                    if (isset($rowData[$derivedField])) {
                        $fields[] = cr_escape_identifier($derivedField);
                        $values[] = $rowData[$derivedField];
                    }
                }
            }

            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
            $dbErrorCode = 0; $dbErrorMessage = '';
            if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
                $insertedRows++;
            } elseif (count($importErrors) < 5) {
                $importErrors[] = 'Row ' . ($rowIndex + 1) . ': ' . itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
            }
        }

        echo json_encode(['ok' => true, 'inserted' => $insertedRows, 'failed' => count($importErrors), 'errors' => $importErrors]);
        exit;
    }

// HANDLE BULK DELETIONS (from POST)
if ($crud_action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }

    cr_require_valid_csrf_token();

    $bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
    $dbErrorCode = 0;
    $dbErrorMessage = '';

    if ($bulkAction === 'clear_table') {
        $where = '';
        if ($hasCompany && $company_id > 0) { $where = ' WHERE company_id=' . (int)$company_id; }
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
        if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
        header('Location: ' . $listUrl);
        exit;
    }

    if ($bulkAction === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) { $ids = []; }
        $idList = [];
        foreach ($ids as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) { $idList[$id] = $id; }
        }

        if (!empty($idList)) {
            $where = ' WHERE id IN (' . implode(',', array_values($idList)) . ')';
            if ($hasCompany && $company_id > 0) { $where .= ' AND company_id=' . (int)$company_id; }
            $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
            if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
                $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
            }
        } else {
            $_SESSION['crud_error'] = 'No records selected for deletion.';
        }
        header('Location: ' . $listUrl);
        exit;
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $where = ' WHERE id=' . $id;
        if ($hasCompany && $company_id > 0) { $where .= ' AND company_id=' . (int)$company_id; }
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1';
        if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
    }
    header('Location: ' . $listUrl);
    exit;
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($crud_action, ['view', 'index', 'list_all'], true)
    && isset($_POST['generate_subnet_ips'])
) {
    cr_require_valid_csrf_token();
    $subnetId = (int)($_POST['subnet_id'] ?? 0);
    $createdCount = 0;
    $skippedCount = 0;
    $generateError = '';

    if ($subnetId > 0 && function_exists('itm_ipam_bulk_generate_subnet_ips')) {
        $ok = itm_ipam_bulk_generate_subnet_ips($conn, (int)$company_id, $subnetId, $createdCount, $skippedCount, $generateError);
        if ($ok) {
            $_SESSION['crud_success'] = 'Generated ' . (int)$createdCount . ' IP(s); skipped ' . (int)$skippedCount . ' existing.';
        } else {
            $_SESSION['crud_error'] = $generateError !== '' ? $generateError : 'Unable to generate subnet IPs.';
        }
    } else {
        $_SESSION['crud_error'] = 'Invalid subnet selected for bulk generate.';
    }

    if ($crud_action === 'view') {
        header('Location: ' . $modulePath . '/view.php?id=' . max(1, $subnetId));
    } else {
        header('Location: ' . $listUrl);
    }
    exit;
}

$errors = [];
if (!empty($_SESSION['crud_error'])) {
    $errors[] = (string)$_SESSION['crud_error'];
    unset($_SESSION['crud_error']);
}
$crudSuccessMessage = '';
if (!empty($_SESSION['crud_success'])) {
    $crudSuccessMessage = (string)$_SESSION['crud_success'];
    unset($_SESSION['crud_success']);
}
$data = [];
foreach ($fieldColumns as $col) {
    $data[$col['Field']] = '';
}

// HANDLE FETCH FOR EDIT/VIEW
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (in_array($crud_action, ['edit', 'view'], true) && $editId > 0) {
    $where = ' WHERE id=' . $editId;
    if ($hasCompany && $company_id > 0) { $where .= ' AND company_id=' . (int)$company_id; }
    $q = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1');
    $data = ($q && mysqli_num_rows($q) === 1) ? mysqli_fetch_assoc($q) : [];
    if (!$data) { $errors[] = 'Record not found.'; }
}

// HANDLE FORM SUBMISSION (CREATE/EDIT)

// Handle sample data seeding for empty companies in list view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && isset($_POST['add_sample_data'])) {
    cr_require_valid_csrf_token();

    if (!$hasCompany || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Sample data requires an active company.';
        header('Location: ' . $listUrl);
        exit;
    }

    $where = ' WHERE company_id=' . (int)$company_id;
    $countSql = 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where;
    $countResult = mysqli_query($conn, $countSql);
    $existingRows = 0;
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $existingRows = (int)($countRow['total_rows'] ?? 0);
    }

    if ($existingRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when no records exist.';
        header('Location: ' . $listUrl);
        exit;
    }

    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, $crud_table, (int)$company_id, $seedError);
    if ($insertedRows <= 0 && $seedError !== '') {
        $_SESSION['crud_error'] = $seedError;
    }

    header('Location: ' . $listUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    cr_require_valid_csrf_token();

    if (function_exists('itm_ipam_prepare_post_before_crud')) {
        itm_ipam_prepare_post_before_crud($conn, $crud_table, (int)$company_id, $_POST, $crud_action, (int)$editId, $errors);
    }

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = (bool)preg_match('/^tinyint(\(\d+\))?/i', (string)$col['Type']);
        
        // Booleans (checkboxes)
        if ($isTinyInt) {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            $sqlValues[$name] = (string) (int) $data[$name];
            continue;
        }

        // Automatic company scoping
        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int)$company_id;
            $sqlValues[$name] = (string) (int) $company_id;
            continue;
        }

        if (preg_match('/(_by|_by_user_id)$/', (string)$name)) {
            $userValue = trim((string)($_POST[$name] ?? ''));
            $data[$name] = ($userValue === '') ? 'NULL' : (string)(int)$userValue;
            continue;
        }

        // Foreign keys with inline addition capability
        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = '';
                $sqlValues[$name] = 'NULL';
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                // Inline insertion of a missing reference record.
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];
                $newValueEsc = mysqli_real_escape_string($conn, $newValueRaw);

                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "='" . $newValueEsc . "'";
                if (in_array('company_id', $available, true) && $company_id > 0) { $findSql .= ' AND company_id=' . (int)$company_id; }
                $findSql .= ' LIMIT 1';
                $existing = mysqli_query($conn, $findSql);
                if ($existing && mysqli_num_rows($existing) > 0) {
                    $row = mysqli_fetch_assoc($existing);
                    $data[$name] = (string) (int) $row['id'];
                    $sqlValues[$name] = (string) (int) $row['id'];
                } else {
                    $insertFields = [cr_escape_identifier($labelCol)];
                    $insertValues = ["'" . $newValueEsc . "'"];
                    if (in_array('company_id', $available, true) && $company_id > 0) {
                        $insertFields[] = '`company_id`';
                        $insertValues[] = (string)(int)$company_id;
                    }
                    $insertSql = 'INSERT INTO ' . cr_escape_identifier($fkTable)
                        . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertValues) . ')';
                    $dbErrorCode = 0;
                    $dbErrorMessage = '';
                    if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                        $resolvedId = (string) (int) mysqli_insert_id($conn);
                        $data[$name] = $resolvedId;
                        $sqlValues[$name] = $resolvedId;
                    } else {
                        $errors[] = 'Could not add related value for ' . $name . '. ' . itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                        $data[$name] = '';
                        $sqlValues[$name] = 'NULL';
                    }
                }
                continue;
            }
        }

        // Generic field handling
        $value = $_POST[$name] ?? null;
        if ($value === '' || $value === null) {
            $data[$name] = '';
            $sqlValues[$name] = 'NULL';
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $normalizedNumeric = null;
            $numericError = '';
            if (!cr_validate_numeric_value($value, $col, $name, $normalizedNumeric, $numericError)) {
                $errors[] = $numericError;
                $data[$name] = '';
                $sqlValues[$name] = 'NULL';
            } else {
                $data[$name] = $normalizedNumeric;
                $sqlValues[$name] = $normalizedNumeric;
            }
        } else {
            $data[$name] = (string) $value;
            $sqlValues[$name] = "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }

    if (function_exists('itm_ipam_apply_derived_sql_to_data')) {
        itm_ipam_apply_derived_sql_to_data($conn, $crud_table, $data, $_POST);
    }

    if (($crud_table ?? '') === 'ip_subnets' && function_exists('itm_ipam_assert_subnet_save_ready')) {
        itm_ipam_assert_subnet_save_ready($data, $_POST, $errors);
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            $fields = []; $values = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $data[$name] ?? 'NULL';
            }
            if (($crud_table ?? '') === 'ip_subnets') {
                $includedFields = array_map(static function ($col) {
                    return (string)$col['Field'];
                }, $fieldColumns);
                foreach (['network_ip', 'prefix_length'] as $derivedField) {
                    if (!in_array($derivedField, $includedFields, true) && isset($data[$derivedField])) {
                        $fields[] = cr_escape_identifier($derivedField);
                        $values[] = $data[$derivedField];
                    }
                }
            }
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
        } else {
            $sets = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $sets[] = cr_escape_identifier($name) . '=' . ($data[$name] ?? 'NULL');
            }
            if (($crud_table ?? '') === 'ip_subnets') {
                foreach (['network_ip', 'prefix_length'] as $derivedField) {
                    if (isset($data[$derivedField])) {
                        $sets[] = cr_escape_identifier($derivedField) . '=' . $data[$derivedField];
                    }
                }
            }
            $where = ' WHERE id=' . $editId;
            if ($hasCompany && $company_id > 0) { $where .= ' AND company_id=' . (int)$company_id; }
            $sql = 'UPDATE ' . cr_escape_identifier($crud_table) . ' SET ' . implode(',', $sets) . $where . ' LIMIT 1';
        }

        $dbErrorCode = 0; $dbErrorMessage = '';
        if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
            $savedId = ($crud_action === 'create') ? (int)mysqli_insert_id($conn) : (int)$editId;
            if (function_exists('itm_ipam_after_crud_save')) {
                itm_ipam_after_crud_save($conn, $crud_table, (int)$company_id, $savedId, $_POST);
            }
            header('Location: ' . $listUrl);
            exit;
        }
        $errors[] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
    }
}
