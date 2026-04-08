<?php
/**
 * IDFs Module - Index
 * 
 * Provides a management dashboard for telecom closets (IDFs).
 * Allows users to:
 * - Create new IDF profiles
 * - View a list of existing IDFs with their locations
 * - Quick-access individual IDF layouts
 * - Securely delete IDF records
 */

require_once __DIR__ . '/../../config/config.php';

// Ensure company context is set
if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrf = itm_get_csrf_token();

// --- ACTION: CREATE IDF ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_idf'])) {
    itm_require_post_csrf();

    $name = trim((string)($_POST['name'] ?? ''));
    $idf_code = trim((string)($_POST['idf_code'] ?? ''));
    $location_id = (int)($_POST['location_id'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));

    // Validation
    if ($name === '' || $location_id <= 0 || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Please provide IDF name and location.';
        header('Location: index.php'); exit;
    }

    $idf_code_val = $idf_code !== '' ? $idf_code : null;
    $notes_val = $notes !== '' ? $notes : null;

    // Secure insertion
    $stmt = mysqli_prepare($conn, "INSERT INTO idfs (company_id, location_id, name, idf_code, notes) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iisss', $company_id, $location_id, $name, $idf_code_val, $notes_val);
        if (!mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_error'] = 'DB error creating IDF: ' . mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            header('Location: index.php'); exit;
        }
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        header('Location: view.php?id=' . $newId); exit;
    } else {
        $_SESSION['crud_error'] = 'DB error preparing statement: ' . mysqli_error($conn);
        header('Location: index.php'); exit;
    }
}

// --- ACTION: DELETE IDF ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_idf'])) {
    itm_require_post_csrf();

    $idf_id = (int)($_POST['idf_id'] ?? 0);
    if ($idf_id <= 0 || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Invalid IDF selected for deletion.';
        header('Location: index.php'); exit;
    }

    // Security check: verify ownership before deletion
    $checkStmt = mysqli_prepare($conn, "SELECT id FROM idfs WHERE id=? AND company_id=? LIMIT 1");
    if ($checkStmt) {
        mysqli_stmt_bind_param($checkStmt, 'ii', $idf_id, $company_id);
        mysqli_stmt_execute($checkStmt);
        $checkRes = mysqli_stmt_get_result($checkStmt);
        $found = $checkRes && mysqli_fetch_assoc($checkRes);
        mysqli_stmt_close($checkStmt);

        if (!$found) {
            $_SESSION['crud_error'] = 'IDF not found.';
            header('Location: index.php'); exit;
        }
    }

    // Perform deletion
    $deleteStmt = mysqli_prepare($conn, "DELETE FROM idfs WHERE id=? AND company_id=? LIMIT 1");
    if ($deleteStmt) {
        mysqli_stmt_bind_param($deleteStmt, 'ii', $idf_id, $company_id);
        if (!mysqli_stmt_execute($deleteStmt)) {
            $_SESSION['crud_error'] = 'DB error deleting IDF: ' . mysqli_stmt_error($deleteStmt);
        }
        mysqli_stmt_close($deleteStmt);
    }

    $_SESSION['crud_success'] = 'IDF deleted successfully.';
    header('Location: index.php');
    exit;
}


// --- ACTION: BULK DELETE / CLEAR TABLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    itm_require_post_csrf();

    $bulkAction = (string)($_POST['bulk_action'] ?? '');
    if ($company_id <= 0) {
        $_SESSION['crud_error'] = 'Invalid company context.';
        header('Location: index.php');
        exit;
    }

    if ($bulkAction === 'clear_table') {
        $stmtClear = mysqli_prepare($conn, 'DELETE FROM idfs WHERE company_id = ?');
        if ($stmtClear) {
            mysqli_stmt_bind_param($stmtClear, 'i', $company_id);
            if (!mysqli_stmt_execute($stmtClear)) {
                $_SESSION['crud_error'] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
            }
            mysqli_stmt_close($stmtClear);
        }
        header('Location: index.php');
        exit;
    }

    if ($bulkAction === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || !$ids) {
            $_SESSION['crud_error'] = 'Please select at least one IDF.';
            header('Location: index.php');
            exit;
        }

        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM idfs WHERE id = ? AND company_id = ? LIMIT 1');
        if ($deleteStmt) {
            foreach ($ids as $rawId) {
                $idfId = (int)$rawId;
                if ($idfId <= 0) {
                    continue;
                }
                mysqli_stmt_bind_param($deleteStmt, 'ii', $idfId, $company_id);
                mysqli_stmt_execute($deleteStmt);
            }
            mysqli_stmt_close($deleteStmt);
        }
        header('Location: index.php');
        exit;
    }
}

// FETCH DATA FOR DISPLAY
$locations = [];
if ($company_id > 0) {
    $stmtLoc = mysqli_prepare($conn, "SELECT id, name FROM it_locations WHERE company_id=? ORDER BY name");
    if ($stmtLoc) {
        mysqli_stmt_bind_param($stmtLoc, 'i', $company_id);
        mysqli_stmt_execute($stmtLoc);
        $resLoc = mysqli_stmt_get_result($stmtLoc);
        while ($resLoc && ($row = mysqli_fetch_assoc($resLoc))) { $locations[] = $row; }
        mysqli_stmt_close($stmtLoc);
    }
}

$idfs = [];
if ($company_id > 0) {
    $stmtIdfs = mysqli_prepare(
        $conn,
        "SELECT i.*, l.name AS location_name
         FROM idfs i
         JOIN it_locations l ON l.id=i.location_id
         WHERE i.company_id=?
         ORDER BY i.created_at DESC, i.id DESC"
    );
    if ($stmtIdfs) {
        mysqli_stmt_bind_param($stmtIdfs, 'i', $company_id);
        mysqli_stmt_execute($stmtIdfs);
        $res = mysqli_stmt_get_result($stmtIdfs);
        while ($res && ($row = mysqli_fetch_assoc($res))) { $idfs[] = $row; }
        mysqli_stmt_close($stmtIdfs);
    }
}

$ui_config = itm_get_ui_configuration($conn, $company_id);
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$totalRows = count($idfs);
$showBulkActions = $totalRows >= $perPage;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>IDFs</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="content">
            <div class="idf-page-shell">
                <!-- HERO SECTION -->
                <section class="idf-hero">
                    <div>
                        <h2>🗄️ IDF Dashboard Studio</h2>
                        <p>Design and manage your telecom closets with faster provisioning and clean rack visibility.</p>
                    </div>
                    <div class="idf-stat-grid">
                        <div class="idf-stat"><small>Total IDFs</small><strong><?php echo count($idfs); ?></strong></div>
                        <div class="idf-stat"><small>Locations</small><strong><?php echo count($locations); ?></strong></div>
                    </div>
                </section>

                <div class="idf-layout-grid">
                    <!-- CREATION PANEL -->
                    <section class="idf-panel">
                        <h3>➕ Create IDF</h3>
                        <form method="post" class="idf-grid-2">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                            <input type="hidden" name="create_idf" value="1">
                            <div><label class="label">Name</label><input class="input" name="name" required></div>
                            <div><label class="label">IDF Code</label><input class="input" name="idf_code"></div>
                            <div><label class="label">Location</label>
                                <select class="input" name="location_id" required data-addable-select="1" data-add-table="it_locations" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1">
                                    <option value="">-- Select location --</option>
                                    <?php foreach ($locations as $l): ?>
                                        <option value="<?php echo (int)$l['id']; ?>"><?php echo sanitize($l['name']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">➕</option>
                                </select>
                            </div>
                            <div><label class="label">Notes</label><input class="input" name="notes"></div>
                            <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end;"><button class="btn btn-primary" type="submit">Create IDF</button></div>
                        </form>
                    </section>

                    <!-- LISTING PANEL -->
                    <section class="idf-panel">
                        <h3>📋 Existing IDFs</h3>
                        <?php if ($showBulkActions): ?>
                            <!-- Batch controls are intentionally hidden on short lists to reduce accidental destructive use. -->
                            <form id="bulk-delete-form" method="post" style="display:flex;gap:8px;margin-bottom:10px;">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                                <button type="submit" class="btn btn-sm btn-danger" name="bulk_action" value="bulk_delete">Select to Delete</button>
                                <button type="submit" class="btn btn-sm btn-danger" name="bulk_action" value="clear_table" onclick="return confirm('Clear all IDFs? This cannot be undone.');">Clear Table</button>
                            </form>
                        <?php endif; ?>
                        <table class="table idf-list-table">
                            <thead><tr><?php if ($showBulkActions): ?><th>Select</th><?php endif; ?><th>ID</th><th>Name</th><th>Code</th><th>Location</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if (!$idfs): ?><tr><td colspan="6">No IDFs yet.</td></tr><?php endif; ?>
                                <?php foreach ($idfs as $idf): ?>
                                    <tr data-open-url="view.php?id=<?php echo (int)$idf['id']; ?>">
                                        <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$idf['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                                        <td><?php echo (int)$idf['id']; ?></td>
                                        <td><?php echo sanitize($idf['name']); ?></td>
                                        <td><?php echo sanitize((string)($idf['idf_code'] ?? '')); ?></td>
                                        <td><?php echo sanitize($idf['location_name']); ?></td>
                                        <td>
                                            <div class="itm-actions-wrap">
                                                <a class="btn btn-sm" href="view.php?id=<?php echo (int)$idf['id']; ?>">Open</a>
                                                <form method="post" onsubmit="return confirm('Delete this IDF?');" style="margin:0;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
                                                    <input type="hidden" name="delete_idf" value="1">
                                                    <input type="hidden" name="idf_id" value="<?php echo (int)$idf['id']; ?>">
                                                    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                                </form>
                                            </div>
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
/**
 * Row click navigation
 */
document.addEventListener('click', function (event) {
    var openControl = event.target.closest('a, button, input, select, textarea, form');
    if (openControl) return;
    var row = event.target.closest('tr[data-open-url]');
    if (row) window.location.href = row.getAttribute('data-open-url');
});
</script>
</body>
</html>
