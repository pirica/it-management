<?php
/**
 * Per-hotel special rate discounts for the public booking portal (programs + code fields).
 */
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$hotels = [];
$hstmt = mysqli_prepare($conn, 'SELECT id, name FROM hotel_booking_hotels WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC');
if ($hstmt) {
    mysqli_stmt_bind_param($hstmt, 'i', $company_id);
    mysqli_stmt_execute($hstmt);
    $hres = mysqli_stmt_get_result($hstmt);
    while ($hres && ($h = mysqli_fetch_assoc($hres))) {
        $hotels[] = $h;
    }
    mysqli_stmt_close($hstmt);
}

$hotelId = (int) ($_GET['hotel_id'] ?? $_POST['hotel_id'] ?? 0);
if ($hotelId < 1 && !empty($hotels)) {
    $hotelId = (int) $hotels[0]['id'];
}

$errors = [];
$saved = false;
$codeSaved = false;
$codeDeleted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hotelId > 0) {
    itm_require_post_csrf();
    $postAction = trim((string) ($_POST['form_action'] ?? 'rates'));

    if ($postAction === 'add_code') {
        $rateSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($_POST['code_rate_slug'] ?? '')));
        $codeValue = (string) ($_POST['code_value'] ?? '');
        $codeLabel = trim((string) ($_POST['code_label'] ?? ''));
        $result = itm_hotel_booking_special_rate_codes_save_row($conn, $company_id, $hotelId, $rateSlug, $codeValue, $employee_id, $codeLabel);
        if (!empty($result['ok'])) {
            $codeSaved = true;
        } else {
            $errors[] = (string) ($result['error'] ?? 'Could not save code.');
        }
    } elseif ($postAction === 'delete_code') {
        $codeId = (int) ($_POST['code_id'] ?? 0);
        if ($codeId > 0 && itm_hotel_booking_special_rate_codes_soft_delete($conn, $company_id, $hotelId, $codeId, $employee_id)) {
            $codeDeleted = true;
        } else {
            $errors[] = 'Could not delete code.';
        }
    } else {
        $discounts = isset($_POST['discount_percent']) && is_array($_POST['discount_percent']) ? $_POST['discount_percent'] : [];
        $actives = isset($_POST['active']) && is_array($_POST['active']) ? $_POST['active'] : [];
        itm_hotel_booking_ensure_special_rates_for_hotel($conn, $company_id, $hotelId, $employee_id);
        foreach (itm_hotel_booking_canonical_special_rate_definitions() as $def) {
            $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($def['slug'] ?? '')));
            if ($slug === '') {
                continue;
            }
            $pct = itm_hotel_booking_normalize_special_rate_percent_input($discounts[$slug] ?? '0');
            $active = !empty($actives[$slug]) ? 1 : 0;
            $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_special_rates SET discount_percent = ?, active = ?, updated_by = ?, updated_at = NOW() WHERE company_id = ? AND hotel_id = ? AND rate_slug = ? AND deleted_at IS NULL');
            if ($upd) {
                mysqli_stmt_bind_param($upd, 'diiiis', $pct, $active, $employee_id, $company_id, $hotelId, $slug);
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
        }
        $saved = true;
    }
}

$rateRows = $hotelId > 0 ? itm_hotel_booking_special_rates_admin_rows($conn, $company_id, $hotelId) : [];
$codeRows = $hotelId > 0 ? itm_hotel_booking_special_rate_codes_admin_rows($conn, $company_id, $hotelId) : [];
$programSlugs = [];
foreach (itm_hotel_booking_portal_rate_program_options() as $opt) {
    $programSlugs[(string) ($opt['slug'] ?? '')] = true;
}
$codeSlugs = [];
$codeSlugLabels = [];
foreach (itm_hotel_booking_portal_code_rate_options() as $opt) {
    $slug = (string) ($opt['slug'] ?? '');
    $codeSlugs[$slug] = true;
    $codeSlugLabels[$slug] = (string) ($opt['label'] ?? $slug);
}
$codesBySlug = [];
foreach ($codeRows as $codeRow) {
    $slug = (string) ($codeRow['rate_slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    if (!isset($codesBySlug[$slug])) {
        $codesBySlug[$slug] = [];
    }
    $codesBySlug[$slug][] = $codeRow;
}

$crud_title = 'Booking Special Rates';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_special_rates', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Booking special rates">🏷️</h1>
<p class="text-muted">Set discount % per hotel for portal checkboxes (Use Points, AAA, Senior, …) and code fields (Promotion, Group, Corporate, Member). Guests see the % on Select a Room → Special rates. Register valid codes below — only listed codes apply the discount.</p>
<?php if ($saved): ?>
<p class="badge badge-success">Discounts saved</p>
<?php endif; ?>
<?php if ($codeSaved): ?>
<p class="badge badge-success">Code added</p>
<?php endif; ?>
<?php if ($codeDeleted): ?>
<p class="badge badge-success">Code removed</p>
<?php endif; ?>
<?php foreach ($errors as $e): ?>
<p class="badge badge-danger"><?php echo sanitize($e); ?></p>
<?php endforeach; ?>
<?php if (empty($hotels)): ?>
<p>No active hotels. Add a hotel first.</p>
<div class="itm-hospitality-list-actions" style="margin-bottom:16px;">
<?php itm_hospitality_render_list_create_and_hub('btn btn-primary', '../hotel_booking_hotels/create.php'); ?>
</div>
<?php else: ?>
<form method="get" class="form-inline" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
<label for="hotel_id">Hotel</label>
<select name="hotel_id" id="hotel_id" class="form-control" onchange="this.form.submit()">
<?php foreach ($hotels as $h): ?>
<option value="<?php echo (int) $h['id']; ?>"<?php echo (int) $h['id'] === $hotelId ? ' selected' : ''; ?>><?php echo sanitize($h['name']); ?></option>
<?php endforeach; ?>
</select>
<noscript><button type="submit" class="btn btn-sm" title="Load">Load</button></noscript>
</form>
<div class="itm-hospitality-list-actions" style="margin-bottom:16px;">
<?php itm_hospitality_render_bookings_hub_link('btn'); ?>
</div>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<input type="hidden" name="form_action" value="rates">
<table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
<thead>
<tr><th>Rate</th><th>Discount %</th><th>Active</th></tr>
</thead>
<tbody>
<?php foreach ($rateRows as $row):
    $slug = (string) ($row['rate_slug'] ?? '');
    $section = isset($codeSlugs[$slug]) ? 'Code fields' : (isset($programSlugs[$slug]) ? 'Programs' : 'Other');
    $pctVal = itm_hotel_booking_format_discount_percent_label($row['discount_percent'] ?? 0);
?>
<tr>
<td><?php echo sanitize($row['name'] ?? $slug); ?><br><span class="text-muted" style="font-size:.8rem;"><?php echo sanitize($section); ?> · <code><?php echo sanitize($slug); ?></code></span></td>
<td><input type="text" name="discount_percent[<?php echo sanitize($slug); ?>]" class="form-control" style="max-width:6rem;" inputmode="decimal" value="<?php echo sanitize($pctVal); ?>" title="Discount percent"></td>
<td>
<label class="itm-checkbox-control">
<input type="checkbox" name="active[<?php echo sanitize($slug); ?>]" value="1"<?php echo !empty($row['active']) ? ' checked' : ''; ?>>
<span><?php echo sanitize(itm_humanize_field_name('active')); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo !empty($row['active']) ? '✅' : '❌'; ?></span></span>
</label>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<button type="submit" class="btn btn-primary" title="Save">💾</button>
</form>

<h2 style="margin-top:24px;" title="Registered portal codes">🔑</h2>
<p class="text-muted">Guests must enter one of these codes (up to 8 letters or digits) for promotion, group, corporate, and member discounts. Codes are not case-sensitive.</p>

<?php foreach ($codeSlugLabels as $slug => $slugLabel):
    $slugCodes = $codesBySlug[$slug] ?? [];
?>
<div class="card" style="margin-top:16px;padding:12px;">
<h3><?php echo sanitize($slugLabel); ?> <span class="text-muted" style="font-size:.85rem;">(<code><?php echo sanitize($slug); ?></code>)</span></h3>
<?php if (empty($slugCodes)): ?>
<p class="text-muted">No codes registered — guests cannot use this discount until you add at least one code.</p>
<?php else: ?>
<table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
<thead><tr><th>Code</th><th>Label</th><th>Valid from</th><th>Valid to</th><th>Active</th><th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th></tr></thead>
<tbody>
<?php foreach ($slugCodes as $codeRow): ?>
<tr>
<td><code><?php echo sanitize($codeRow['code'] ?? ''); ?></code></td>
<td><?php echo sanitize($codeRow['label'] ?? ''); ?></td>
<td><?php echo sanitize(itm_format_date_display($codeRow['valid_from'] ?? '')); ?></td>
<td><?php echo sanitize(itm_format_date_display($codeRow['valid_to'] ?? '')); ?></td>
<td><?php if (!empty($codeRow['active'])): ?><span class="badge badge-success">Active</span><?php else: ?><span class="badge badge-danger">Inactive</span><?php endif; ?></td>
<td class="itm-actions-cell" data-itm-actions-origin="1">
<form method="post" style="display:inline;" onsubmit="return confirm('Remove this code? Guests will no longer be able to use it.');">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<input type="hidden" name="form_action" value="delete_code">
<input type="hidden" name="code_id" value="<?php echo (int) ($codeRow['id'] ?? 0); ?>">
<button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
<form method="post" class="form-inline" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<input type="hidden" name="hotel_id" value="<?php echo (int) $hotelId; ?>">
<input type="hidden" name="form_action" value="add_code">
<input type="hidden" name="code_rate_slug" value="<?php echo sanitize($slug); ?>">
<div class="form-group">
<label for="code_value_<?php echo sanitize($slug); ?>">New code</label>
<input type="text" class="form-control" id="code_value_<?php echo sanitize($slug); ?>" name="code_value" maxlength="8" pattern="[A-Za-z0-9]{1,8}" required title="Code (up to 8 characters)">
</div>
<div class="form-group">
<label for="code_label_<?php echo sanitize($slug); ?>">Label (optional)</label>
<input type="text" class="form-control" id="code_label_<?php echo sanitize($slug); ?>" name="code_label" maxlength="120" title="Internal label">
</div>
<button type="submit" class="btn btn-primary" title="Add code">➕</button>
</form>
</div>
<?php endforeach; ?>

<?php endif; ?>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
