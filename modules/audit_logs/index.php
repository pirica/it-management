<?php
require '../../config/config.php';

$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId <= 0) {
    http_response_code(403);
    exit('Company context is required.');
}

$search = trim((string)($_GET['search'] ?? ''));
$action = strtoupper(trim((string)($_GET['action_filter'] ?? '')));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$allowedActions = ['INSERT', 'UPDATE', 'DELETE'];
if (!in_array($action, $allowedActions, true)) {
    $action = '';
}

$where = ['al.company_id = ?'];
$params = [$companyId];
$types = 'i';

if ($search !== '') {
    $where[] = '(al.table_name LIKE ? OR CAST(al.record_id AS CHAR) LIKE ? OR CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, "")) LIKE ? OR COALESCE(u.username, "") LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'ssss';
}

if ($action !== '') {
    $where[] = 'al.action = ?';
    $params[] = $action;
    $types .= 's';
}

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

$sql = 'SELECT al.*, u.username, u.first_name, u.last_name '
     . 'FROM audit_logs al '
     . 'LEFT JOIN users u ON u.id = al.user_id '
     . 'WHERE ' . implode(' AND ', $where) . ' '
     . 'ORDER BY al.changed_at DESC LIMIT 300';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .audit-json {
            white-space: pre-wrap;
            word-break: break-word;
            max-width: 500px;
            margin: 8px 0 0;
            font-size: 12px;
            line-height: 1.4;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px;
        }

        .audit-summary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .audit-user {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>Audit Logs</h1>
                <a href="index.php" class="btn btn-primary">🔄 Refresh</a>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto auto;gap:10px;align-items:end;">
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

            <div class="card">
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
                            $userName = trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
                            if ($userName === '') {
                                $userName = trim((string)($row['username'] ?? ''));
                            }
                            if ($userName === '') {
                                $userName = $row['user_id'] ? ('User #' . (int)$row['user_id']) : 'System';
                            }

                            $oldValues = (string)($row['old_values'] ?? '');
                            $newValues = (string)($row['new_values'] ?? '');
                            $previewText = 'Old: ' . itm_audit_preview($oldValues, 80) . ' | New: ' . itm_audit_preview($newValues, 80);
                            ?>
                            <tr>
                                <td><?php echo sanitize((string)$row['changed_at']); ?></td>
                                <td class="audit-user" title="<?php echo sanitize($userName); ?>"><?php echo sanitize($userName); ?></td>
                                <td><?php echo sanitize((string)$row['table_name']); ?></td>
                                <td><?php echo (int)$row['record_id']; ?></td>
                                <td><?php echo sanitize((string)$row['action']); ?></td>
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
