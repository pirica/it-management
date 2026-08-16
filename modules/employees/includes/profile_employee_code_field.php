<?php
/**
 * Employee code field for employee create/edit forms.
 *
 * Expects: $form (array with employee_code).
 */
?>
<div class="form-group">
    <label>Employee Code</label>
    <input type="text" name="employee_code" value="<?= sanitize((string)($form['employee_code'] ?? '')) ?>">
</div>
