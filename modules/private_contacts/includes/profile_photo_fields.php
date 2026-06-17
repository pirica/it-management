<?php
/**
 * Employees-style profile photo field for private contact create/edit.
 *
 * Expects: $contact (array, may be empty on create).
 */
require_once __DIR__ . '/private_contact_photo.php';

$pcPhotoUrl = pc_contact_photo_serve_url($contact ?? []);
$pcHasExistingPhoto = $pcPhotoUrl !== '';
?>
<div class="itm-employee-photo-field">
    <label class="itm-employee-photo-label">Profile Photo</label>
    <div class="itm-employee-photo-target itm-photo-upload-target" role="button" tabindex="0" aria-label="Upload profile photo">
        <input type="file" name="photo" id="private-contact-photo-input" class="itm-employee-photo-input" accept=".png,.jpg,.jpeg,image/png,image/jpeg,image/jpg,image/pjpeg">
        <input type="hidden" name="confirm_replace" id="confirm_replace" value="0">
        <label for="private-contact-photo-input" class="itm-employee-photo-trigger">
            <?php if ($pcPhotoUrl !== ''): ?>
                <img src="<?= sanitize($pcPhotoUrl) ?>" id="private-contact-photo-preview" class="itm-employee-photo-preview" alt="" onerror="this.onerror=null; this.src='../../images/5x5-pixel.png';">
            <?php else: ?>
                <div id="private-contact-photo-placeholder" class="itm-employee-photo-placeholder" aria-hidden="true">📷</div>
                <img src="../../images/5x5-pixel.png" id="private-contact-photo-preview" class="itm-employee-photo-preview is-hidden" alt="">
            <?php endif; ?>
        </label>
    </div>
    <p class="itm-employee-photo-hint">Drag and drop or click to upload PNG/JPG.</p>
</div>
<script src="../../js/itm-upload-helper.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof itmUploadHelper !== 'undefined') {
        itmUploadHelper.setupByClass('.itm-employee-photo-target');
    }
    var photoInput = document.getElementById('private-contact-photo-input');
    if (!photoInput) {
        return;
    }
    var hasExistingPhoto = <?= $pcHasExistingPhoto ? 'true' : 'false' ?>;
    photoInput.addEventListener('change', function (evt) {
        var file = evt.target.files && evt.target.files[0];
        if (!file) {
            return;
        }
        var lowerName = (file.name || '').toLowerCase();
        if (!lowerName.endsWith('.png') && !lowerName.endsWith('.jpg') && !lowerName.endsWith('.jpeg')) {
            alert('Only PNG and JPG profile photos are allowed.');
            evt.target.value = '';
            return;
        }
        if (hasExistingPhoto && !confirm('A photo already exists. Do you want to replace it?')) {
            evt.target.value = '';
            return;
        }
        if (hasExistingPhoto) {
            document.getElementById('confirm_replace').value = '1';
        }
        var preview = document.getElementById('private-contact-photo-preview');
        var placeholder = document.getElementById('private-contact-photo-placeholder');
        if (preview) {
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('is-hidden');
        }
        if (placeholder) {
            placeholder.classList.add('is-hidden');
        }
    });
});
</script>
<style>
.itm-employee-photo-field {
    margin-bottom: 16px;
}
.itm-employee-photo-label {
    display: block;
    margin-bottom: 8px;
}
.itm-employee-photo-target {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 110px;
    height: 110px;
    padding: 5px !important;
    border: 2px dashed var(--border-color, #d0d7de);
    border-radius: 50% !important;
    cursor: pointer;
    box-sizing: border-box;
    transition: border-color 0.15s ease, background 0.15s ease;
}
.itm-employee-photo-target.is-dragover {
    border-color: var(--accent-color, #0969da) !important;
    background: var(--accent-muted, rgba(9, 105, 218, 0.08));
}
.itm-employee-photo-target .itm-employee-photo-input {
    display: none !important;
}
.itm-employee-photo-trigger {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100px;
    height: 100px;
    margin: 0;
    cursor: pointer;
}
.itm-employee-photo-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: var(--bg-muted, #f6f8fa);
    font-size: 36px;
    line-height: 1;
}
.itm-employee-photo-preview {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
}
.itm-employee-photo-preview.is-hidden,
.itm-employee-photo-placeholder.is-hidden {
    display: none;
}
.itm-employee-photo-hint {
    margin: 8px 0 0;
    max-width: 320px;
    font-size: 13px;
    opacity: 0.85;
}
</style>
