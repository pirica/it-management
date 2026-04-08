<?php
/**
 * IDFs Module - Device Position View
 * 
 * Manages the ports and links for a specific device within an IDF rack.
 * Features:
 * - Comprehensive port list for the device (Switch, Patch Panel, etc.)
 * - Port regeneration based on device capacity
 * - Creating/Deleting physical cable links between ports
 * - Linking IDF ports directly to system Equipment Assets
 * - Link loop detection and reporting
 */

require_once __DIR__ . '/../../config/config.php';

// Authentication
if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$position_id = (int)($_GET['position_id'] ?? 0);

function idf_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

$csrf = idf_csrf_token();

// FETCH DEVICE CONTEXT
$pos = null;
if ($position_id > 0 && $company_id > 0) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT p.*, i.name AS idf_name, i.id AS idf_id,
                COALESCE(e.is_switch, 0) AS equipment_is_switch
         FROM idf_positions p
         JOIN idfs i ON i.id = p.idf_id
         LEFT JOIN equipment e ON e.id = p.equipment_id
         WHERE p.id = ? AND i.company_id = ?
         LIMIT 1'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $position_id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $pos = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
}

if (!$pos) {
    $_SESSION['crud_error'] = 'Device not found.';
    header('Location: index.php'); exit;
}

// FETCH LOCAL PORTS AND THEIR LINKS
$ports = [];
$resPorts = mysqli_query(
    $conn,
    "SELECT pr.*, l.id AS link_id, l.cable_color, l.cable_label, l.notes AS link_notes,
       l.port_id_b AS other_port_id, l.equipment_id AS linked_equipment_id,
       COALESCE(le.is_switch, 0) AS linked_equipment_is_switch
     FROM idf_ports pr
     LEFT JOIN idf_links l ON l.port_id_a = pr.id
     LEFT JOIN equipment le ON le.id = l.equipment_id
     WHERE pr.position_id=$position_id
     ORDER BY pr.port_no ASC"
);
while ($resPorts && ($row = mysqli_fetch_assoc($resPorts))) { $ports[] = $row; }

// FETCH REMOTE END OF LINKS FOR MAPPING
$otherIds = [];
foreach ($ports as $p) { if (!empty($p['other_port_id'])) { $otherIds[] = (int)$p['other_port_id']; } }
$otherMap = [];
if ($otherIds) {
    $list = implode(',', array_unique($otherIds));
    $resOther = mysqli_query($conn, "SELECT pr.id AS port_id, pr.port_no, p.position_no, p.device_name FROM idf_ports pr JOIN idf_positions p ON p.id=pr.position_id WHERE pr.id IN ($list)");
    while ($resOther && ($r = mysqli_fetch_assoc($resOther))) { $otherMap[(int)$r['port_id']] = $r; }
}

// ... [LOOKUP DATA PREPARATION CONTINUES] ...

$ui_config = itm_get_ui_configuration($conn, $company_id);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Device Ports Management</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>

        <div class="content">
            <!-- TOOLBAR -->
            <div class="idf-toolbar">
                <div class="left">
                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$pos['idf_id']; ?>">🔙</a>
                    <div>
                        <div class="idf-rack-title">🔧 <?php echo sanitize($pos['device_name']); ?></div>
                        <div style="opacity:.85; font-size:12px;">IDF: <?php echo sanitize($pos['idf_name']); ?></div>
                    </div>
                </div>
                <div class="right">
                    <button class="btn btn-sm" onclick="idfPortsExportExcel()">Export CSV</button>
                </div>
            </div>

            <!-- PORT MANAGEMENT TABLE -->
            <div class="card">
                <h3>🔌 Ports</h3>
                <div style="margin-bottom:10px;"><button class="btn btn-sm" onclick="regeneratePorts()">Regenerate Ports</button></div>
                <table class="table idf-ports-table">
                    <thead><tr><th>#</th><th>Type</th><th>Label</th><th>Status</th><th>Link</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($ports as $p): ?>
                            <tr>
                                <td><?php echo (int)$p['port_no']; ?></td>
                                <td><?php echo sanitize($p['port_type']); ?></td>
                                <td><?php echo sanitize($p['label']); ?></td>
                                <td><?php echo sanitize($p['status']); ?></td>
                                <td><!-- Link display logic --></td>
                                <td>
                                    <button class="btn btn-sm" onclick="openPortModal(<?php echo (int)$p['id']; ?>)">✏️</button>
                                    <button class="btn btn-sm" onclick="openLinkModal(<?php echo (int)$p['id']; ?>)">Link</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- PORT AND LINK MODALS [OMITTED FOR BREVITY] -->

<script>
    /**
     * AJAX Client for Port and Link management
     */
    (function () {
        // ...
    })();
</script>
</body>
</html>
