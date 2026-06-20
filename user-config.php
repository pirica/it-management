<?php
require_once 'config/config.php';
require_once ROOT_PATH . 'includes/itm_vault_master_key.php';
require_once ROOT_PATH . 'includes/employee_profile_photo.php';

/**
 * user-config.php - Employee Dashboard & Profile System
 *
 * Scoped to the logged-in employee.
 * Provides a dashboard with stat cards linking to modules.
 * Two-column layout:
 *   - Left: Profile Summary, Stats, Progress, Org Chart Path.
 *   - Right: Profile Editing, Security, Sidebar Prefs, Activity, etc.
 */

// Auth check
if (!isset($_SESSION['employee_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = (int)$_SESSION['employee_id'];
$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrfToken = itm_get_csrf_token();

// --- 1. GATHER ALL REQUIRED DATA ---

// Fetch current employee profile data
$stmt = mysqli_prepare($conn, '
    SELECT e.*, ep.name AS position_name, d.name AS department_name, es.name AS status_name
    FROM employees e
    LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN employee_statuses es ON e.employment_status_id = es.id
    WHERE e.id = ? AND e.company_id = ?
');
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
mysqli_stmt_execute($stmt);
$current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$current_user) {
    die('User not found.');
}

// Stats gathering - All modules using specified fields
$all_stats = [];
$stat_definitions = [
    ['table' => 'alerts', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Alerts', 'slug' => 'alerts'],
    ['table' => 'alerts', 'field' => 'created_by_employee_id', 'label' => 'Created Alerts', 'slug' => 'alerts'],
    ['table' => 'approvers', 'field' => 'employee_id', 'label' => 'Approver Roles', 'slug' => 'approvers'],
    ['table' => 'attempts', 'field' => 'employee_id', 'label' => 'Login Attempts', 'slug' => 'attempts'],
    ['table' => 'audit_logs', 'field' => 'employee_id', 'label' => 'Audit Logs', 'slug' => 'audit_logs'],
    ['table' => 'bookmark_folders', 'field' => 'employee_id', 'label' => 'Bookmark Folders', 'slug' => 'bookmarks'],
    ['table' => 'bookmarks', 'field' => 'employee_id', 'label' => 'My Bookmarks', 'slug' => 'bookmarks'],
    ['table' => 'employee_assignment_history', 'field' => 'employee_id', 'label' => 'Asset History', 'slug' => 'employee_assignment_history'],
    ['table' => 'employee_assignment_history', 'field' => 'received_by_employee_id', 'label' => 'Items Received', 'slug' => 'employee_assignment_history'],
    ['table' => 'employee_companies', 'field' => 'employee_id', 'label' => 'Companies', 'slug' => 'employee_companies'],
    ['table' => 'employee_companies', 'field' => 'granted_by_employee_id', 'label' => 'Access Granted', 'slug' => 'employee_companies'],
    ['table' => 'employee_onboarding_requests', 'field' => 'employee_id', 'label' => 'Onboarding Req', 'slug' => 'employee_onboarding_requests'],
    ['table' => 'employee_sidebar_preferences', 'field' => 'employee_id', 'label' => 'Sidebar Prefs', 'slug' => 'employee_sidebar_preferences'],
    ['table' => 'employee_system_access', 'field' => 'employee_id', 'label' => 'System Access', 'slug' => 'employee_system_access'],
    ['table' => 'equipment', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Assets', 'slug' => 'equipment'],
    ['table' => 'events', 'field' => 'assigned_to_employee_id', 'label' => 'My Events', 'slug' => 'events'],
    ['table' => 'explorer', 'field' => 'employee_id', 'label' => 'My Files', 'slug' => 'explorer'],
    ['table' => 'floor_plans', 'field' => 'created_by_employee_id', 'label' => 'Floor Plans', 'slug' => 'floor_plans'],
    ['table' => 'inventory_items', 'field' => 'last_employee_id', 'label' => 'Last Handled', 'slug' => 'inventory_items'],
    ['table' => 'note_labels', 'field' => 'employee_id', 'label' => 'Note Tags', 'slug' => 'notes'],
    ['table' => 'notes', 'field' => 'employee_id', 'label' => 'My Notes', 'slug' => 'notes'],
    ['table' => 'password_entries', 'field' => 'employee_id', 'label' => 'Vault Entries', 'slug' => 'passwords'],
    ['table' => 'password_folders', 'field' => 'employee_id', 'label' => 'Vault Folders', 'slug' => 'passwords'],
    ['table' => 'private_contacts', 'field' => 'employee_id', 'label' => 'My Contacts', 'slug' => 'private_contacts'],
    ['table' => 'registration_invitations', 'field' => 'invited_by_employee_id', 'label' => 'Invites Sent', 'slug' => 'registration_invitations'],
    ['table' => 'tickets', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Tickets', 'slug' => 'tickets'],
    ['table' => 'tickets', 'field' => 'created_by_employee_id', 'label' => 'Created Tickets', 'slug' => 'tickets'],
    ['table' => 'todo', 'field' => 'created_by_employee_id', 'label' => 'My Todos', 'slug' => 'todo'],
    ['table' => 'todo_categories', 'field' => 'cat_from_employee_id', 'label' => 'Todo Categories', 'slug' => 'todo'],
    ['table' => 'ui_configuration', 'field' => 'employee_id', 'label' => 'UI Preferences', 'slug' => 'settings'],
];

foreach ($stat_definitions as $def) {
    $sql = "SELECT COUNT(*) as cnt FROM `{$def['table']}` WHERE `{$def['field']}` = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $all_stats[] = array_merge($def, ['count' => (int)$res['cnt']]);
    mysqli_stmt_close($stmt);
}

// Specific Stats for Cards (Assigned Assets, My Tickets, My Vault)
$assigned_assets_count = 0;
foreach ($all_stats as $s) if ($s['table'] === 'equipment' && $s['field'] === 'assigned_to_employee_id') $assigned_assets_count = $s['count'];

$stmt = mysqli_prepare($conn, "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN ts.is_closed = 0 THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN ts.is_closed = 1 THEN 1 ELSE 0 END) as closed
    FROM tickets t
    JOIN ticket_statuses ts ON t.status_id = ts.id
    WHERE (t.created_by_employee_id = ? OR t.assigned_to_employee_id = ?)
    AND t.company_id = ?
");
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $user_id, $company_id);
mysqli_stmt_execute($stmt);
$ticket_summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$vault_entries_count = 0;
foreach ($all_stats as $s) if ($s['table'] === 'password_entries' && $s['field'] === 'employee_id') $vault_entries_count = $s['count'];

// Last Login
$stmt = mysqli_prepare($conn, "SELECT created_at FROM attempts WHERE employee_id = ? AND attempt_source = 'login' AND attempt_type = 'success' ORDER BY created_at DESC LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$last_login_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// System Access Overview
$stmt = mysqli_prepare($conn, "SELECT * FROM employee_system_access WHERE employee_id = ? AND company_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
mysqli_stmt_execute($stmt);
$system_access_overview = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Hardware Details (Workstation)
$stmt = mysqli_prepare($conn, "
    SELECT e.*, et.name as type_name, m.name as manufacturer_name, ls.name as status_name
    FROM equipment e
    JOIN equipment_types et ON e.equipment_type_id = et.id
    LEFT JOIN manufacturers m ON e.manufacturer_id = m.id
    LEFT JOIN equipment_statuses ls ON e.status_id = ls.id
    WHERE e.assigned_to_employee_id = ? AND e.company_id = ? AND et.name = 'Workstation'
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
mysqli_stmt_execute($stmt);
$workstation = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// Direct Reports
$stmt = mysqli_prepare($conn, "
    SELECT e.id, e.display_name, e.first_name, e.last_name, e.photo, ep.name as position
    FROM employees e
    LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id
    WHERE e.reports_to = ? AND e.company_id = ? AND e.employment_status_id IN (SELECT id FROM employee_statuses WHERE name = 'Active' AND company_id = ?)
");
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $company_id, $company_id);
mysqli_stmt_execute($stmt);
$direct_reports = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Reporting Chain
$reporting_chain = [];
$curr_id = $user_id;
while ($curr_id) {
    $stmt = mysqli_prepare($conn, "SELECT id, display_name, first_name, last_name, photo, reports_to, employee_position_id FROM employees WHERE id = ? AND on_orgchart = 1 AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $curr_id, $company_id);
    mysqli_stmt_execute($stmt);
    $member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($member) {
        $reporting_chain[] = $member;
        $curr_id = $member['reports_to'];
        if ($curr_id == $member['id']) break;
    } else break;
}

// Sidebar Preferences
$stmt = mysqli_prepare($conn, "SELECT * FROM employee_sidebar_preferences WHERE employee_id = ? AND company_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
mysqli_stmt_execute($stmt);
$sidebar_prefs_raw = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
$sidebar_prefs = [];
foreach ($sidebar_prefs_raw as $p) $sidebar_prefs[$p['entry_id']] = (int)$p['is_visible'];

// Recent Activity
$activity_list = [];
$stmt = mysqli_prepare($conn, "SELECT 'audit' as type, table_name, action, created_at FROM audit_logs WHERE employee_id = ? AND company_id = ? ORDER BY created_at DESC LIMIT 10");
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
mysqli_stmt_execute($stmt);
foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $r) $activity_list[] = $r;
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT 'login' as type, attempt_source, attempt_type, created_at FROM attempts WHERE employee_id = ? ORDER BY created_at DESC LIMIT 10");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $r) $activity_list[] = $r;
mysqli_stmt_close($stmt);

usort($activity_list, function($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
$activity_list = array_slice($activity_list, 0, 10);

// --- 2. HANDLE POST UPDATES ---
$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $theme = $_POST['theme'] ?? 'light';
        $ec_name = trim($_POST['emergency_contact_name'] ?? '');
        $ec_rel = trim($_POST['emergency_contact_relationship'] ?? '');
        $ec_phone = trim($_POST['emergency_contact_phone'] ?? '');

        $sql = "UPDATE employees SET work_email = ?, mobile_phone = ?, theme = ?, emergency_contact_name = ?, emergency_contact_relationship = ?, emergency_contact_phone = ? WHERE id = ? AND company_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssssssii', $email, $phone, $theme, $ec_name, $ec_rel, $ec_phone, $user_id, $company_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Profile updated successfully!';
            $message_type = 'success';
        } else {
            $message = 'Error updating profile.';
            $message_type = 'error';
        }
        mysqli_stmt_close($stmt);
    } elseif ($action === 'change_password') {
        $curr_pw = $_POST['current_password'] ?? '';
        $new_pw = $_POST['new_password'] ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';
        if (password_verify($curr_pw, $current_user['password'])) {
            if ($new_pw === $confirm_pw) {
                $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE employees SET password = ? WHERE id = ? AND company_id = ?");
                mysqli_stmt_bind_param($stmt, 'sii', $hash, $user_id, $company_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $message = 'Password updated!';
                $message_type = 'success';
            } else { $message = 'Passwords do not match.'; $message_type = 'error'; }
        } else { $message = 'Current password incorrect.'; $message_type = 'error'; }
    } elseif ($action === 'vault_key_change') {
        $curr_pw = $_POST['current_password'] ?? '';
        if (password_verify($curr_pw, $current_user['password'])) {
            $new_vk = $_POST['new_master_key'] ?? '';
            $confirm_vk = $_POST['confirm_master_key'] ?? '';
            if ($new_vk === $confirm_vk) {
                $old_vk_verify = $_POST['old_master_key_verify'] ?? '';
                $is_first = empty($current_user['vault_key_hash']);
                if ($is_first || password_verify($old_vk_verify, $current_user['vault_key_hash'])) {
                    mysqli_begin_transaction($conn);
                    try {
                        if (!$is_first) {
                            $res = itm_vault_reencrypt_password_entries($conn, $user_id, hash('sha256', $old_vk_verify), hash('sha256', $new_vk));
                            if (!$res['ok']) throw new Exception($res['message']);
                        }
                        $vk_hash = password_hash($new_vk, PASSWORD_DEFAULT);
                        $stmt = mysqli_prepare($conn, "UPDATE employees SET vault_key_hash = ? WHERE id = ? AND company_id = ?");
                        mysqli_stmt_bind_param($stmt, 'sii', $vk_hash, $user_id, $company_id);
                        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
                        mysqli_commit($conn);
                        if (isset($_SESSION['vault_key'])) $_SESSION['vault_key'] = hash('sha256', $new_vk);
                        $message = 'Vault key updated!'; $message_type = 'success';
                    } catch (Exception $e) { mysqli_rollback($conn); $message = $e->getMessage(); $message_type = 'error'; }
                } else { $message = 'Old Vault Key incorrect.'; $message_type = 'error'; }
            } else { $message = 'Vault keys do not match.'; $message_type = 'error'; }
        } else { $message = 'System password incorrect.'; $message_type = 'error'; }
    } elseif ($action === 'update_sidebar') {
        $items = $_POST['sidebar_items'] ?? [];
        mysqli_begin_transaction($conn);
        $stmt = mysqli_prepare($conn, "UPDATE employee_sidebar_preferences SET is_visible = 0 WHERE employee_id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        foreach ($items as $item_id) {
            $stmt = mysqli_prepare($conn, "INSERT INTO employee_sidebar_preferences (company_id, employee_id, entry_type, entry_id, is_visible) VALUES (?, ?, 'item', ?, 1) ON DUPLICATE KEY UPDATE is_visible = 1");
            mysqli_stmt_bind_param($stmt, 'iis', $company_id, $user_id, $item_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        mysqli_commit($conn);
        $message = 'Sidebar updated!'; $message_type = 'success';
    } elseif ($action === 'upload_photo') {
        if (isset($_FILES['photo'])) {
            $res = emp_profile_photo_store_upload($company_id, $current_user, $_FILES['photo']);
            if ($res['ok']) {
                $stmt = mysqli_prepare($conn, "UPDATE employees SET photo = ? WHERE id = ? AND company_id = ?");
                mysqli_stmt_bind_param($stmt, 'sii', $res['filename'], $user_id, $company_id);
                mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
                $message = 'Photo updated!'; $message_type = 'success';
            } else { $message = $res['error']; $message_type = 'error'; }
        }
    }
    // Reload user data
    $stmt = mysqli_prepare($conn, 'SELECT e.*, ep.name AS position_name, d.name AS department_name, es.name AS status_name FROM employees e LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN employee_statuses es ON e.employment_status_id = es.id WHERE e.id = ? AND e.company_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);
    mysqli_stmt_execute($stmt); $current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
}

// Profile Completeness
$pc_fields = ['photo', 'work_email', 'mobile_phone', 'emergency_contact_name', 'emergency_contact_phone', 'vault_key_hash', 'birthday'];
$pc_filled = 0;
foreach ($pc_fields as $f) if (!empty($current_user[$f])) $pc_filled++;
$pc_percent = round(($pc_filled / count($pc_fields)) * 100);

$messageClass = ($message_type === 'success') ? 'crud_success' : (($message_type === 'error') ? 'crud_error' : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?php echo sanitize($current_user['display_name'] ?: $current_user['username']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .emp-dashboard { display: flex; flex-direction: column; gap: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
        .stat-card { background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e1e4e8; text-decoration: none; color: inherit; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-3px); border-color: #0366d6; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-val { font-size: 22px; font-weight: 700; display: block; }
        .stat-lbl { font-size: 12px; color: #586069; }
        .layout-2col { display: grid; grid-template-columns: 280px 1fr; gap: 20px; }
        .col-left { display: flex; flex-direction: column; gap: 20px; }
        .col-right { display: flex; flex-direction: column; gap: 20px; }
        .profile-pic { width: 120px; height: 120px; border-radius: 50%; border: 4px solid #f6f8fa; margin: 0 auto; overflow: hidden; position: relative; cursor: pointer; background: #eee; display: flex; align-items: center; justify-content: center; }
        .profile-pic img { width: 100%; height: 100%; object-fit: cover; }
        .profile-pic .overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.4); color: #fff; display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.2s; font-size: 12px; }
        .profile-pic:hover .overlay { opacity: 1; }
        .org-path { position: relative; padding-left: 20px; border-left: 2px solid #e1e4e8; list-style: none; margin: 0; font-size: 13px; }
        .org-path li { margin-bottom: 10px; position: relative; }
        .org-path li::before { content: ''; position: absolute; left: -26px; top: 6px; width: 10px; height: 10px; border-radius: 50%; background: #fff; border: 2px solid #0366d6; }
        .progress { height: 6px; background: #e1e4e8; border-radius: 3px; overflow: hidden; margin: 5px 0; }
        .progress-bar { height: 100%; background: #28a745; }
        .access-item { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; margin: 2px; border: 1px solid #e1e4e8; }
        .access-on { background: #e6ffed; color: #22863a; }
        .access-off { background: #ffeef0; color: #cb2431; opacity: 0.7; }
        .timeline { list-style: none; padding: 0; font-size: 13px; }
        .timeline-item { padding-bottom: 12px; border-left: 2px solid #eee; padding-left: 15px; position: relative; }
        .timeline-item::after { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #0366d6; }
    </style>
</head>
<body>
<div class="container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        <div class="content">
            <?php if ($message) echo "<div class='$messageClass'>".sanitize($message)."</div>"; ?>

            <div class="emp-dashboard">
                <!-- 1. DASHBOARD STAT CARDS -->
                <div class="stats-grid">
                    <a href="modules/equipment/index.php" class="stat-card">
                        <span class="stat-val"><?php echo $assigned_assets_count; ?></span>
                        <span class="stat-lbl">💻 My Assets</span>
                    </a>
                    <a href="modules/tickets/index.php" class="stat-card">
                        <span class="stat-val"><?php echo (int)$ticket_summary['open']; ?>/<?php echo (int)$ticket_summary['total']; ?></span>
                        <span class="stat-lbl">🎫 My Tickets (Open/Total)</span>
                    </a>
                    <a href="modules/passwords/index.php" class="stat-card">
                        <span class="stat-val"><?php echo $vault_entries_count; ?></span>
                        <span class="stat-lbl">🔐 Vault Entries</span>
                    </a>
                    <div class="stat-card">
                        <span class="stat-val"><?php echo $last_login_row ? date('d/m/y', strtotime($last_login_row['created_at'])) : 'Never'; ?></span>
                        <span class="stat-lbl">🕒 Last Login</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-val"><?php echo date('d/m/y', strtotime($current_user['created_at'])); ?></span>
                        <span class="stat-lbl">📅 Joined Date</span>
                    </div>
                    <!-- All other 30 combinations grid -->
                    <?php foreach ($all_stats as $s): if ($s['count'] > 0 && !in_array($s['table'], ['equipment','tickets','password_entries','attempts'])): ?>
                        <a href="modules/<?php echo $s['slug']; ?>/index.php" class="stat-card">
                            <span class="stat-val"><?php echo $s['count']; ?></span>
                            <span class="stat-lbl"><?php echo sanitize($s['label']); ?></span>
                        </a>
                    <?php endif; endforeach; ?>
                </div>

                <div class="layout-2col">
                    <!-- LEFT COLUMN -->
                    <div class="col-left">
                        <div class="card" style="text-align:center;">
                            <div class="profile-pic" onclick="document.getElementById('file-photo').click()">
                                <?php $purl = emp_profile_photo_url($current_user); ?>
                                <?php if ($purl): ?><img src="<?php echo $purl; ?>" alt="Profile"><?php else: ?>👤<?php endif; ?>
                                <div class="overlay">Upload</div>
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="form-photo" style="display:none;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="upload_photo">
                                <input type="file" name="photo" id="file-photo" onchange="this.form.submit()">
                            </form>
                            <h3 style="margin:10px 0 5px;"><?php echo sanitize($current_user['display_name'] ?: $current_user['first_name'].' '.$current_user['last_name']); ?></h3>
                            <div style="font-size:13px; color:#586069; margin-bottom:10px;"><?php echo sanitize($current_user['position_name']); ?></div>
                            <span class="badge badge-success"><?php echo sanitize($current_user['status_name']); ?></span>

                            <div style="margin-top:20px; text-align:left;">
                                <div class="stat-lbl">Profile Completeness</div>
                                <div class="progress"><div class="progress-bar" style="width:<?php echo $pc_percent; ?>%;"></div></div>
                                <div style="font-size:11px; text-align:right;"><?php echo $pc_percent; ?>%</div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>Org Chart Path</strong></div>
                            <ul class="org-path">
                                <?php foreach (array_reverse($reporting_chain) as $m): ?>
                                    <li>
                                        <strong><?php echo sanitize($m['display_name'] ?: $m['first_name']); ?></strong><br>
                                        <small style="color:#586069;"><?php echo sanitize($m['id'] == $user_id ? 'You' : 'Manager'); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="col-right">
                        <div class="card">
                            <div class="card-header"><strong>✏️ Edit Profile</strong></div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="form-row">
                                    <div class="form-group"><label>Full Name</label><input type="text" value="<?php echo sanitize($current_user['first_name'].' '.$current_user['last_name']); ?>" readonly></div>
                                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize($current_user['work_email']); ?>" required></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo sanitize($current_user['mobile_phone']); ?>"></div>
                                    <div class="form-group"><label>Theme</label><select name="theme"><option value="light" <?php if($current_user['theme']=='light')echo'selected';?>>Light</option><option value="dark" <?php if($current_user['theme']=='dark')echo'selected';?>>Dark</option></select></div>
                                </div>
                                <div class="card-header" style="background:none; border:none; padding:10px 0;"><strong>Emergency Contact</strong></div>
                                <div class="form-row">
                                    <div class="form-group"><label>Name</label><input type="text" name="emergency_contact_name" value="<?php echo sanitize($current_user['emergency_contact_name']); ?>"></div>
                                    <div class="form-group"><label>Relationship</label><input type="text" name="emergency_contact_relationship" value="<?php echo sanitize($current_user['emergency_contact_relationship']); ?>"></div>
                                    <div class="form-group"><label>Phone</label><input type="text" name="emergency_contact_phone" value="<?php echo sanitize($current_user['emergency_contact_phone']); ?>"></div>
                                </div>
                                <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                            </form>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>🌐 System Access Overview</strong></div>
                            <div>
                                <?php $af = ['network_access'=>'Network','email_account'=>'Email','opera_username'=>'Opera/PMS','micros_emc'=>'Micros EMC','office_key_card'=>'Office Key','mobile_email'=>'Mobile Email'];
                                foreach ($af as $f=>$l): $on = ($system_access_overview && isset($system_access_overview[$f]) && $system_access_overview[$f]) || (isset($current_user[$f]) && $current_user[$f]); ?>
                                    <span class="access-item <?php echo $on?'access-on':'access-off';?>"><?php echo $on?'✅':'❌';?> <?php echo $l;?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if($workstation): ?>
                        <div class="card">
                            <div class="card-header"><strong>💻 My Hardware</strong></div>
                            <div style="font-size:13px;">
                                <strong><?php echo sanitize($workstation['manufacturer_name'].' '.$workstation['model']); ?></strong><br>
                                <code><?php echo sanitize($workstation['hostname']); ?></code> • SN: <?php echo sanitize($workstation['serial_number']); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-row">
                            <div class="card">
                                <div class="card-header"><strong>🔑 Change Password</strong></div>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="change_password">
                                    <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
                                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
                                    <div class="form-group"><label>Confirm</label><input type="password" name="confirm_password" required></div>
                                    <button type="submit" class="btn btn-primary">💾 Update</button>
                                </form>
                            </div>
                            <div class="card" id="vault-security">
                                <div class="card-header"><strong>🔐 Vault Security</strong></div>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="vault_key_change">
                                    <div class="form-group"><label>System Password</label><input type="password" name="current_password" required></div>
                                    <?php if($current_user['vault_key_hash']):?><div class="form-group"><label>Current Master Key</label><input type="password" name="old_master_key_verify" required></div><?php endif;?>
                                    <div class="form-group"><label>New Master Key</label><input type="password" name="new_master_key" required></div>
                                    <div class="form-group"><label>Confirm</label><input type="password" name="confirm_master_key" required></div>
                                    <button type="submit" class="btn btn-primary">💾 Update Vault</button>
                                </form>
                            </div>
                        </div>

                        <?php if($direct_reports): ?>
                        <div class="card">
                            <div class="card-header"><strong>👥 Direct Reports</strong></div>
                            <div class="stats-grid">
                                <?php foreach($direct_reports as $dr): ?>
                                    <div class="stat-card" style="text-align:center;">
                                        <div style="width:40px;height:40px;border-radius:50%;margin:0 auto 8px;overflow:hidden;background:#eee;">
                                            <?php if($dr['photo']):?><img src="<?php echo emp_profile_photo_url($dr);?>" style="width:100%;height:100%;object-fit:cover;"><?php else:?>👤<?php endif;?>
                                        </div>
                                        <div style="font-size:12px;font-weight:700;"><?php echo sanitize($dr['display_name']?:$dr['first_name']);?></div>
                                        <div style="font-size:10px;color:#586069;"><?php echo sanitize($dr['position']);?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header"><strong>📑 Personalized Sidebar</strong></div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="update_sidebar">
                                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:10px;">
                                    <?php foreach(itm_sidebar_item_catalog() as $id=>$item): if($id==='dashboard_link')continue; ?>
                                        <label class="itm-checkbox-control">
                                            <input type="checkbox" name="sidebar_items[]" value="<?php echo $id; ?>" <?php echo ($sidebar_prefs[$id]??1)?'checked':''; ?>>
                                            <span><?php echo sanitize($item['label']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn btn-primary" style="margin-top:15px;">💾 Save Prefs</button>
                            </form>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>🕒 Recent Activity</strong></div>
                            <ul class="timeline">
                                <?php foreach($activity_list as $act): ?>
                                    <li class="timeline-item">
                                        <?php if($act['type']=='audit'):?><strong><?php echo sanitize($act['action']);?></strong> in <?php echo sanitize($act['table_name']);?>
                                        <?php else:?><strong>Login</strong> <?php echo sanitize($act['attempt_type']);?> (<?php echo sanitize($act['attempt_source']);?>)<?php endif;?>
                                        <div style="color:#586069; font-size:11px;"><?php echo date('d M Y, H:i', strtotime($act['created_at']));?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;"><a class="btn" href="dashboard.php">🔙 Dashboard</a></div>
        </div>
    </div>
</div>
<script src="js/theme.js"></script>
<script>
    const pic = document.querySelector('.profile-pic');
    pic.addEventListener('dragover', e=>{ e.preventDefault(); pic.style.borderColor='#0366d6'; });
    pic.addEventListener('dragleave', ()=>{ pic.style.borderColor='#f6f8fa'; });
    pic.addEventListener('drop', e=>{ e.preventDefault();
        if(e.dataTransfer.files.length){
            document.getElementById('file-photo').files = e.dataTransfer.files;
            document.getElementById('form-photo').submit();
        }
    });
</script>
</body>
</html>
