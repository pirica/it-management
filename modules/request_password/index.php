<?php
/**
 * Request Password Module - Index
 *
 * Handles user requests for password changes/resets with HR/HOD/ISM workflow.
 */
$crud_table = 'request_password';
$crud_title = 'Request Password';
if (!isset($crud_action)) {
    $crud_action = 'index';
}


require_once '../../config/config.php';
require_once '../../includes/itm_crud_fk_label_search.php';



// WHY: Enforce RBAC permissions for the request_password module.
if (function_exists('itm_require_crud_role_module_permission')) {
    $check_action = ($crud_action == 'index' || $crud_action == 'list_all') ? 'view' : $crud_action;
    itm_require_crud_role_module_permission($conn, $check_action, 'request_password');
}																 
$pk = 'id';

// Multi-tenancy check
if (!$company_id) {
    die("Company ID not found.");
}

/**
 * Formats date for display DD/MM/YYYY
 */
function cr_format_date($date) {
    if (!$date || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return '';
    return date('d/m/Y', strtotime($date));
}

/**
 * Handle Approval API from Email
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['approval_api'])) {
    $recordId = (int)$_GET['id'];
    $target = $_GET['target']; // hr, hod
    $decision = $_GET['decision']; // approve, decline
    $token = $_GET['token'];

    // Verify token
    $secret = 'request_password_secret_key_2024';
    $expectedToken = hash_hmac('sha256', $recordId . $target . $decision, $secret);

    if (!hash_equals($expectedToken, $token)) {
        die("Invalid or expired authorization link.");
    }

    $statusField = ($target == 'hr') ? 'hr_approval_status' : 'hod_approval_status';
    $dateField = ($target == 'hr') ? 'hr_signature_date' : 'hod_signature_date';
    $statusValue = ($decision == 'approve') ? 'Approved' : 'Declined';

    // Why: Use prepared statements to prevent SQL injection and enforce multi-tenancy.
    $sql = "UPDATE request_password SET $statusField = ?, $dateField = CURDATE() WHERE id = ? AND company_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sii', $statusValue, $recordId, $company_id);

    if (mysqli_stmt_execute($stmt)) {
        echo "<h2>Request " . htmlspecialchars($statusValue) . "</h2>";
        echo "<p>The password request has been updated. You may close this window.</p>";
    } else {
        echo "Error updating status: " . mysqli_error($conn);
    }
    exit;
}

/**
 * Handle Email Notifications
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email_action'])) {
    itm_require_post_csrf();
    $recordId = (int)$_POST['id'];
    $action = $_POST['send_email_action']; // hr, hod, ism

    // Fetch record details
    $sql = "SELECT rp.*, e.first_name, e.last_name, e.work_email, e.personal_email
            FROM request_password rp
            JOIN employees e ON rp.employee_id = e.id
            WHERE rp.id = ? AND rp.company_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $recordId, $company_id);
    mysqli_stmt_execute($stmt);
    $record = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$record) die("Record not found.");

    $applicantName = $record['first_name'] . ' ' . $record['last_name'];
    $applicantEmail = $record['work_email'] ?: $record['personal_email'];

    $subject = "Password Change Request - " . $applicantName;
    $secret = 'request_password_secret_key_2024';

    if ($action == 'hr' || $action == 'hod') {
        $approveToken = hash_hmac('sha256', $recordId . $action . 'approve', $secret);
        $declineToken = hash_hmac('sha256', $recordId . $action . 'decline', $secret);

        $approveUrl = BASE_URL . "modules/request_password/index.php?approval_api=1&id=$recordId&target=$action&decision=approve&token=$approveToken";
        $declineUrl = BASE_URL . "modules/request_password/index.php?approval_api=1&id=$recordId&target=$action&decision=decline&token=$declineToken";

        $message = "<h2>Password Change Request</h2>";
        $message .= "<p>A password change request has been submitted by <strong>$applicantName</strong> for the application: <strong>" . htmlspecialchars($record['application']) . "</strong>.</p>";
        $message .= "<p>Reason: " . htmlspecialchars($record['reason']) . "</p>";
        $message .= "<p>Please authorize or decline this request using the links below:</p>";
        $message .= "<p><a href='$approveUrl' style='background-color: #2da44e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Authorize Request</a></p>";
        $message .= "<p><a href='$declineUrl' style='background-color: #cf222e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Decline Request</a></p>";

        // Find approver email from approvers table
        $approverTypeDesc = ($action == 'hr') ? 'HRD Approval' : 'HOD Approval';
        $approverSql = "SELECT e.work_email, e.personal_email FROM approvers a
                        JOIN employees e ON a.employee_id = e.id
                        JOIN approver_type at ON a.approver_type_id = at.id
                        WHERE a.company_id = ? AND at.approver_type_description = ? LIMIT 1";
        $aStmt = mysqli_prepare($conn, $approverSql);
        mysqli_stmt_bind_param($aStmt, 'is', $company_id, $approverTypeDesc);
        mysqli_stmt_execute($aStmt);
        $approver = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt));
        $toEmail = $approver ? ($approver['work_email'] ?: $approver['personal_email']) : '';

        if ($toEmail) {
            itm_send_email($toEmail, $subject, $message, $company_id);
            $_SESSION['crud_success'] = "Email sent to $approverTypeDesc for authorization.";
        } else {
            $_SESSION['crud_error'] = "No approver found for $approverTypeDesc. Please check 'Approvers' settings.";
        }
    } elseif ($action == 'ism') {
        $message = "<h2>Password Request Processed</h2>";
        $message .= "<p>Dear $applicantName,</p>";
        $message .= "<p>Your password change request for <strong>" . htmlspecialchars($record['application']) . "</strong> has been processed by ISM.</p>";

        if ($applicantEmail) {
            itm_send_email($applicantEmail, "Password Request Processed", $message, $company_id);
            $stmt = mysqli_prepare($conn, "UPDATE request_password SET ism_signature_date = CURDATE() WHERE id = ? AND company_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $recordId, $company_id);
            mysqli_stmt_execute($stmt);
            $_SESSION['crud_success'] = "Final notification sent to applicant.";
        } else {
            $_SESSION['crud_error'] = "Applicant email not found.";
        }
    }

    header("Location: view.php?id=$recordId");
    exit;
}

/**
 * Handle CRUD Actions
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit', 'delete'])) {
    itm_require_post_csrf();

    if ($crud_action == 'delete') {
        $id = (int)$_POST['id'];
        $deleted_by = (int)($_SESSION['employee_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE request_password SET active = 0, deleted_by = ?, deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $deleted_by, $id, $company_id);
        mysqli_stmt_execute($stmt);
        header("Location: index.php");
        exit;
    }

    $employee_id = (int)$_POST['employee_id'];
    $requested_by_employee_id = (int)$_POST['requested_by_employee_id'];
    $application = $_POST['application'];
    $reason = $_POST['reason'];

    $active = isset($_POST['active']) && $_POST['active'] !== '' ? (int)$_POST['active'] : 1;
    $created_by = isset($_POST['created_by']) && $_POST['created_by'] !== '' ? (int)$_POST['created_by'] : (int)($_SESSION['employee_id'] ?? 0);
    $updated_by = (int)($_SESSION['employee_id'] ?? 0);
    $created_at = !empty($_POST['created_at']) ? $_POST['created_at'] : null;
    $updated_at = !empty($_POST['updated_at']) ? $_POST['updated_at'] : null;
    $deleted_by = isset($_POST['deleted_by']) && $_POST['deleted_by'] !== '' ? (int)$_POST['deleted_by'] : null;
    $deleted_at = !empty($_POST['deleted_at']) ? $_POST['deleted_at'] : null;

    if ($crud_action == 'create') {
        $sql = "INSERT INTO request_password (company_id, employee_id, requested_by_employee_id, application, reason, applicant_signature_date, active, deleted_by, deleted_at, created_by, created_at, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iiississisis', $company_id, $employee_id, $requested_by_employee_id, $application, $reason, $active, $deleted_by, $deleted_at, $created_by, $created_at, $updated_by, $updated_at);
    } else {
        $id = (int)$_POST['id'];
        $sql = "UPDATE request_password SET employee_id = ?, requested_by_employee_id = ?, application = ?, reason = ?, active = ?, deleted_by = ?, deleted_at = ?, created_by = ?, created_at = ?, updated_by = ?, updated_at = ? WHERE id = ? AND company_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iississisiiii', $employee_id, $requested_by_employee_id, $application, $reason, $active, $deleted_by, $deleted_at, $created_by, $created_at, $updated_by, $updated_at, $id, $company_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        $last_id = ($crud_action == 'create') ? mysqli_insert_id($conn) : $id;
        header("Location: view.php?id=$last_id");
        exit;
    } else {
        $error = "Error saving record: " . mysqli_error($conn);
    }
}

// Fetch record for view/edit
$data = [];
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $sql = "SELECT rp.*, e.username, e.first_name, e.last_name, d.name as department_name
            FROM request_password rp
            JOIN employees e ON rp.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE rp.id = ? AND rp.company_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($res);
}

// Fetch applications for dropdown
function get_employee_applications($conn, $employee_id, $company_id) {
    $apps = [];
    $sql = "SELECT * FROM employee_system_access WHERE employee_id = ? AND company_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $employee_id, $company_id);
    mysqli_stmt_execute($stmt);
    $access = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($access) {
        $skip = ['id', 'company_id', 'employee_id', 'changed_at', 'updated_at', 'created_at'];
        foreach ($access as $key => $val) {
            if (!in_array($key, $skip) && $val == 1) {
                $apps[] = ucwords(str_replace('_', ' ', $key));
            }
        }
    }
    return $apps;
}

$successMessage = $_SESSION['crud_success'] ?? '';
unset($_SESSION['crud_success']);
$errorMessage = $_SESSION['crud_error'] ?? ($error ?? '');
unset($_SESSION['crud_error']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Request Password';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .request-header { background: #f6f8fa; padding: 20px; border: 1px solid #d0d7de; border-radius: 6px; margin-bottom: 20px; }
        .request-header h2 { margin-top: 0; text-align: center; }
        .signature-container { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 30px; }
        .signature-box { border-top: 1px solid #333; margin-top: 40px; padding-top: 10px; position: relative; }
        .signature-box .label { font-weight: bold; position: absolute; top: -30px; left: 0; }
        .signature-box .name-date { font-size: 0.9em; color: #555; }
        .status-badge { font-weight: bold; padding: 2px 8px; border-radius: 4px; font-size: 0.85em; }
        .status-waiting { background: #f1f8ff; color: #0366d6; }
        .status-approved { background: #dafbe1; color: #1a7f37; }
        .status-declined { background: #ffebe9; color: #cf222e; }
        @media print {
            .btn, .sidebar, .header, .form-actions { display: none !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; }
            .request-header { border: none; background: none; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if ($successMessage): ?><div class="alert alert-success"><?php echo sanitize($successMessage); ?></div><?php endif; ?>
            <?php if ($errorMessage): ?><div class="alert alert-danger"><?php echo sanitize($errorMessage); ?></div><?php endif; ?>

            <?php if ($crud_action == 'index' || $crud_action == 'list_all'): ?>
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <a href="create.php" class="btn btn-primary" title="Create">➕</a>
                    <h1>🔑 <?php echo sanitize($crud_title); ?></h1>
                    <span></span>
                </div>

                <div class="card">
                    <form method="GET" style="margin-bottom: 15px; display: flex; gap: 10px;">
                        <input type="text" name="search" placeholder="Search requests..." value="<?php echo sanitize($_GET['search'] ?? ''); ?>" style="flex: 1;">
                        <button type="submit" class="btn">Search</button>
                    </form>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Application</th>
                                    <th>HR Status</th>
                                    <th>HOD Status</th>
                                    <th>ISM Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $search = $_GET['search'] ?? '';
                                $where = "rp.company_id = ? AND rp.active = 1";
                                $params = [$company_id];
                                $types = 'i';
                                if ($search) {
                                    $where .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR rp.application LIKE ?)";
                                    $searchTerm = "%$search%";
                                    $params[] = $searchTerm;
                                    $params[] = $searchTerm;
                                    $params[] = $searchTerm;
                                    $types .= 'sss';
                                }
                                $sql = "SELECT rp.*, e.first_name, e.last_name, d.name as department_name
                                        FROM request_password rp
                                        JOIN employees e ON rp.employee_id = e.id
                                        LEFT JOIN departments d ON e.department_id = d.id
                                        WHERE $where ORDER BY rp.id DESC";
                                $stmt = mysqli_prepare($conn, $sql);
                                mysqli_stmt_bind_param($stmt, $types, ...$params);
                                mysqli_stmt_execute($stmt);
                                $res = mysqli_stmt_get_result($stmt);
                                while ($row = mysqli_fetch_assoc($res)):
                                    $ism_status = $row['ism_signature_date'] ? 'PROCESSED' : 'WAITING';
                                ?>
                                <tr>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm" title="View">🔎</a>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm" title="Edit">✏️</a>
                                    </td>
                                    <td><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><?php echo sanitize($row['department_name']); ?></td>
                                    <td><?php echo sanitize($row['application']); ?></td>
                                    <td><span class="status-badge status-<?php echo strtolower($row['hr_approval_status']); ?>"><?php echo strtoupper($row['hr_approval_status']); ?></span></td>
                                    <td><span class="status-badge status-<?php echo strtolower($row['hod_approval_status']); ?>"><?php echo strtoupper($row['hod_approval_status']); ?></span></td>
                                    <td><span class="status-badge status-<?php echo strtolower($ism_status); ?>"><?php echo $ism_status; ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($crud_action == 'create' || $crud_action == 'edit'): ?>
                <?php
                $emp_id = ($crud_action == 'edit') ? $data['employee_id'] : $_SESSION['employee_id'];
                $sql = "SELECT e.*, d.name as department_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id WHERE e.id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'i', $emp_id);
                mysqli_stmt_execute($stmt);
                $e_res = mysqli_stmt_get_result($stmt);
                $subject_emp = mysqli_fetch_assoc($e_res);
                $apps = get_employee_applications($conn, $emp_id, $company_id);
                ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                    <input type="hidden" name="id" value="<?php echo $data['id'] ?? ''; ?>">
                    <input type="hidden" name="employee_id" value="<?php echo $emp_id; ?>">
                    <input type="hidden" name="active" value="<?php echo sanitize((string)($data['active'] ?? 1)); ?>">
                    <input type="hidden" name="deleted_by" value="<?php echo sanitize((string)($data['deleted_by'] ?? '')); ?>">
                    <input type="hidden" name="deleted_at" value="<?php echo sanitize((string)($data['deleted_at'] ?? '')); ?>">
                    <input type="hidden" name="created_by" value="<?php echo sanitize((string)($data['created_by'] ?? ($_SESSION['employee_id'] ?? ''))); ?>">
                    <input type="hidden" name="created_at" value="<?php echo sanitize((string)($data['created_at'] ?? '')); ?>">
                    <input type="hidden" name="updated_by" value="<?php echo sanitize((string)($data['updated_by'] ?? ($_SESSION['employee_id'] ?? ''))); ?>">
                    <input type="hidden" name="updated_at" value="<?php echo sanitize((string)($data['updated_at'] ?? '')); ?>">

                    <div class="request-header">
                        <h2>REQUEST FORM CHANGE OF PASSWORDS</h2>
                        <p><strong>Information Security Policy</strong></p>
                        <p>[Access Management] G-Authentication and Password : Password must be securely delivered to any user and kept secured at all times.</p>
                    </div>

                    <div class="card">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" value="<?php echo sanitize(($subject_emp['first_name'] ?? '') . ' ' . ($subject_emp['last_name'] ?? '')); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Department</label>
                                <input type="text" value="<?php echo sanitize($subject_emp['department_name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" value="<?php echo sanitize($subject_emp['username'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Application</label>
                                <select name="application" required>
                                    <option value="">-- Select Application --</option>
                                    <?php foreach ($apps as $app): ?>
                                        <option value="<?php echo sanitize($app); ?>" <?php echo (isset($data['application']) && $data['application'] == $app) ? 'selected' : ''; ?>><?php echo sanitize($app); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Requested by</label>
                                <select name="requested_by_employee_id" required>
                                    <option value="">-- Select Employee --</option>
                                    <?php
                                    $stmt = mysqli_prepare($conn, "SELECT e.id, e.first_name, e.last_name FROM employees e LEFT JOIN employee_statuses es ON e.employment_status_id = es.id WHERE e.company_id = ? AND (es.active = 1 OR es.id IS NULL) ORDER BY e.first_name");
                                    mysqli_stmt_bind_param($stmt, 'i', $company_id);
                                    mysqli_stmt_execute($stmt);
                                    $emp_res = mysqli_stmt_get_result($stmt);
                                    while ($emp = mysqli_fetch_assoc($emp_res)):
                                    ?>
                                        <option value="<?php echo $emp['id']; ?>" <?php echo (($data['requested_by_employee_id'] ?? $_SESSION['employee_id']) == $emp['id']) ? 'selected' : ''; ?>>
                                            <?php echo sanitize($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 20px;">
                            <label>Reason for request:</label>
                            <?php
                            $reasons = [
                                'Cannot recall password',
                                'Password expired',
                                'Account locked',
                                'Security reasons (3rd party may know password)'
                            ];
                            foreach ($reasons as $r):
                            ?>
                                <label class="radio-label" style="display: block; margin-bottom: 10px;">
                                    <input type="radio" name="reason" value="<?php echo sanitize($r); ?>" <?php echo (isset($data['reason']) && $data['reason'] == $r) ? 'checked' : ''; ?> required>
                                    <?php echo sanitize($r); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" title="Save">💾</button>
                            <a href="index.php" class="btn" title="Back">🔙</a>
                        </div>
                    </div>
                </form>

            <?php elseif ($crud_action == 'view'): ?>
                <div class="request-header">
                    <div style="float: right;">
                        <button class="btn btn-sm" onclick="window.print()">📄 Export PDF</button>
                    </div>
                    <h2>REQUEST FORM CHANGE OF PASSWORDS</h2>
                    <p><strong>Information Security Policy</strong></p>
                    <p>[Access Management] G-Authentication and Password : Password must be securely delivered to any user and kept secured at all times.</p>
                </div>

                <div class="card">
                    <table class="table-borderless">
                        <tr><th style="width: 250px;">Name:</th><td><?php echo sanitize($data['first_name'] . ' ' . $data['last_name']); ?></td></tr>
                        <tr><th>Department:</th><td><?php echo sanitize($data['department_name']); ?></td></tr>
                        <tr><th>Application:</th><td><?php echo sanitize($data['application']); ?></td></tr>
                        <tr><th>Username:</th><td><?php echo sanitize($data['username']); ?></td></tr>
                        <tr><th>Requested by:</th><td>
                            <?php
                            $rb_id = (int)$data['requested_by_employee_id'];
                            $stmt = mysqli_prepare($conn, "SELECT first_name, last_name FROM employees WHERE id = ?");
                            mysqli_stmt_bind_param($stmt, 'i', $rb_id);
                            mysqli_stmt_execute($stmt);
                            $rb = mysqli_stmt_get_result($stmt);
                            $rb_data = mysqli_fetch_assoc($rb);
                            echo sanitize(($rb_data['first_name'] ?? '') . ' ' . ($rb_data['last_name'] ?? ''));
                            ?>
                        </td></tr>
                        <tr><th>Reason for request:</th><td><?php echo sanitize($data['reason']); ?></td></tr>
                    </table>

                    <div class="signature-container">
                        <div class="signature-box">
                            <div class="label">Signature Applicant: (Save Date)</div>
                            <div class="name-date">
                                <?php echo sanitize($data['first_name'] . ' ' . $data['last_name']); ?> - <?php echo cr_format_date($data['applicant_signature_date']); ?>
                                <br><small>(Name / Date)</small>
                            </div>
                        </div>

                        <div class="signature-box">
                            <div class="label">Password changed by ISM: (Save Date)</div>
                            <div class="name-date">
                                <?php if ($data['ism_signature_date']): ?>
                                    ISM - <?php echo cr_format_date($data['ism_signature_date']); ?>
                                <?php else: ?>
                                    <span style="color:#aaa;">_____________________________________</span>
                                <?php endif; ?>
                                <br><small>(Name/Date)</small>
                            </div>
                            <?php
                            $canSendIsm = ($data['hr_approval_status'] == 'Approved' && $data['hod_approval_status'] == 'Approved');
                            ?>
                            <form method="POST" style="margin-top: 15px;">
                                <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                                <input type="hidden" name="id" value="<?php echo $data['id']; ?>">
                                <button type="submit" name="send_email_action" value="ism" class="btn btn-sm btn-primary" <?php echo $canSendIsm ? '' : 'disabled'; ?>>Submit Email</button>
                            </form>
                        </div>

                        <div class="signature-box">
                            <div class="label">Approved(green)/Declined(red) by HR: (Submit Email button)</div>
                            <div class="name-date">
                                <?php if ($data['hr_approval_status'] != 'Waiting'): ?>
                                    <span class="status-badge status-<?php echo strtolower($data['hr_approval_status']); ?>">
                                        HR (<?php echo $data['hr_approval_status']; ?>) - <?php echo cr_format_date($data['hr_signature_date']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#aaa;">_____________________________________</span>
                                <?php endif; ?>
                                <br><small>(Name/Date)</small>
                            </div>
                            <form method="POST" style="margin-top: 15px;">
                                <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                                <input type="hidden" name="id" value="<?php echo $data['id']; ?>">
                                <button type="submit" name="send_email_action" value="hr" class="btn btn-sm">Submit Email</button>
                            </form>
                        </div>

                        <div class="signature-box">
                            <div class="label">Approved(green)/Declined(red) by HOD: (Submit Email button)</div>
                            <div class="name-date">
                                <?php if ($data['hod_approval_status'] != 'Waiting'): ?>
                                    <span class="status-badge status-<?php echo strtolower($data['hod_approval_status']); ?>">
                                        HOD (<?php echo $data['hod_approval_status']; ?>) - <?php echo cr_format_date($data['hod_signature_date']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:#aaa;">_____________________________________</span>
                                <?php endif; ?>
                                <br><small>(Name/Date)</small>
                            </div>
                            <form method="POST" style="margin-top: 15px;">
                                <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                                <input type="hidden" name="id" value="<?php echo $data['id']; ?>">
                                <button type="submit" name="send_email_action" value="hod" class="btn btn-sm">Submit Email</button>
                            </form>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 40px;">
                        <a href="index.php" class="btn" title="Back">🔙</a>
                        <a href="edit.php?id=<?php echo $data['id']; ?>" class="btn" title="Edit">✏️</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
