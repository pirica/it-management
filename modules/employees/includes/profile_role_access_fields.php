<?php
require_once '../../includes/itm_role_assignment_rights.php';

/**
 * Employee role and access level FK selects (tenant-scoped with persisted-value fallback).
 */
$empRoleAccessForm = $form ?? [];
$empRoleAccessCompanyId = (int)($company_id ?? 0);

// Role Assignment Rights Filtering
$empRoleAccessAssignableIds = [];
$empRoleAccessCurrentUserId = (int)($_SESSION['employee_id'] ?? 0);
$empRoleAccessIsAdminUser = itm_is_admin($conn, $empRoleAccessCurrentUserId);

if (!$empRoleAccessIsAdminUser) {
    $empRoleAccessUserRoleId = itm_get_employee_role_id($conn, $empRoleAccessCurrentUserId);
    $empRoleAccessAssignableIds = itm_get_assignable_role_ids($conn, $empRoleAccessCompanyId, $empRoleAccessUserRoleId);
}

$empRoleAccessRolesSql = 'SELECT id, name FROM employee_roles WHERE company_id=' . $empRoleAccessCompanyId;
if ($empRoleAccessIsAdminUser) {
    // Admins see all roles for their company
} elseif (!empty($empRoleAccessAssignableIds)) {
    $empRoleAccessRolesSql .= ' AND id IN (' . implode(',', $empRoleAccessAssignableIds) . ')';
} else {
    $empRoleAccessRolesSql .= ' AND 1=0'; // Non-admin with no assignable rights sees nothing
}
$empRoleAccessRolesSql .= ' ORDER BY name';

$empRoleAccessRoles = mysqli_query($conn, $empRoleAccessRolesSql);
$empRoleAccessLevels = mysqli_query($conn, 'SELECT id, name FROM access_levels WHERE company_id=' . $empRoleAccessCompanyId . ' ORDER BY name');
$empRoleAccessRoleLookup = [];
if ($empRoleAccessRoles) {
    while ($roleRow = mysqli_fetch_assoc($empRoleAccessRoles)) {
        $roleId = (int)($roleRow['id'] ?? 0);
        if ($roleId > 0) {
            $empRoleAccessRoleLookup[$roleId] = (string)($roleRow['name'] ?? '');
        }
    }
}
$empRoleAccessLevelLookup = [];
if ($empRoleAccessLevels) {
    while ($levelRow = mysqli_fetch_assoc($empRoleAccessLevels)) {
        $levelId = (int)($levelRow['id'] ?? 0);
        if ($levelId > 0) {
            $empRoleAccessLevelLookup[$levelId] = (string)($levelRow['name'] ?? '');
        }
    }
}
$empSelectedRoleId = (string)($empRoleAccessForm['role_id'] ?? '');
$empSelectedAccessLevelId = (string)($empRoleAccessForm['access_level_id'] ?? '');
?>
<div class="form-group"><label>Role</label>
    <select name="role_id" data-addable-select="1" data-add-table="employee_roles" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="role">
        <option value="">-- None --</option>
        <?php foreach ($empRoleAccessRoleLookup as $roleId => $roleName): ?>
            <option value="<?php echo (int)$roleId; ?>" <?php echo ((string)$roleId === $empSelectedRoleId) ? 'selected' : ''; ?>><?php echo sanitize($roleName); ?></option>
        <?php endforeach; ?>
        <?php if ($empSelectedRoleId !== '' && !isset($empRoleAccessRoleLookup[(int)$empSelectedRoleId])): ?>
            <option value="<?php echo (int)$empSelectedRoleId; ?>" selected>#<?php echo (int)$empSelectedRoleId; ?></option>
        <?php endif; ?>
        <option value="__add_new__">➕</option>
    </select>
</div>
<div class="form-group"><label>Access Level</label>
    <select name="access_level_id" data-addable-select="1" data-add-table="access_levels" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="access level">
        <option value="">-- None --</option>
        <?php foreach ($empRoleAccessLevelLookup as $levelId => $levelName): ?>
            <option value="<?php echo (int)$levelId; ?>" <?php echo ((string)$levelId === $empSelectedAccessLevelId) ? 'selected' : ''; ?>><?php echo sanitize($levelName); ?></option>
        <?php endforeach; ?>
        <?php if ($empSelectedAccessLevelId !== '' && !isset($empRoleAccessLevelLookup[(int)$empSelectedAccessLevelId])): ?>
            <option value="<?php echo (int)$empSelectedAccessLevelId; ?>" selected>#<?php echo (int)$empSelectedAccessLevelId; ?></option>
        <?php endif; ?>
        <option value="__add_new__">➕</option>
    </select>
</div>
