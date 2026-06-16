<?php
/**
 * Shared profile fields for employee create/edit forms.
 *
 * Expects: $form (array), optional $employee row for edit preview.
 */
$empPhotoEmployee = [
    'username' => (string)($form['username'] ?? ($employee['username'] ?? '')),
    'user_id' => (int)($form['user_id'] ?? ($employee['user_id'] ?? 0)),
    'photo' => (string)($form['photo'] ?? ($employee['photo'] ?? '')),
];
$empPhotoUrl = emp_profile_photo_url($empPhotoEmployee);
$empCanUploadPhoto = emp_profile_photo_can_store($empPhotoEmployee);
?>
<div class="form-group" style="grid-column: 1 / -1;">
    <label>Profile Photo</label>
    <div class="position-relative itm-photo-upload-target cursor-pointer text-center" style="border: 2px dashed currentColor; border-radius: 50%; padding: 5px; width: 110px; height: 110px;<?= $empCanUploadPhoto ? '' : ' opacity:0.6; pointer-events:none;' ?>">
        <input type="file" name="photo" id="employee-photo-input" class="d-none" accept=".png,.jpg,.jpeg,image/png,image/jpeg" style="display:none;" <?= $empCanUploadPhoto ? '' : 'disabled' ?>>
        <label for="employee-photo-input" class="mb-0 cursor-pointer d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
            <?php if ($empPhotoUrl !== ''): ?>
                <img src="<?= sanitize($empPhotoUrl) ?>" id="employee-photo-preview" class="rounded-circle" width="100" height="100" style="object-fit: cover;" onerror="this.onerror=null; this.src='../../images/5x5-pixel.png';">
            <?php else: ?>
                <div id="employee-photo-placeholder" class="rounded-circle d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                    <span style="font-size:28px;">👤</span>
                </div>
                <img src="../../images/5x5-pixel.png" id="employee-photo-preview" class="rounded-circle d-none" width="100" height="100" style="object-fit: cover;">
            <?php endif; ?>
        </label>
    </div>
    <small class="text-muted d-block mt-2">
        <?php if ($empCanUploadPhoto): ?>
            Drag and drop or click to upload PNG/JPG. Saved as Private/<?= sanitize(emp_profile_photo_safe_username($empPhotoEmployee['username'])) ?>_<?= (int)$empPhotoEmployee['user_id'] ?>/profile/.
        <?php else: ?>
            Set username and link a user account to enable profile photo upload.
        <?php endif; ?>
    </small>
</div>
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
<script src="../../js/itm-upload-helper.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof itmUploadHelper !== 'undefined') {
        itmUploadHelper.setupByClass('.itm-photo-upload-target');
    }
    var photoInput = document.getElementById('employee-photo-input');
    if (!photoInput) {
        return;
    }
    photoInput.addEventListener('change', function (evt) {
        var file = evt.target.files && evt.target.files[0];
        if (!file) {
            return;
        }
        var preview = document.getElementById('employee-photo-preview');
        var placeholder = document.getElementById('employee-photo-placeholder');
        if (preview) {
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('d-none');
        }
        if (placeholder) {
            placeholder.classList.add('d-none');
        }
    });
});
</script>
<style>
.itm-photo-upload-target.is-dragover { opacity: 0.85; }
</style>
