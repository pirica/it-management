<?php
/**
 * Ticket Survey Dashboard — KPI summary for post-ticket questionnaires.
 */

$crud_title = 'Survey Dashboard';

require_once dirname(__DIR__, 2) . '/config/config.php';

itm_require_crud_role_module_permission($conn, 'view', 'ticket_survey_dashboard');

$questionnaireId = (int)($_GET['questionnaire_id'] ?? 0);
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$questionnaires = [];
$qStmt = mysqli_prepare($conn, 'SELECT id, name FROM ticket_questionnaires WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC');
if ($qStmt) {
    mysqli_stmt_bind_param($qStmt, 'i', $company_id);
    mysqli_stmt_execute($qStmt);
    $qRes = mysqli_stmt_get_result($qStmt);
    while ($qRes && ($qRow = mysqli_fetch_assoc($qRes))) {
        $questionnaires[] = $qRow;
    }
    mysqli_stmt_close($qStmt);
}

$stats = itm_ticket_survey_stats_aggregate(
    $conn,
    (int)$company_id,
    $questionnaireId,
    $dateFrom !== '' ? $dateFrom : null,
    $dateTo !== '' ? $dateTo : null
);

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$tsdEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
$tsdIcon = itm_resolve_module_sidebar_icon($conn, (int)$company_id, $tsdEmployeeId, $moduleSlug);
$moduleListHeading = trim($tsdIcon . ' ' . itm_module_access_strip_catalog_label_prefix($crud_title));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    if (!isset($currentUiConfig)) {
        $currentUiConfig = $ui_config ?? [];
    }
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), $tsdEmployeeId, $moduleSlug, (string)$crud_title);
    ?>
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .tsvd-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .tsvd-summary-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 14px; text-align: center; }
        .tsvd-summary-card strong { display: block; font-size: 1.5rem; }
        .tsvd-muted { color: var(--text-secondary); font-size: 0.875rem; }
        .tsvd-nps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 12px; }
        .tsvd-filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="Survey Dashboard"><?php echo sanitize($moduleListHeading); ?></h1>
            <p class="tsvd-muted">Post-ticket survey KPIs — filter by questionnaire and issued date range. Average score (30d) uses completed surveys in the last 30 days.</p>

            <div class="card" style="margin-bottom:20px;">
                <form method="GET" class="tsvd-filter-form">
                    <div class="form-group">
                        <label for="tsvdQuestionnaire">Questionnaire</label>
                        <select name="questionnaire_id" id="tsvdQuestionnaire">
                            <option value="0">All questionnaires</option>
                            <?php foreach ($questionnaires as $qRow): ?>
                                <option value="<?php echo (int)$qRow['id']; ?>" <?php echo $questionnaireId === (int)$qRow['id'] ? 'selected' : ''; ?>><?php echo sanitize($qRow['name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tsvdDateFrom">From (issued)</label>
                        <input type="date" name="date_from" id="tsvdDateFrom" value="<?php echo sanitize($dateFrom); ?>">
                    </div>
                    <div class="form-group">
                        <label for="tsvdDateTo">To (issued)</label>
                        <input type="date" name="date_to" id="tsvdDateTo" value="<?php echo sanitize($dateTo); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="index.php" class="btn" title="Clear filters">🔙</a>
                    </div>
                </form>
            </div>

            <div class="tsvd-summary-grid">
                <div class="tsvd-summary-card">
                    <span class="tsvd-muted">Response rate</span>
                    <strong><?php echo $stats['response_rate'] !== null ? sanitize((string)$stats['response_rate']) . '%' : '—'; ?></strong>
                    <span class="tsvd-muted"><?php echo (int)$stats['completed']; ?> / <?php echo (int)$stats['issued']; ?> issued</span>
                </div>
                <div class="tsvd-summary-card">
                    <span class="tsvd-muted">Avg score (30d)</span>
                    <strong><?php echo $stats['avg_score_30d'] !== null ? sanitize((string)$stats['avg_score_30d']) : '—'; ?></strong>
                </div>
                <div class="tsvd-summary-card">
                    <span class="tsvd-muted">NPS score</span>
                    <strong><?php echo $stats['nps_score'] !== null ? sanitize((string)$stats['nps_score']) : '—'; ?></strong>
                </div>
            </div>

            <div class="card">
                <h2 style="margin-top:0;" title="NPS buckets">NPS buckets</h2>
                <p class="tsvd-muted">Rating questions only — promoters (4–5), passives (3), detractors (1–2).</p>
                <div class="tsvd-nps-grid">
                    <div class="tsvd-summary-card">
                        <span class="tsvd-muted">Promoters</span>
                        <strong><?php echo (int)$stats['nps_promoters']; ?></strong>
                    </div>
                    <div class="tsvd-summary-card">
                        <span class="tsvd-muted">Passives</span>
                        <strong><?php echo (int)$stats['nps_passives']; ?></strong>
                    </div>
                    <div class="tsvd-summary-card">
                        <span class="tsvd-muted">Detractors</span>
                        <strong><?php echo (int)$stats['nps_detractors']; ?></strong>
                    </div>
                </div>
            </div>

            <p class="tsvd-muted" style="margin-top:16px;">
                <a class="itm-plain-link" href="../ticket_surveys/index.php">Open ticket surveys list</a> ·
                <a class="itm-plain-link" href="../ticket_questionnaires/index.php">Manage questionnaires</a>
            </p>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
