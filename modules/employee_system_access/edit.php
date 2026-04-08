<?php
/**
 * Employee System Access Module - Edit
 * 
 * Provides an interface to manage system permissions for a specific employee.
 * Users can toggle access to various systems via checkboxes.
 */

require '../../config/config.php';
require '../../includes/employee_system_access.php';

// Ensure tables exist
esa_ensure_table($conn);

// Resolve and validate employee ID
$employeeId = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
if ($employeeId <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch employee details
$employeeSql = 'SELECT id, first_name, last_name, display_name, email FROM employees WHERE id=' . $employeeId . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
$employeeRes = mysqli_query($conn, $employeeSql);
$employee = ($employeeRes && mysqli_num_rows($employeeRes) === 1) ? mysqli_fetch_assoc($employeeRes) : null;
if (!$employee) {
    header('Location: index.php');
    exit;
}

// Load available systems and current grants
$systemAccessCatalog = esa_get_system_access_catalog($conn, (int)$company_id, false);
$selectedSystemAccessIds = esa_get_employee_access_ids($conn, (int)$company_id, $employeeId);
$csrfToken = itm_get_csrf_token();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    
    // Sanitize and unique the submitted IDs
    $selectedSystemAccessIds = array_values(array_unique(array_map('intval', $_POST['system_access_ids'] ?? [])));
    
    // Save updated permissions
    esa_save_employee_access_ids($conn, (int)$company_id, $employeeId, $selectedSystemAccessIds);
    
    header('Location: index.php');
    exit;
}

/**
 * Helper to determine if a checkbox should be checked
 */
function esa_module_checked($ids, $id) {
    return in_array((int)$id, array_map('intval', is_array($ids) ? $ids : []), true) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee System Access</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;">
                <h1 style="margin:0;">Edit Access: <?php echo sanitize((string)($employee['display_name'] ?: trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']))); ?></h1>
                <a href="index.php" class="btn">🔙</a>
            </div>
            
            <!-- PERMISSION MANAGEMENT FORM -->
            <div class="card">
                <p style="margin-top:0;"><strong>Email:</strong> <?php echo sanitize((string)($employee['email'] ?? '')); ?></p>
                <?php if (empty($systemAccessCatalog)): ?>
                    <p>No active System Access records were found. Add some in <a href="../system_access/">System Access</a>.</p>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="employee_id" value="<?php echo (int)$employeeId; ?>">
                    
                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                        <?php foreach ($systemAccessCatalog as $access): ?>
                            <label class="role-flag-option">
                                <input type="checkbox" name="system_access_ids[]" value="<?php echo (int)$access['id']; ?>" <?php echo esa_module_checked($selectedSystemAccessIds, (int)$access['id']); ?>>
                                <span><?php echo sanitize((string)$access['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="form-actions itm-form-actions itm-align-left">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
