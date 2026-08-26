<?php
/**
 * Birthday and hide-year fields for employee create/edit forms.
 *
 * Expects: $form (array).
 */
?>
<div class="form-group">
    <label>Birthday</label>
    <?php itm_render_uk_date_input('birthday', 'employee-birthday', (string) ($form['birthday'] ?? '')); ?>
</div>
<div class="form-group">
    <label>Hide Year</label>
    <label class="itm-checkbox-control">
        <input type="checkbox" name="hide_year" value="1" <?= ((int)($form['hide_year'] ?? 0) === 1) ? 'checked' : '' ?>>
        <span>Hide Year <span class="itm-check-indicator" aria-hidden="true"><?= ((int)($form['hide_year'] ?? 0) === 1) ? '✅' : '❌' ?></span></span>
    </label>
</div>
