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

// Handle template downloads
if (isset($_GET['download'])) {
    $template = $_GET['download'];
    if ($template === 'assets') {
        $file = __DIR__ . '/asset_template.xlsx';
        if (file_exists($file)) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="asset_template.xlsx"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
    } elseif ($template === 'employees') {
        $file = __DIR__ . '/employee_template.xlsx';
        if (file_exists($file)) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="employee_template.xlsx"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
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

// Import logic (JSON handle for AJAX imports - now handles BOTH CSV and XLSX)
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        $action = (string)($itmImportJsonBody['action'] ?? '');
        $tableName = ($action === 'import_assets') ? 'equipment' : (($action === 'import_employees') ? 'employees' : '');
        if ($tableName !== '') {
            itm_handle_json_table_import($conn, $tableName, (int)($company_id ?? 0));
        }
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

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Successfully imported data.</div>
            <?php endif; ?>

            <div class="import-grid">
                <!-- Import Assets Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><span style="color: #0969da;">📁</span> Import Assets (Excel/CSV)</h3>
                    </div>
                    <div class="card-body" style="padding: 20px;">
                        <div class="info-alert">
                            <span>ℹ️</span>
                            <p style="margin:0;">Upload an Excel or CSV file with asset data. Download the template below to see the required format.</p>
                        </div>

                        <a href="?download=assets" class="template-btn" style="color: #0969da; border-color: #0969da;">📥 Download Asset Excel Template</a>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="company_id" value="<?= (int)($company_id ?? 0) ?>">
                            <input type="hidden" name="action" value="import_assets">

                            <div class="form-group">
                                <label>File <span style="color:red;">*</span></label>
                                <div class="itm-photo-upload-target" style="padding: 30px; border: 2px dashed #d0d7de; border-radius: 6px; text-align: center; cursor: pointer; background: #f6f8fa; transition: background 0.2s;">
                                    <input type="file" name="import_file" accept=".csv, .xlsx" required style="display: none;">
                                    <div class="itm-dropzone-hint">
                                        <span style="font-size: 2rem; display: block; margin-bottom: 10px;">📄</span>
                                        <strong>Click to select</strong> or drag and drop your file here
                                    </div>
                                    <div class="itm-dropzone-hint-secondary" style="margin-top: 8px; font-size: 0.85rem; color: #57606a;">
                                        Only .csv and .xlsx files are supported
                                    </div>
                                </div>
                                <small style="display:block; margin-top:5px; color:#666;">Max 5MB. Must have headers matching the template. <strong style="color: #c62828;">Bold red headers</strong> in Excel template are required.</small>
                            </div>

                            <button type="button" class="btn btn-primary btn-import-assets" data-action="import_assets">📤 Import Assets</button>
                        </form>
                    </div>
                </div>

                <!-- Import Employees Card -->
                <div class="card">
                    <div class="card-header">
                        <h3><span style="color: #2e7d32;">👥</span> Import Employees (Excel/CSV)</h3>
                    </div>
                    <div class="card-body" style="padding: 20px;">
                        <div class="info-alert" style="background-color: #e8f5e9; border-color: #4caf50; color: #1b5e20;">
                            <span>ℹ️</span>
                            <p style="margin:0;">Import employees with role and department assignments. Missing Departments and Positions will be created automatically.</p>
                        </div>

                        <a href="?download=employees" class="template-btn">📥 Download Employee Excel Template</a>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="company_id" value="<?= (int)($company_id ?? 0) ?>">
                            <input type="hidden" name="action" value="import_employees">

                            <div class="form-group">
                                <label>File <span style="color:red;">*</span></label>
                                <div class="itm-photo-upload-target" style="padding: 30px; border: 2px dashed #d0d7de; border-radius: 6px; text-align: center; cursor: pointer; background: #f6f8fa; transition: background 0.2s;">
                                    <input type="file" name="import_file" accept=".csv, .xlsx" required style="display: none;">
                                    <div class="itm-dropzone-hint">
                                        <span style="font-size: 2rem; display: block; margin-bottom: 10px;">👥</span>
                                        <strong>Click to select</strong> or drag and drop your file here
                                    </div>
                                    <div class="itm-dropzone-hint-secondary" style="margin-top: 8px; font-size: 0.85rem; color: #57606a;">
                                        Only .csv and .xlsx files are supported
                                    </div>
                                </div>
                                <small style="display:block; margin-top:5px; color:#666;"><strong style="color: #c62828;">Bold red headers</strong> in Excel template are required.</small>
                            </div>

                            <button type="button" class="btn btn-primary btn-import-employees" data-action="import_employees">📤 Import Employees</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- CSV Format Reference -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 Import Format Reference</h3>
                </div>
                <div class="card-body" style="padding: 20px;">
                    <div class="csv-reference-grid">
                        <!-- Assets Columns -->
                        <div>
                            <h4>Assets Import Columns</h4>
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
                            <h4>Employees Import Columns</h4>
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
<script src="../../js/vendor/xlsx.full.min.js"></script>
<script src="../../js/itm-upload-helper.js"></script>
<script>
    /**
     * Initialize drag-and-drop
     */
    itmUploadHelper.setupByClass('.itm-photo-upload-target');

    /**
     * Update dropzone text when file is selected
     */
    document.querySelectorAll('.itm-photo-upload-target input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            const target = this.closest('.itm-photo-upload-target');
            const hint = target.querySelector('.itm-dropzone-hint');
            if (this.files && this.files.length > 0) {
                hint.innerHTML = '<strong>Selected:</strong> ' + this.files[0].name;
                target.style.borderColor = '#2e7d32';
                target.style.background = '#f1f8e9';
            }
        });
    });

    /**
     * AJAX Excel/CSV Import handler
     */
    document.querySelectorAll('.btn-import-assets, .btn-import-employees').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const form = this.closest('form');
            const fileInput = form.querySelector('input[type="file"]');
            const action = this.dataset.action;

            if (!fileInput.files.length) {
                alert("Please select a file to import.");
                return;
            }

            const file = fileInput.files[0];
            const ext = file.name.split('.').pop().toLowerCase();
            const reader = new FileReader();

            btn.disabled = true;
            btn.textContent = "⏳ Processing...";

            const sendPayload = (rows) => {
                const payload = {
                    action: action,
                    import_excel_rows: rows,
                    csrf_token: form.querySelector('input[name="csrf_token"]').value,
                    company_id: form.querySelector('input[name="company_id"]').value
                };

                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(res => {
                    if (res.ok) {
                        window.location.href = 'index.php?success=1';
                    } else {
                        alert("Import failed: " + (res.error || res.message || (res.errors ? res.errors.join(', ') : "Unknown error")));
                        btn.disabled = false;
                        btn.textContent = "📤 Import " + (action === 'import_assets' ? 'Assets' : 'Employees');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Error communicating with server.");
                    btn.disabled = false;
                    btn.textContent = "📤 Import " + (action === 'import_assets' ? 'Assets' : 'Employees');
                });
            };

            if (ext === 'xlsx' || ext === 'xls') {
                reader.onload = function(e) {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, {type: 'array'});
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const rows = XLSX.utils.sheet_to_json(firstSheet, {header: 1});

                    if (rows.length < 2) {
                        alert("The file has no data rows.");
                        btn.disabled = false;
                        btn.textContent = "📤 Import " + (action === 'import_assets' ? 'Assets' : 'Employees');
                        return;
                    }
                    sendPayload(rows);
                };
                reader.readAsArrayBuffer(file);
            } else if (ext === 'csv') {
                reader.onload = function(e) {
                    const text = e.target.result;
                    const rows = parseCsv(text);
                    if (rows.length < 2) {
                        alert("The file has no data rows.");
                        btn.disabled = false;
                        btn.textContent = "📤 Import " + (action === 'import_assets' ? 'Assets' : 'Employees');
                        return;
                    }
                    sendPayload(rows);
                };
                reader.readAsText(file);
            } else {
                alert("Unsupported file format. Please use .csv or .xlsx");
                btn.disabled = false;
                btn.textContent = "📤 Import " + (action === 'import_assets' ? 'Assets' : 'Employees');
            }
        });
    });

    function parseCsv(text) {
        const rows = [];
        let row = [];
        let cell = '';
        let inQuotes = false;
        for (let i = 0; i < text.length; i++) {
            const char = text[i];
            const next = text[i + 1];
            if (inQuotes) {
                if (char === '"' && next === '"') { cell += '"'; i++; }
                else if (char === '"') { inQuotes = false; }
                else { cell += char; }
            } else {
                if (char === '"') { inQuotes = true; }
                else if (char === ',') { row.push(cell.trim()); cell = ''; }
                else if (char === '\n' || char === '\r') {
                    if (char === '\r' && next === '\n') i++;
                    row.push(cell.trim());
                    if (row.some(c => c !== '')) rows.push(row);
                    row = []; cell = '';
                } else { cell += char; }
            }
        }
        if (cell !== '' || row.length > 0) {
            row.push(cell.trim());
            if (row.some(c => c !== '')) rows.push(row);
        }
        return rows;
    }
</script>
</body>
</html>
