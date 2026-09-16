<?php
/**
 * Termination date field for employee create/edit forms (after employee type).
 *
 * Expects: $form (array with termination_date).
 */
?>
<div class="form-group">
    <label>Termination Date</label>
    <?php itm_render_uk_date_input('termination_date', 'employee-termination-date', (string) ($form['termination_date'] ?? '')); ?>
</div>
