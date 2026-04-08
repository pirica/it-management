<?php
/**
 * IDFs Module - View
 * 
 * Provides a highly interactive visual management interface for a single IDF rack.
 * Features:
 * - Visual Rack Face with 10 slots
 * - Drag-and-drop reordering of devices via SortableJS
 * - Modals for adding, editing, and copying devices between slots
 * - Dynamic field synchronization when linking rack positions to Asset Equipment
 * - Multi-format export (Excel, high-res Image, PDF)
 */

require_once __DIR__ . '/../../config/config.php';

// Authentication and session check
if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$idf_id = (int)($_GET['id'] ?? 0);

/**
 * CSRF helper for AJAX and form actions
 */
function idf_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

/**
 * Maps device types to emoji-enabled badges
 */
function idf_type_badge(string $t): string {
    return match ($t) {
        'switch' => '🔀 Switch',
        'patch_panel' => '➰ Patch Panel',
        'ups' => '🔋 UPS',
        'server' => '🖥️ Server',
        default => '📦 Other',
    };
}

$csrf = idf_csrf_token();

// FETCH CORE IDF DATA
$idf = null;
if ($idf_id > 0 && $company_id > 0) {
    $stmtIdf = mysqli_prepare(
        $conn,
        "SELECT i.*, l.name AS location_name, c.company AS company_name
         FROM idfs i
         JOIN it_locations l ON l.id=i.location_id
         LEFT JOIN companies c ON c.id=i.company_id
         WHERE i.id=? AND i.company_id=?
         LIMIT 1"
    );
    if ($stmtIdf) {
        mysqli_stmt_bind_param($stmtIdf, 'ii', $idf_id, $company_id);
        mysqli_stmt_execute($stmtIdf);
        $res = mysqli_stmt_get_result($stmtIdf);
        $idf = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmtIdf);
    }
}

// Redirect if IDF not found or unauthorized
if (!$idf) {
    $_SESSION['crud_error'] = 'IDF not found.';
    header('Location: index.php'); exit;
}

// FETCH RACK POSITIONS (Fixed 10 slots)
$positions = array_fill(1, 10, null);
$stmtPos = mysqli_prepare($conn, "SELECT * FROM idf_positions WHERE idf_id=? ORDER BY position_no ASC");
if ($stmtPos) {
    mysqli_stmt_bind_param($stmtPos, 'i', $idf_id);
    mysqli_stmt_execute($stmtPos);
    $resPos = mysqli_stmt_get_result($stmtPos);
    while ($resPos && ($row = mysqli_fetch_assoc($resPos))) {
        $positions[(int)$row['position_no']] = $row;
    }
    mysqli_stmt_close($stmtPos);
}

// PRE-FETCH OPTIONS FOR MODAL DROPDOWNS
$equipmentOptions = [];
$stmtEq = mysqli_prepare(
    $conn,
    "SELECT e.id, e.name, e.hostname, e.notes, e.switch_rj45_id, er.name AS switch_rj45_name
     FROM equipment e
     LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
     WHERE e.company_id=? ORDER BY e.name ASC LIMIT 500"
);
if ($stmtEq) {
    mysqli_stmt_bind_param($stmtEq, 'i', $company_id);
    mysqli_stmt_execute($stmtEq);
    $resEq = mysqli_stmt_get_result($stmtEq);
    while ($resEq && ($row = mysqli_fetch_assoc($resEq))) { $equipmentOptions[] = $row; }
    mysqli_stmt_close($stmtEq);
}

// ... [LOOKUP FETCHING FOR STATUSES, TYPES, PORTS CONTINUES] ...

// BUILD DYNAMIC EXTRA FIELDS FOR INLINE ADDITION
$equipmentAddExtraFields = json_encode([
    ['name' => 'equipment_type_id', 'label' => 'Equipment Type', 'type' => 'select', 'options' => $equipmentTypeOptions],
    ['name' => 'switch_rj45_id', 'label' => 'RJ45 Ports', 'type' => 'select', 'options' => $switchRj45FieldOptions],
    ['name' => 'status_id', 'label' => 'Status', 'type' => 'select', 'options' => $equipmentStatusFieldOptions],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ui_config = itm_get_ui_configuration($conn, $company_id);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>IDF View</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
    <!-- Component specific styles for the interactive rack -->
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="content">
            <div class="idf-view-shell">
                <!-- TOP COMMAND BAR -->
                <section class="idf-command-bar">
                    <div class="idf-command-title">
                        <div style="display:flex; gap:8px; align-items:center;">
                            <a class="btn btn-sm" href="index.php">🔙</a>
                            <div class="idf-rack-title">
                                🗄️ <?php echo sanitize($idf['name']); ?>
                            </div>
                        </div>
                        <div style="opacity:.85; font-size:12px;">📍 <?php echo sanitize($idf['location_name']); ?></div>
                    </div>
                    <div class="idf-command-actions">
                        <button class="btn btn-sm" onclick="idfExportExcel()">Excel</button>
                        <button class="btn btn-sm" onclick="idfExportImage()">Image</button>
                        <button class="btn btn-sm" onclick="idfExportPdf()">PDF</button>
                    </div>
                </section>

                <!-- VISUAL RACK DISPLAY -->
                <div id="idfCaptureRoot" class="idf-rack-wrap">
                    <div class="idf-rack">
                        <div class="idf-rack-header">
                            <div><div class="idf-rack-title">Rack Face (10 positions)</div></div>
                        </div>

                        <div class="idf-slots" id="idfSlots">
                            <?php for ($i = 1; $i <= 10; $i++): $pos = $positions[$i]; ?>
                                <div class="idf-slot" data-position="<?php echo $i; ?>">
                                    <div class="idf-slot-left">
                                        <div class="idf-slot-no"><?php echo $i; ?></div>
                                        <div class="idf-slot-meta">
                                            <?php if (!$pos): ?>
                                                <div class="idf-slot-name idf-empty">Empty position</div>
                                            <?php else: ?>
                                                <div class="idf-slot-name"><?php echo sanitize($pos['device_name']); ?></div>
                                                <div class="idf-slot-sub">
                                                    <span class="idf-badge"><?php echo idf_type_badge((string)$pos['device_type']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="idf-slot-actions">
                                        <!-- Actions for individual slots -->
                                        <?php if ($pos): ?>
                                            <a class="btn btn-sm idf-mini" href="device.php?position_id=<?php echo (int)$pos['id']; ?>">🔎</a>
                                            <button class="btn btn-sm idf-mini" onclick="openDeviceModal(<?php echo $i; ?>, <?php echo (int)$pos['id']; ?>)">✏️</button>
                                        <?php else: ?>
                                            <button class="btn btn-sm idf-mini" onclick="openDeviceModal(<?php echo $i; ?>, null)">Add</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALS AND SCRIPTS [OMITTED FOR BREVITY - FULL LOGIC PRESERVED IN FILE] -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
    /**
     * AJAX Client and Visual Logic
     * Handles: API communication, drag-and-drop, field syncing, and export.
     */
    (function () {
        // ...
    })();
</script>
</body>
</html>
