<?php 
/**
 * Common Header Template
 * 
 * Included at the top of most pages. Renders the top navigation bar,
 * handles common UI alerts (CRUD success/error), and injects global
 * JavaScript configuration and dependencies.
 */

if (!isset($company_id)) $company_id = $_SESSION['company_id'] ?? 0;
$csrfToken = itm_get_csrf_token();
?>
<div class="header">
    <div>
        <h4 style="margin: 0; display: flex; gap: 10px; align-items: center;">
            <!-- Mobile-friendly sidebar toggle -->
            <button type="button" id="sidebarToggleBtn" class="btn btn-sm sidebar-toggle-btn" title="Hide/Show Dashboard Menu" data-sidebar-bound="false">☰</button>
            ⚙️ <strong><?php echo sanitize($_SESSION['company_name'] ?? 'System'); ?></strong>
        </h4>
    </div>
    <div style="display: flex; gap: 15px; align-items: center;">
        <!-- Global UI Action Buttons -->
        <button onclick="toggleTheme()" class="btn btn-sm" title="Toggle Dark/Light Mode">🌙</button>
        <form method="POST" action="<?php echo BASE_URL; ?>logout.php" style="display:inline; margin:0;">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <button type="submit" class="btn btn-sm">🚪 Logout</button>
        </form>
    </div>
</div>

<?php
// Display one-time session-based notifications (Flash Messages)
if (!empty($_SESSION['crud_error'])) {
    echo '<div class="crud_error">' . htmlspecialchars($_SESSION['crud_error']) . '</div>';
    unset($_SESSION['crud_error']);
}

if (!empty($_SESSION['crud_success'])) {
    echo '<div class="crud_success">' . htmlspecialchars($_SESSION['crud_success']) . '</div>';
    unset($_SESSION['crud_success']);
}
?>

<!-- Load Theme Logic Early -->
<script src="<?php echo BASE_URL; ?>js/theme.js"></script>

<script>
/**
 * Injects server-side configuration into the global window object for JS access
 */
window.ITM_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
window.ITM_UI_CONFIG = <?php echo json_encode($ui_config ?? itm_ui_config_defaults()); ?>;
window.ITM_APP_NAME = <?php echo json_encode($app_name ?? itm_ui_config_app_name($ui_config ?? null)); ?>;
</script>

<!-- Global JS Library Dependencies -->
<script src="<?php echo BASE_URL; ?>js/select-add-option.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="<?php echo BASE_URL; ?>js/ui-layout.js"></script>
<script src="<?php echo BASE_URL; ?>js/table-tools.js"></script>

<script>
/**
 * Global Delete Handler
 * 
 * Intercepts clicks on "delete" links and converts them into secure POST requests.
 * This prevents accidental deletion via crawlers or direct GET requests.
 */
document.addEventListener('click', function (event) {
    const link = event.target.closest('a[href*="delete.php?id="]');
    if (!link) return;
    event.preventDefault();

    // Browser-native confirmation before destructive action
    const ok = window.confirm(link.dataset.confirm || 'Are you sure you want to delete this record?');
    if (!ok) return;

    const href = link.getAttribute('href') || '';
    const target = new URL(href, window.location.href);
    const id = target.searchParams.get('id') || '';
    if (!id) {
        window.location.href = href;
        return;
    }

    // Programmatically create and submit a POST form
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

/**
 * Adds lightweight emoji-prefixed tooltips to links/buttons that do not
 * already define a title. This improves intent clarity without forcing
 * module-by-module markup edits across the legacy codebase.
 */
document.addEventListener('DOMContentLoaded', function () {
    if (window.ITM_APP_NAME && typeof document.title === 'string') {
        const appName = String(window.ITM_APP_NAME).trim();
        if (appName !== '' && !document.title.includes(appName)) {
            document.title = document.title ? (document.title + ' - ' + appName) : appName;
        }
    }

    const intentRules = [
        { test: /(delete|remove|trash)/i, emoji: '🗑️', label: 'Delete item' },
        { test: /(edit|update|modify)/i, emoji: '✏️', label: 'Edit item' },
        { test: /(view|details|open)/i, emoji: '👀', label: 'View details' },
        { test: /(create|add|new)/i, emoji: '➕', label: 'Add new item' },
        { test: /(save|submit|apply)/i, emoji: '💾', label: 'Save changes' },
        { test: /(logout|sign out)/i, emoji: '🚪', label: 'Log out' },
        { test: /(search|find)/i, emoji: '🔎', label: 'Search' },
        { test: /(export|download)/i, emoji: '📤', label: 'Export data' },
        { test: /(import|upload)/i, emoji: '📥', label: 'Import data' },
        { test: /(back|return)/i, emoji: '↩️', label: 'Go back' },
    ];

    const nodes = document.querySelectorAll('a, button, input[type="submit"], input[type="button"]');
    nodes.forEach(function (node) {
        if (!node || node.hasAttribute('title') || node.dataset.itmAutoTooltip === 'off') {
            return;
        }

        const classText = node.className || '';
        const ariaText = node.getAttribute('aria-label') || '';
        const hrefText = node.getAttribute('href') || '';
        const valueText = node.value || '';
        const visibleText = (node.textContent || '').replace(/\s+/g, ' ').trim();
        const signal = [visibleText, ariaText, valueText, classText, hrefText].join(' ').trim();
        if (!signal) {
            return;
        }

        const matched = intentRules.find(function (rule) {
            return rule.test.test(signal);
        });

        if (matched) {
            node.setAttribute('title', matched.emoji + ' ' + matched.label);
            return;
        }

        // Fallback keeps tooltip useful even when no known intent keyword matches.
        node.setAttribute('title', '🔗 ' + visibleText.slice(0, 80));
    });
});
</script>
