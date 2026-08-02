<?php
/**
 * Start date field for employee create/edit forms (after request fields).
 *
 * Expects: $form (array with start_date).
 */
?>
<div class="form-group">
    <label>Start Date</label>
    <input type="date" name="start_date" value="<?= sanitize((string)($form['start_date'] ?? '')) ?>">
</div>
