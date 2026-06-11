<div class="row">
    <!-- Profile & Name -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="position-relative">
                            <?php if (isset($contact['photo']) && $contact['photo']): ?>
                                <img src="../../files/<?php echo $_SESSION[company_id]; ?>/Private/<?php echo $_SESSION[username] . "_" . $_SESSION[user_id]; ?>/private_contacts/<?php echo htmlspecialchars($contact['photo']); ?>" id="photo-preview" class="rounded-circle border" width="100" height="100" style="object-fit: cover;">
                            <?php else: ?>
                                <div id="photo-placeholder" class="rounded-circle bg-light border d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                    <i class="fas fa-user text-muted fa-3x"></i>
                                </div>
                                <img src="" id="photo-preview" class="rounded-circle border d-none" width="100" height="100" style="object-fit: cover;">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group mb-0">
                            <label>Photo</label>
                            <div class="custom-file">
                                <input type="file" name="photo" class="custom-file-input" id="photo-input" accept="image/*">
                                <label class="custom-file-label" for="photo-input">Choose File</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="is_favorite" class="custom-control-input" id="chkFavorite" <?php echo (isset($contact['is_favorite']) && $contact['is_favorite']) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="chkFavorite"><i class="fas fa-star text-warning"></i> Favorite</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Name Section -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Name</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 form-group">
                        <label>Prefix</label>
                        <input type="text" name="name_prefix" class="form-control" value="<?php echo htmlspecialchars($contact['name_prefix'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($contact['first_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($contact['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($contact['last_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-1 form-group">
                        <label>Suffix</label>
                        <input type="text" name="name_suffix" class="form-control" value="<?php echo htmlspecialchars($contact['name_suffix'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3 form-group">
                        <label>Phonetic First</label>
                        <input type="text" name="phonetic_first_name" class="form-control" value="<?php echo htmlspecialchars($contact['phonetic_first_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Phonetic Middle</label>
                        <input type="text" name="phonetic_middle_name" class="form-control" value="<?php echo htmlspecialchars($contact['phonetic_middle_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Phonetic Last</label>
                        <input type="text" name="phonetic_last_name" class="form-control" value="<?php echo htmlspecialchars($contact['phonetic_last_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Nickname</label>
                        <input type="text" name="nickname" class="form-control" value="<?php echo htmlspecialchars($contact['nickname'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-4 form-group">
                        <label>File As</label>
                        <input type="text" name="file_as" class="form-control" value="<?php echo htmlspecialchars($contact['file_as'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Methods -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Contact Methods</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <label>Email</label>
                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <select name="email1_label" class="form-control rounded-0">
                                    <?php foreach(['Work','Home','Other'] as $lbl): ?>
                                        <option value="<?php echo $lbl; ?>" <?php echo (($contact['email1_label'] ?? '') === $lbl) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="email" name="email1_value" class="form-control" value="<?php echo htmlspecialchars($contact['email1_value'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label>Phone / Mobile</label>
                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <select name="phone1_label" class="form-control rounded-0">
                                    <?php foreach(['Mobile','Work','Home','Main','Other'] as $lbl): ?>
                                        <option value="<?php echo $lbl; ?>" <?php echo (($contact['phone1_label'] ?? '') === $lbl) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="text" name="phone1_value" class="form-control" value="<?php echo htmlspecialchars($contact['phone1_value'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label>Website</label>
                        <div class="input-group mb-3">
                            <div class="input-group-prepend">
                                <input type="text" name="website1_label" class="form-control rounded-0" placeholder="Label (e.g. Portfolio)" value="<?php echo htmlspecialchars($contact['website1_label'] ?? ''); ?>">
                            </div>
                            <input type="text" name="website1_value" class="form-control" value="<?php echo htmlspecialchars($contact['website1_value'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Organization -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Organization</h5></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Company</label>
                    <input type="text" name="organization_name" class="form-control" value="<?php echo htmlspecialchars($contact['organization_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Job Title</label>
                    <input type="text" name="organization_title" class="form-control" value="<?php echo htmlspecialchars($contact['organization_title'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="organization_department" class="form-control" value="<?php echo htmlspecialchars($contact['organization_department'] ?? ''); ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Address (UK localized labels) -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Address</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Label</label>
                        <input type="text" name="address1_label" class="form-control" placeholder="e.g. Home, Office" value="<?php echo htmlspecialchars($contact['address1_label'] ?? ''); ?>">
                    </div>
                    <div class="col-md-8 form-group">
                        <label>Street</label>
                        <input type="text" name="address1_street" class="form-control" value="<?php echo htmlspecialchars($contact['address1_street'] ?? ''); ?>">
                        <label>Extended Address</label>
                        <input type="text" name="address1_extended" class="form-control" value="<?php echo htmlspecialchars($contact['address1_extended'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>City</label>
                        <input type="text" name="address1_city" class="form-control" value="<?php echo htmlspecialchars($contact['address1_city'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Region / County</label>
                        <input type="text" name="address1_region" class="form-control" value="<?php echo htmlspecialchars($contact['address1_region'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Postcode</label>
                        <input type="text" name="address1_postcode" class="form-control" value="<?php echo htmlspecialchars($contact['address1_postcode'] ?? ''); ?>">
                    <div class="col-md-4 form-group">
                        <label>PO Box</label>
                        <input type="text" name="address1_po_box" class="form-control" value="<?php echo htmlspecialchars($contact['address1_po_box'] ?? ''); ?>">
                    </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Country</label>
                        <input type="text" name="address1_country" class="form-control" value="<?php echo htmlspecialchars($contact['address1_country'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Important Dates & Relations -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Important Dates & Relations</h5></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Birthday</label>
                    <input type="date" name="birthday" class="form-control" value="<?php echo $contact['birthday'] ?? ''; ?>">
                </div>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Event</label>
                        <input type="text" name="event1_label" class="form-control" placeholder="e.g. Anniversary" value="<?php echo htmlspecialchars($contact['event1_label'] ?? ''); ?>">
                    </div>
                    <div class="col-md-9 form-group">
                        <label>&nbsp;</label>
                        <input type="date" name="event1_value" class="form-control" value="<?php echo $contact['event1_value'] ?? ''; ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Relation</label>
                        <input type="text" name="relation1_label" class="form-control" placeholder="e.g. Spouse" value="<?php echo htmlspecialchars($contact['relation1_label'] ?? ''); ?>">
                    </div>
                    <div class="col-md-9 form-group">
                        <label>&nbsp;</label>
                        <input type="text" name="relation1_value" class="form-control" value="<?php echo htmlspecialchars($contact['relation1_value'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes & Labels -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Notes & Labels</h5></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Labels (comma separated)</label>
                    <input type="text" name="labels" class="form-control" placeholder="e.g. Personal, Work, VIP" value="<?php echo htmlspecialchars($contact['labels'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($contact['notes'] ?? ''); ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Custom Field</label>
                        <input type="text" name="custom_field1_label" class="form-control" placeholder="Label" value="<?php echo htmlspecialchars($contact['custom_field1_label'] ?? ''); ?>">
                    </div>
                    <div class="col-md-9 form-group">
                        <label>&nbsp;</label>
                        <input type="text" name="custom_field1_value" class="form-control" value="<?php echo htmlspecialchars($contact['custom_field1_value'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('photo-input').onchange = evt => {
    const [file] = evt.target.files;
    if (file) {
        document.getElementById('photo-preview').src = URL.createObjectURL(file);
        document.getElementById('photo-preview').classList.remove('d-none');
        if (document.getElementById('photo-placeholder')) {
            document.getElementById('photo-placeholder').classList.add('d-none');
        }
    }
}
</script>
