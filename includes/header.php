<?php 
if (!isset($company_id)) $company_id = $_SESSION['company_id'] ?? 0;
$csrfToken = itm_get_csrf_token();
?>
<div class="header">
    <div>
        <h4 style="margin: 0; display: flex; gap: 10px; align-items: center;">
            <button type="button" id="sidebarToggleBtn" class="btn btn-sm sidebar-toggle-btn" title="Hide/Show Dashboard Menu">☰</button>
            ⚙️ <strong><?php echo sanitize($_SESSION['company_name'] ?? 'System'); ?></strong>
        </h4>
    </div>
    <div style="display: flex; gap: 15px; align-items: center;">
        <button onclick="toggleTheme()" class="btn btn-sm" title="Toggle Dark/Light Mode">🌙</button>
        <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-sm">🚪 Logout</a>
    </div>
</div>
<?php if (!empty($_SESSION['crud_error'])): ?>
    <div class="alert alert-error" style="margin: 12px 0;">
        <?php echo sanitize((string)$_SESSION['crud_error']); ?>
    </div>
    <?php unset($_SESSION['crud_error']); ?>
<?php endif; ?>
<script src="<?php echo BASE_URL; ?>js/theme.js"></script>


<script>
window.ITM_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
window.ITM_UI_CONFIG = <?php echo json_encode($ui_config ?? itm_ui_config_defaults()); ?>;
</script>
<script src="<?php echo BASE_URL; ?>js/select-add-option.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="<?php echo BASE_URL; ?>js/ui-layout.js"></script>
<script src="<?php echo BASE_URL; ?>js/table-tools.js"></script>
