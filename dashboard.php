<?php
/**
 * Employee landing dashboard — personal stat cards scoped to the signed-in employee.
 */

require_once 'config/config.php';
require_once ROOT_PATH . 'includes/employee_profile_photo.php';
require_once ROOT_PATH . 'includes/itm_employee_dashboard.php';

if (!isset($_SESSION['employee_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = (int)$_SESSION['employee_id'];
$company_id = (int)($_SESSION['company_id'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    'SELECT e.*, ep.name AS position_name, d.name AS department_name, es.name AS status_name
     FROM employees e
     LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id
     LEFT JOIN departments d ON e.department_id = d.id
     LEFT JOIN employee_statuses es ON e.employment_status_id = es.id
     WHERE e.id = ?'
);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$current_user) {
    die('User not found.');
}

if ($company_id <= 0) {
    $company_id = (int)($current_user['company_id'] ?? 0);
    $_SESSION['company_id'] = $company_id;
}

$dash = itm_employee_dashboard_load_context($conn, $user_id, $company_id, $current_user);
if (!empty($dash['reload_required'])) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$isAdminUser = itm_is_admin($conn, $user_id);
$displayName = trim((string)($current_user['display_name'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string)($current_user['first_name'] ?? '') . ' ' . (string)($current_user['last_name'] ?? ''));
}
if ($displayName === '') {
    $displayName = (string)($current_user['username'] ?? 'Employee');
}

$companyLabel = trim((string)($_SESSION['company_name'] ?? ''));
if ($companyLabel === '' && $company_id > 0) {
    $companyStmt = mysqli_prepare($conn, 'SELECT company FROM companies WHERE id = ? LIMIT 1');
    if ($companyStmt) {
        mysqli_stmt_bind_param($companyStmt, 'i', $company_id);
        if (mysqli_stmt_execute($companyStmt)) {
            $companyRes = mysqli_stmt_get_result($companyStmt);
            $companyRow = $companyRes ? mysqli_fetch_assoc($companyRes) : null;
            if (is_array($companyRow)) {
                $companyLabel = trim((string)($companyRow['company'] ?? ''));
            }
        }
        mysqli_stmt_close($companyStmt);
    }
}

$profilePhotoUrl = emp_profile_photo_url($current_user);
$statusName = trim((string)($current_user['status_name'] ?? ''));
$positionName = trim((string)($current_user['position_name'] ?? ''));
$departmentName = trim((string)($current_user['department_name'] ?? ''));
$heroMetaParts = array_filter([$positionName, $departmentName, $companyLabel !== '' ? $companyLabel : null]);
$heroMeta = implode(' · ', $heroMetaParts);
$profileTheme = (strtolower(trim((string)($current_user['theme'] ?? ($_SESSION['ui_theme'] ?? 'light')))) === 'dark') ? 'dark' : 'light';
$stylesCssPath = ROOT_PATH . 'css/styles.css';
$stylesCssVersion = is_file($stylesCssPath) ? (string)filemtime($stylesCssPath) : '1';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo sanitize($profileTheme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo sanitize($displayName); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css?v=<?php echo sanitize($stylesCssVersion); ?>">
    <script>
    (function () {
        var theme = <?php echo json_encode($profileTheme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ITM_PREFERRED_THEME = theme;
        try { localStorage.setItem('theme', theme); } catch (e) {}
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
</head>
<body class="itm-employee-dashboard-page">
<div class="container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        <div class="content itm-employee-dashboard">
            <div class="itm-emp-dash-shell">
                <div class="itm-emp-dash-hero">
                    <div class="itm-emp-dash-hero-photo">
                        <?php if ($profilePhotoUrl): ?>
                            <img src="<?php echo sanitize($profilePhotoUrl); ?>" alt="Profile">
                        <?php else: ?>
                            <span aria-hidden="true">👤</span>
                        <?php endif; ?>
                    </div>
                    <div class="itm-emp-dash-hero-body">
                        <p class="itm-emp-dash-hero-kicker" title="Dashboard">📊 Welcome back</p>
                        <h2 class="itm-emp-dash-hero-name"><?php echo sanitize($displayName); ?></h2>
                        <?php if ($heroMeta !== ''): ?>
                            <p class="itm-emp-dash-hero-meta"><?php echo sanitize($heroMeta); ?></p>
                        <?php endif; ?>
                        <?php if ($statusName !== ''): ?>
                            <span class="badge badge-success"><?php echo sanitize($statusName); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="itm-emp-dash-hero-actions">
                        <a class="btn btn-primary" href="<?php echo BASE_URL; ?>user-config.php" title="Edit profile">✏️</a>
                        <a class="btn" href="<?php echo BASE_URL; ?>user-config.php" title="Profile and preferences">👤</a>
                        <?php if ($isAdminUser): ?>
                            <a class="btn" href="<?php echo BASE_URL; ?>admin.php" title="Admin overview">🛡️</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="itm-emp-dash-body">
                    <?php include ROOT_PATH . 'includes/itm_employee_dashboard_cards.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="js/theme.js"></script>
<script src="js/script.js"></script>
<script>
    window.ITM_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
</script>
</body>
</html>
