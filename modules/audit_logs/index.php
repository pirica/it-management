<?php
/**
 * Audit Logs Module - Index
 * 
 * Provides a searchable, filterable history of all changes made within the system.
 * Shows who made a change, when, in which table, and exactly what values changed.
 * Logs are scoped to the current company context.
 */

require '../../config/config.php';

/**
 * Audit Logs - Search and List
 * 
 * Displays a historical record of all database changes (INSERT, UPDATE, DELETE)
 * that occurred within the current company's context. 
 * It allows IT staff to track who changed what and when.
 */

// Ensure the user has a valid company context
$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId <= 0) {
    http_response_code(403);
    exit('Company context is required.');
}

// Extract filter parameters from the URL for persistent search/filtering
$search = trim((string)($_GET['search'] ?? ''));
$action = strtoupper(trim((string)($_GET['action_filter'] ?? '')));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$allowedActions = ['INSERT', 'UPDATE', 'DELETE'];
if (!in_array($action, $allowedActions, true)) {
    $action = '';
}

// Initialize dynamic query components
$where = ['al.company_id = ?'];
$params = [$companyId];
$types = 'i';

// Apply text search across multiple fields
if ($search !== '') {
    $where[] = '(al.table_name LIKE ? OR CAST(al.record_id AS CHAR) LIKE ? OR CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, "")) LIKE ? OR COALESCE(al.actor_username, u.username, "") LIKE ? OR COALESCE(al.actor_email, u.email, "") LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'sssss';
}

// Apply action filter (INSERT/UPDATE/DELETE)
if ($action !== '') {
    $where[] = 'al.action = ?';
    $params[] = $action;
    $types .= 's';
}

// Apply date range filters
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
    $where[] = 'al.changed_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
    $where[] = 'al.changed_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}

// Resolve pagination limit
$perPage = itm_resolve_records_per_page($ui_config ?? null);

// Final query construction. 
// Uses JOINs to resolve user details and Prepared Statements for security.
$sql = 'SELECT al.*, u.username, u.email, u.first_name, u.last_name '
     . 'FROM audit_logs al '
     . 'LEFT JOIN users u ON u.id = al.user_id '
     . 'WHERE ' . implode(' AND ', $where) . ' '
     . 'ORDER BY al.changed_at DESC LIMIT ' . (int)$perPage;

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    http_response_code(500);
    exit('Unable to load audit logs.');
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];
while ($result && ($row = mysqli_fetch_assoc($result))) {
    $rows[] = $row;
}
mysqli_stmt_close($stmt);

/**
 * Utility to truncate long JSON strings for cleaner table display.
 */
function itm_audit_preview($text, $limit = 120) {
    $text = trim((string)$text);
    if ($text === '') {
        return '—';
    }

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return mb_substr($text, 0, $limit) . '...';
}

$moduleListHeading = '🧾 Audit Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .audit-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
        .audit-toolbar h1 { margin:0; font-size:1.5rem; font-weight:700; }
        .audit-filters form { display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto auto; gap:10px; align-items:end; }
        .audit-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
        .audit-kpi { border:1px solid var(--border); border-radius:10px; padding:10px 12px; background:var(--input-bg); }
        .audit-kpi .label { font-size:12px; opacity:.8; margin-bottom:4px; }
        .audit-kpi .value { font-size:18px; font-weight:700; }
        .audit-table-wrap { overflow-x:auto; }
        .audit-row-chip { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; border:1px solid transparent; }
        .audit-row-chip.insert { background:#e8f8ee; border-color:#9cd8b1; color:#18794e; }
        .audit-row-chip.update { background:#eef4ff; border-color:#9eb8ee; color:#1d4f91; }
        .audit-row-chip.delete { background:#fdecec; border-color:#f0b6b6; color:#a52727; }
        .audit-user { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .audit-summary { display:grid; gap:8px; }
        .audit-json { white-space:pre-wrap; word-break:break-word; max-width:520px; margin:0; font-size:12px; line-height:1.4; background:var(--input-bg); border:1px solid var(--border); border-radius:8px; padding:10px; }
        @media (max-width:1080px) { .audit-filters form { grid-template-columns:1fr 1fr; } .audit-kpis { grid-template-columns:1fr 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
            <div class="audit-toolbar">
                <h1><?php echo sanitize($moduleListHeading); ?></h1>
                <a href="index.php" class="btn btn-primary">🔄 Refresh</a>
            </div>

            <!-- SEARCH AND FILTER FORM -->
            <div class="card audit-filters" style="margin-bottom:16px;">
                <form method="GET">
                    <div class="form-group" style="margin:0;">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Table, record id, or user name">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Action</label>
                        <select name="action_filter">
                            <option value="">All</option>
                            <?php foreach ($allowedActions as $opt): ?>
                                <option value="<?php echo sanitize($opt); ?>" <?php echo $action === $opt ? 'selected' : ''; ?>><?php echo sanitize($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo sanitize($dateFrom); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo sanitize($dateTo); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn">Clear</a>
                </form>
            </div>

            <div class="audit-kpis">
                <div class="audit-kpi"><div class="label">Rows on Screen</div><div class="value"><?php echo (int)count($rows); ?></div></div>
                <div class="audit-kpi"><div class="label">Insert Events</div><div class="value"><?php echo (int)count(array_filter($rows, static fn($r) => (($r['action'] ?? '') === 'INSERT'))); ?></div></div>
                <div class="audit-kpi"><div class="label">Update Events</div><div class="value"><?php echo (int)count(array_filter($rows, static fn($r) => (($r['action'] ?? '') === 'UPDATE'))); ?></div></div>
                <div class="audit-kpi"><div class="label">Delete Events</div><div class="value"><?php echo (int)count(array_filter($rows, static fn($r) => (($r['action'] ?? '') === 'DELETE'))); ?></div></div>
            </div>

            <!-- LOG DATA TABLE -->
            <div class="card audit-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>User</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>Action</th>
                            <th>Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="6">No audit logs found for the selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            // Determine the most appropriate user display name
                            $userName = trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
                            $userEmail = trim((string)($row['actor_email'] ?? $row['email'] ?? ''));
                            if ($userName === '') {
                                $userName = trim((string)($row['actor_username'] ?? ''));
                            }
                            if ($userName === '' && $userEmail !== '') {
                                $userName = $userEmail;
                            }
                            if ($userName === '') {
                                $userName = trim((string)($row['username'] ?? ''));
                            }
                            if ($userName === '') {
                                $userName = $row['user_id'] ? ('User #' . (int)$row['user_id']) : 'System';
                            }

                            // Styling based on action type
                            $actionClass = 'update';
                            if (($row['action'] ?? '') === 'INSERT') {
                                $actionClass = 'insert';
                            } elseif (($row['action'] ?? '') === 'DELETE') {
                                $actionClass = 'delete';
                            }

                            $oldValues = (string)($row['old_values'] ?? '');
                            $newValues = (string)($row['new_values'] ?? '');
                            $previewText = 'Old: ' . itm_audit_preview($oldValues, 80) . ' | New: ' . itm_audit_preview($newValues, 80);
                            ?>
                            <tr>
                                <td><?php echo sanitize((string)$row['changed_at']); ?></td>
                                <td class="audit-user" title="<?php echo sanitize($userEmail !== '' ? ($userName . ' <' . $userEmail . '>') : $userName); ?>">
                                    <?php echo sanitize($userName); ?>
                                    <?php if ($userEmail !== ''): ?>
                                        <br><small><?php echo sanitize($userEmail); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitize((string)$row['table_name']); ?></td>
                                <td><?php echo (int)$row['record_id']; ?></td>
                                <td><span class="audit-row-chip <?php echo sanitize($actionClass); ?>"><?php echo sanitize((string)$row['action']); ?></span></td>
                                <td class="itm-actions-cell itm-actions-left">
                                    <div class="audit-summary">
                                        <span><?php echo sanitize($previewText); ?></span>
                                        <details>
                                            <summary class="btn btn-sm btn-primary" style="cursor:pointer;">Click to see more</summary>
                                            <div class="audit-json"><strong>Old Values</strong><br><?php echo sanitize($oldValues !== '' ? $oldValues : '—'); ?></div>
                                            <div class="audit-json"><strong>New Values</strong><br><?php echo sanitize($newValues !== '' ? $newValues : '—'); ?></div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
