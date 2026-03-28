<?php 
if (!isset($company_id)) $company_id = $_SESSION['company_id'] ?? 0;
?>
<div class="header">
    <div>
        <h4 style="margin: 0; display: flex; gap: 10px; align-items: center;">
            <button type="button" onclick="toggleSidebar()" class="btn btn-sm sidebar-toggle-btn" title="Hide/Show Dashboard Menu">☰</button>
            ⚙️ <strong><?php echo sanitize($_SESSION['company_name'] ?? 'System'); ?></strong>
        </h4>
    </div>
    <div style="display: flex; gap: 15px; align-items: center;">
        <button onclick="toggleTheme()" class="btn btn-sm" title="Toggle Dark/Light Mode">🌙</button>
        <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-sm">🚪 Logout</a>
    </div>
</div>
<script src="<?php echo BASE_URL; ?>js/theme.js"></script>
