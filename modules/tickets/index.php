<?php
require '../../config/config.php';

function ticket_is_valid_hex_color(string $value): bool
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
}

$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchSql = " AND (\n        CAST(t.id AS CHAR) LIKE '{$searchEsc}'\n        OR t.ticket_external_code LIKE '{$searchEsc}'\n        OR t.title LIKE '{$searchEsc}'\n        OR ts.name LIKE '{$searchEsc}'\n        OR tp.name LIKE '{$searchEsc}'\n        OR CAST(t.created_at AS CHAR) LIKE '{$searchEsc}'\n    )";
}

$sortableColumns = ['id', 'ticket_external_code', 'title', 'status_name', 'priority_name', 'created_at'];
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
    'ticket_external_code' => 't.ticket_external_code',
    'title' => 't.title',
    'status_name' => 'ts.name',
    'priority_name' => 'tp.name',
    'created_at' => 't.created_at',
];

$items = mysqli_query(
    $conn,
    "SELECT t.*, ts.name AS status_name, ts.color AS status_color, tp.name AS priority_name
     FROM tickets t
     LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
     LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
     WHERE t.company_id = $company_id{$searchSql}
     ORDER BY {$orderByMap[$sort]} {$dir}"
);
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
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>🎫 Tickets</h1>
                <a class="btn btn-primary" href="create.php">➕</a>
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
                        <?php foreach (['id' => 'ID', 'ticket_external_code' => 'External Code', 'title' => 'Title', 'status_name' => 'Status', 'priority_name' => 'Priority', 'created_at' => 'Created'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($items && mysqli_num_rows($items)): while ($t = mysqli_fetch_assoc($items)): ?>
                        <?php $rowColor = (isset($t['ui_color']) && ticket_is_valid_hex_color((string)$t['ui_color'])) ? (string)$t['ui_color'] : ''; ?>
                        <tr<?php echo $rowColor !== '' ? ' class="ticket-row-colorized" style="--ticket-row-color:' . sanitize($rowColor) . ';border-left:4px solid ' . sanitize($rowColor) . ';"' : ''; ?>>
                            <td><?php echo (int)$t['id']; ?></td>
                            <td><?php echo sanitize($t['ticket_external_code'] ?? '-'); ?></td>
                            <td><?php echo sanitize($t['title']); ?></td>
                            <td><span class="badge" style="background-color:<?php echo sanitize($t['status_color'] ?: '#9aa4b2'); ?>33;color:<?php echo sanitize($t['status_color'] ?: '#9aa4b2'); ?>;"><?php echo sanitize($t['status_name'] ?: 'Open'); ?></span></td>
                            <td><?php echo sanitize($t['priority_name'] ?: '-'); ?></td>
                            <td><?php echo sanitize($t['created_at']); ?></td>
                            <td>
                                <a class="btn btn-sm" href="view.php?id=<?php echo (int)$t['id']; ?>">👁️</a>
                                <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$t['id']; ?>">✏️</a>
                                <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$t['id']; ?>" onclick="return confirm('Delete ticket?');">🗑️</a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
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
