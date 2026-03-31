<?php
require '../../config/config.php';

$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
$params = [];
$types = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchSql = " AND (
        CAST(t.id AS CHAR) LIKE ?
        OR t.ticket_code LIKE ?
        OR t.title LIKE ?
        OR ts.name LIKE ?
        OR tp.name LIKE ?
        OR CAST(t.created_at AS CHAR) LIKE ?
    )";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchPattern;
        $types .= 's';
    }
}

$sortableColumns = ['id', 'ticket_code', 'title', 'status_name', 'priority_name', 'created_at'];
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$orderByMap = [
    'id' => 't.id',
    'ticket_code' => 't.ticket_code',
    'title' => 't.title',
    'status_name' => 'ts.name',
    'priority_name' => 'tp.name',
    'created_at' => 't.created_at',
];

$sql = "SELECT t.*, ts.name AS status_name, ts.color AS status_color, tp.name AS priority_name
        FROM tickets t
        LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
        LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
        WHERE t.company_id = $company_id{$searchSql}
        ORDER BY {$orderByMap[$sort]} {$dir}";

$stmt = mysqli_prepare($conn, $sql);
$items = [];
if ($stmt) {
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
}

if (($_GET['export'] ?? '') === 'csv' && !empty($items)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tickets_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Code', 'Title', 'Status', 'Priority', 'Created']);
    foreach ($items as $t) {
        fputcsv($output, [
            $t['id'], $t['ticket_code'], $t['title'], $t['status_name'],
            $t['priority_name'], $t['created_at']
        ]);
    }
    fclose($output);
    exit;
}

$errors = [];
$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {
    itm_require_post_csrf();
    $csvData = trim((string)($_POST['csv_text'] ?? ''));
    if ($csvData === '') {
        $errors[] = 'CSV text is empty.';
    } else {
        $lines = preg_split('/\r\n|\n|\r/', $csvData);
        $created = 0;
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') continue;
            $parts = str_getcsv($line);
            if (count($parts) < 1) continue;

            $title = $parts[0] ?? '';
            if (!$title) continue;

            $stmt = mysqli_prepare($conn, "INSERT INTO tickets (company_id, title, status_id, priority_id, created_by_user_id) VALUES (?, ?, 1, 1, 1)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $company_id, $title);
                if (mysqli_stmt_execute($stmt)) $created++;
                mysqli_stmt_close($stmt);
            }
        }
        $messages[] = "Imported $created records. (Basic title import)";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endforeach; ?>
            <?php foreach ($messages as $msg): ?><div class="alert alert-success"><?php echo sanitize($msg); ?></div><?php endforeach; ?>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:gap;gap:10px;">
                <h1>🎫 Tickets</h1>
                <div style="display:flex;gap:8px;">
                    <a class="btn btn-primary" href="create.php">➕ Add New</a>
                    <a href="?export=csv&search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>" class="btn">⬇ Export CSV</a>
                </div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                    <input type="hidden" name="action" value="import_csv">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label for="csv_text">Import CSV (title)</label>
                        <textarea id="csv_text" name="csv_text" rows="2" placeholder="Issue title"></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm">📥 Import CSV</button>
                </form>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="ticketSearch">Search (all fields)</label>
                        <input type="text" id="ticketSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%wifi%%">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn btn-sm">Clear</a>
                    </div>
                </form>
            </div>
            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <?php foreach (['id' => 'ID', 'ticket_code' => 'Code', 'title' => 'Title', 'status_name' => 'Status', 'priority_name' => 'Priority', 'created_at' => 'Created'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($items)): foreach ($items as $t): ?>
                        <tr>
                            <td><?php echo (int)$t['id']; ?></td>
                            <td><?php echo sanitize($t['ticket_code'] ?? '-'); ?></td>
                            <td><?php echo sanitize($t['title']); ?></td>
                            <td><span class="badge" style="background-color:<?php echo sanitize($t['status_color'] ?: '#9aa4b2'); ?>33;color:<?php echo sanitize($t['status_color'] ?: '#9aa4b2'); ?>;"><?php echo sanitize($t['status_name'] ?: 'Open'); ?></span></td>
                            <td><?php echo sanitize($t['priority_name'] ?: '-'); ?></td>
                            <td><?php echo sanitize($t['created_at']); ?></td>
                            <td class="itm-actions-cell">
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$t['id']; ?>">👁️</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$t['id']; ?>">✏️</a>
                                    <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$t['id']; ?>" onclick="return confirm('Delete ticket?');">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" style="text-align:center;">No tickets found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
