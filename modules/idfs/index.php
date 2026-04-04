<?php
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrf = itm_get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_idf'])) {
    itm_require_post_csrf();

    $name = trim((string)($_POST['name'] ?? ''));
    $idf_code = trim((string)($_POST['idf_code'] ?? ''));
    $location_id = (int)($_POST['location_id'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));

    if ($name === '' || $location_id <= 0 || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Please provide IDF name and location.';
        header('Location: index.php');
        exit;
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $codeEsc = $idf_code !== '' ? ("'" . mysqli_real_escape_string($conn, $idf_code) . "'") : 'NULL';
    $notesEsc = $notes !== '' ? ("'" . mysqli_real_escape_string($conn, $notes) . "'") : 'NULL';

    $sql = "INSERT INTO idfs (company_id, location_id, name, idf_code, notes)
            VALUES ($company_id, $location_id, '$nameEsc', $codeEsc, $notesEsc)";

    if (!mysqli_query($conn, $sql)) {
        $_SESSION['crud_error'] = 'DB error creating IDF: ' . mysqli_error($conn);
        header('Location: index.php');
        exit;
    }

    $newId = (int)mysqli_insert_id($conn);
    header('Location: view.php?id=' . $newId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_idf'])) {
    itm_require_post_csrf();

    $idf_id = (int)($_POST['idf_id'] ?? 0);
    if ($idf_id <= 0 || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Invalid IDF selected for deletion.';
        header('Location: index.php');
        exit;
    }

    $checkSql = "SELECT id FROM idfs WHERE id=$idf_id AND company_id=$company_id LIMIT 1";
    $checkRes = mysqli_query($conn, $checkSql);
    if (!$checkRes || !mysqli_fetch_assoc($checkRes)) {
        $_SESSION['crud_error'] = 'IDF not found.';
        header('Location: index.php');
        exit;
    }

    $deleteSql = "DELETE FROM idfs WHERE id=$idf_id AND company_id=$company_id LIMIT 1";
    if (!mysqli_query($conn, $deleteSql)) {
        $_SESSION['crud_error'] = 'DB error deleting IDF: ' . mysqli_error($conn);
        header('Location: index.php');
        exit;
    }

    $_SESSION['crud_success'] = 'IDF deleted successfully.';
    header('Location: index.php');
    exit;
}

$locations = [];
if ($company_id > 0) {
    $resLoc = mysqli_query($conn, "SELECT id, name FROM it_locations WHERE company_id=$company_id ORDER BY name");
    while ($resLoc && ($row = mysqli_fetch_assoc($resLoc))) {
        $locations[] = $row;
    }
}

$idfs = [];
if ($company_id > 0) {
    $res = mysqli_query(
        $conn,
        "SELECT i.*, l.name AS location_name
         FROM idfs i
         JOIN it_locations l ON l.id=i.location_id
         WHERE i.company_id=$company_id
         ORDER BY i.created_at DESC, i.id DESC"
    );
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $idfs[] = $row;
    }
}

$ui_config = itm_get_ui_configuration($conn, $company_id);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>IDFs</title>
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
                            <strong><?php echo count($idfs); ?></strong>
                        </div>
                        <div class="idf-stat">
                            <small>Known Locations</small>
                            <strong><?php echo count($locations); ?></strong>
                        </div>
                    </div>
                </section>

                <div class="idf-layout-grid">
                    <section class="idf-panel">
                        <h3>➕ Create IDF <span class="idf-badge">New closet profile</span></h3>
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
                        <label class="label">Location</label>
                        <select class="input" name="location_id" required
                                data-addable-select="1"
                                data-add-table="it_locations"
                                data-add-id-col="id"
                                data-add-label-col="name"
                                data-add-company-scoped="1"
                                data-add-friendly="location">
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
                    <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
                        <button class="btn btn-primary" type="submit">Create IDF</button>
                    </div>
                </form>
                    </section>

                    <section class="idf-panel">
                        <h3>📋 Existing IDFs <span class="idf-badge">Tap an IDF to open</span></h3>
                        <table class="table idf-list-table">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Code</th><th>Location</th><th>Created</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$idfs): ?>
                            <tr><td colspan="6" style="opacity:.8;">No IDFs yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($idfs as $idf): ?>
                            <tr data-open-url="view.php?id=<?php echo (int)$idf['id']; ?>">
                                <td><?php echo (int)$idf['id']; ?></td>
                                <td><?php echo sanitize($idf['name']); ?></td>
                                <td><?php echo sanitize((string)($idf['idf_code'] ?? '')); ?></td>
                                <td><?php echo sanitize($idf['location_name']); ?></td>
                                <td><?php echo sanitize((string)$idf['created_at']); ?></td>
                                <td style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$idf['id']; ?>">Open</a>
                                    <form method="post" onsubmit="return confirm('Delete this IDF? This action cannot be undone.');" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                                        <input type="hidden" name="delete_idf" value="1">
                                        <input type="hidden" name="idf_id" value="<?php echo (int)$idf['id']; ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/select-add-option.js"></script>
<script>
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
