<?php
/**
 * Start date field for employee create/edit forms (after request fields).
 *
 * Expects: $form (array with start_date).
 */
?>
<div class="form-group">
    <label>Start Date</label>
    <?php itm_render_uk_date_input('start_date', 'employee-start-date', (string) ($form['start_date'] ?? '')); ?>
</div>
