<?php

$crud_title = 'Contacts';



require '../../config/config.php';
$csrfToken = itm_get_csrf_token();

// 1. Fetch Departments
$deptSql = "SELECT id, name, extension, dect, phone FROM departments WHERE company_id = ? AND active = 1 ORDER BY name ASC";
$deptStmt = mysqli_prepare($conn, $deptSql);
if (!$deptStmt) {
    die("Database error (prepare departments): " . mysqli_error($conn));
}
mysqli_stmt_bind_param($deptStmt, 'i', $company_id);
if (!mysqli_stmt_execute($deptStmt)) {
    die("Database error (execute departments): " . mysqli_stmt_error($deptStmt));
}
$deptRes = mysqli_stmt_get_result($deptStmt);
$departments = [];
while ($d = mysqli_fetch_assoc($deptRes)) { $departments[] = $d; }
mysqli_stmt_close($deptStmt);

// 2. Fetch Employees
$empSql = "SELECT e.id, e.department_id, e.first_name, e.last_name, e.work_email, e.extension, e.mobile_phone, e.external_number, e.dect, ep.name as position_title FROM employees e LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id LEFT JOIN employee_statuses es ON e.employment_status_id = es.id WHERE e.company_id = ? AND e.on_contacts = 1 AND (es.active = 1 OR es.id IS NULL) ORDER BY e.reports_to ASC, e.first_name ASC, e.last_name ASC";
$empStmt = mysqli_prepare($conn, $empSql);
if (!$empStmt) {
    die("Database error (prepare employees): " . mysqli_error($conn));
}
mysqli_stmt_bind_param($empStmt, 'i', $company_id);
if (!mysqli_stmt_execute($empStmt)) {
    die("Database error (execute employees): " . mysqli_stmt_error($empStmt));
}
$empRes = mysqli_stmt_get_result($empStmt);
$employeesByDept = [];
while ($e = mysqli_fetch_assoc($empRes)) { $employeesByDept[(int)$e['department_id']][] = $e; }
mysqli_stmt_close($empStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Contacts Resume';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title><link rel="stylesheet" href="../../css/styles.css">
    <style>
        .dept-header td {
            background-color: var(--bg-tertiary);
            color: var(--accent);
            font-weight: 700;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 14px 12px;
        }
        .inline-edit {
            cursor: pointer;
            border-bottom: 1px dashed var(--text-tertiary);
            transition: all 0.2s;
        }
        .inline-edit:hover {
            border-bottom-color: var(--accent);
            color: var(--accent);
        }
        .inline-edit input {
            padding: 2px 4px;
            font-size: inherit;
            font-family: inherit;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--accent);
            border-radius: 4px;
            width: auto;
            max-width: 100%;
        }
        small {
            color: var(--text-secondary);
            display: block;
            margin-top: 2px;
            font-weight: 400;
        }
        tr:hover:not(.dept-header) {
            background-color: var(--bg-secondary);
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>Contacts 📓</h1>
            <div class="card">
                <table class="table" data-itm-no-import-excel="1">
                    <thead><tr><th>Name</th><th>Extension</th><th>Dect</th><th>Mobile Phone</th><th>External Number</th><th>Job Role</th></tr></thead>
                    <tbody>
                        <?php foreach ($departments as $dept): $did = (int)$dept['id']; ?>
                            <tr class="dept-header">
                                <td><?php echo sanitize($dept['name']); ?></td>
                                <td><span class="inline-edit" data-type="dept" data-id="<?php echo $did; ?>" data-field="extension"><?php echo sanitize($dept['extension'] ?: '—'); ?></span></td>
                                <td><span class="inline-edit" data-type="dept" data-id="<?php echo $did; ?>" data-field="dect"><?php echo sanitize($dept['dect'] ?: '—'); ?></span></td>
                                <td>—</td>
                                <td><span class="inline-edit" data-type="dept" data-id="<?php echo $did; ?>" data-field="phone"><?php echo sanitize($dept['phone'] ?: '—'); ?></span></td>
                                <td>—</td>
                            </tr>
                            <?php if (isset($employeesByDept[$did])): foreach ($employeesByDept[$did] as $emp): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($emp['work_email'])): ?>
                                            <a href="mailto:<?php echo sanitize($emp['work_email']); ?>" data-outlook-link="1" data-outlook-href="ms-outlook://compose?to=<?php echo sanitize($emp['work_email']); ?>" style="text-decoration:none;color:inherit;">
                                                <strong><?php echo sanitize($emp['first_name'].' '.$emp['last_name']); ?></strong>
                                            </a>
                                        <?php else: ?>
                                            <strong><?php echo sanitize($emp['first_name'].' '.$emp['last_name']); ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="inline-edit" data-type="emp" data-id="<?php echo (int)$emp['id']; ?>" data-field="extension"><?php echo sanitize($emp['extension'] ?: '—'); ?></span></td>
                                    <td><span class="inline-edit" data-type="emp" data-id="<?php echo (int)$emp['id']; ?>" data-field="dect"><?php echo sanitize($emp['dect'] ?: '—'); ?></span></td>
                                    <td><span class="inline-edit" data-type="emp" data-id="<?php echo (int)$emp['id']; ?>" data-field="mobile_phone"><?php echo sanitize($emp['mobile_phone'] ?: '—'); ?></span></td>
                                    <td><span class="inline-edit" data-type="emp" data-id="<?php echo (int)$emp['id']; ?>" data-field="external_number"><?php echo sanitize($emp['external_number'] ?: '—'); ?></span></td>
                                    <td><?php echo sanitize($emp['position_title'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.inline-edit').forEach(el => {
    el.onclick = function() {
        if (this.querySelector('input')) return;
        const oldVal = this.innerText === '—' ? '' : this.innerText;
        const input = document.createElement('input');
        input.value = oldVal;
        this.innerHTML = ''; this.appendChild(input); input.focus();
        input.onblur = () => {
            const newVal = input.value;
            if (newVal !== oldVal) {
                fetch('api/inline_edit.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `csrf_token=<?php echo $csrfToken; ?>&type=${this.dataset.type}&id=${this.dataset.id}&field=${this.dataset.field}&value=${encodeURIComponent(newVal)}`
                }).then(r => r.json()).then(res => { if (!res.ok) alert(res.error); });
            }
            this.innerText = newVal || '—';
        };
        input.onkeydown = (e) => {
            if (e.key === 'Enter') input.blur();
            if (e.key === 'Escape') {
                input.value = oldVal;
                input.blur();
            }
        };
    };
});

document.addEventListener('click', function (event) {
    const link = event.target.closest('a[data-outlook-link="1"]');
    if (!link) return;
    const outlookHref = link.getAttribute('data-outlook-href');
    if (outlookHref) {
        // Attempt to open custom protocol
        window.location.href = outlookHref;
    }
});
</script>
</body></html>
