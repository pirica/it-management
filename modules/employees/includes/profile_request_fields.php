<?php
/**
 * Request / termination request fields for employee create/edit forms (before start date).
 *
 * Expects: $form (array with request_date, requested_by, termination_requested_by).
 */
?>
<div class="form-group">
    <label>Request Date</label>
    <?php itm_render_uk_date_input('request_date', 'employee-request-date', (string) ($form['request_date'] ?? '')); ?>
</div>
<div class="form-group">
    <label>Requested By</label>
    <input type="text" name="requested_by" value="<?= sanitize((string)($form['requested_by'] ?? '')) ?>">
</div>
<div class="form-group">
    <label>Termination Requested By</label>
    <input type="text" name="termination_requested_by" value="<?= sanitize((string)($form['termination_requested_by'] ?? '')) ?>">
</div>
