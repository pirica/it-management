<?php
require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'edit', 'tickets');
$sourceId = (int)($_GET['source_id'] ?? $_POST['source_id'] ?? 0);
$error = '';
$sourceTicket = null;
if ($sourceId > 0 && $company_id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT id, title, ticket_external_code, merged_into_ticket_id, deleted_at FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $sourceId, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $sourceTicket = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
}
$targetOptions = [];
if ($company_id > 0) {
    $listStmt = mysqli_prepare($conn, 'SELECT id, title, ticket_external_code FROM tickets WHERE company_id = ? AND deleted_at IS NULL AND merged_into_ticket_id IS NULL ORDER BY id DESC LIMIT 200');
    if ($listStmt) {
        mysqli_stmt_bind_param($listStmt, 'i', $company_id);
        mysqli_stmt_execute($listStmt);
        $listRes = mysqli_stmt_get_result($listStmt);
        while ($listRes && ($row = mysqli_fetch_assoc($listRes))) {
            $tid = (int)$row['id'];
            if ($tid === $sourceId) continue;
            $label = trim((string)($row['ticket_external_code'] ?? ''));
            if ($label !== '') $label .= ' — ';
            $label .= (string)($row['title'] ?? '');
            $targetOptions[] = ['id' => $tid, 'label' => $label];
        }
        mysqli_stmt_close($listStmt);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['merge_tickets'])) {
    itm_require_post_csrf();
    $targetId = (int)($_POST['target_ticket_id'] ?? 0);
    $actorId = (int)($_SESSION['employee_id'] ?? 0);
    if (!$sourceTicket || !empty($sourceTicket['deleted_at']) || !empty($sourceTicket['merged_into_ticket_id'])) {
        $error = 'Source ticket is not available for merge.';
    } elseif ($targetId <= 0) {
        $error = 'Select a target ticket.';
    } else {
        $result = itm_ticket_merge_tickets($conn, (int)$company_id, $sourceId, $targetId, $actorId);
        if (!empty($result['ok'])) {
            header('Location: view.php?id=' . $targetId . '&merged=1');
            exit;
        }
        $error = (string)($result['error'] ?? 'Merge failed.');
    }
}
$crud_title = 'Merge ticket';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), 'tickets', (string)$crud_title); ?>
    <title><?php echo sanitize($crud_title); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config ?? [])); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="Merge ticket">🔗</h1>
            <div class="card" style="max-width:640px;">
                <?php if (!$sourceTicket || !empty($sourceTicket['deleted_at'])): ?>
                    <div class="alert alert-danger">Source ticket not found.</div>
                <?php elseif (!empty($sourceTicket['merged_into_ticket_id'])): ?>
                    <div class="alert alert-danger">This ticket was already merged.</div>
                <?php else: ?>
                    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?>
                    <p><strong>Source:</strong> <?php
                        $srcLabel = trim((string)($sourceTicket['ticket_external_code'] ?? ''));
                        if ($srcLabel !== '') $srcLabel .= ' — ';
                        $srcLabel .= (string)($sourceTicket['title'] ?? '');
                        echo sanitize($srcLabel); ?></p>
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                        <input type="hidden" name="source_id" value="<?php echo (int)$sourceId; ?>">
                        <div class="form-group">
                            <label for="target_ticket_id">Merge into ticket</label>
                            <select name="target_ticket_id" id="target_ticket_id" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($targetOptions as $opt): ?>
                                    <option value="<?php echo (int)$opt['id']; ?>"><?php echo sanitize($opt['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="merge_tickets" value="1" class="btn btn-primary" title="Merge">🔗</button>
                            <a href="view.php?id=<?php echo (int)$sourceId; ?>" class="btn" title="Back">🔙</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
