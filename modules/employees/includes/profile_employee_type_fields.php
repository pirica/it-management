<?php
/**
 * Employee type select for employee create/edit forms (before birthday fields).
 *
 * Expects: $conn, $company_id, $form (array with employee_type_id).
 */
$employeeTypeOptions = [];
$defaultTeamMemberId = 0;
$typeRes = mysqli_query($conn, 'SELECT id, name_type FROM employee_type WHERE company_id=' . (int)$company_id . ' AND active=1 ORDER BY name_type');
if ($typeRes) {
    while ($typeRow = mysqli_fetch_assoc($typeRes)) {
        $typeId = (int)($typeRow['id'] ?? 0);
        $typeName = (string)($typeRow['name_type'] ?? '');
        if ($typeId > 0 && $typeName !== '') {
            $employeeTypeOptions[] = ['id' => $typeId, 'name_type' => $typeName];
            if ($defaultTeamMemberId === 0 && strcasecmp($typeName, 'Team member') === 0) {
                $defaultTeamMemberId = $typeId;
            }
        }
    }
}
$selectedEmployeeTypeId = (string)($form['employee_type_id'] ?? '');
if ($selectedEmployeeTypeId === '' && $defaultTeamMemberId > 0) {
    $selectedEmployeeTypeId = (string)$defaultTeamMemberId;
}
?>
<div class="form-group">
    <label>Employee Type</label>
    <select name="employee_type_id" data-addable-select="1" data-add-table="employee_type" data-add-id-col="id" data-add-label-col="name_type" data-add-company-scoped="1" data-add-friendly="employee type">
        <?php foreach ($employeeTypeOptions as $employeeTypeOption): ?>
            <option value="<?= (int)$employeeTypeOption['id'] ?>" <?= ((string)$employeeTypeOption['id'] === $selectedEmployeeTypeId) ? 'selected' : '' ?>><?= sanitize($employeeTypeOption['name_type']) ?></option>
        <?php endforeach; ?>
        <option value="__add_new__">➕</option>
    </select>
</div>
