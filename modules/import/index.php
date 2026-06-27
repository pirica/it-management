<?php
/**
 * Bulk Import Module
 *
 * Provides a centralized interface for importing Assets and Employees from CSV/Excel.
 */

require '../../config/config.php';
// Admin access usually required for bulk imports
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);

$messages = [];
$errors = [];
$csrfToken = itm_get_csrf_token();

/**
 * Resolves a label to an ID in a lookup table, creating it if it doesn't exist.
 * Scopes by company_id if the table supports it.
 */
function resolve_lookup_id($conn, $table, $column, $value, $company_id) {
    $value = trim((string)$value);
    if ($value === '' || strtolower($value) === 'none' || strtolower($value) === 'n/a') return null;

    $escapedValue = mysqli_real_escape_string($conn, $value);

    // Check if table has company_id column for scoping
    static $table_columns_cache = [];
    if (!isset($table_columns_cache[$table])) {
        $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE 'company_id'");
        $table_columns_cache[$table] = ($colRes && mysqli_num_rows($colRes) > 0);
    }
    $hasCompanyId = $table_columns_cache[$table];

    $sql = "SELECT id FROM `{$table}` WHERE `{$column}` = '{$escapedValue}'";
    if ($hasCompanyId) {
        $sql .= " AND company_id = " . (int)$company_id;
    }
    $sql .= " LIMIT 1";

    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return (int)$row['id'];
    }

    // Create new entry if not found
    $insertSql = "INSERT INTO `{$table}` (`{$column}`" . ($hasCompanyId ? ", company_id" : "") . ") VALUES ('{$escapedValue}'" . ($hasCompanyId ? ", " . (int)$company_id : "") . ")";
    if (mysqli_query($conn, $insertSql)) {
        return (int)mysqli_insert_id($conn);
    }

    return null;
}

// Handle template downloads
if (isset($_GET['download'])) {
    $template = $_GET['download'];
    if ($template === 'assets') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="template_assets.csv"');
        echo "name,brand,model,serial_number,category,purchase_date,purchase_cost\n";
        echo "Dell Laptop,Dell Technologies,Latitude 5520,SN123456,Workstation,2024-01-15,1200.00\n";
        exit;
    } elseif ($template === 'employees') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="template_employees.csv"');
        echo "full_name,email,username,phone,job_title,employee_id\n";
        echo "John Smith,john@company.com,jsmith,+1234567890,IT Manager,EMP001\n";
        exit;
    }
}

// Fetch roles for dropdown
$roles = [];
$rolesRes = mysqli_query($conn, "SELECT id, name FROM employee_roles WHERE company_id = " . (int)$company_id . " AND active = 1 ORDER BY name ASC");
while ($rolesRes && ($row = mysqli_fetch_assoc($rolesRes))) {
    $roles[] = $row;
}

// Fetch departments for dropdown
$departments = [];
$deptRes = mysqli_query($conn, "SELECT id, name FROM departments WHERE company_id = " . (int)$company_id . " AND active = 1 ORDER BY name ASC");
while ($deptRes && ($row = mysqli_fetch_assoc($deptRes))) {
    $departments[] = $row;
}

// Import logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    itm_require_post_csrf();
    $action = $_POST['action'];
    $skipErrors = isset($_POST['skip_errors']);

    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['import_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if ($handle) {
            $headers = fgetcsv($handle);
            if ($headers) {
                $headers = array_map('trim', $headers);
                $rowCount = 0;
                $successCount = 0;
                $errorCount = 0;

                if ($action === 'import_assets') {
                    $activeStatusId = resolve_lookup_id($conn, 'equipment_statuses', 'name', 'Active', $company_id);

                    while (($data = fgetcsv($handle)) !== false) {
                        $rowCount++;
                        if (empty($data) || (count($data) === 1 && $data[0] === null)) continue;
                        $rowData = array_combine($headers, array_pad($data, count($headers), ''));

                        $name = trim($rowData['name'] ?? '');
                        if ($name === '') {
                            $errorCount++;
                            if (!$skipErrors) { $errors[] = "Row $rowCount: Name is required."; break; }
                            continue;
                        }

                        $brandId = resolve_lookup_id($conn, 'manufacturers', 'name', $rowData['brand'] ?? 'Unknown', $company_id);
                        $categoryId = resolve_lookup_id($conn, 'equipment_types', 'name', $rowData['category'] ?? 'Other', $company_id);
                        $model = trim($rowData['model'] ?? '');
                        $serial = trim($rowData['serial_number'] ?? '');
                        $pDate = !empty($rowData['purchase_date']) ? $rowData['purchase_date'] : null;
                        $pCost = !empty($rowData['purchase_cost']) ? (float)$rowData['purchase_cost'] : 0;

                        $stmt = $conn->prepare("INSERT INTO equipment (company_id, name, manufacturer_id, equipment_type_id, model, serial_number, status_id, purchase_date, purchase_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isiiisiss", $company_id, $name, $brandId, $categoryId, $model, $serial, $activeStatusId, $pDate, $pCost);

                        if ($stmt->execute()) {
                            $successCount++;
                        } else {
                            $errorCount++;
                            if (!$skipErrors) { $errors[] = "Row $rowCount: " . $stmt->error; break; }
                        }
                    }
                    $messages[] = "Successfully imported $successCount assets. $errorCount errors.";

                } elseif ($action === 'import_employees') {
                    $defaultRoleId = !empty($_POST['default_role']) ? (int)$_POST['default_role'] : null;
                    $defaultDeptId = !empty($_POST['default_dept']) ? (int)$_POST['default_dept'] : null;
                    $activeStatusId = resolve_lookup_id($conn, 'employee_statuses', 'name', 'Active', $company_id);

                    while (($data = fgetcsv($handle)) !== false) {
                        $rowCount++;
                        if (empty($data) || (count($data) === 1 && $data[0] === null)) continue;
                        $rowData = array_combine($headers, array_pad($data, count($headers), ''));

                        $fullName = trim($rowData['full_name'] ?? '');
                        $email = trim($rowData['email'] ?? '');

                        if ($fullName === '' || $email === '') {
                            $errorCount++;
                            if (!$skipErrors) { $errors[] = "Row $rowCount: Full Name and Email are required."; break; }
                            continue;
                        }

                        $parts = explode(' ', $fullName, 2);
                        $firstName = $parts[0];
                        $lastName = $parts[1] ?? '';

                        $username = !empty($rowData['username']) ? trim($rowData['username']) : strtolower(str_replace(' ', '.', $fullName));
                        $phone = trim($rowData['phone'] ?? '');
                        $jobTitle = trim($rowData['job_title'] ?? '');
                        $empCode = trim($rowData['employee_id'] ?? '');

                        $posId = null;
                        if ($jobTitle !== '') {
                            $posId = resolve_lookup_id($conn, 'employee_positions', 'name', $jobTitle, $company_id);
                        }

                        $password = password_hash('Welcome123!', PASSWORD_DEFAULT);

                        $stmt = $conn->prepare("INSERT INTO employees (company_id, first_name, last_name, display_name, work_email, username, mobile_phone, employee_code, department_id, employee_position_id, role_id, employment_status_id, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssssssiiiis", $company_id, $firstName, $lastName, $fullName, $email, $username, $phone, $empCode, $defaultDeptId, $posId, $defaultRoleId, $activeStatusId, $password);

                        if ($stmt->execute()) {
                            $successCount++;
                        } else {
                            $errorCount++;
                            if (!$skipErrors) { $errors[] = "Row $rowCount: " . $stmt->error; break; }
                        }
                    }
                    $messages[] = "Successfully imported $successCount employees. $errorCount errors.";
                }
            }
            fclose($handle);
        } else {
            $errors[] = "Could not open the uploaded file.";
        }
    } else {
        $errors[] = "Please upload a valid CSV file.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .import-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 992px) {
            .import-grid {
                grid-template-columns: 1fr;
            }
        }
        .info-alert {
            background-color: #e1f5fe;
            border-left: 4px solid #03a9f4;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            color: #01579b;
            font-size: 14px;
        }
        .template-btn {
            margin-bottom: 20px;
            display: inline-block;
            text-decoration: none;
            color: #2e7d32;
            background: #fff;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #2e7d32;
            font-size: 14px;
            font-weight: 500;
        }
        .template-btn:hover {
            background: #f1f8e9;
        }
        .csv-reference-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 992px) {
            .csv-reference-grid {
                grid-template-columns: 1fr;
            }
        }
        .required-badge {
            background-color: #ffebee;
            color: #c62828;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .card-header h3 {
            margin: 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-import-assets {
            width:100%; padding: 12px; background-color: #0969da; border-color: #0969da;
        }
        .btn-import-employees {
            width:100%; padding: 12px; background-color: #2e7d32; border-color: #2e7d32;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="Bulk Import"><span style="margin-right: 10px;">📥</span> Bulk Import</h1>

            <?php foreach ($messages as $msg): ?>
                <div class="alert alert-success"><?= sanitize($msg) ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger"><?= sanitize($err) ?></div>
            <?php endforeach; ?>

            <div class="import-grid">
                <!-- Import Assets Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><span style="color: #0969da;">📁</span> Import Assets (CSV)</h3>
                    </div>
                    <div class="card-body" style="padding: 20px;">
                        <div class="info-alert">
                            <span>ℹ️</span>
                            <p style="margin:0;">Upload a CSV file with asset data. Download the template below to see the required format.</p>
                        </div>

                        <a href="?download=assets" class="template-btn" style="color: #0969da; border-color: #0969da;">📥 Download Asset CSV Template</a>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="import_assets">

                            <div class="form-group">
                                <label>CSV File <span style="color:red;">*</span></label>
                                <input type="file" name="import_file" accept=".csv" required>
                                <small style="display:block; margin-top:5px; color:#666;">Max 5MB. Must have headers matching the template.</small>
                            </div>

                            <div class="form-group">
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="skip_errors" value="1">
                                    <span>Skip rows with errors and continue <span class="itm-check-indicator" aria-hidden="true">❌</span></span>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary btn-import-assets">📤 Import Assets</button>
                        </form>
                    </div>
                </div>

                <!-- Import Employees Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><span style="color: #2e7d32;">👥</span> Import Employees (CSV)</h3>
                    </div>
                    <div class="card-body" style="padding: 20px;">
                        <div class="info-alert" style="background-color: #e8f5e9; border-color: #4caf50; color: #1b5e20;">
                            <span>ℹ️</span>
                            <p style="margin:0;">Import employees with role and department assignments. Passwords will be auto-generated.</p>
                        </div>

                        <a href="?download=employees" class="template-btn">📥 Download Employee CSV Template</a>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="action" value="import_employees">

                            <div class="form-group">
                                <label>CSV File <span style="color:red;">*</span></label>
                                <input type="file" name="import_file" accept=".csv" required>
                            </div>

                            <div class="form-group">
                                <label>Default Role for New Users</label>
                                <select name="default_role">
                                    <option value="">Administrator</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= (int)$role['id'] ?>"><?= sanitize($role['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Default Department</label>
                                <select name="default_dept">
                                    <option value="">None</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= (int)$dept['id'] ?>"><?= sanitize($dept['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="skip_errors" value="1">
                                    <span>Skip rows with errors and continue <span class="itm-check-indicator" aria-hidden="true">❌</span></span>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary btn-import-employees">📤 Import Employees</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- CSV Format Reference -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 CSV Format Reference</h3>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <div class="csv-reference-grid">
                        <!-- Assets Columns -->
                        <div>
                            <h4>Assets CSV Columns</h4>
                            <table class="table-sm">
                                <thead>
                                    <tr>
                                        <th>COLUMN</th>
                                        <th>REQUIRED</th>
                                        <th>EXAMPLE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>name</td><td><span class="required-badge">Yes</span></td><td>Dell Laptop</td></tr>
                                    <tr><td>brand</td><td>No</td><td>Dell Technologies</td></tr>
                                    <tr><td>model</td><td>No</td><td>Latitude 5520</td></tr>
                                    <tr><td>serial_number</td><td>No</td><td>SN123456</td></tr>
                                    <tr><td>category</td><td>No</td><td>Workstation</td></tr>
                                    <tr><td>purchase_date</td><td>No</td><td>2024-01-15</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Employees Columns -->
                        <div>
                            <h4>Employees CSV Columns</h4>
                            <table class="table-sm">
                                <thead>
                                    <tr>
                                        <th>COLUMN</th>
                                        <th>REQUIRED</th>
                                        <th>EXAMPLE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td>full_name</td><td><span class="required-badge">Yes</span></td><td>John Smith</td></tr>
                                    <tr><td>email</td><td><span class="required-badge">Yes</span></td><td>john@company.com</td></tr>
                                    <tr><td>username</td><td>No</td><td>jsmith</td></tr>
                                    <tr><td>phone</td><td>No</td><td>+1234567890</td></tr>
                                    <tr><td>job_title</td><td>No</td><td>IT Manager</td></tr>
                                    <tr><td>employee_id</td><td>No</td><td>EMP001</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script>
    document.querySelectorAll('.itm-checkbox-control input').forEach(cb => {
        cb.addEventListener('change', function() {
            const indicator = this.parentNode.querySelector('.itm-check-indicator');
            if (indicator) {
                indicator.textContent = this.checked ? '✅' : '❌';
            }
        });
    });
</script>
</body>
</html>
