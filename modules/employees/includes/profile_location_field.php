<?php
/**
 * IT location select for employee create/edit forms.
 *
 * Expects: $conn, $company_id, $form (array with location_id).
 */
require_once dirname(__DIR__, 3) . '/includes/itm_fk_option_labels.php';

$locationOptions = [];
$selectedLocationId = (string)($form['location_id'] ?? '');
$locationRes = mysqli_query($conn, 'SELECT id, name, location_code FROM it_locations WHERE company_id=' . (int)$company_id . ' AND active=1 ORDER BY name');
if ($locationRes) {
    while ($locationRow = mysqli_fetch_assoc($locationRes)) {
        $locationId = (int)($locationRow['id'] ?? 0);
        if ($locationId > 0) {
            $locationOptions[$locationId] = itm_location_option_label($locationRow);
        }
    }
}
if ($selectedLocationId !== '' && !isset($locationOptions[(int)$selectedLocationId])) {
    $persistedLocationRes = mysqli_query(
        $conn,
        'SELECT id, name, location_code FROM it_locations WHERE company_id=' . (int)$company_id . ' AND id=' . (int)$selectedLocationId . ' LIMIT 1'
    );
    if ($persistedLocationRes && ($persistedLocationRow = mysqli_fetch_assoc($persistedLocationRes))) {
        $locationOptions[(int)$persistedLocationRow['id']] = itm_location_option_label($persistedLocationRow);
        if ($locationOptions[(int)$persistedLocationRow['id']] === '') {
            $locationOptions[(int)$persistedLocationRow['id']] = '#' . (int)$persistedLocationRow['id'];
        }
    }
}
?>
<div class="form-group">
    <label>IT Location</label>
    <select name="location_id" data-addable-select="1" data-add-table="it_locations" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="IT location">
        <option value="">-- None --</option>
        <?php foreach ($locationOptions as $locationOptionId => $locationOptionName): ?>
            <option value="<?= (int)$locationOptionId ?>" <?= ((string)$locationOptionId === $selectedLocationId) ? 'selected' : '' ?>><?= sanitize($locationOptionName) ?></option>
        <?php endforeach; ?>
        <option value="__add_new__">➕</option>
    </select>
</div>
