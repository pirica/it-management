<?php
require '../../config/config.php';

function emp_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

function emp_parse_delimited_rows($content) {
    $lines = preg_split('/\r\n|\n|\r/', trim((string)$content));
    if (!$lines || count($lines) < 2) {
        return [];
    }

    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $delimiter = str_contains($line, "\t") ? "\t" : ',';
        $rows[] = str_getcsv($line, $delimiter);
    }
    return $rows;
}

function emp_canonical_header($header) {
    $normalized = strtolower(trim((string)$header));
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = str_replace(['_', '-'], ' ', $normalized);

    $map = [
        'hilton id' => 'hilton_id',
        'employee code' => 'employee_code',
        'user name' => 'username',
        'username' => 'username',
        'display name' => 'display_name',
        'email' => 'email',
        'employee status' => 'raw_status_code',
        'status' => 'raw_status_code',
        'first name' => 'first_name',
        'last name' => 'last_name',
        'job code' => 'job_code',
        'title' => 'job_title',
        'comments' => 'comments',
        'comment' => 'comments'
    ];

    return $map[$normalized] ?? null;
}

function emp_status_id_from_raw($conn, $rawStatus) {
    $status = strtoupper(trim((string)$rawStatus));
    $name = 'Active';
    if ($status === 'I') {
        $name = 'Inactive';
    } elseif ($status === 'L') {
        $name = 'On Leave';
    } elseif ($status === 'T') {
        $name = 'Terminated';
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $q = mysqli_query($conn, "SELECT id FROM employee_statuses WHERE name='{$nameEsc}' LIMIT 1");
    if ($q && mysqli_num_rows($q) === 1) {
        $row = mysqli_fetch_assoc($q);
        return (int)$row['id'];
    }

    if (mysqli_query($conn, "INSERT INTO employee_statuses (name) VALUES ('{$nameEsc}')")) {
        return (int)mysqli_insert_id($conn);
    }

    return 1;
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'import_employees')) {
    $payload = trim((string)($_POST['import_payload'] ?? ''));
    $rows = [];

    if ($payload !== '') {
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            $rows = $decoded;
        }
    }

    if (!$rows && !empty($_POST['import_text'])) {
        $rows = emp_parse_delimited_rows((string)$_POST['import_text']);
    }

    if (!$rows || count($rows) < 2) {
        $errors[] = 'No importable rows were found. Upload an Excel/CSV file or paste tabular content.';
    } else {
        $headers = array_map('emp_canonical_header', $rows[0]);
        $validIdx = [];
        foreach ($headers as $i => $field) {
            if ($field !== null) {
                $validIdx[$i] = $field;
            }
        }

        if (!in_array('email', $headers, true) && !in_array('employee_code', $headers, true) && !in_array('hilton_id', $headers, true)) {
            $errors[] = 'Include at least one unique identifier column: Email, Employee Code, or Hilton ID.';
        }

        if (empty($errors)) {
            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach (array_slice($rows, 1) as $row) {
                $mapped = [
                    'company_id' => (int)$company_id,
                    'first_name' => '',
                    'last_name' => '',
                    'email' => '',
                    'employee_code' => '',
                    'hilton_id' => '',
                    'username' => '',
                    'display_name' => '',
                    'job_code' => '',
                    'job_title' => '',
                    'comments' => '',
                    'raw_status_code' => '',
                    'employment_status_id' => 1,
                    'active' => 1
                ];

                foreach ($validIdx as $idx => $field) {
                    $mapped[$field] = trim((string)($row[$idx] ?? ''));
                }

                if ($mapped['display_name'] === '' && ($mapped['first_name'] !== '' || $mapped['last_name'] !== '')) {
                    $mapped['display_name'] = trim($mapped['first_name'] . ' ' . $mapped['last_name']);
                }
                if ($mapped['first_name'] === '' && $mapped['display_name'] !== '') {
                    $parts = preg_split('/\s+/', $mapped['display_name']);
                    $mapped['first_name'] = $parts[0] ?? '';
                    if ($mapped['last_name'] === '') {
                        $mapped['last_name'] = trim(implode(' ', array_slice($parts, 1)));
                    }
                }

                if ($mapped['first_name'] === '' && $mapped['last_name'] === '' && $mapped['email'] === '' && $mapped['employee_code'] === '' && $mapped['hilton_id'] === '') {
                    $skipped += 1;
                    continue;
                }

                $mapped['employment_status_id'] = emp_status_id_from_raw($conn, $mapped['raw_status_code']);
                $mapped['active'] = strtoupper($mapped['raw_status_code']) === 'I' ? 0 : 1;

                $whereParts = [];
                if ($mapped['email'] !== '') {
                    $whereParts[] = "email='" . mysqli_real_escape_string($conn, $mapped['email']) . "'";
                }
                if ($mapped['employee_code'] !== '') {
                    $whereParts[] = "employee_code='" . mysqli_real_escape_string($conn, $mapped['employee_code']) . "'";
                }
                if ($mapped['hilton_id'] !== '') {
                    $whereParts[] = "hilton_id='" . mysqli_real_escape_string($conn, $mapped['hilton_id']) . "'";
                }

                if (!$whereParts) {
                    $skipped += 1;
                    continue;
                }

                $existingId = 0;
                $findSql = "SELECT id FROM employees WHERE company_id=" . (int)$company_id . " AND (" . implode(' OR ', $whereParts) . ') LIMIT 1';
                $found = mysqli_query($conn, $findSql);
                if ($found && mysqli_num_rows($found) === 1) {
                    $existingId = (int)(mysqli_fetch_assoc($found)['id'] ?? 0);
                }

                $columns = ['company_id','first_name','last_name','email','employee_code','hilton_id','username','display_name','job_code','job_title','comments','raw_status_code','employment_status_id','active'];
                $values = [];
                foreach ($columns as $col) {
                    $value = $mapped[$col] ?? null;
                    if ($value === '' || $value === null) {
                        $values[$col] = 'NULL';
                    } elseif (in_array($col, ['company_id','employment_status_id','active'], true)) {
                        $values[$col] = (string)(int)$value;
                    } else {
                        $values[$col] = "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
                    }
                }

                if ($existingId > 0) {
                    $sets = [];
                    foreach ($columns as $col) {
                        if ($col === 'company_id') {
                            continue;
                        }
                        $sets[] = emp_escape_identifier($col) . '=' . $values[$col];
                    }
                    $sql = 'UPDATE employees SET ' . implode(',', $sets) . ' WHERE id=' . $existingId . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
                    if (mysqli_query($conn, $sql)) {
                        $updated += 1;
                    }
                } else {
                    $sql = 'INSERT INTO employees (' . implode(',', array_map('emp_escape_identifier', $columns)) . ') VALUES (' . implode(',', array_values($values)) . ')';
                    if (mysqli_query($conn, $sql)) {
                        $created += 1;
                    }
                }
            }

            $messages[] = "Import complete: {$created} created, {$updated} updated, {$skipped} skipped.";
        }
    }
}

$where = ' WHERE company_id=' . (int)$company_id;
$rows = mysqli_query($conn, 'SELECT * FROM employees' . $where . ' ORDER BY id DESC LIMIT 500');
$columnsRes = mysqli_query($conn, 'SHOW COLUMNS FROM employees');
$columns = [];
while ($columnsRes && ($c = mysqli_fetch_assoc($columnsRes))) {
    $columns[] = $c['Field'];
}

$preferredOrder = ['id','hilton_id','employee_code','username','display_name','email','raw_status_code','first_name','last_name','job_code','job_title','comments','phone','department_id','location_id','employment_status_id','active'];
usort($columns, function ($a, $b) use ($preferredOrder) {
    $ia = array_search($a, $preferredOrder, true);
    $ib = array_search($b, $preferredOrder, true);
    $ia = ($ia === false) ? 999 : $ia;
    $ib = ($ib === false) ? 999 : $ib;
    return $ia <=> $ib ?: strcmp($a, $b);
});

function emp_label($field) {
    return ucwords(str_replace('_', ' ', trim((string)$field)));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?php echo sanitize($error); ?></div>
            <?php endforeach; ?>
            <?php foreach ($messages as $msg): ?>
                <div class="alert alert-success"><?php echo sanitize($msg); ?></div>
            <?php endforeach; ?>

            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
                <h1 style="margin:0;">Employees</h1>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="create.php" class="btn btn-primary">➕ New Employee</a>
                </div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <h3 style="margin-top:0;">Import from Excel / CSV</h3>
                <p style="margin-top:4px;color:#666;">Accepted headers: Hilton ID, User Name, Display Name, Email, Employee Status, First Name, Last Name, Job Code, Title.</p>
                <form method="POST" id="employeeImportForm">
                    <input type="hidden" name="action" value="import_employees">
                    <input type="hidden" name="import_payload" id="employeeImportPayload" value="">
                    <div class="form-group">
                        <label>Upload file (.xlsx, .xls, .csv)</label>
                        <input type="file" id="employeeImportFile" accept=".xlsx,.xls,.csv" />
                    </div>
                    <div class="form-group">
                        <label>Or paste tabular data</label>
                        <textarea name="import_text" id="employeeImportText" rows="5" placeholder="Paste from Excel with headers in first row"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">📥 Import Employees</button>
                    </div>
                </form>
            </div>

            <div class="card" style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <th><?php echo sanitize(emp_label($col)); ?></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <td><?php echo sanitize((string)($row[$col] ?? '')); ?></td>
                            <?php endforeach; ?>
                            <td>
                                <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">👁️</a>
                                <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this employee?');">🗑️</a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="<?php echo count($columns) + 1; ?>" style="text-align:center;">No employees found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const fileInput = document.getElementById('employeeImportFile');
    const payloadInput = document.getElementById('employeeImportPayload');
    const textInput = document.getElementById('employeeImportText');

    function parseCsv(text) {
        const rows = [];
        let row = [];
        let cell = '';
        let inQuotes = false;

        for (let i = 0; i < text.length; i += 1) {
            const char = text[i];
            const next = text[i + 1];
            if (inQuotes) {
                if (char === '"' && next === '"') {
                    cell += '"';
                    i += 1;
                } else if (char === '"') {
                    inQuotes = false;
                } else {
                    cell += char;
                }
            } else if (char === '"') {
                inQuotes = true;
            } else if (char === ',') {
                row.push(cell.trim());
                cell = '';
            } else if (char === '\n' || char === '\r') {
                if (char === '\r' && next === '\n') i += 1;
                row.push(cell.trim());
                if (row.some((v) => v !== '')) rows.push(row);
                row = [];
                cell = '';
            } else {
                cell += char;
            }
        }

        if (cell.length || row.length) {
            row.push(cell.trim());
            if (row.some((v) => v !== '')) rows.push(row);
        }
        return rows;
    }

    if (!fileInput) return;

    fileInput.addEventListener('change', () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            payloadInput.value = '';
            return;
        }

        const extension = (file.name.split('.').pop() || '').toLowerCase();
        const reader = new FileReader();

        if (extension === 'csv') {
            reader.onload = () => {
                const text = typeof reader.result === 'string' ? reader.result : '';
                payloadInput.value = JSON.stringify(parseCsv(text));
                textInput.value = '';
            };
            reader.readAsText(file);
            return;
        }

        if (window.XLSX && (extension === 'xlsx' || extension === 'xls')) {
            reader.onload = () => {
                const bytes = new Uint8Array(reader.result);
                const workbook = window.XLSX.read(bytes, { type: 'array' });
                const firstSheet = workbook.SheetNames[0];
                const rows = window.XLSX.utils.sheet_to_json(workbook.Sheets[firstSheet], { header: 1, defval: '' });
                payloadInput.value = JSON.stringify(rows);
                textInput.value = '';
            };
            reader.readAsArrayBuffer(file);
            return;
        }

        alert('Unsupported file type. Please use .xlsx, .xls, or .csv');
        fileInput.value = '';
    });
})();
</script>
</body>
</html>
