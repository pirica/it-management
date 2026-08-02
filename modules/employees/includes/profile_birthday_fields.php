<?php
/**
 * Birthday and hide-year fields for employee create/edit forms.
 *
 * Expects: $form (array).
 */
?>
<div class="form-group">
    <label>Birthday</label>
    <input type="date" name="birthday" value="<?= sanitize((string)($form['birthday'] ?? '')) ?>">
</div>
<div class="form-group">
    <label>Hide Year</label>
    <label class="itm-checkbox-control">
        <input type="checkbox" name="hide_year" value="1" <?= ((int)($form['hide_year'] ?? 0) === 1) ? 'checked' : '' ?>>
        <span>Hide Year <span class="itm-check-indicator" aria-hidden="true"><?= ((int)($form['hide_year'] ?? 0) === 1) ? '✅' : '❌' ?></span></span>
    </label>
</div>
