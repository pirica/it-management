<?php
/**
 * Appointment visit reasons — list for the booking dropdown ("What is the reason for your appointment?").
 */
require_once __DIR__ . '/aps_init.php';

aps_require_permission($conn, 'view');

$flashMessage = trim((string)($_GET['msg'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'sort_order'));
$dir = strtoupper(trim((string)($_GET['dir'] ?? 'ASC'))) === 'DESC' ? 'DESC' : 'ASC';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = itm_resolve_records_per_page($ui_config ?? null);

$allowedSort = ['name', 'sort_order', 'active', 'id'];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'sort_order';
}

$visitReasons = itm_appointment_settings_load_visit_reasons_admin($conn, $company_id);
if ($search !== '') {
    $searchLower = strtolower($search);
    $visitReasons = array_values(array_filter($visitReasons, static function ($row) use ($searchLower) {
        $name = strtolower((string)($row['name'] ?? ''));
        $sortOrder = (string)(int)($row['sort_order'] ?? 0);
        return strpos($name, $searchLower) !== false || strpos($sortOrder, $searchLower) !== false;
    }));
}

usort($visitReasons, static function ($a, $b) use ($sort, $dir) {
    $left = $a[$sort] ?? '';
    $right = $b[$sort] ?? '';
    if ($sort === 'sort_order' || $sort === 'active' || $sort === 'id') {
        $cmp = (int)$left <=> (int)$right;
    } else {
        $cmp = strcasecmp((string)$left, (string)$right);
    }
    return $dir === 'DESC' ? -$cmp : $cmp;
});

$totalRows = count($visitReasons);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$listRows = array_slice($visitReasons, $offset, $perPage);

$apsCanCreate = itm_user_has_role_module_permission(
    $conn,
    $employee_id,
    $company_id,
    itm_resolve_rbac_module_name_for_slug($conn, $moduleSlug),
    'create'
);

function aps_visit_reason_list_query(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $parts = [];
    if (trim((string)($params['search'] ?? '')) !== '') {
        $parts[] = 'search=' . rawurlencode((string)$params['search']);
    }
    if (isset($params['sort'])) {
        $parts[] = 'sort=' . rawurlencode((string)$params['sort']);
    }
    if (isset($params['dir'])) {
        $parts[] = 'dir=' . rawurlencode((string)$params['dir']);
    }
    if (isset($params['page'])) {
        $parts[] = 'page=' . (int)$params['page'];
    }
    return $parts === [] ? '' : '?' . implode('&', $parts);
}

function aps_visit_reason_sort_link(string $column, string $label, string $currentSort, string $currentDir): string
{
    $nextDir = ($currentSort === $column && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $arrow = '';
    if ($currentSort === $column) {
        $arrow = $currentDir === 'ASC' ? ' ▲' : ' ▼';
    }
    $href = 'list_all.php' . aps_visit_reason_list_query([
        'sort' => $column,
        'dir' => $nextDir,
        'page' => 1,
    ]);
    return '<a class="itm-plain-link" href="' . sanitize($href) . '">' . sanitize($label . $arrow) . '</a>';
}

$pageTitle = 'Visit reasons';
aps_render_page_shell_open($conn, $company_id, $employee_id, $pageTitle);
?>
<div class="card" style="margin-bottom:16px;">
    <h1 title="Visit reasons for appointment booking">📋</h1>
    <p>Options shown on the employee booking form under <strong>What is the reason for your appointment?</strong></p>
    <p>
        <a href="index.php" class="btn btn-sm" title="Back to appointment settings">🔙</a>
        <?php if ($apsCanCreate): ?>
        <a href="create.php?kind=visit_reason" class="btn btn-sm btn-primary" title="Create">➕</a>
        <?php endif; ?>
        <a href="<?php echo sanitize(BASE_URL . 'modules/appointments/'); ?>" class="btn btn-sm" title="Open employee booking">📅</a>
    </p>
    <?php if ($flashMessage !== ''): ?>
        <p><?php echo sanitize($flashMessage); ?></p>
    <?php endif; ?>
</div>

<div class="card">
    <form method="get" action="list_all.php" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
        <input class="form-control" type="search" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search name or sort order" style="max-width:280px;">
        <button type="submit" class="btn btn-sm" title="Search">Search</button>
        <?php if ($search !== ''): ?>
        <a href="list_all.php" class="btn btn-sm" title="Clear">🔙</a>
        <?php endif; ?>
    </form>
    <table class="appointment-list-table" data-itm-no-import-excel="1">
        <thead>
        <tr>
            <th><?php echo aps_visit_reason_sort_link('name', 'Name', $sort, $dir); ?></th>
            <th><?php echo aps_visit_reason_sort_link('sort_order', 'Sort', $sort, $dir); ?></th>
            <th><?php echo aps_visit_reason_sort_link('active', 'Active', $sort, $dir); ?></th>
            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($listRows as $reason): ?>
            <tr>
                <td><?php echo sanitize($reason['name'] ?? ''); ?></td>
                <td><?php echo (int)($reason['sort_order'] ?? 0); ?></td>
                <td><?php echo (int)($reason['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                <?php aps_actions_cell_open(); ?>
                <a class="btn btn-sm" href="view.php?kind=visit_reason&amp;id=<?php echo (int)$reason['id']; ?>" title="View">🔎</a>
                <a class="btn btn-sm" href="edit.php?kind=visit_reason&amp;id=<?php echo (int)$reason['id']; ?>" title="Edit">✏️</a>
                <form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this visit reason?');">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="kind" value="visit_reason">
                    <input type="hidden" name="id" value="<?php echo (int)$reason['id']; ?>">
                    <input type="hidden" name="return" value="list_all">
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                </form>
                <?php aps_actions_cell_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($listRows)): ?>
            <tr><td colspan="4"><?php echo $search !== '' ? 'No visit reasons match your search.' : 'No visit reasons yet — add options for the booking dropdown.'; ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php if ($totalPages > 1): ?>
    <p style="margin-top:12px;">
        <?php if ($page > 1): ?>
        <a class="btn btn-sm" href="list_all.php<?php echo sanitize(aps_visit_reason_list_query(['page' => 1])); ?>" title="First page">⏮️</a>
        <a class="btn btn-sm" href="list_all.php<?php echo sanitize(aps_visit_reason_list_query(['page' => $page - 1])); ?>" title="Previous page">◀️</a>
        <?php endif; ?>
        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
        <?php if ($page < $totalPages): ?>
        <a class="btn btn-sm" href="list_all.php<?php echo sanitize(aps_visit_reason_list_query(['page' => $page + 1])); ?>" title="Next page">▶️</a>
        <a class="btn btn-sm" href="list_all.php<?php echo sanitize(aps_visit_reason_list_query(['page' => $totalPages])); ?>" title="Last page">⏭️</a>
        <?php endif; ?>
    </p>
    <?php endif; ?>
</div>
<?php
aps_render_page_shell_close();
