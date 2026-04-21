<?php
/**
 * System Access Module - Create/Edit
 * 
 * Handles addition and modification of system access types.
 * Enforces company-scoping and validates unique codes.
 */

require '../../config/config.php';
require '../../includes/employee_system_access.php';

// Lazy schema check.
esa_ensure_table($conn);

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';
$data = ['code' => '', 'name' => '', 'active' => 1];
$csrfToken = itm_get_csrf_token();

// Load existing record for editing.
if ($is_edit) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM system_access WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $q = mysqli_stmt_get_result($stmt);
        if ($q && mysqli_num_rows($q) === 1) {
            $data = mysqli_fetch_assoc($q);
        } else {
            $error = 'System Access record not found.';
            $is_edit = false;
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $code = trim((string)($_POST['code'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;

    if (!$code) {
        $error = 'Code is required.';
    } elseif (!$name) {
        $error = 'Name is required.';
    } else {
        // Build query using prepared statements for security.
        $stmt = $is_edit
            ? mysqli_prepare($conn, 'UPDATE system_access SET code = ?, name = ?, active = ? WHERE id = ? AND company_id = ?')
            : mysqli_prepare($conn, 'INSERT INTO system_access (company_id, code, name, active) VALUES (?, ?, ?, ?)');

        if ($stmt) {
            if ($is_edit) {
                mysqli_stmt_bind_param($stmt, 'ssiii', $code, $name, $active, $id, $company_id);
            } else {
                mysqli_stmt_bind_param($stmt, 'issi', $company_id, $code, $name, $active);
            }

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                header('Location: index.php');
                exit;
            }

            $error = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
            mysqli_stmt_close($stmt);
        } else {
            $error = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> System Access</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1><?php echo $is_edit ? '✏️ Edit' : '➕ Add'; ?> System Access</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>
            <div class="card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Code *</label>
                            <input required name="code" value="<?php echo sanitize($data['code']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Name *</label>
                            <input required name="name" value="<?php echo sanitize($data['name']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="itm-checkbox-control">
                            <input type="checkbox" name="active" <?php echo (int)$data['active'] === 1 ? 'checked' : ''; ?>>
                            <span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo (int)$data['active'] === 1 ? '✅' : '❌'; ?></span></span>
                        </label>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
/**
 * Visual indicator for checkbox state.
 */
document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) indicator.textContent = event.target.checked ? '✅' : '❌';
});
</script>
</body>
</html>
