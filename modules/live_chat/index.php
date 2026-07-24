<?php
require_once '../../config/config.php';

$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$resolvedModuleIcon = itm_resolve_module_sidebar_icon($conn, $companyId, $employeeId, $moduleSlug);
$cleanModuleTitle = itm_module_access_strip_catalog_label_prefix('Live Chat');
$moduleListHeading = trim($resolvedModuleIcon . ' ' . $cleanModuleTitle);
$crud_title = $moduleListHeading;
$ui_config = itm_get_ui_configuration($conn, $companyId, $employeeId);
$initialConversationId = (int)($_GET['conversation_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo sanitize($crud_title); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/live_chat.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="lc-toolbar" data-itm-new-button-managed="server">
                <h1 title="Live Chat"><?php echo sanitize($moduleListHeading); ?></h1>
                <span id="lc-notification-badge" class="lc-notification-badge hidden" title="Unread notifications"></span>
            </div>

            <div id="lc-app" class="lc-app" data-conversation-id="<?php echo (int)$initialConversationId; ?>" data-csrf="<?php echo sanitize($csrfToken); ?>" data-api="<?php echo BASE_URL; ?>modules/live_chat/api.php">
                <aside class="lc-conv-sidebar" aria-label="Conversations">
                    <div class="lc-conv-sidebar-header">
                        <span title="Conversations">💬</span>
                    </div>
                    <ul id="lc-conversation-list" class="lc-conversation-list"></ul>
                </aside>

                <section class="lc-main-panel">
                    <div id="lc-landing" class="lc-landing">
                        <div class="lc-landing-card card">
                            <p class="lc-landing-intro">Choose how you want to connect.</p>
                            <div class="lc-landing-actions">
                                <button type="button" class="btn btn-primary lc-landing-btn" id="lc-btn-live-agent" title="Live Agent">💬</button>
                                <button type="button" class="btn btn-primary lc-landing-btn" id="lc-btn-chat-with" title="Chat with">👥</button>
                            </div>
                        </div>
                        <div id="lc-options-panel" class="lc-options-panel hidden"></div>
                        <div id="lc-wizard-panel" class="lc-wizard-panel hidden"></div>
                    </div>

                    <div id="lc-chat-panel" class="lc-chat-panel hidden">
                        <header class="lc-chat-header">
                            <div class="lc-chat-header-left">
                                <h2 id="lc-chat-title"></h2>
                                <span id="lc-chat-status" class="badge"></span>
                                <span id="lc-rating-badge" class="lc-rating-badge hidden"></span>
                            </div>
                            <div class="lc-chat-header-actions">
                                <button type="button" class="btn btn-sm" id="lc-btn-claim" title="Claim">✋</button>
                                <button type="button" class="btn btn-sm" id="lc-btn-close-chat" title="Close chat">✖</button>
                            </div>
                        </header>
                        <div id="lc-messages" class="lc-messages"></div>
                        <div id="lc-typing" class="lc-typing hidden" aria-live="polite">
                            <span class="lc-typing-dot"></span><span class="lc-typing-dot"></span><span class="lc-typing-dot"></span>
                        </div>
                        <footer class="lc-composer">
                            <div id="lc-rating-row" class="lc-rating-row hidden">
                                <span class="lc-rating-label" title="Rate this session">⭐</span>
                                <div id="lc-rating-stars" class="lc-rating-stars"></div>
                            </div>
                            <div class="lc-composer-row">
                                <label class="lc-attach-btn" title="Attach file">
                                    📎
                                    <input type="file" id="lc-file-input" class="hidden" multiple>
                                </label>
                                <textarea id="lc-message-input" rows="2" placeholder="Type a message…"></textarea>
                                <button type="button" class="btn btn-primary" id="lc-btn-send" title="Send">➤</button>
                            </div>
                        </footer>
                    </div>
                </section>

                <aside class="lc-detail-panel" aria-label="Employee details">
                    <div class="card lc-detail-card">
                        <h3 title="Details">ℹ️</h3>
                        <div id="lc-employee-detail" class="lc-employee-detail">
                            <p class="lc-empty-state">Select a conversation</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
<script>
window.ITM_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
</script>
<script src="<?php echo BASE_URL; ?>js/live_chat.js"></script>
</body>
</html>
