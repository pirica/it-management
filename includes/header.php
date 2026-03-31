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


<script>
document.addEventListener('click', function (event) {
    const link = event.target.closest('a[href*="delete.php?id="]');
    if (!link) return;
    event.preventDefault();

    const ok = window.confirm(link.dataset.confirm || 'Are you sure you want to delete this record?');
    if (!ok) return;

    const href = link.getAttribute('href') || '';
    const target = new URL(href, window.location.href);
    const id = target.searchParams.get('id') || '';
    if (!id) {
        window.location.href = href;
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = target.pathname;

    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = id;

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = window.ITM_CSRF_TOKEN || '';

    form.appendChild(idInput);
    form.appendChild(csrfInput);
    document.body.appendChild(form);
    form.submit();
});
</script>
