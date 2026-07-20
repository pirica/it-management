<?php
/**
 * IDFs — list all records (up to 200) for the active company.
 */
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrf = itm_get_csrf_token();

$sortMap = [
    'id' => 'i.id',
    'name' => 'i.name',
    'idf_code' => 'i.idf_code',
    'location' => 'l.name',
    'rack' => 'r.name',
    'active' => 'i.active',
];
$sort = (string)($_GET['sort'] ?? 'id');
if (!isset($sortMap[$sort])) {
    $sort = 'id';
}
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$orderSql = $sortMap[$sort] . ' ' . $dir . ', i.id DESC';

$idfs = [];
if ($company_id > 0) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT i.id, i.name, i.idf_code, i.active, l.name AS location_name, r.name AS rack_name
         FROM idfs i
         LEFT JOIN it_locations l ON l.id = i.location_id AND l.company_id = i.company_id
         LEFT JOIN racks r ON r.id = i.rack_id AND r.company_id = i.company_id
         WHERE i.company_id = ?
         ORDER BY {$orderSql}
         LIMIT 200"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $idfs[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

function idf_list_all_sort_url(string $column, string $currentSort, string $currentDir): string
{
    $nextDir = ($currentSort === $column && strtolower($currentDir) === 'asc') ? 'DESC' : 'ASC';
    return 'list_all.php?' . http_build_query(['sort' => $column, 'dir' => $nextDir]);
}

function idf_list_all_sort_indicator(string $column, string $currentSort, string $currentDir): string
{
    if ($currentSort !== $column) {
        return '';
    }
    return strtolower($currentDir) === 'asc' ? ' ▲' : ' ▼';
}
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
if (!isset($crud_title)) {
    $crud_title = 'IDFs — List all';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>

    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                <h1>🗄️ IDFs — List all</h1>
                <a class="btn" href="index.php">🔙 Index</a>
            </div>
            <div class="card" style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <th><a href="<?php echo sanitize(idf_list_all_sort_url('id', $sort, $dir)); ?>" style="text-decoration:none;color:inherit;">ID<?php echo sanitize(idf_list_all_sort_indicator('id', $sort, $dir)); ?></a></th>
                        <th><a href="<?php echo sanitize(idf_list_all_sort_url('name', $sort, $dir)); ?>" style="text-decoration:none;color:inherit;">Name<?php echo sanitize(idf_list_all_sort_indicator('name', $sort, $dir)); ?></a></th>
                        <th><a href="<?php echo sanitize(idf_list_all_sort_url('idf_code', $sort, $dir)); ?>" style="text-decoration:none;color:inherit;">Code<?php echo sanitize(idf_list_all_sort_indicator('idf_code', $sort, $dir)); ?></a></th>
                        <th><a href="<?php echo sanitize(idf_list_all_sort_url('location', $sort, $dir)); ?>" style="text-decoration:none;color:inherit;">Location<?php echo sanitize(idf_list_all_sort_indicator('location', $sort, $dir)); ?></a></th>
                        <th><a href="<?php echo sanitize(idf_list_all_sort_url('rack', $sort, $dir)); ?>" style="text-decoration:none;color:inherit;">Rack<?php echo sanitize(idf_list_all_sort_indicator('rack', $sort, $dir)); ?></a></th>
                        <th><a href="<?php echo sanitize(idf_list_all_sort_url('active', $sort, $dir)); ?>" style="text-decoration:none;color:inherit;">Active<?php echo sanitize(idf_list_all_sort_indicator('active', $sort, $dir)); ?></a></th>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$idfs): ?>
                        <tr><td colspan="7" style="text-align:center;">No records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($idfs as $idf): ?>
                            <tr>
                                <td><?php echo (int)$idf['id']; ?></td>
                                <td><?php echo sanitize((string)($idf['name'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($idf['idf_code'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($idf['location_name'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($idf['rack_name'] ?? '')); ?></td>
                                <td><?php echo ((int)($idf['active'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?></td>
                                <td class="itm-actions-cell">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$idf['id']; ?>">🔎</a>
                                    <a class="btn btn-sm" href="index.php?edit_idf=<?php echo (int)$idf['id']; ?>">✏️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($idfs) >= 200): ?>
                <p style="margin-top:12px;opacity:.85;">Showing the first 200 IDFs. Use <a href="index.php">index</a> for search and pagination.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
</body>
</html>
