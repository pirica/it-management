<?php
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

// Why: table-tools.js Import Excel POSTs JSON import_excel_rows to this index (flattened list contract).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode((string) $rawBody, true);
    if (is_array($jsonBody) && isset($jsonBody['import_excel_rows'])) {
        header('Content-Type: application/json; charset=utf-8');

        $requestToken = (string) ($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($requestToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $importRows = $jsonBody['import_excel_rows'];
        if (!is_array($importRows) || count($importRows) < 2) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'The uploaded file has no data rows.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $headerRow = array_map('trim', array_map('strval', (array) ($importRows[0] ?? [])));
        $columnKeys = [];
        foreach ($headerRow as $headerValue) {
            $columnKeys[] = strtolower(preg_replace('/\s+/', ' ', $headerValue));
        }

        $aliasMap = [
            'id' => 'id',
            'code' => 'channel_code',
            'channel code' => 'channel_code',
            'channel_code' => 'channel_code',
            'name' => 'name',
            'standard' => 'standard',
            'hourly limit' => 'hourly_rate_limit',
            'hourly rate limit' => 'hourly_rate_limit',
            'hourly_rate_limit' => 'hourly_rate_limit',
            'webhook url' => 'webhook_url',
            'webhook_url' => 'webhook_url',
            'active' => 'active',
            'api key' => 'api_key',
            'api_key' => 'api_key',
        ];
        $columnFields = [];
        foreach ($columnKeys as $labelKey) {
            $columnFields[] = $aliasMap[$labelKey] ?? null;
        }

        $standards = itm_hotel_booking_distribution_standards();
        $insertedRows = 0;
        $updatedRows = 0;

        for ($rowIndex = 1; $rowIndex < count($importRows); $rowIndex++) {
            $sourceRow = (array) $importRows[$rowIndex];
            if (empty(array_filter($sourceRow, static function ($v) {
                return trim((string) $v) !== '';
            }))) {
                continue;
            }

            $rowValues = [];
            foreach ($columnFields as $idx => $fieldName) {
                if ($fieldName === null) {
                    continue;
                }
                $rawValue = trim((string) ($sourceRow[$idx] ?? ''));
                if ($rawValue === '' || $rawValue === '—') {
                    continue;
                }
                $rowValues[$fieldName] = $rawValue;
            }

            $recordId = isset($rowValues['id']) ? (int) $rowValues['id'] : 0;
            $channelCode = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($rowValues['channel_code'] ?? '')));
            $name = trim((string) ($rowValues['name'] ?? ''));
            $standard = trim((string) ($rowValues['standard'] ?? 'itm_native'));
            if (!isset($standards[$standard])) {
                $standard = 'itm_native';
            }
            $hourlyLimit = max(1, (int) ($rowValues['hourly_rate_limit'] ?? 1000));
            $webhookUrl = trim((string) ($rowValues['webhook_url'] ?? ''));
            if ($webhookUrl !== '' && !preg_match('#^https?://#i', $webhookUrl)) {
                continue;
            }

            $activeRaw = strtolower((string) ($rowValues['active'] ?? '1'));
            $active = in_array($activeRaw, ['0', 'inactive', 'no', 'false', 'off', '❌'], true) ? 0 : 1;

            $plainApiKey = trim((string) ($rowValues['api_key'] ?? ''));
            $rotateApiKey = $plainApiKey !== '';
            if ($rotateApiKey) {
                $prefix = itm_hotel_booking_distribution_api_key_prefix($plainApiKey);
                $hash = itm_hotel_booking_distribution_hash_api_key($plainApiKey);
            }

            if ($recordId > 0) {
                if ($name === '') {
                    continue;
                }
                if ($rotateApiKey) {
                    $upd = mysqli_prepare(
                        $conn,
                        'UPDATE hotel_booking_distribution_channels
                         SET name = ?, standard = ?, api_key_prefix = ?, api_key_hash = ?, webhook_url = NULLIF(?, \'\'), hourly_rate_limit = ?, active = ?, updated_by = ?, updated_at = NOW()
                         WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
                    );
                    if ($upd) {
                        mysqli_stmt_bind_param($upd, 'sssssiiiii', $name, $standard, $prefix, $hash, $webhookUrl, $hourlyLimit, $active, $employee_id, $recordId, $company_id);
                        if (mysqli_stmt_execute($upd)) {
                            $updatedRows += (int) mysqli_stmt_affected_rows($upd);
                        }
                        mysqli_stmt_close($upd);
                    }
                } else {
                    $upd = mysqli_prepare(
                        $conn,
                        'UPDATE hotel_booking_distribution_channels
                         SET name = ?, standard = ?, webhook_url = NULLIF(?, \'\'), hourly_rate_limit = ?, active = ?, updated_by = ?, updated_at = NOW()
                         WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
                    );
                    if ($upd) {
                        mysqli_stmt_bind_param($upd, 'sssiiiii', $name, $standard, $webhookUrl, $hourlyLimit, $active, $employee_id, $recordId, $company_id);
                        if (mysqli_stmt_execute($upd)) {
                            $updatedRows += (int) mysqli_stmt_affected_rows($upd);
                        }
                        mysqli_stmt_close($upd);
                    }
                }
                continue;
            }

            if ($channelCode === '' || $name === '') {
                continue;
            }

            if (!$rotateApiKey) {
                $plainApiKey = itm_hotel_booking_distribution_generate_api_key();
                $prefix = itm_hotel_booking_distribution_api_key_prefix($plainApiKey);
                $hash = itm_hotel_booking_distribution_hash_api_key($plainApiKey);
            }

            $ins = mysqli_prepare(
                $conn,
                'INSERT INTO hotel_booking_distribution_channels (company_id, channel_code, name, standard, api_key_prefix, api_key_hash, webhook_url, hourly_rate_limit, active, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, \'\'), ?, ?, ?, NOW())'
            );
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'issssssiii', $company_id, $channelCode, $name, $standard, $prefix, $hash, $webhookUrl, $hourlyLimit, $active, $employee_id);
                if (mysqli_stmt_execute($ins)) {
                    $insertedRows++;
                }
                mysqli_stmt_close($ins);
            }
        }

        echo json_encode(['ok' => true, 'inserted' => $insertedRows, 'updated' => $updatedRows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();
    $existingCount = itm_hotel_booking_distribution_count_channels($conn, $company_id);
    if ($existingCount === 0) {
        $seedResult = itm_hotel_booking_distribution_seed_sample_data($conn, $company_id, $employee_id);
        $created = (int) ($seedResult['channels_created'] ?? 0);
        if (!empty($seedResult['demo_api_keys'][0])) {
            $_SESSION['hb_dist_seed_demo_api_key'] = (string) $seedResult['demo_api_keys'][0];
        }
        header('Location: index.php?sample_seeded=' . $created);
        exit;
    }
    header('Location: index.php');
    exit;
}

$rows = [];

$totalChannelRows = itm_hotel_booking_distribution_count_channels($conn, $company_id);
$showSampleDataButton = ($totalChannelRows === 0);
$seedDemoApiKey = '';
if (!empty($_SESSION['hb_dist_seed_demo_api_key'])) {
    $seedDemoApiKey = (string) $_SESSION['hb_dist_seed_demo_api_key'];
    unset($_SESSION['hb_dist_seed_demo_api_key']);
}

$ui_config = itm_get_ui_configuration($conn, $company_id, $employee_id);
$perPage = itm_resolve_records_per_page($ui_config ?? null);

$searchRaw = trim((string) ($_GET['search'] ?? ''));
$sortableColumns = ['channel_code', 'name', 'standard', 'api_key_prefix', 'hourly_rate_limit', 'active', 'created_at'];
$sort = (string) ($_GET['sort'] ?? 'channel_code');
$dir = strtoupper((string) ($_GET['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'channel_code';
}

$whereSql = 'company_id = ? AND deleted_at IS NULL';
$listBindTypes = 'i';
$listBindValues = [$company_id];

if ($searchRaw !== '') {
    $searchPattern = (strpos($searchRaw, '%') !== false || strpos($searchRaw, '_') !== false)
        ? $searchRaw
        : '%' . $searchRaw . '%';
    $searchFields = ['id', 'channel_code', 'name', 'standard', 'api_key_prefix', 'hourly_rate_limit', 'active'];
    $searchParts = [];
    foreach ($searchFields as $searchField) {
        $searchParts[] = 'CAST(`' . $searchField . '` AS CHAR) LIKE ?';
        $listBindTypes .= 's';
        $listBindValues[] = $searchPattern;
    }
    $whereSql .= ' AND (' . implode(' OR ', $searchParts) . ')';
}

$totalRows = 0;
$countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS cnt FROM hotel_booking_distribution_channels WHERE ' . $whereSql);
if ($countStmt) {
    mysqli_stmt_bind_param($countStmt, $listBindTypes, ...$listBindValues);
    mysqli_stmt_execute($countStmt);
    $countRes = mysqli_stmt_get_result($countStmt);
    $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
    $totalRows = (int) ($countRow['cnt'] ?? 0);
    mysqli_stmt_close($countStmt);
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$totalPages = max(1, (int) ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sortColumn = str_replace('`', '', $sort);
$sortSql = '`' . $sortColumn . '` ' . $dir;
$listSql = 'SELECT id, channel_code, name, standard, api_key_prefix, hourly_rate_limit, active, created_at
     FROM hotel_booking_distribution_channels
     WHERE ' . $whereSql . '
     ORDER BY ' . $sortSql . '
     LIMIT ?, ?';
$listBindTypesWithLimit = $listBindTypes . 'ii';
$listBindValuesWithLimit = array_merge($listBindValues, [$offset, $perPage]);

$deadByChannel = [];
$deadStmt = mysqli_prepare(
    $conn,
    'SELECT channel_id, COUNT(*) AS dead_count
     FROM hotel_booking_distribution_webhook_queue
     WHERE company_id = ? AND deleted_at IS NULL AND direction = \'outbound\' AND status = \'dead\'
     GROUP BY channel_id'
);
if ($deadStmt) {
    mysqli_stmt_bind_param($deadStmt, 'i', $company_id);
    mysqli_stmt_execute($deadStmt);
    $deadRes = mysqli_stmt_get_result($deadStmt);
    while ($deadRes && ($drow = mysqli_fetch_assoc($deadRes))) {
        $deadByChannel[(int) ($drow['channel_id'] ?? 0)] = (int) ($drow['dead_count'] ?? 0);
    }
    mysqli_stmt_close($deadStmt);
}

$listStmt = mysqli_prepare($conn, $listSql);
if ($listStmt) {
    mysqli_stmt_bind_param($listStmt, $listBindTypesWithLimit, ...$listBindValuesWithLimit);
    mysqli_stmt_execute($listStmt);
    $res = mysqli_stmt_get_result($listStmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($listStmt);
}

$columnLabels = [
    'channel_code' => 'Code',
    'name' => 'Name',
    'standard' => 'Standard',
    'api_key_prefix' => 'Key prefix',
    'hourly_rate_limit' => 'Hourly limit',
    'active' => 'Active',
];

$crud_title = 'Distribution Channels';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_distribution_channels', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Distribution channels">📡</h1>
<div class="itm-hospitality-list-actions" style="margin-bottom:16px;">
<?php itm_hospitality_render_bookings_hub_link('btn'); ?>
<a class="btn btn-primary" href="create.php" title="Create">➕</a>
</div>
<p>Partner channels receive a dedicated API key for <code>modules/hotel_booking_api/api.php</code> (shop, book, cancel, ARI).</p>
<?php if (!empty($_GET['sample_seeded'])): ?>
<p class="badge badge-success">Added <?php echo (int) $_GET['sample_seeded']; ?> sample channel(s) with hotel, room-type, and rate-plan mappings.</p>
<?php if ($seedDemoApiKey !== ''): ?>
<p class="muted">Demo API key (ITM Demo Channel): <code><?php echo sanitize($seedDemoApiKey); ?></code> — use with <code>ITM_DIST_API_KEY</code> in api-examples.</p>
<?php endif; ?>
<?php endif; ?>
<div class="card" style="margin-bottom:16px;">
<form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
<input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
<input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
<input type="hidden" name="page" value="1">
<div class="form-group" style="margin:0;min-width:260px;flex:1;">
<label for="hb-dist-channel-search">Search (all fields)</label>
<input type="text" id="hb-dist-channel-search" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
</div>
<div class="form-actions" style="margin:0;display:flex;gap:8px;">
<button type="submit" class="btn btn-primary">Search</button>
<a href="index.php" class="btn" title="Clear">🔙</a>
</div>
</form>
</div>
<table class="table" data-itm-db-import-endpoint="index.php">
<thead>
<tr>
<?php foreach ($sortableColumns as $sortCol): ?>
<?php if (!isset($columnLabels[$sortCol])) { continue; } ?>
<?php $nextDir = ($sort === $sortCol && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
<th>
<a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sortCol); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int) $page; ?>" style="text-decoration:none;color:inherit;">
<?php echo sanitize($columnLabels[$sortCol]); ?>
<?php if ($sort === $sortCol): ?>
<?php echo $dir === 'ASC' ? '▲' : '▼'; ?>
<?php endif; ?>
</a>
</th>
<?php endforeach; ?>
<th>Dead webhooks</th>
<th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($rows)): ?>
<tr><td colspan="8">No distribution channels yet.</td></tr>
<?php else: ?>
<?php foreach ($rows as $row): ?>
<tr>
<td><?php echo sanitize($row['channel_code'] ?? ''); ?></td>
<td><?php echo sanitize($row['name'] ?? ''); ?></td>
<td><?php echo sanitize($row['standard'] ?? ''); ?></td>
<td><code><?php echo sanitize($row['api_key_prefix'] ?? ''); ?>…</code></td>
<td><?php echo (int) ($row['hourly_rate_limit'] ?? 0); ?></td>
<td><?php
$deadCount = (int) ($deadByChannel[(int) ($row['id'] ?? 0)] ?? 0);
if ($deadCount > 0) {
    echo '<span class="badge badge-danger">' . $deadCount . '</span>';
} else {
    echo '0';
}
?></td>
<td><?php echo !empty($row['active']) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
<td class="itm-actions-cell" data-itm-actions-origin="1">
<div class="itm-actions-wrap">
<a class="btn btn-sm" href="view.php?id=<?php echo (int) $row['id']; ?>" title="View">🔎</a>
<a class="btn btn-sm" href="edit.php?id=<?php echo (int) $row['id']; ?>" title="Edit">✏️</a>
<a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int) $row['id']; ?>" title="Delete">🗑️</a>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
<?php if ($totalRows > $perPage): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;flex-wrap:wrap;gap:8px;">
<div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
<div style="display:flex;gap:4px;align-items:center;">
<?php if ($page > 1): ?>
<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=1" title="First page">⏮️</a>
<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="Previous page">◀️</a>
<?php endif; ?>
<span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
<?php if ($page < $totalPages): ?>
<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="Next page">▶️</a>
<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $totalPages; ?>" title="Last page">⏭️</a>
<?php endif; ?>
</div>
</div>
<?php endif; ?>
<?php if ($showSampleDataButton): ?>
<form method="post" style="margin-top:16px;">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
</form>
<?php endif; ?>
</div>
<?php itm_hospitality_admin_layout_end(); ?>