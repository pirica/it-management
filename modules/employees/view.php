<?php
/**
 * Employees Module - View
 *
 * Provides a detailed view of an employee record, including profile details,
 * department/status assignments, and current system access permissions.
 * Features:
 * - Read-only display of all employee fields
 * - Join-based lookup for department and status names
 * - Integrated display of system access permissions
 * - Mailto links for email addresses with Outlook support
 */

require '../../config/config.php';
require '../../includes/employee_system_access.php';

// Validate ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch employee data with associated names
// We join with departments and employee_statuses to show human-readable labels instead of IDs
$sql = "SELECT e.*,
               d.name AS department_name,
               od.name AS office_department_name,
               es.name AS employment_status_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN departments od ON od.id = e.office_key_card_department_id
        LEFT JOIN employee_statuses es ON es.id = e.employment_status_id
        WHERE e.id = ? AND e.company_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
$employee = null;

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $employee = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$employee) {
    header('Location: index.php');
    exit;
}

// Fetch system access catalog and current employee permissions
esa_ensure_table($conn);
$systemAccessCatalog = esa_get_system_access_catalog($conn, (int)$company_id, true);
$employeeAccessIds = esa_get_employee_access_ids($conn, (int)$company_id, $id);

/**
 * Humanizes labels specifically for the employee module view
 */
function emp_label($field) {
    $map = [
        'id' => 'ID',
        'hilton_id' => 'Id',
        'department_id' => 'Department Name',
        'office_key_card_department_id' => 'Office Key Card Department',
        'employment_status_id' => 'Employment Status',
    ];
    return $map[$field] ?? ucwords(str_replace('_', ' ', trim((string)$field)));
}

/**
 * Renders a value, handling booleans and special types like emails
 */
function emp_render_val($field, $value, $row) {
    if ($value === null || $value === '') {
        return '—';
    }

    // Special handling for email with Outlook integration
    if ($field === 'email') {
        $safeEmail = sanitize($value);
        return '<a href="mailto:' . $safeEmail . '" data-outlook-link="1" data-outlook-href="ms-outlook://compose?to=' . $safeEmail . '">' . $safeEmail . '</a>';
    }

    // Use joined names where available
    if ($field === 'department_id') return sanitize($row['department_name'] ?? '');
    if ($field === 'office_key_card_department_id') return sanitize($row['office_department_name'] ?? '');
    if ($field === 'employment_status_id') return sanitize($row['employment_status_name'] ?? '');

    return sanitize((string)$value);
}

$csrfToken = itm_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Employee - <?php echo sanitize($employee['display_name'] ?? 'Record'); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;">
                <h1 style="margin:0;">Employee Details</h1>
                <div style="display:flex;gap:8px;">
                    <a href="index.php" class="btn">← Back</a>
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary">✏️ Edit</a>
                </div>
            </div>

            <?php if ((int)($employee['duplicate'] ?? 0) === 1): ?>
                <div class="alert alert-error" style="margin-bottom:16px;">
                    ⚠️ This record is flagged as a duplicate. Please verify the Email, Employee Code, or Hilton ID.
                </div>
            <?php endif; ?>

            <div class="card">
                <h3 style="margin-top:0;border-bottom:1px solid var(--border-color);padding-bottom:8px;">Profile Information</h3>
                <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">
                    <table class="details-table">
                        <tbody>
                            <?php
                            $profileFields = ['id', 'hilton_id', 'first_name', 'last_name', 'display_name', 'email', 'username'];
                            foreach ($profileFields as $f): ?>
                                <tr>
                                    <th style="width:180px;text-align:left;padding:8px 0;color:var(--text-muted);"><?php echo sanitize(emp_label($f)); ?></th>
                                    <td style="padding:8px 0;font-weight:500;"><?php echo emp_render_val($f, $employee[$f] ?? '', $employee); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <table class="details-table">
                        <tbody>
                            <?php
                            $jobFields = ['job_code', 'job_title', 'department_id', 'office_key_card_department_id', 'employment_status_id', 'raw_status_code'];
                            foreach ($jobFields as $f): ?>
                                <tr>
                                    <th style="width:180px;text-align:left;padding:8px 0;color:var(--text-muted);"><?php echo sanitize(emp_label($f)); ?></th>
                                    <td style="padding:8px 0;font-weight:500;"><?php echo emp_render_val($f, $employee[$f] ?? '', $employee); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card" style="margin-top:20px;">
                <h3 style="margin-top:0;border-bottom:1px solid var(--border-color);padding-bottom:8px;">Lifecycle & Comments</h3>
                <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">
                    <table class="details-table">
                        <tbody>
                            <?php
                            $lifecycleFields = ['request_date', 'requested_by', 'termination_requested_by', 'termination_date'];
                            foreach ($lifecycleFields as $f): ?>
                                <tr>
                                    <th style="width:180px;text-align:left;padding:8px 0;color:var(--text-muted);"><?php echo sanitize(emp_label($f)); ?></th>
                                    <td style="padding:8px 0;font-weight:500;"><?php echo emp_render_val($f, $employee[$f] ?? '', $employee); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="padding:8px 0;">
                        <label style="display:block;color:var(--text-muted);margin-bottom:4px;">Comments</label>
                        <div style="white-space:pre-wrap;font-weight:500;"><?php echo sanitize($employee['comments'] ?? '—'); ?></div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top:20px;">
                <h3 style="margin-top:0;border-bottom:1px solid var(--border-color);padding-bottom:8px;">System Access Permissions</h3>
                <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;padding-top:12px;">
                    <?php foreach ($systemAccessCatalog as $access): ?>
                        <?php $isGranted = in_array((int)$access['id'], $employeeAccessIds, true); ?>
                        <div style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--card-bg-secondary);border-radius:4px;border:1px solid var(--border-color);">
                            <span style="font-size:18px;"><?php echo $isGranted ? '✅' : '❌'; ?></span>
                            <span style="font-size:14px;font-weight:500;<?php echo !$isGranted ? 'color:var(--text-muted);' : ''; ?>">
                                <?php echo sanitize($access['name']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-top:20px;display:flex;justify-content:flex-end;">
                <form method="POST" action="delete.php" onsubmit="return confirm('Are you sure you want to delete this employee?');">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="btn btn-danger">🗑️ Delete Employee</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Outlook Integration - Intercepts mailto clicks to use ms-outlook protocol if available
 */
document.addEventListener('click', function (event) {
    const link = event.target.closest('a[data-outlook-link="1"]');
    if (link) {
        const outlookHref = link.getAttribute('data-outlook-href');
        if (outlookHref) {
            // We set location.href but don't prevent default,
            // the browser will handle the custom protocol or fallback
            window.location.href = outlookHref;
        }
    }
});
</script>
</body>
</html>
