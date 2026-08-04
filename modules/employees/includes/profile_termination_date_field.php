<?php
/**
 * Termination date field for employee create/edit forms (after employee type).
 *
 * Expects: $form (array with termination_date).
 */
?>
<div class="form-group">
    <label>Termination Date</label>
    <input type="date" name="termination_date" value="<?= sanitize((string)($form['termination_date'] ?? '')) ?>">
</div>
