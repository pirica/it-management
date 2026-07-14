<?php

$crud_table = 'idfs';
$crud_title = 'Idfs';
$crud_action = $crud_action ?? 'index';

require_once __DIR__ . '/../../config/config.php';
// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, 'idfs', (int)($company_id ?? 0));
    }
}


if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrf = itm_get_csrf_token();
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['refresh_select_options'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($company_id <= 0) {
        echo json_encode(['ok' => false, 'message' => 'No active company selected.']);
        exit;
    }

    $refreshTarget = trim((string)($_GET['refresh_select_options'] ?? ''));
    $allowedTargets = [
        'rack' => [
            'sql' => "SELECT id, name FROM racks WHERE company_id=? ORDER BY name",
            'placeholder' => '-- Select rack --',
        ],
        'location' => [
            'sql' => "SELECT id, name FROM it_locations WHERE company_id=? ORDER BY name",
            'placeholder' => '-- Select location --',
        ],
    ];

    if (!isset($allowedTargets[$refreshTarget])) {
        echo json_encode(['ok' => false, 'message' => 'Invalid refresh target.']);
        exit;
    }

    $targetConfig = $allowedTargets[$refreshTarget];
    $options = [];
    $stmtOptions = mysqli_prepare($conn, $targetConfig['sql']);
    if ($stmtOptions) {
        mysqli_stmt_bind_param($stmtOptions, 'i', $company_id);
        mysqli_stmt_execute($stmtOptions);
        $resultOptions = mysqli_stmt_get_result($stmtOptions);
        while ($resultOptions && ($optionRow = mysqli_fetch_assoc($resultOptions))) {
            $options[] = [
                'value' => (int)($optionRow['id'] ?? 0),
                'label' => (string)($optionRow['name'] ?? ''),
            ];
        }
        mysqli_stmt_close($stmtOptions);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Unable to prepare select refresh query.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'placeholder' => $targetConfig['placeholder'],
        'options' => $options,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_idf'])) {
    itm_require_post_csrf();

    $name = trim((string)($_POST['name'] ?? ''));
    $idf_code = trim((string)($_POST['idf_code'] ?? ''));
    $location_id = (int)($_POST['location_id'] ?? 0);
    $rack_id = (int)($_POST['rack_id'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '' || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Please provide IDF name.';
        header('Location: index.php');
        exit;
    }

    $idf_code_val = $idf_code !== '' ? $idf_code : null;
    $notes_val = $notes !== '' ? $notes : null;

    $stmt = mysqli_prepare($conn, "INSERT INTO idfs (company_id, location_id, rack_id, name, idf_code, notes, active) VALUES (?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iiisssi', $company_id, $location_id, $rack_id, $name, $idf_code_val, $notes_val, $active);
        if (!mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_stmt_errno($stmt), mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            header('Location: index.php');
            exit;
        }
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
        header('Location: index.php');
        exit;
    }
    header('Location: view.php?id=' . $newId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_idf'])) {
    itm_require_post_csrf();

    $idf_id = (int)($_POST['idf_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $idf_code = trim((string)($_POST['idf_code'] ?? ''));
    $location_id = (int)($_POST['location_id'] ?? 0);
    $rack_id = (int)($_POST['rack_id'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;

    if ($idf_id <= 0 || $name === '' || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Please provide valid IDF values before saving.';
        header('Location: index.php');
        exit;
    }

    $idf_code_val = $idf_code !== '' ? $idf_code : null;
    $notes_val = $notes !== '' ? $notes : null;

    $updateStmt = mysqli_prepare(
        $conn,
        "UPDATE idfs SET location_id=NULLIF(?, 0), rack_id=NULLIF(?, 0), name=?, idf_code=?, notes=?, active=? WHERE id=? AND company_id=? LIMIT 1"
    );
    if ($updateStmt) {
        mysqli_stmt_bind_param($updateStmt, 'iisssiii', $location_id, $rack_id, $name, $idf_code_val, $notes_val, $active, $idf_id, $company_id);
        if (!mysqli_stmt_execute($updateStmt)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_stmt_errno($updateStmt), mysqli_stmt_error($updateStmt));
            mysqli_stmt_close($updateStmt);
            header('Location: index.php?edit_idf=' . $idf_id);
            exit;
        }
        if (mysqli_stmt_affected_rows($updateStmt) < 0) {
            $_SESSION['crud_error'] = 'Unable to update the selected IDF.';
            mysqli_stmt_close($updateStmt);
            header('Location: index.php?edit_idf=' . $idf_id);
            exit;
        }
        mysqli_stmt_close($updateStmt);
    } else {
        $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
        header('Location: index.php?edit_idf=' . $idf_id);
        exit;
    }

    $_SESSION['crud_success'] = 'IDF updated successfully.';
    header('Location: index.php?edit_idf=' . $idf_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();

    if ($company_id <= 0) {
        $_SESSION['crud_error'] = 'No active company selected for sample data.';
        header('Location: index.php');
        exit;
    }

    $stmtCount = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM idfs WHERE company_id=?");
    $hasRows = 0;
    if ($stmtCount) {
        mysqli_stmt_bind_param($stmtCount, 'i', $company_id);
        mysqli_stmt_execute($stmtCount);
        $resCount = mysqli_stmt_get_result($stmtCount);
        $countRow = $resCount ? mysqli_fetch_assoc($resCount) : null;
        $hasRows = (int)($countRow['total'] ?? 0);
        mysqli_stmt_close($stmtCount);
    }

    if ($hasRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when IDFs are empty.';
        header('Location: index.php');
        exit;
    }

    $sampleName = 'Primary IDF';
    $sampleCode = 'IDF-01';
    $sampleNotes = 'Sample seeded IDF row for first-time setup.';
    $sampleActive = 1;
    $stmtSeed = mysqli_prepare($conn, "INSERT INTO idfs (company_id, location_id, rack_id, name, idf_code, notes, active) VALUES (?, NULL, NULL, ?, ?, ?, ?)");
    if ($stmtSeed) {
        mysqli_stmt_bind_param($stmtSeed, 'isssi', $company_id, $sampleName, $sampleCode, $sampleNotes, $sampleActive);
        if (mysqli_stmt_execute($stmtSeed)) {
            $_SESSION['crud_success'] = 'Sample IDF data added.';
        } else {
            $_SESSION['crud_error'] = 'Unable to add sample IDF data. ' . itm_format_db_constraint_error(mysqli_stmt_errno($stmtSeed), mysqli_stmt_error($stmtSeed));
        }
        mysqli_stmt_close($stmtSeed);
    } else {
        $_SESSION['crud_error'] = 'Unable to prepare sample IDF insert.';
    }

    header('Location: index.php');
    exit;
}

$edit_idf = null;
$edit_idf_id = (int)($_GET['edit_idf'] ?? 0);
if ($edit_idf_id > 0 && $company_id > 0) {
    $editStmt = mysqli_prepare($conn, "SELECT * FROM idfs WHERE id=? AND company_id=? LIMIT 1");
    if ($editStmt) {
        mysqli_stmt_bind_param($editStmt, 'ii', $edit_idf_id, $company_id);
        mysqli_stmt_execute($editStmt);
        $editResult = mysqli_stmt_get_result($editStmt);
        $edit_idf = $editResult ? mysqli_fetch_assoc($editResult) : null;
        mysqli_stmt_close($editStmt);
    }
}

$locations = [];
if ($company_id > 0) {
    $stmtLoc = mysqli_prepare($conn, "SELECT id, name FROM it_locations WHERE company_id=? ORDER BY name");
    if ($stmtLoc) {
        mysqli_stmt_bind_param($stmtLoc, 'i', $company_id);
        mysqli_stmt_execute($stmtLoc);
        $resLoc = mysqli_stmt_get_result($stmtLoc);
        while ($resLoc && ($row = mysqli_fetch_assoc($resLoc))) {
            $locations[] = $row;
        }
        mysqli_stmt_close($stmtLoc);
    }
}

$racks = [];
if ($company_id > 0) {
    $stmtRack = mysqli_prepare($conn, "SELECT id, name FROM racks WHERE company_id=? ORDER BY name");
    if ($stmtRack) {
        mysqli_stmt_bind_param($stmtRack, 'i', $company_id);
        mysqli_stmt_execute($stmtRack);
        $resRack = mysqli_stmt_get_result($stmtRack);
        while ($resRack && ($row = mysqli_fetch_assoc($resRack))) {
            $racks[] = $row;
        }
        mysqli_stmt_close($stmtRack);
    }
}

if ($edit_idf && $company_id > 0) {
    $editLocationId = (int)($edit_idf['location_id'] ?? 0);
    $hasEditLocationOption = false;
    foreach ($locations as $existingLocation) {
        if ((int)($existingLocation['id'] ?? 0) === $editLocationId) {
            $hasEditLocationOption = true;
            break;
        }
    }
    if (!$hasEditLocationOption && $editLocationId > 0) {
        $stmtEditLocation = mysqli_prepare($conn, "SELECT id, name FROM it_locations WHERE id=? AND company_id=? LIMIT 1");
        if ($stmtEditLocation) {
            mysqli_stmt_bind_param($stmtEditLocation, 'ii', $editLocationId, $company_id);
            mysqli_stmt_execute($stmtEditLocation);
            $editLocationResult = mysqli_stmt_get_result($stmtEditLocation);
            $editLocationRow = $editLocationResult ? mysqli_fetch_assoc($editLocationResult) : null;
            mysqli_stmt_close($stmtEditLocation);
            if ($editLocationRow) {
                $locations[] = $editLocationRow;
            }
        }
    }

    $editRackId = (int)($edit_idf['rack_id'] ?? 0);
    $hasEditRackOption = false;
    foreach ($racks as $existingRack) {
        if ((int)($existingRack['id'] ?? 0) === $editRackId) {
            $hasEditRackOption = true;
            break;
        }
    }
    if (!$hasEditRackOption && $editRackId > 0) {
        $stmtEditRack = mysqli_prepare($conn, "SELECT id, name FROM racks WHERE id=? AND company_id=? LIMIT 1");
        if ($stmtEditRack) {
            mysqli_stmt_bind_param($stmtEditRack, 'ii', $editRackId, $company_id);
            mysqli_stmt_execute($stmtEditRack);
            $editRackResult = mysqli_stmt_get_result($stmtEditRack);
            $editRackRow = $editRackResult ? mysqli_fetch_assoc($editRackResult) : null;
            mysqli_stmt_close($stmtEditRack);
            if ($editRackRow) {
                $racks[] = $editRackRow;
            }
        }
    }
}

$locationTypes = [];
if ($company_id > 0) {
    $stmtLocationTypes = mysqli_prepare($conn, "SELECT id, name FROM location_types WHERE company_id=? ORDER BY name");
    if ($stmtLocationTypes) {
        mysqli_stmt_bind_param($stmtLocationTypes, 'i', $company_id);
        mysqli_stmt_execute($stmtLocationTypes);
        $resLocationTypes = mysqli_stmt_get_result($stmtLocationTypes);
        while ($resLocationTypes && ($row = mysqli_fetch_assoc($resLocationTypes))) {
            $locationTypes[] = $row;
        }
        mysqli_stmt_close($stmtLocationTypes);
    }
}

$rackStatuses = [];
if ($company_id > 0) {
    $stmtRackStatuses = mysqli_prepare($conn, "SELECT id, name FROM rack_statuses WHERE company_id=? ORDER BY name");
    if ($stmtRackStatuses) {
        mysqli_stmt_bind_param($stmtRackStatuses, 'i', $company_id);
        mysqli_stmt_execute($stmtRackStatuses);
        $resRackStatuses = mysqli_stmt_get_result($stmtRackStatuses);
        while ($resRackStatuses && ($row = mysqli_fetch_assoc($resRackStatuses))) {
            $rackStatuses[] = $row;
        }
        mysqli_stmt_close($stmtRackStatuses);
    }
}

$ui_config = itm_get_ui_configuration($conn, $company_id);

$idfSortMap = [
    'id' => 'i.id',
    'name' => 'i.name',
    'idf_code' => 'i.idf_code',
    'code' => 'i.idf_code',
    'location' => 'l.name',
    'rack' => 'r.name',
    'active' => 'i.active',
];
$legacySortBy = (string)($_GET['sort_by'] ?? '');
$idf_sort = (string)($_GET['sort'] ?? '');
if ($idf_sort === '' && $legacySortBy !== '') {
    $idf_sort = $legacySortBy;
}
if (!isset($idfSortMap[$idf_sort])) {
    $idf_sort = 'id';
}
$idf_sort_dir = strtolower((string)($_GET['dir'] ?? ''));
if ($idf_sort_dir !== 'asc' && $idf_sort_dir !== 'desc') {
    $legacyDir = strtolower((string)($_GET['sort_dir'] ?? 'desc'));
    $idf_sort_dir = $legacyDir === 'asc' ? 'asc' : 'desc';
}
$idfOrderSql = $idfSortMap[$idf_sort] . ' ' . strtoupper($idf_sort_dir) . ', i.id DESC';
$idf_search = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));
$idf_search_like = '%' . $idf_search . '%';

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$totalRows = 0;
$idfWhereSearchSql = '';
if ($company_id > 0) {
    if ($idf_search !== '') {
        $idfWhereSearchSql = " AND (
            CAST(i.id AS CHAR) LIKE ?
            OR i.name LIKE ?
            OR i.idf_code LIKE ?
            OR l.name LIKE ?
            OR r.name LIKE ?
            OR CASE WHEN i.active=1 THEN 'active' ELSE 'inactive' END LIKE ?
        )";
    }

    $countSql = "SELECT COUNT(*) AS total_rows
         FROM idfs i
         LEFT JOIN it_locations l ON l.id=i.location_id
         LEFT JOIN racks r ON r.id=i.rack_id
         WHERE i.company_id=? {$idfWhereSearchSql}";
    $stmtCount = mysqli_prepare($conn, $countSql);
    if ($stmtCount) {
        if ($idf_search !== '') {
            mysqli_stmt_bind_param(
                $stmtCount,
                'issssss',
                $company_id,
                $idf_search_like,
                $idf_search_like,
                $idf_search_like,
                $idf_search_like,
                $idf_search_like,
                $idf_search_like
            );
        } else {
            mysqli_stmt_bind_param($stmtCount, 'i', $company_id);
        }
        mysqli_stmt_execute($stmtCount);
        $countRes = mysqli_stmt_get_result($stmtCount);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        $totalRows = (int)($countRow['total_rows'] ?? 0);
        mysqli_stmt_close($stmtCount);
    }
}

$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
$showBulkActions = ($totalRows >= $perPage);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$idfs = [];
if ($company_id > 0) {
    $stmtIdfs = mysqli_prepare(
        $conn,
        "SELECT i.*, l.name AS location_name, r.name AS rack_name
         FROM idfs i
         LEFT JOIN it_locations l ON l.id=i.location_id
         LEFT JOIN racks r ON r.id=i.rack_id
         WHERE i.company_id=? {$idfWhereSearchSql}
         ORDER BY {$idfOrderSql}
         LIMIT ?, ?"
    );
    if ($stmtIdfs) {
        if ($idf_search !== '') {
            mysqli_stmt_bind_param(
                $stmtIdfs,
                'issssssii',
                $company_id,
                $idf_search_like,
                $idf_search_like,
                $idf_search_like,
                $idf_search_like,
                $idf_search_like,
                $idf_search_like,
                $offset,
                $perPage
            );
        } else {
            mysqli_stmt_bind_param($stmtIdfs, 'iii', $company_id, $offset, $perPage);
        }
        mysqli_stmt_execute($stmtIdfs);
        $res = mysqli_stmt_get_result($stmtIdfs);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $idfs[] = $row;
        }
        mysqli_stmt_close($stmtIdfs);
    }
}
$locationTypeOptions = array_map(static function ($type) {
    return [
        'value' => (string)((int)($type['id'] ?? 0)),
        'label' => (string)($type['name'] ?? ''),
    ];
}, $locationTypes);

$locationFieldConfig = [
    [
        'name' => 'type_id',
        'label' => 'Location Type',
        'type' => 'select',
        'options' => $locationTypeOptions,
        'addable' => [
            'table' => 'location_types',
            'id_col' => 'id',
            'label_col' => 'name',
            'company_scoped' => '1',
        ],
    ],
];

$locationExtraFieldsJson = htmlspecialchars(
    json_encode($locationFieldConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);

$rackExtraFieldsJson = htmlspecialchars(
    json_encode([
        [
            'name' => 'location_id',
            'label' => 'Location',
            'type' => 'select',
            'options' => array_map(static function ($location) {
                return [
                    'value' => (string)((int)($location['id'] ?? 0)),
                    'label' => (string)($location['name'] ?? ''),
                ];
            }, $locations),
            'addable' => [
                'table' => 'it_locations',
                'id_col' => 'id',
                'label_col' => 'name',
                'company_scoped' => '1',
                'extra_fields' => $locationFieldConfig,
            ],
        ],
        [
            'name' => 'status_id',
            'label' => 'Rack Status',
            'type' => 'select',
            'options' => array_map(static function ($status) {
                return [
                    'value' => (string)((int)($status['id'] ?? 0)),
                    'label' => (string)($status['name'] ?? ''),
                ];
            }, $rackStatuses),
            'addable' => [
                'table' => 'rack_statuses',
                'id_col' => 'id',
                'label_col' => 'name',
                'company_scoped' => '1',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);

function itm_idf_list_url(array $overrides = [])
{
    global $idf_search, $idf_sort, $idf_sort_dir, $page;
    $query = [
        'search' => $idf_search,
        'sort' => $idf_sort,
        'dir' => $idf_sort_dir,
        'page' => $page,
    ];
    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }
    return 'index.php?' . http_build_query($query);
}

function itm_idf_sort_url($column, $currentSort, $currentSortDir, $searchTerm = '')
{
    $nextDir = ($currentSort === $column && $currentSortDir === 'asc') ? 'desc' : 'asc';
    return itm_idf_list_url([
        'sort' => $column,
        'dir' => $nextDir,
        'search' => $searchTerm,
        'page' => 1,
    ]);
}

function itm_idf_sort_indicator($column, $currentSort, $currentSortDir)
{
    if ($currentSort !== $column && !($column === 'code' && $currentSort === 'idf_code')) {
        return '';
    }
    return $currentSortDir === 'asc' ? ' ▲' : ' ▼';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'IDFs';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
    <style>
        .idf-page-shell { display:grid; gap:16px; }
        .idf-hero {
            background: linear-gradient(135deg, rgba(9,105,218,.18), rgba(88,166,255,.08));
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: var(--shadow);
        }
        .idf-hero h2 { margin: 0; font-size: 26px; }
        .idf-hero p { margin: 6px 0 0; color: var(--text-secondary); }
        .idf-stat-grid { display:grid; grid-template-columns: repeat(2,minmax(140px,1fr)); gap:10px; min-width:300px; }
        .idf-stat {
            background: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 10px 12px;
        }
        .idf-stat strong { font-size: 20px; display:block; }
        .idf-layout-grid { display:grid; grid-template-columns: 1.1fr .9fr; gap:16px; }
        .idf-panel {
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 16px;
            background: var(--bg-primary);
            box-shadow: var(--shadow);
        }
        .idf-panel h3 { margin: 0 0 12px; display:flex; justify-content:space-between; align-items:center; }
        .idf-list-table tbody tr:hover { background: var(--bg-secondary); }
        .idf-list-table tbody tr[data-open-url] { cursor: pointer; }
        @media (max-width: 1080px) {
            .idf-layout-grid { grid-template-columns: 1fr; }
            .idf-stat-grid { width: 100%; min-width: unset; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="content">
            <div class="idf-page-shell">
                <section class="idf-hero">
                    <div>
                        <h2>🗄️ IDF Dashboard Studio</h2>
                        <p>Design and manage your telecom closets with faster provisioning and clean rack visibility.</p>
                    </div>
                    <div class="idf-stat-grid">
                        <div class="idf-stat">
                            <small>Total IDFs</small>
                            <strong><?php echo (int)$totalRows; ?></strong>
                        </div>
                        <div class="idf-stat">
                            <small>Known Locations</small>
                            <strong><?php echo count($locations); ?></strong>
                        </div>
                    </div>
                </section>

                <section class="idf-panel">
                    <h3>🔎 Search IDFs <span class="idf-badge">Filter current company records</span></h3>
                    <form method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                        <div style="min-width:280px; flex:1;">
                            <label class="label" for="moduleSearch">Search</label>
                            <input class="input" type="text" id="moduleSearch" name="search" value="<?php echo sanitize($idf_search); ?>" placeholder="Search ID, name, code, location, rack, active...">
                        </div>
                        <input type="hidden" name="sort" value="<?php echo sanitize($idf_sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($idf_sort_dir); ?>">
                        <input type="hidden" name="page" value="1">
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-primary" type="submit">Search</button>
                            <a class="btn" href="index.php">Clear</a>
                        </div>
                    </form>
                </section>

                <div class="idf-layout-grid">
                    <section class="idf-panel">
                        <h3 title="Create closet profile">➕ <span class="idf-badge">New closet profile</span></h3>
                        <form method="post" class="idf-grid-2">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                    <input type="hidden" name="create_idf" value="1">
                    <div>
                        <label class="label">Name</label>
                        <input class="input" name="name" placeholder="e.g. IDF-01 Main Closet" required>
                    </div>
                    <div>
                        <label class="label">IDF Code (optional)</label>
                        <input class="input" name="idf_code" placeholder="e.g. IDF-01">
                    </div>
                    <div>
                        <label class="label">Rack</label>
                        <select class="input" id="idf-rack-select" name="rack_id"
                                data-addable-select="1"
                                data-add-table="racks"
                                data-add-id-col="id"
                                data-add-label-col="name"
                                data-add-company-scoped="1"
                                data-add-friendly="rack"
                                data-add-extra-fields="<?php echo $rackExtraFieldsJson; ?>">
                            <option value="">-- Select rack --</option>
                            <?php foreach ($racks as $rack): ?>
                                <option value="<?php echo (int)$rack['id']; ?>"><?php echo sanitize($rack['name']); ?></option>
                            <?php endforeach; ?>
                            <option value="__add_new__">➕</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Location</label>
                        <select class="input" id="idf-location-select" name="location_id"
                                data-addable-select="1"
                                data-add-table="it_locations"
                                data-add-id-col="id"
                                data-add-label-col="name"
                                data-add-company-scoped="1"
                                data-add-friendly="location"
                                data-add-extra-fields="<?php echo $locationExtraFieldsJson; ?>">
                            <option value="">-- Select location --</option>
                            <?php foreach ($locations as $l): ?>
                                <option value="<?php echo (int)$l['id']; ?>"><?php echo sanitize($l['name']); ?></option>
                            <?php endforeach; ?>
                            <option value="__add_new__">➕</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Notes</label>
                        <input class="input" name="notes" placeholder="Optional notes">
                    </div>
                    <div>
                        <label class="label">Active</label>
                        <div class="role-flags-grid">
                            <label class="role-flag-option">
                                <input type="checkbox" name="active" value="1" checked>
                            </label>
                        </div>
                    </div>
                    <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                        <button class="btn btn-primary" type="submit" title="Create closet">➕</button>
                    </div>
                </form>
                        <?php if ($edit_idf): ?>
                            <hr style="margin:16px 0; border:0; border-top:1px solid var(--border);">
                            <h3 title="Update closet profile">✏️ <span class="idf-badge">Update closet profile</span></h3>
                            <form method="post" class="idf-grid-2">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                                <input type="hidden" name="update_idf" value="1">
                                <input type="hidden" name="idf_id" value="<?php echo (int)$edit_idf['id']; ?>">
                                <div>
                                    <label class="label">Name</label>
                                    <input class="input" name="name" placeholder="e.g. IDF-01 Main Closet" value="<?php echo sanitize((string)($edit_idf['name'] ?? '')); ?>" required>
                                </div>
                                <div>
                                    <label class="label">IDF Code (optional)</label>
                                    <input class="input" name="idf_code" placeholder="e.g. IDF-01" value="<?php echo sanitize((string)($edit_idf['idf_code'] ?? '')); ?>">
                                </div>
                                <div>
                                    <label class="label">Rack</label>
                                    <select class="input" id="edit-idf-rack-select" name="rack_id"
                                            data-addable-select="1"
                                            data-add-table="racks"
                                            data-add-id-col="id"
                                            data-add-label-col="name"
                                            data-add-company-scoped="1"
                                            data-add-friendly="rack"
                                            data-add-extra-fields="<?php echo $rackExtraFieldsJson; ?>">
                                        <option value="">-- Select rack --</option>
                                        <?php foreach ($racks as $rack): ?>
                                            <option value="<?php echo (int)$rack['id']; ?>" <?php echo ((int)$rack['id'] === (int)($edit_idf['rack_id'] ?? 0)) ? 'selected' : ''; ?>><?php echo sanitize($rack['name']); ?></option>
                                        <?php endforeach; ?>
                                        <option value="__add_new__">➕</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Location</label>
                                    <select class="input" id="edit-idf-location-select" name="location_id"
                                            data-addable-select="1"
                                            data-add-table="it_locations"
                                            data-add-id-col="id"
                                            data-add-label-col="name"
                                            data-add-company-scoped="1"
                                            data-add-friendly="location"
                                            data-add-extra-fields="<?php echo $locationExtraFieldsJson; ?>">
                                        <option value="">-- Select location --</option>
                                        <?php foreach ($locations as $l): ?>
                                            <option value="<?php echo (int)$l['id']; ?>" <?php echo ((int)$l['id'] === (int)($edit_idf['location_id'] ?? 0)) ? 'selected' : ''; ?>><?php echo sanitize($l['name']); ?></option>
                                        <?php endforeach; ?>
                                        <option value="__add_new__">➕</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="label">Notes</label>
                                    <input class="input" name="notes" placeholder="Optional notes" value="<?php echo sanitize((string)($edit_idf['notes'] ?? '')); ?>">
                                </div>
                                <div>
                                    <label class="label">Active</label>
                                    <div class="role-flags-grid">
                                        <label class="role-flag-option">
                                            <input type="checkbox" name="active" value="1" <?php echo ((int)($edit_idf['active'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                        </label>
                                    </div>
                                </div>
                                <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                                    <a class="btn" href="index.php">Cancel Edit</a>
                                    <button class="btn btn-primary" type="submit">Update IDF</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </section>

                    <section class="idf-panel">
                        <h3>📋 Existing IDFs <span class="idf-badge">Tap an IDF to open</span></h3>
                        <?php if ($showBulkActions): ?>
                        <div class="card" style="margin-bottom:12px;">
                            <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;flex-wrap:wrap;">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                                <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                                <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                            </form>
                        </div>
                        <?php endif; ?>
                        <div class="card" style="overflow:auto;">
                        <table class="table idf-list-table" data-itm-db-import-endpoint="index.php">
                    <thead>
                        <tr>
                            <?php if ($showBulkActions): ?><th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th><?php endif; ?>
                            <th data-itm-actions-origin="1" class="itm-actions-cell itm-actions-left">Actions</th>
                            <th><a href="<?php echo sanitize(itm_idf_sort_url('id', $idf_sort, $idf_sort_dir, $idf_search)); ?>" style="text-decoration:none;color:inherit;">ID<?php echo sanitize(itm_idf_sort_indicator('id', $idf_sort, $idf_sort_dir)); ?></a></th>
                            <th><a href="<?php echo sanitize(itm_idf_sort_url('name', $idf_sort, $idf_sort_dir, $idf_search)); ?>" style="text-decoration:none;color:inherit;">Name<?php echo sanitize(itm_idf_sort_indicator('name', $idf_sort, $idf_sort_dir)); ?></a></th>
                            <th><a href="<?php echo sanitize(itm_idf_sort_url('code', $idf_sort, $idf_sort_dir, $idf_search)); ?>" style="text-decoration:none;color:inherit;">Code<?php echo sanitize(itm_idf_sort_indicator('code', $idf_sort, $idf_sort_dir)); ?></a></th>
                            <th><a href="<?php echo sanitize(itm_idf_sort_url('location', $idf_sort, $idf_sort_dir, $idf_search)); ?>" style="text-decoration:none;color:inherit;">Location<?php echo sanitize(itm_idf_sort_indicator('location', $idf_sort, $idf_sort_dir)); ?></a></th>
                            <th><a href="<?php echo sanitize(itm_idf_sort_url('rack', $idf_sort, $idf_sort_dir, $idf_search)); ?>" style="text-decoration:none;color:inherit;">Rack<?php echo sanitize(itm_idf_sort_indicator('rack', $idf_sort, $idf_sort_dir)); ?></a></th>
                            <th><a href="<?php echo sanitize(itm_idf_sort_url('active', $idf_sort, $idf_sort_dir, $idf_search)); ?>" style="text-decoration:none;color:inherit;">Active<?php echo sanitize(itm_idf_sort_indicator('active', $idf_sort, $idf_sort_dir)); ?></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$idfs): ?>
                            <tr><td colspan="<?php echo $showBulkActions ? 8 : 7; ?>" style="opacity:.8;">No records found.</td></tr>
                            <?php if ($totalRows === 0): ?>
                            <tr>
                                <td colspan="<?php echo $showBulkActions ? 8 : 7; ?>" style="text-align:center; padding:12px;">
                                    <form method="post" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                                        <input type="hidden" name="add_sample_data" value="1">
                                        <button class="btn btn-primary" type="submit">Add sample data</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php foreach ($idfs as $idf): ?>
                            <tr data-open-url="view.php?id=<?php echo (int)$idf['id']; ?>">
                                <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$idf['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                                <td class="itm-actions-cell itm-actions-left" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$idf['id']; ?>" title="View IDF">🔎</a>
                                        <a class="btn btn-sm" href="index.php?edit_idf=<?php echo (int)$idf['id']; ?>" title="Edit closet">✏️</a>
                                        <form method="post" action="delete.php" onsubmit="return confirm('Delete this IDF? This action cannot be undone.');" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                                            <input type="hidden" name="bulk_action" value="single_delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$idf['id']; ?>">
                                            <button class="btn btn-sm btn-danger" type="submit" title="Delete IDF">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                                <td><?php echo (int)$idf['id']; ?></td>
                                <td><?php echo sanitize($idf['name']); ?></td>
                                <td><?php echo sanitize((string)($idf['idf_code'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($idf['location_name'] ?? '')); ?></td>
                                <td><?php echo sanitize((string)($idf['rack_name'] ?? '')); ?></td>
                                <td>
                                    <input type="checkbox" <?php echo ((int)($idf['active'] ?? 1) === 1) ? 'checked' : ''; ?> disabled>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                        <?php if ($totalRows > $perPage): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                            <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo (int)$totalRows; ?></div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                <?php if ($page > 1): ?>
                                    <a class="btn btn-sm" href="<?php echo sanitize(itm_idf_list_url(['page' => $page - 1])); ?>" title="◀️ Previous">Previous</a>
                                <?php endif; ?>
                                <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                                <?php if ($page < $totalPages): ?>
                                    <a class="btn btn-sm" href="<?php echo sanitize(itm_idf_list_url(['page' => $page + 1])); ?>" title="▶️ Next">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/select-add-option.js"></script>
<script>
function refreshIdfSelectOptions(selectElement, refreshTarget) {
    if (!selectElement || !refreshTarget) {
        return;
    }

    var currentValue = selectElement.value;
    var endpoint = 'index.php?refresh_select_options=' + encodeURIComponent(refreshTarget) + '&_=' + Date.now();

    fetch(endpoint, { credentials: 'same-origin' })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Failed to load latest options.');
            }
            return response.json();
        })
        .then(function (payload) {
            if (!payload || payload.ok !== true || !Array.isArray(payload.options)) {
                throw new Error('Invalid options response.');
            }

            var placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = payload.placeholder || '-- Select --';

            selectElement.innerHTML = '';
            selectElement.appendChild(placeholderOption);

            payload.options.forEach(function (optionItem) {
                var optionValue = String(optionItem.value || '');
                var optionNode = document.createElement('option');
                optionNode.value = optionValue;
                optionNode.textContent = String(optionItem.label || '');
                if (optionValue !== '' && optionValue === currentValue) {
                    optionNode.selected = true;
                }
                selectElement.appendChild(optionNode);
            });

            var addNewOption = document.createElement('option');
            addNewOption.value = '__add_new__';
            addNewOption.textContent = '➕';
            selectElement.appendChild(addNewOption);
        })
        .catch(function () {
            // Why: keep user flow uninterrupted when a transient refresh call fails.
        });
}

var rackSelect = document.getElementById('idf-rack-select');
if (rackSelect) {
    rackSelect.addEventListener('mousedown', function () {
        refreshIdfSelectOptions(rackSelect, 'rack');
    });
}

var editRackSelect = document.getElementById('edit-idf-rack-select');
if (editRackSelect) {
    editRackSelect.addEventListener('mousedown', function () {
        refreshIdfSelectOptions(editRackSelect, 'rack');
    });
}

var locationSelect = document.getElementById('idf-location-select');
if (locationSelect) {
    locationSelect.addEventListener('mousedown', function () {
        refreshIdfSelectOptions(locationSelect, 'location');
    });
}

var editLocationSelect = document.getElementById('edit-idf-location-select');
if (editLocationSelect) {
    editLocationSelect.addEventListener('mousedown', function () {
        refreshIdfSelectOptions(editLocationSelect, 'location');
    });
}

document.addEventListener('click', function (event) {
    var openControl = event.target.closest('a, button, input, select, textarea, form');
    if (openControl) {
        return;
    }

    var row = event.target.closest('tr[data-open-url]');
    if (!row) {
        return;
    }

    window.location.href = row.getAttribute('data-open-url');
});
</script>
</body>
</html>
