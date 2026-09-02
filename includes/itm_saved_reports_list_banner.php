<?php
/**
 * Optional banner when a list is opened from Reports Hub saved-view link (?saved_view_id=).
 *
 * Expects $conn and active $company_id.
 */
if (!isset($conn) || !function_exists('itm_saved_reports_fetch_by_id')) {
    return;
}
$itmSavedViewBannerId = (int) ($_GET['saved_view_id'] ?? 0);
if ($itmSavedViewBannerId <= 0) {
    return;
}
if (!function_exists('itm_saved_reports_can_view')) {
    require_once ROOT_PATH . 'includes/itm_saved_reports.php';
}
$itmSavedViewBannerRow = itm_saved_reports_fetch_by_id($conn, $itmSavedViewBannerId, (int) ($company_id ?? 0));
$itmSavedViewBannerEmployeeId = (int) ($_SESSION['employee_id'] ?? 0);
if (!$itmSavedViewBannerRow || !itm_saved_reports_can_view($conn, $itmSavedViewBannerRow, $itmSavedViewBannerEmployeeId, (int) ($company_id ?? 0))) {
    return;
}
$itmSavedViewBannerName = trim((string) ($itmSavedViewBannerRow['name'] ?? ''));
if ($itmSavedViewBannerName === '') {
    return;
}
?>
<div class="card" style="margin-bottom:16px;padding:12px 16px;">
    <strong>Saved report:</strong> <?php echo sanitize($itmSavedViewBannerName); ?>
    <span style="opacity:.85;"> — filters restored from Reports Hub.</span>
    <a class="btn btn-sm" href="index.php" style="margin-left:8px;" title="Clear saved view filters">🔙</a>
</div>
