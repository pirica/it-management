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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="container">
            <div class="idf-toolbar">
                <div class="left">
                    <h2 style="margin:0;">🗄️ IDFs</h2>
                    <span class="idf-badge">Modern rack-face view</span>
                </div>
            </div>

            <div class="card" style="padding:14px; border-radius:18px;">
                <h3 style="margin-top:0;">➕ Create IDF</h3>
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
                        <button class="btn" type="submit">Create</button>
                    </div>
                </form>
            </div>

            <div style="height:14px;"></div>

            <div class="card" style="padding:14px; border-radius:18px;">
                <h3 style="margin-top:0;">📋 Existing IDFs</h3>
                <table class="table">
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
                            <tr>
                                <td><?php echo (int)$idf['id']; ?></td>
                                <td><?php echo sanitize($idf['name']); ?></td>
                                <td><?php echo sanitize((string)($idf['idf_code'] ?? '')); ?></td>
                                <td><?php echo sanitize($idf['location_name']); ?></td>
                                <td><?php echo sanitize((string)$idf['created_at']); ?></td>
                                <td>
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$idf['id']; ?>">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="../../js/select-add-option.js"></script>
</body>
</html>
