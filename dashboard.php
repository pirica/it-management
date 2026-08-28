<?php
/**
 * Employee landing dashboard — personal stat cards scoped to the signed-in employee,
 * plus a company switcher for tenants the session may access.
 */

require_once 'config/config.php';
require_once ROOT_PATH . 'includes/employee_profile_photo.php';
require_once ROOT_PATH . 'includes/itm_employee_dashboard.php';
require_once ROOT_PATH . 'includes/itm_dashboard_widgets.php';

if (!isset($_SESSION['employee_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = (int)$_SESSION['employee_id'];
$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$dashCsrfError = '';
$loginEmployeeId = function_exists('itm_company_session_login_employee_id')
    ? itm_company_session_login_employee_id()
    : $user_id;
if ($loginEmployeeId <= 0) {
    $loginEmployeeId = $user_id;
}
$isAdminUser = itm_is_admin($conn, $loginEmployeeId);

// Why: Restore the tenant switcher on the employee landing page (same contract as admin.php / index.php).
// Switch from the authenticated login employee so Admin remap targets the tenant seed Admin, not a prior context id.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_id'])) {
    if (!itm_try_post_csrf()) {
        $dashCsrfError = 'Invalid CSRF token.';
        $csrfToken = itm_get_csrf_token();
    } else {
        $requestedCompanyId = (int)($_POST['company_id'] ?? 0);
        if ($requestedCompanyId > 0 && function_exists('itm_switch_active_company_session')) {
            if (itm_switch_active_company_session($conn, $loginEmployeeId, $requestedCompanyId, $isAdminUser)) {
                header('Location: ' . BASE_URL . 'dashboard.php');
                exit;
            }
        }
    }
}

$user_id = (int)$_SESSION['employee_id'];
$company_id = (int)($_SESSION['company_id'] ?? 0);
$isAdminUser = itm_is_admin($conn, $user_id);

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
    unset($_SESSION['employee_id'], $_SESSION['login_employee_id']);
    header('Location: ' . BASE_URL . 'login.php');
    exit;
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

$smartDash = itm_dashboard_load_smart_widgets($conn, $company_id, $user_id);

$accessibleCompanies = function_exists('itm_list_employee_accessible_companies')
    ? itm_list_employee_accessible_companies($conn, $user_id, $isAdminUser)
    : [];

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

                <div class="card itm-emp-dash-company-switch">
                    <div class="card-header">
                        <h2>Company</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($dashCsrfError !== ''): ?>
                            <p class="crud_error" style="margin-top:0;"><?php echo htmlspecialchars($dashCsrfError, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($accessibleCompanies)): ?>
                            <form method="POST" class="itm-emp-dash-company-switch-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="itm-emp-dash-company-switch-fields">
                                    <label for="company"><strong>Switch Company:</strong></label>
                                    <select name="company_id" id="company" required>
                                        <option value="">-- Select a Company --</option>
                                        <?php foreach ($accessibleCompanies as $c): ?>
                                            <?php
                                            $optionId = (int)($c['id'] ?? 0);
                                            $optionName = trim((string)($c['company'] ?? ''));
                                            if ($optionId <= 0) {
                                                continue;
                                            }
                                            ?>
                                            <option value="<?php echo $optionId; ?>" <?php echo ($optionId === $company_id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($optionName, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary" title="Change company">Change Company</button>
                            </form>
                        <?php else: ?>
                            <p style="color:#999;margin:0;">No companies available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="itm-emp-dash-body">
                    <?php include ROOT_PATH . 'includes/itm_dashboard_widgets_cards.php'; ?>
                    <?php include ROOT_PATH . 'includes/itm_employee_dashboard_cards.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="js/theme.js"></script>
<script src="js/script.js"></script>
<?php if (!empty($smartDash['widgets'])): ?>
<script src="<?php echo BASE_URL; ?>js/vendor/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        return;
    }
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.05)';
    var textColor = isDark ? '#e1e1e1' : '#333';
    Chart.defaults.color = textColor;
    document.querySelectorAll('[data-itm-smart-dash-chart="1"]').forEach(function (canvas) {
        var labels = [];
        var data = [];
        try {
            labels = JSON.parse(canvas.getAttribute('data-chart-labels') || '[]');
            data = JSON.parse(canvas.getAttribute('data-chart-data') || '[]');
        } catch (e) {
            labels = [];
            data = [];
        }
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: textColor },
                        grid: { color: gridColor }
                    }
                }
            }
        });
    });
});
</script>
<?php endif; ?>
<script>
    window.ITM_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
</script>
</body>
</html>
