<?php
require '../../config/config.php';
$csrfToken = itm_get_csrf_token();
$deptSql = "SELECT * FROM departments WHERE company_id = ? ORDER BY name ASC";
$deptStmt = mysqli_prepare($conn, $deptSql);
mysqli_stmt_bind_param($deptStmt, 'i', $company_id);
mysqli_stmt_execute($deptStmt);
$deptRes = mysqli_stmt_get_result($deptStmt);
$departments = [];
while ($d = mysqli_fetch_assoc($deptRes)) { $departments[] = $d; }
mysqli_stmt_close($deptStmt);
$empSql = "SELECT e.*, ep.name as position_title FROM employees e LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id WHERE e.company_id = ? AND e.on_contacts = 1 ORDER BY e.reports_to ASC, e.first_name ASC, e.last_name ASC";
$empStmt = mysqli_prepare($conn, $empSql);
mysqli_stmt_bind_param($empStmt, 'i', $company_id);
mysqli_stmt_execute($empStmt);
$empRes = mysqli_stmt_get_result($empStmt);
$employeesByDept = [];
while ($e = mysqli_fetch_assoc($empRes)) { $employeesByDept[(int)$e['department_id']][] = $e; }
mysqli_stmt_close($empStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Contacts Resume</title><link rel="stylesheet" href="../../css/styles.css">
    <style>.dept-header { background: #f0f0f0; font-weight: bold; } .inline-edit { cursor: pointer; border-bottom: 1px dashed #999; }</style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>Contacts 📓</h1>
            <div class="card">
                <table class="table">
                    <thead><tr><th>Name / Position</th><th>Email</th><th>Deck</th><th>Phone</th></tr></thead>
                    <tbody>
                        <?php foreach ($departments as $dept): $did = (int)$dept['id']; ?>
                            <tr class="dept-header">
                                <td><?php echo sanitize($dept['name']); ?></td>
                                <td><span class="inline-edit" data-type="dept" data-id="<?php echo $did; ?>" data-field="email"><?php echo sanitize($dept['email'] ?: '—'); ?></span></td>
                                <td><span class="inline-edit" data-type="dept" data-id="<?php echo $did; ?>" data-field="deck"><?php echo sanitize($dept['deck'] ?: '—'); ?></span></td>
                                <td><span class="inline-edit" data-type="dept" data-id="<?php echo $did; ?>" data-field="phone"><?php echo sanitize($dept['phone'] ?: '—'); ?></span></td>
                            </tr>
                            <?php if (isset($employeesByDept[$did])): foreach ($employeesByDept[$did] as $emp): ?>
                                <tr>
                                    <td><strong><?php echo sanitize($emp['first_name'].' '.$emp['last_name']); ?></strong><br><small><?php echo sanitize($emp['position_title'] ?: '—'); ?></small></td>
                                    <td><span class="inline-edit" data-type="emp" data-id="<?php echo (int)$emp['id']; ?>" data-field="work_email"><?php echo sanitize($emp['work_email'] ?: '—'); ?></span></td>
                                    <td><span class="inline-edit" data-type="emp" data-id="<?php echo (int)$emp['id']; ?>" data-field="deck"><?php echo sanitize($emp['deck'] ?: '—'); ?></span></td>
                                    <td><span class="inline-edit" data-type="emp" data-id="<?php echo (int)$emp['id']; ?>" data-field="work_phone"><?php echo sanitize($emp['work_phone'] ?: '—'); ?></span></td>
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
    };
});
</script>
</body></html>
