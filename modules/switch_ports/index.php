<?php
$crud_table = $crud_table ?? 'switch_ports';
$crud_title = $crud_title ?? 'Switch Ports';
$crud_action = $crud_action ?? 'index';
?>
<?php
require '../../config/config.php';

if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$crud_title = $crud_title ?? ucwords(str_replace('_', ' ', $crud_table));
$crud_action = $crud_action ?? 'index';
$pk = 'id';

function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

function cr_table_columns($conn, $table) {
    $cols = [];
    $res = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cols[] = $row;
    }
    return $cols;
}

function cr_fk_map($conn, $table) {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tableEsc}'
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $map = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $map[$row['COLUMN_NAME']] = $row;
    }

    if ($table === 'switch_ports') {
        $manual = [
            'equipment_id' => ['COLUMN_NAME' => 'equipment_id', 'REFERENCED_TABLE_NAME' => 'equipment', 'REFERENCED_COLUMN_NAME' => 'id'],
            'status_id' => ['COLUMN_NAME' => 'status_id', 'REFERENCED_TABLE_NAME' => 'switch_status', 'REFERENCED_COLUMN_NAME' => 'id'],
            'color_id' => ['COLUMN_NAME' => 'color_id', 'REFERENCED_TABLE_NAME' => 'cable_colors', 'REFERENCED_COLUMN_NAME' => 'id'],
            'vlan_id' => ['COLUMN_NAME' => 'vlan_id', 'REFERENCED_TABLE_NAME' => 'vlans', 'REFERENCED_COLUMN_NAME' => 'id'],
            'port_type' => ['COLUMN_NAME' => 'port_type', 'REFERENCED_TABLE_NAME' => 'switch_port_types', 'REFERENCED_COLUMN_NAME' => 'type'],
            'to_location_id' => ['COLUMN_NAME' => 'to_location_id', 'REFERENCED_TABLE_NAME' => 'it_locations', 'REFERENCED_COLUMN_NAME' => 'id'],
        ];

        foreach ($manual as $column => $definition) {
            if (!isset($map[$column])) {
                $map[$column] = $definition;
            }
        }
    }

    return $map;
}

function cr_fk_options($conn, $fk, $company_id) {
    $table = $fk['REFERENCED_TABLE_NAME'];
    $col = $fk['REFERENCED_COLUMN_NAME'];

    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];

    $where = '';
    if (in_array('company_id', $available, true) && $company_id > 0) {
        $where = ' WHERE company_id=' . (int)$company_id;
    }

    $sql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . cr_escape_identifier($labelCol) . " AS label FROM " . cr_escape_identifier($table) . $where . ' ORDER BY label';
    $rows = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    return $rows;
}

function cr_fk_metadata($conn, $table) {
    $labelCol = 'name';
    $des = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    $available = [];
    while ($des && ($d = mysqli_fetch_assoc($des))) {
        $available[] = $d['Field'];
    }

    $tableLabelCandidates = [
        'equipment' => ['hostname', 'name'],
        'switch_status' => ['name', 'status'],
        'cable_colors' => ['color_name', 'name'],
        'vlans' => ['vlan_name', 'name'],
        'switch_port_types' => ['name', 'type'],
        'it_locations' => ['name', 'title'],
    ];

    $candidates = $tableLabelCandidates[$table] ?? ['name', 'title', 'username', 'code', 'mode_name', 'status', 'color_name', 'vlan_name'];
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $available, true)) {
            $labelCol = $candidate;
            break;
        }
    }

    return [
        'label_col' => $labelCol,
        'available' => $available,
    ];
}

function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        return !in_array($c['Field'], ['id', 'company_id'], true);
    }));
}

function cr_form_display_value($value) {
    return itm_cr_form_display_value($value);
}
function cr_humanize_field($field) {
    $label = trim((string)$field);
    if ($label === '') {
        return '';
    }

    $map = [
        'to_patch_port' => 'To patch port',
        'to_location_id' => 'To Location',
        'department_id' => 'Department Name',
        'office_key_card_department_id' => 'Office Key Card Department',
        'opera_username' => 'OPERA Username',
        'onq_ri' => 'OnQ R&I',
        'hu_the_lobby' => 'HU & The Lobby',
    ];

    if (isset($map[$label])) {
        return $map[$label];
    }

    if ($label === 'id') {
        return 'ID';
    }

    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

function cr_is_hidden_employee_field($field) {
    if (($GLOBALS['crud_table'] ?? '') !== 'employees') {
        return false;
    }

    $hidden = ['company_id', 'user_id', 'location_id', 'phone', 'location', 'employee_code'];
    return in_array($field, $hidden, true);
}

function cr_is_hidden_display_field($field) {
    if (($GLOBALS['crud_table'] ?? '') === 'switch_ports' && $field === 'company_id') {
        return true;
    }

    return cr_is_hidden_employee_field($field);
}

function cr_fk_label_lookup($conn, $table, $idCol, $labelCol, $value, $companyId, $hasCompanyScope) {
    static $cache = [];

    $rawValue = trim((string)$value);
    if ($rawValue === '') {
        return null;
    }
    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($idCol) || !itm_is_safe_identifier($labelCol)) {
        return null;
    }

    $cacheKey = $table . '|' . $idCol . '|' . $labelCol . '|' . $rawValue . '|' . (int)$companyId . '|' . (int)$hasCompanyScope;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $label = null;
    $lookup = function ($withCompany) use ($conn, $table, $idCol, $labelCol, $rawValue, $companyId, &$label) {
        $sql = 'SELECT ' . cr_escape_identifier($labelCol) . ' AS label FROM ' . cr_escape_identifier($table)
            . ' WHERE ' . cr_escape_identifier($idCol) . ' = ?';
        if ($withCompany && $companyId > 0) {
            $sql .= ' AND company_id = ?';
        }
        $sql .= ' LIMIT 1';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return;
        }
        if ($withCompany && $companyId > 0) {
            mysqli_stmt_bind_param($stmt, 'si', $rawValue, $companyId);
        } else {
            mysqli_stmt_bind_param($stmt, 's', $rawValue);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res)) && isset($row['label']) && trim((string)$row['label']) !== '') {
            $label = (string)$row['label'];
        }
        mysqli_stmt_close($stmt);
    };

    $lookup($hasCompanyScope);
    if ($label === null && $hasCompanyScope && $companyId > 0) {
        $lookup(false);
    }

    $cache[$cacheKey] = $label;
    return $label;
}

function cr_render_cell_value($table, $field, $value) {
    if (isset($GLOBALS['fkMap'][$field]) && (string)$value !== '') {
        $fk = $GLOBALS['fkMap'][$field];
        $fkTable = $fk['REFERENCED_TABLE_NAME'];
        $fkCol = $fk['REFERENCED_COLUMN_NAME'];
        $fkMeta = cr_fk_metadata($GLOBALS['conn'], $fkTable);
        $labelCol = $fkMeta['label_col'];
        $hasCompanyScope = in_array('company_id', $fkMeta['available'], true);
        $label = cr_fk_label_lookup($GLOBALS['conn'], $fkTable, $fkCol, $labelCol, (string)$value, (int)$GLOBALS['company_id'], $hasCompanyScope);
        if ($label !== null) {
            $label = sanitize((string)$label);
            if (($GLOBALS['crud_table'] ?? '') === 'switch_ports' && $field === 'equipment_id') {
                $equipmentId = (int)$value;
                return '<a href="../equipment/index.php?switch_id=' . $equipmentId . '&spm=1#switch-port-manager">' . $label . '</a>';
            }
            return $label;
        }
    }

    if ($field === 'active') {
        $isActive = ((int)$value === 1);
        return '<span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">' . ($isActive ? 'Active' : 'Inactive') . '</span>';
    }

    if (($GLOBALS['crud_table'] ?? '') === 'employees') {
        $employeeBoolFields = ['network_access', 'micros_emc', 'opera_username', 'micros_card', 'pms_id', 'synergy_mms', 'hu_the_lobby', 'navision', 'onq_ri', 'birchstreet', 'delphi', 'omina', 'vingcard_system', 'digital_rev', 'office_key_card'];
        if (in_array($field, $employeeBoolFields, true)) {
            return ((int)$value === 1) ? '✅' : '❌';
        }
    }

    $text = (string)($value ?? '');
    if ($table === 'employees' && $field === 'email' && $text !== '') {
        $safeEmail = sanitize($text);
        $mailto = 'mailto:' . $text;
        $outlook = 'ms-outlook://compose?to=' . $text;
        return '<a href="' . sanitize($mailto) . '" data-outlook-link="1" data-outlook-href="' . sanitize($outlook) . '">' . $safeEmail . '</a>';
    }

    return sanitize($text);
}

function cr_switch_port_rack_label($equipmentId) {
    $equipmentId = (int)$equipmentId;
    if ($equipmentId <= 0) {
        return '';
    }

    $sql = 'SELECT r.name AS rack_name
            FROM `equipment` e
            LEFT JOIN `racks` r ON r.id = e.rack_id
            WHERE e.id = ' . $equipmentId;

    if ((int)($GLOBALS['company_id'] ?? 0) > 0) {
        $sql .= ' AND e.company_id = ' . (int)$GLOBALS['company_id'];
    }
    $sql .= ' LIMIT 1';

    $res = mysqli_query($GLOBALS['conn'], $sql);
    if (!$res) {
        return '';
    }

    $row = mysqli_fetch_assoc($res);
    if (!$row || !isset($row['rack_name'])) {
        return '';
    }

    return (string)$row['rack_name'];
}

/**
 * Keeps hostname aligned with selected equipment for better port context.
 */
function cr_switch_port_fill_hostname_from_equipment($conn, &$data, $company_id) {
    if (!array_key_exists('equipment_id', $data) || !array_key_exists('hostname', $data)) {
        return;
    }

    $hostnameRaw = $data['hostname'];
    $hostname = trim((string)$hostnameRaw, "'");
    if ($hostname !== '' && strtoupper($hostname) !== 'NULL') {
        return;
    }

    $equipmentId = (int)($data['equipment_id'] ?? 0);
    if ($equipmentId <= 0) {
        return;
    }

    $sql = 'SELECT hostname FROM `equipment` WHERE id=?';
    $companyId = (int)$company_id;
    if ($companyId > 0) {
        $sql .= ' AND company_id=?';
    }
    $sql .= ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return;
    }
    if ($companyId > 0) { mysqli_stmt_bind_param($stmt, 'ii', $equipmentId, $companyId); }
    else { mysqli_stmt_bind_param($stmt, 'i', $equipmentId); }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $resolvedHostname = trim((string)($row['hostname'] ?? ''));
        if ($resolvedHostname !== '') {
            $data['hostname'] = "'" . mysqli_real_escape_string($conn, $resolvedHostname) . "'";
        }
    }
    mysqli_stmt_close($stmt);
}



/**
 * Keeps IDF aligned with selected equipment when the field is hidden in the form.
 */
function cr_switch_port_fill_idf_from_equipment($conn, &$data, $company_id) {
    if (!array_key_exists('equipment_id', $data) || !array_key_exists('idf_id', $data)) {
        return;
    }

    $idfRaw = trim((string)($data['idf_id'] ?? ''), "' ");
    if ($idfRaw !== '' && strtoupper($idfRaw) !== 'NULL') {
        return;
    }

    $equipmentId = (int)($data['equipment_id'] ?? 0);
    if ($equipmentId <= 0) {
        return;
    }

    $sql = 'SELECT idf_id FROM `equipment` WHERE id=?';
    $companyId = (int)$company_id;
    if ($companyId > 0) {
        $sql .= ' AND company_id=?';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return;
    }

    if ($companyId > 0) { mysqli_stmt_bind_param($stmt, 'ii', $equipmentId, $companyId); }
    else { mysqli_stmt_bind_param($stmt, 'i', $equipmentId); }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $resolvedIdfId = (int)($row['idf_id'] ?? 0);
        if ($resolvedIdfId > 0) {
            $data['idf_id'] = (string)$resolvedIdfId;
        }
    }

    mysqli_stmt_close($stmt);
}

function cr_switch_port_label_column($conn)
{
    static $column = null;

    if ($column !== null) {
        return $column;
    }

    foreach (['to_patch_port', 'label', 'patch_port'] as $candidate) {
        if (itm_table_has_column($conn, 'switch_ports', $candidate)) {
            $column = $candidate;
            return $column;
        }
    }

    $column = 'to_patch_port';
    return $column;
}

function cr_switch_port_fetch_snapshot_by_id($conn, $companyId, $switchPortId)
{
    if ($companyId <= 0 || $switchPortId <= 0) {
        return null;
    }

    $labelColumn = cr_escape_identifier(cr_switch_port_label_column($conn));
    $sql = "SELECT id, company_id, equipment_id, port_type, port_number,
                   {$labelColumn} AS sync_label, status_id, color_id, vlan_id,
                   fiber_port_id, management_id, comments, hostname
            FROM switch_ports
            WHERE id = ? AND company_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $switchPortId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function cr_switch_port_fetch_snapshots_by_ids($conn, $companyId, $ids)
{
    $idList = [];
    foreach ($ids as $rawId) {
        $id = (int)$rawId;
        if ($id > 0) {
            $idList[$id] = $id;
        }
    }
    if ($companyId <= 0 || empty($idList)) {
        return [];
    }

    $labelColumn = cr_escape_identifier(cr_switch_port_label_column($conn));
    $placeholders = implode(',', array_fill(0, count($idList), '?'));
    $sql = "SELECT id, company_id, equipment_id, port_type, port_number,
                   {$labelColumn} AS sync_label, status_id, color_id, vlan_id,
                   fiber_port_id, management_id, comments, hostname
            FROM switch_ports
            WHERE company_id = ? AND id IN ({$placeholders})";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    $types = 'i' . str_repeat('i', count($idList));
    $params = array_merge([$companyId], array_values($idList));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

function cr_switch_port_fetch_snapshots_for_company($conn, $companyId)
{
    if ($companyId <= 0) {
        return [];
    }

    $labelColumn = cr_escape_identifier(cr_switch_port_label_column($conn));
    $sql = "SELECT id, company_id, equipment_id, port_type, port_number,
                   {$labelColumn} AS sync_label, status_id, color_id, vlan_id,
                   fiber_port_id, management_id, comments, hostname
            FROM switch_ports
            WHERE company_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

function cr_switch_port_resolve_port_type_id($conn, $companyId, $rawPortType)
{
    $portTypeRaw = trim((string)$rawPortType);
    if ($portTypeRaw === '') {
        return 0;
    }
    if (ctype_digit($portTypeRaw)) {
        return (int)$portTypeRaw;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id
         FROM switch_port_types
         WHERE company_id = ? AND LOWER(type) = LOWER(?)
         ORDER BY id ASC
         LIMIT 1"
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $portTypeRaw);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (int)($row['id'] ?? 0);
}

function cr_switch_port_find_position_id($conn, $companyId, $equipmentId)
{
    if ($companyId <= 0 || $equipmentId <= 0) {
        return 0;
    }

    $equipmentIdStr = (string)$equipmentId;
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id
         FROM idf_positions
         WHERE company_id = ? AND equipment_id = ?
         ORDER BY id ASC
         LIMIT 1"
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $equipmentIdStr);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (int)($row['id'] ?? 0);
}

function cr_switch_port_sync_snapshot($conn, $companyId, $snapshot, &$syncError = null)
{
    $syncError = null;
    $equipmentId = (int)($snapshot['equipment_id'] ?? 0);
    $portNo = (int)($snapshot['port_number'] ?? 0);
    if ($companyId <= 0 || $equipmentId <= 0 || $portNo <= 0) {
        return true;
    }

    $positionId = cr_switch_port_find_position_id($conn, $companyId, $equipmentId);
    if ($positionId <= 0) {
        return true;
    }

    $portTypeId = cr_switch_port_resolve_port_type_id($conn, $companyId, $snapshot['port_type'] ?? '');
    if ($portTypeId <= 0) {
        return true;
    }

    $colorId = (int)($snapshot['color_id'] ?? 0);
    $colorName = null;
    $hexColor = null;
    if ($colorId > 0) {
        $stmtColor = mysqli_prepare(
            $conn,
            "SELECT color_name, hex_color
             FROM cable_colors
             WHERE company_id = ? AND id = ?
             LIMIT 1"
        );
        if ($stmtColor) {
            mysqli_stmt_bind_param($stmtColor, 'ii', $companyId, $colorId);
            mysqli_stmt_execute($stmtColor);
            $resColor = mysqli_stmt_get_result($stmtColor);
            $rowColor = $resColor ? mysqli_fetch_assoc($resColor) : null;
            mysqli_stmt_close($stmtColor);
            if ($rowColor) {
                $colorName = trim((string)($rowColor['color_name'] ?? ''));
                $hexColor = trim((string)($rowColor['hex_color'] ?? ''));
                $colorName = $colorName !== '' ? $colorName : null;
                $hexColor = $hexColor !== '' ? $hexColor : null;
            }
        }
    }

    $label = trim((string)($snapshot['sync_label'] ?? ''));
    $comments = trim((string)($snapshot['comments'] ?? ''));
    $statusId = (int)($snapshot['status_id'] ?? 0);
    $vlanId = (int)($snapshot['vlan_id'] ?? 0);
    $speedId = (int)($snapshot['fiber_port_id'] ?? 0);
    $managementId = (int)($snapshot['management_id'] ?? 0);
    $connectedTo = trim((string)($snapshot['hostname'] ?? ''));
    $labelValue = $label !== '' ? $label : null;
    $commentsValue = $comments !== '' ? $comments : null;
    $connectedToValue = $connectedTo !== '' ? $connectedTo : null;

    $idfPortId = 0;
    $stmtExisting = mysqli_prepare(
        $conn,
        "SELECT id
         FROM idf_ports
         WHERE company_id = ? AND position_id = ? AND port_no = ? AND port_type = ?
         LIMIT 1"
    );
    if ($stmtExisting) {
        mysqli_stmt_bind_param($stmtExisting, 'iiii', $companyId, $positionId, $portNo, $portTypeId);
        mysqli_stmt_execute($stmtExisting);
        $resExisting = mysqli_stmt_get_result($stmtExisting);
        $existingRow = $resExisting ? mysqli_fetch_assoc($resExisting) : null;
        mysqli_stmt_close($stmtExisting);
        $idfPortId = (int)($existingRow['id'] ?? 0);
    }

    if ($idfPortId > 0) {
        $stmtUpdate = mysqli_prepare(
            $conn,
            "UPDATE idf_ports
             SET label = ?,
                 status_id = NULLIF(?, 0),
                 vlan_id = NULLIF(?, 0),
                 speed_id = NULLIF(?, 0),
                 management_id = NULLIF(?, 0),
                 connected_to = ?,
                 cable_color = ?,
                 hex_color = ?,
                 notes = ?
             WHERE id = ? AND company_id = ?
             LIMIT 1"
        );
        if (!$stmtUpdate) {
            $syncError = 'Unable to prepare IDF port update sync query.';
            return false;
        }
        mysqli_stmt_bind_param(
            $stmtUpdate,
            'siiiissssii',
            $labelValue,
            $statusId,
            $vlanId,
            $speedId,
            $managementId,
            $connectedToValue,
            $colorName,
            $hexColor,
            $commentsValue,
            $idfPortId,
            $companyId
        );
        if (!mysqli_stmt_execute($stmtUpdate)) {
            $syncError = 'Unable to sync IDF port update. ' . itm_format_db_constraint_error(mysqli_stmt_errno($stmtUpdate), mysqli_stmt_error($stmtUpdate));
            mysqli_stmt_close($stmtUpdate);
            return false;
        }
        mysqli_stmt_close($stmtUpdate);
    } else {
        $stmtInsert = mysqli_prepare(
            $conn,
            "INSERT INTO idf_ports
                (company_id, position_id, port_no, port_type, label, status_id, vlan_id, speed_id, management_id, connected_to, cable_color, hex_color, notes)
             VALUES
                (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?)"
        );
        if (!$stmtInsert) {
            $syncError = 'Unable to prepare IDF port insert sync query.';
            return false;
        }
        mysqli_stmt_bind_param(
            $stmtInsert,
            'iiiisiiiissss',
            $companyId,
            $positionId,
            $portNo,
            $portTypeId,
            $labelValue,
            $statusId,
            $vlanId,
            $speedId,
            $managementId,
            $connectedToValue,
            $colorName,
            $hexColor,
            $commentsValue
        );
        if (!mysqli_stmt_execute($stmtInsert)) {
            $syncError = 'Unable to sync IDF port insert. ' . itm_format_db_constraint_error(mysqli_stmt_errno($stmtInsert), mysqli_stmt_error($stmtInsert));
            mysqli_stmt_close($stmtInsert);
            return false;
        }
        $idfPortId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmtInsert);
    }

    if ($idfPortId > 0) {
        $stmtLinkMeta = mysqli_prepare(
            $conn,
            "UPDATE idf_links
             SET equipment_label = ?,
                 equipment_status_id = NULLIF(?, 0),
                 equipment_vlan_id = NULLIF(?, 0),
                 equipment_comments = ?,
                 equipment_color_id = NULLIF(?, 0)
             WHERE company_id = ?
               AND (port_id_a = ? OR port_id_b = ?)"
        );
        if ($stmtLinkMeta) {
            mysqli_stmt_bind_param(
                $stmtLinkMeta,
                'siisiiii',
                $labelValue,
                $statusId,
                $vlanId,
                $commentsValue,
                $colorId,
                $companyId,
                $idfPortId,
                $idfPortId
            );
            if (!mysqli_stmt_execute($stmtLinkMeta)) {
                $syncError = 'Unable to sync IDF link metadata. ' . itm_format_db_constraint_error(mysqli_stmt_errno($stmtLinkMeta), mysqli_stmt_error($stmtLinkMeta));
                mysqli_stmt_close($stmtLinkMeta);
                return false;
            }
            mysqli_stmt_close($stmtLinkMeta);
        }

        $stmtLinkColor = mysqli_prepare(
            $conn,
            "UPDATE idf_links
             SET cable_color_id = NULLIF(?, 0),
                 cable_color_hex = ?
             WHERE company_id = ?
               AND (port_id_a = ? OR port_id_b = ?)"
        );
        if ($stmtLinkColor) {
            mysqli_stmt_bind_param($stmtLinkColor, 'isiii', $colorId, $hexColor, $companyId, $idfPortId, $idfPortId);
            if (!mysqli_stmt_execute($stmtLinkColor)) {
                $syncError = 'Unable to sync IDF link color. ' . itm_format_db_constraint_error(mysqli_stmt_errno($stmtLinkColor), mysqli_stmt_error($stmtLinkColor));
                mysqli_stmt_close($stmtLinkColor);
                return false;
            }
            mysqli_stmt_close($stmtLinkColor);
        }
    }

    return true;
}

function cr_switch_port_delete_synced_snapshots($conn, $companyId, $snapshots, &$syncError = null)
{
    $syncError = null;
    if ($companyId <= 0 || empty($snapshots)) {
        return true;
    }

    $idfPortIds = [];
    foreach ($snapshots as $snapshot) {
        $equipmentId = (int)($snapshot['equipment_id'] ?? 0);
        $portNo = (int)($snapshot['port_number'] ?? 0);
        if ($equipmentId <= 0 || $portNo <= 0) {
            continue;
        }

        $positionId = cr_switch_port_find_position_id($conn, $companyId, $equipmentId);
        if ($positionId <= 0) {
            continue;
        }

        $portTypeId = cr_switch_port_resolve_port_type_id($conn, $companyId, $snapshot['port_type'] ?? '');
        if ($portTypeId <= 0) {
            continue;
        }

        $stmtFind = mysqli_prepare(
            $conn,
            "SELECT id
             FROM idf_ports
             WHERE company_id = ? AND position_id = ? AND port_no = ? AND port_type = ?"
        );
        if (!$stmtFind) {
            continue;
        }
        mysqli_stmt_bind_param($stmtFind, 'iiii', $companyId, $positionId, $portNo, $portTypeId);
        mysqli_stmt_execute($stmtFind);
        $resFind = mysqli_stmt_get_result($stmtFind);
        while ($resFind && ($row = mysqli_fetch_assoc($resFind))) {
            $portId = (int)($row['id'] ?? 0);
            if ($portId > 0) {
                $idfPortIds[$portId] = $portId;
            }
        }
        mysqli_stmt_close($stmtFind);
    }

    foreach (array_values($idfPortIds) as $idfPortId) {
        $stmtDeleteLinks = mysqli_prepare(
            $conn,
            "DELETE FROM idf_links
             WHERE company_id = ?
               AND (port_id_a = ? OR port_id_b = ?)"
        );
        if ($stmtDeleteLinks) {
            mysqli_stmt_bind_param($stmtDeleteLinks, 'iii', $companyId, $idfPortId, $idfPortId);
            if (!mysqli_stmt_execute($stmtDeleteLinks)) {
                $syncError = 'Unable to delete synced IDF links. ' . itm_format_db_constraint_error(mysqli_stmt_errno($stmtDeleteLinks), mysqli_stmt_error($stmtDeleteLinks));
                mysqli_stmt_close($stmtDeleteLinks);
                return false;
            }
            mysqli_stmt_close($stmtDeleteLinks);
        }

        $stmtDeletePort = mysqli_prepare(
            $conn,
            "DELETE FROM idf_ports
             WHERE company_id = ? AND id = ?
             LIMIT 1"
        );
        if ($stmtDeletePort) {
            mysqli_stmt_bind_param($stmtDeletePort, 'ii', $companyId, $idfPortId);
            if (!mysqli_stmt_execute($stmtDeletePort)) {
                $syncError = 'Unable to delete synced IDF port. ' . itm_format_db_constraint_error(mysqli_stmt_errno($stmtDeletePort), mysqli_stmt_error($stmtDeletePort));
                mysqli_stmt_close($stmtDeletePort);
                return false;
            }
            mysqli_stmt_close($stmtDeletePort);
        }
    }

    return true;
}

function cr_get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function cr_require_valid_csrf_token() {
    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        echo 'Forbidden: invalid CSRF token.';
        exit;
    }
}

function cr_numeric_validation_error($field, $message) {
    return cr_humanize_field($field) . ' ' . $message . '.';
}

function cr_validate_numeric_value($rawValue, $column, $fieldName, &$normalizedValue, &$error) {
    $type = strtolower((string)$column['Type']);
    $isUnsigned = str_contains($type, 'unsigned');
    $raw = trim((string)$rawValue);

    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $type, $match)) {
        $intVal = filter_var($raw, FILTER_VALIDATE_INT);
        if ($intVal === false) {
            $error = cr_numeric_validation_error($fieldName, 'must be a valid integer');
            return false;
        }

        $ranges = [
            'tinyint' => [-128, 127, 0, 255],
            'smallint' => [-32768, 32767, 0, 65535],
            'mediumint' => [-8388608, 8388607, 0, 16777215],
            'int' => [-2147483648, 2147483647, 0, 4294967295],
        ];
        $typeName = $match[1];

        if (isset($ranges[$typeName])) {
            [$signedMin, $signedMax, $unsignedMin, $unsignedMax] = $ranges[$typeName];
            $min = $isUnsigned ? $unsignedMin : $signedMin;
            $max = $isUnsigned ? $unsignedMax : $signedMax;
            if ($intVal < $min || $intVal > $max) {
                $error = cr_numeric_validation_error($fieldName, 'is out of range');
                return false;
            }
        } elseif ($typeName === 'bigint' && $isUnsigned && $intVal < 0) {
            $error = cr_numeric_validation_error($fieldName, 'must be zero or greater');
            return false;
        }

        $normalizedValue = (string)$intVal;
        return true;
    }

    if (preg_match('/^(decimal|float|double)\b/', $type)) {
        if (!is_numeric($raw)) {
            $error = cr_numeric_validation_error($fieldName, 'must be a valid number');
            return false;
        }

        $floatVal = (float)$raw;
        if (!is_finite($floatVal)) {
            $error = cr_numeric_validation_error($fieldName, 'must be a finite number');
            return false;
        }

        if ($isUnsigned && $floatVal < 0) {
            $error = cr_numeric_validation_error($fieldName, 'must be zero or greater');
            return false;
        }

        $normalizedValue = (string)$raw;
        return true;
    }

    $error = cr_numeric_validation_error($fieldName, 'has an unsupported numeric type');
    return false;
}

$columns = cr_table_columns($conn, $crud_table);
$fkMap = cr_fk_map($conn, $crud_table);
$fieldColumns = cr_manageable_columns($columns);
$fieldColumns = array_values(array_filter($fieldColumns, function ($col) {
    return !cr_is_hidden_employee_field($col['Field']);
}));
$hasCompany = false;
foreach ($columns as $c) {
    if ($c['Field'] === 'company_id') { $hasCompany = true; break; }
}
$visibleFieldColumns = array_values(array_filter($fieldColumns, function ($col) {
    return !cr_is_hidden_display_field($col['Field']);
}));

if (($crud_table ?? '') === 'switch_ports') {
    usort($visibleFieldColumns, static function ($a, $b) {
        $order = [
            'port_type' => 10,
            'to_location_id' => 20,
            'comments' => 30,
        ];
        $aField = (string)($a['Field'] ?? '');
        $bField = (string)($b['Field'] ?? '');
        $aWeight = $order[$aField] ?? 1000;
        $bWeight = $order[$bField] ?? 1000;
        if ($aWeight === $bWeight) {
            return 0;
        }
        return ($aWeight < $bWeight) ? -1 : 1;
    });
}
$hasRackDisplayColumn = false;
foreach ($visibleFieldColumns as $visibleFieldColumn) {
    if (($visibleFieldColumn['Field'] ?? '') === 'port_number') {
        $hasRackDisplayColumn = true;
        break;
    }
}

// Why: Search uses the same visible column set as the list table.
$displayFieldColumns = $visibleFieldColumns;

$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';
$csrfToken = cr_get_csrf_token();

// Handle Excel/CSV database import requests from table-tools.js.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && strpos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode((string)$rawBody, true);
    if (is_array($jsonBody) && isset($jsonBody['import_excel_rows'])) {
        header('Content-Type: application/json');

        $requestToken = (string)($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($requestToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        if (!$hasCompany || $company_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Import requires an active company.']);
            exit;
        }

        $importRows = $jsonBody['import_excel_rows'];
        if (!is_array($importRows) || count($importRows) < 2) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'The uploaded file has no data rows.']);
            exit;
        }

        $headerRow = array_map('trim', array_map('strval', (array)($importRows[0] ?? [])));
        $columnKeys = [];
        foreach ($headerRow as $headerValue) {
            $columnKeys[] = strtolower(preg_replace('/\s+/', ' ', $headerValue));
        }

        $fieldByLabel = [];
        foreach ($fieldColumns as $col) {
            $fieldName = (string)$col['Field'];
            $fieldByLabel[strtolower((string)cr_humanize_field($fieldName))] = $col;
            $fieldByLabel[strtolower(str_replace('_', ' ', $fieldName))] = $col;
        }
        $fieldByLabel['id'] = null;

        $importColumns = [];
        foreach ($columnKeys as $labelKey) {
            $importColumns[] = $fieldByLabel[$labelKey] ?? null;
        }

        $insertedRows = 0;
        for ($rowIndex = 1; $rowIndex < count($importRows); $rowIndex++) {
            $sourceRow = (array)$importRows[$rowIndex];
            if (empty(array_filter($sourceRow, function ($v) { return trim((string)$v) !== ''; }))) {
                continue;
            }

            $rowData = [];
            foreach ($fieldColumns as $col) {
                $rowData[$col['Field']] = 'NULL';
            }

            foreach ($importColumns as $idx => $columnMeta) {
                if (!is_array($columnMeta)) {
                    continue;
                }

                $fieldName = (string)$columnMeta['Field'];
                $rawValue = trim((string)($sourceRow[$idx] ?? ''));
                if ($rawValue === '' || $rawValue === '—') {
                    continue;
                }

                if ($fieldName === 'company_id' || $fieldName === 'id') {
                    continue;
                }

                $isTinyInt = (bool)preg_match('/^tinyint(\(\d+\))?/i', (string)$columnMeta['Type']);
                if ($isTinyInt) {
                    $normalizedBool = strtolower($rawValue);
                    if (in_array($normalizedBool, ['1', 'active', 'yes', 'true', 'on', '✅'], true)) {
                        $rowData[$fieldName] = '1';
                    } elseif (in_array($normalizedBool, ['0', 'inactive', 'no', 'false', 'off', '❌'], true)) {
                        $rowData[$fieldName] = '0';
                    }
                    continue;
                }

                if (isset($fkMap[$fieldName])) {
                    $fk = $fkMap[$fieldName];
                    $options = cr_fk_options($conn, $fk, (int)$company_id);
                    $columnType = strtolower((string)($columnMeta['Type'] ?? ''));
                    $isNumericTarget = (bool)preg_match('/int|decimal|float|double/', $columnType);
                    $resolvedValue = '';
                    foreach ($options as $option) {
                        if (strcasecmp((string)$option['label'], $rawValue) === 0) {
                            $resolvedValue = trim((string)$option['id']);
                            break;
                        }
                    }
                    if ($resolvedValue === '') {
                        if ($isNumericTarget && ctype_digit($rawValue)) {
                            $resolvedValue = (string)(int)$rawValue;
                        } elseif (!$isNumericTarget) {
                            $resolvedValue = $rawValue;
                        }
                    }
                    if ($resolvedValue === '') {
                        $rowData[$fieldName] = 'NULL';
                    } elseif ($isNumericTarget) {
                        $rowData[$fieldName] = (string)$resolvedValue;
                    } else {
                        $rowData[$fieldName] = "'" . mysqli_real_escape_string($conn, $resolvedValue) . "'";
                    }
                    continue;
                }

                if (preg_match('/int|decimal|float|double/', (string)$columnMeta['Type'])) {
                    $normalizedNumeric = null; $numericError = '';
                    if (cr_validate_numeric_value($rawValue, $columnMeta, $fieldName, $normalizedNumeric, $numericError)) {
                        $rowData[$fieldName] = $normalizedNumeric;
                    }
                    continue;
                }

                $rowData[$fieldName] = "'" . mysqli_real_escape_string($conn, $rawValue) . "'";
            }

            if ($hasCompany) {
                $rowData['company_id'] = (string)(int)$company_id;
            }

            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = (string)$col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $rowData[$name] ?? 'NULL';
            }
            if ($hasCompany) {
                $fields[] = '`company_id`';
                $values[] = (string)(int)$company_id;
            }

            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
            $dbErrorCode = 0; $dbErrorMessage = '';
            if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
                $newSwitchPortId = (int)mysqli_insert_id($conn);
                if ($newSwitchPortId > 0) {
                    $snapshot = cr_switch_port_fetch_snapshot_by_id($conn, (int)$company_id, $newSwitchPortId);
                    $syncError = null;
                    if ($snapshot && !cr_switch_port_sync_snapshot($conn, (int)$company_id, $snapshot, $syncError)) {
                        $stmtRollbackImport = mysqli_prepare(
                            $conn,
                            "DELETE FROM switch_ports
                             WHERE id = ? AND company_id = ?
                             LIMIT 1"
                        );
                        if ($stmtRollbackImport) {
                            $companyIdParam = (int)$company_id;
                            mysqli_stmt_bind_param($stmtRollbackImport, 'ii', $newSwitchPortId, $companyIdParam);
                            mysqli_stmt_execute($stmtRollbackImport);
                            mysqli_stmt_close($stmtRollbackImport);
                        }
                        http_response_code(500);
                        echo json_encode([
                            'ok' => false,
                            'error' => $syncError !== null && $syncError !== '' ? $syncError : 'Import sync failed.'
                        ]);
                        exit;
                    }
                }
                $insertedRows++;
            }
        }

        echo json_encode(['ok' => true, 'inserted' => $insertedRows]);
        exit;
    }
}

if ($crud_action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method not allowed.';
        exit;
    }

    cr_require_valid_csrf_token();

    $bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
    $dbErrorCode = 0;
    $dbErrorMessage = '';
    $syncError = null;

    if ($bulkAction === 'clear_table') {
        $snapshots = $hasCompany && $company_id > 0
            ? cr_switch_port_fetch_snapshots_for_company($conn, (int)$company_id)
            : [];

        mysqli_begin_transaction($conn);
        $where = '';
        if ($hasCompany && $company_id > 0) {
            $where = ' WHERE company_id=' . (int)$company_id;
        }
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
        $deleteOk = itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage);
        $syncOk = $deleteOk
            ? cr_switch_port_delete_synced_snapshots($conn, (int)$company_id, $snapshots, $syncError)
            : false;

        if ($deleteOk && $syncOk) {
            mysqli_commit($conn);
        } else {
            mysqli_rollback($conn);
            $_SESSION['crud_error'] = $deleteOk
                ? ($syncError !== null && $syncError !== '' ? $syncError : 'Delete sync failed.')
                : itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
        header('Location: ' . $listUrl);
        exit;
    }

    if ($bulkAction === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $idList = [];
        foreach ($ids as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) {
                $idList[$id] = $id;
            }
        }

        if (!empty($idList)) {
            $snapshots = cr_switch_port_fetch_snapshots_by_ids($conn, (int)$company_id, array_values($idList));

            mysqli_begin_transaction($conn);
            $where = ' WHERE id IN (' . implode(',', array_values($idList)) . ')';
            if ($hasCompany && $company_id > 0) {
                $where .= ' AND company_id=' . (int)$company_id;
            }
            $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
            $deleteOk = itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage);
            $syncOk = $deleteOk
                ? cr_switch_port_delete_synced_snapshots($conn, (int)$company_id, $snapshots, $syncError)
                : false;

            if ($deleteOk && $syncOk) {
                mysqli_commit($conn);
            } else {
                mysqli_rollback($conn);
                $_SESSION['crud_error'] = $deleteOk
                    ? ($syncError !== null && $syncError !== '' ? $syncError : 'Delete sync failed.')
                    : itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
            }
        } else {
            $_SESSION['crud_error'] = 'No records selected for deletion.';
        }
        header('Location: ' . $listUrl);
        exit;
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $snapshot = cr_switch_port_fetch_snapshot_by_id($conn, (int)$company_id, $id);

        mysqli_begin_transaction($conn);
        $where = ' WHERE id=' . $id;
        if ($hasCompany && $company_id > 0) {
            $where .= ' AND company_id=' . (int)$company_id;
        }
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1';
        $deleteOk = itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage);
        $syncOk = $deleteOk
            ? cr_switch_port_delete_synced_snapshots($conn, (int)$company_id, $snapshot ? [$snapshot] : [], $syncError)
            : false;

        if ($deleteOk && $syncOk) {
            mysqli_commit($conn);
        } else {
            mysqli_rollback($conn);
            $_SESSION['crud_error'] = $deleteOk
                ? ($syncError !== null && $syncError !== '' ? $syncError : 'Delete sync failed.')
                : itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
    }
    header('Location: ' . $listUrl);
    exit;
}

$errors = [];
if (!empty($_SESSION['crud_error'])) {
    $errors[] = (string)$_SESSION['crud_error'];
    unset($_SESSION['crud_error']);
}
$data = [];
foreach ($fieldColumns as $col) {
    $data[$col['Field']] = '';
}

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (in_array($crud_action, ['edit', 'view'], true) && $editId > 0) {
    $where = ' WHERE id=' . $editId;
    if ($hasCompany && $company_id > 0) {
        $where .= ' AND company_id=' . (int)$company_id;
    }
    $q = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1');
    $data = ($q && mysqli_num_rows($q) === 1) ? mysqli_fetch_assoc($q) : [];
    if (!$data) {
        $errors[] = 'Record not found.';
    }
}


// Handle sample data seeding for empty companies in list view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && isset($_POST['add_sample_data'])) {
    cr_require_valid_csrf_token();

    if (!$hasCompany || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Sample data requires an active company.';
        header('Location: ' . $listUrl);
        exit;
    }

    $where = ' WHERE company_id=' . (int)$company_id;
    $countSql = 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where;
    $countResult = mysqli_query($conn, $countSql);
    $existingRows = 0;
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $existingRows = (int)($countRow['total_rows'] ?? 0);
    }

    if ($existingRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when no records exist.';
        header('Location: ' . $listUrl);
        exit;
    }

    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, $crud_table, (int)$company_id, $seedError);
    if ($insertedRows <= 0 && $seedError !== '') {
        $_SESSION['crud_error'] = $seedError;
    } elseif ($insertedRows > 0) {
        $syncError = null;
        $snapshots = cr_switch_port_fetch_snapshots_for_company($conn, (int)$company_id);
        foreach ($snapshots as $snapshot) {
            if (!cr_switch_port_sync_snapshot($conn, (int)$company_id, $snapshot, $syncError)) {
                $_SESSION['crud_error'] = $syncError !== null && $syncError !== '' ? $syncError : 'Sample data sync failed.';
                break;
            }
        }
    }

    header('Location: ' . $listUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    cr_require_valid_csrf_token();

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');
        if ($isTinyInt) {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            $sqlValues[$name] = (string) (int) $data[$name];
            continue;
        }

        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int)$company_id;
            $sqlValues[$name] = (string) (int) $company_id;
            continue;
        }

        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
        if ($name === 'to_location_id') {
            $submittedPortType = strtoupper(trim((string)($_POST['port_type'] ?? '')));
            if ($submittedPortType !== 'RJ45') {
                $data[$name] = 'NULL';
                continue;
            }
        }
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = '';
                $sqlValues[$name] = 'NULL';
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];
                $newValueEsc = mysqli_real_escape_string($conn, $newValueRaw);

                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "='" . $newValueEsc . "'";
                if (in_array('company_id', $available, true) && $company_id > 0) {
                    $findSql .= ' AND company_id=' . (int)$company_id;
                }
                $findSql .= ' LIMIT 1';
                $existing = mysqli_query($conn, $findSql);
                if ($existing && mysqli_num_rows($existing) > 0) {
                    $row = mysqli_fetch_assoc($existing);
                    $data[$name] = (string) (int) $row['id'];
                    $sqlValues[$name] = (string) (int) $row['id'];
                } else {
                    $insertFields = [cr_escape_identifier($labelCol)];
                    $insertValues = ["'" . $newValueEsc . "'"];
                    if (in_array('company_id', $available, true) && $company_id > 0) {
                        $insertFields[] = '`company_id`';
                        $insertValues[] = (string)(int)$company_id;
                    }
                    $insertSql = 'INSERT INTO ' . cr_escape_identifier($fkTable)
                        . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertValues) . ')';
                    $dbErrorCode = 0;
                    $dbErrorMessage = '';
                    if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                        $resolvedId = (string) (int) mysqli_insert_id($conn);
                        $data[$name] = $resolvedId;
                        $sqlValues[$name] = $resolvedId;
                    } else {
                        $errors[] = 'Could not add related value for ' . $name . '. ' . itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                        $data[$name] = '';
                        $sqlValues[$name] = 'NULL';
                    }
                }
                continue;
            }
        }

        $value = $_POST[$name] ?? null;
        if ($name === 'to_location_id') {
            $submittedPortType = strtoupper(trim((string)($_POST['port_type'] ?? '')));
            if ($submittedPortType !== 'RJ45') {
                $data[$name] = 'NULL';
                continue;
            }
        }
        if ($value === '' || $value === null) {
            $data[$name] = '';
            $sqlValues[$name] = 'NULL';
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $normalizedNumeric = null;
            $numericError = '';
            if (!cr_validate_numeric_value($value, $col, $name, $normalizedNumeric, $numericError)) {
                $errors[] = $numericError;
                $data[$name] = '';
                $sqlValues[$name] = 'NULL';
            } else {
                $data[$name] = $normalizedNumeric;
                $sqlValues[$name] = $normalizedNumeric;
            }
        } else {
            $data[$name] = (string) $value;
            $sqlValues[$name] = "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }

    if (empty($errors)) {
        cr_switch_port_fill_hostname_from_equipment($conn, $data, $company_id);
        cr_switch_port_fill_idf_from_equipment($conn, $data, $company_id);

        mysqli_begin_transaction($conn);
        $syncError = null;
        $savedSwitchPortId = 0;

        if ($crud_action === 'create') {
            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $sqlValues[$name] ?? 'NULL';
            }
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
        } else {
            $sets = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $sets[] = cr_escape_identifier($name) . '=' . ($sqlValues[$name] ?? 'NULL');
            }
            $where = ' WHERE id=' . $editId;
            if ($hasCompany && $company_id > 0) {
                $where .= ' AND company_id=' . (int)$company_id;
            }
            $sql = 'UPDATE ' . cr_escape_identifier($crud_table) . ' SET ' . implode(',', $sets) . $where . ' LIMIT 1';
        }

        $dbErrorCode = 0;
        $dbErrorMessage = '';
        if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
            $savedSwitchPortId = $crud_action === 'create' ? (int)mysqli_insert_id($conn) : (int)$editId;
            $snapshot = $savedSwitchPortId > 0
                ? cr_switch_port_fetch_snapshot_by_id($conn, (int)$company_id, $savedSwitchPortId)
                : null;
            $syncOk = $snapshot
                ? cr_switch_port_sync_snapshot($conn, (int)$company_id, $snapshot, $syncError)
                : true;

            if ($syncOk) {
                mysqli_commit($conn);
                header('Location: ' . $listUrl);
                exit;
            }

            mysqli_rollback($conn);
            $errors[] = $syncError !== null && $syncError !== '' ? $syncError : 'Save sync failed.';
        } else {
            mysqli_rollback($conn);
            $errors[] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
    }
}

$where = '';
if ($hasCompany && $company_id > 0) {
    $where = ' WHERE company_id=' . (int)$company_id;
}
$searchRaw = trim((string)($_GET['search'] ?? ''));
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchParts = [];
    foreach ($visibleFieldColumns as $col) {
        $searchParts[] = 'CAST(' . cr_escape_identifier($col['Field']) . " AS CHAR) LIKE '" . $searchEsc . "'";
    }
    if (!empty($searchParts)) {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . '(' . implode(' OR ', $searchParts) . ')';
    }
}
$sortableColumns = array_map(static function ($col) {
    return $col['Field'];
}, $fieldColumns);

$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = cr_escape_identifier($sort) . ' ' . $dir;

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where);
$totalRows = 0;
if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
    $totalRows = (int)($countRow['total_rows'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$showBulkActions = ($totalRows >= $perPage);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$rows = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' ORDER BY ' . $sortSql . ' LIMIT ' . $offset . ', ' . $perPage);
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($crud_title); ?> Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>
            <?php if ($showBulkActions): ?>
            <div class="card" style="margin-bottom:16px;">
                <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                    <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                </form>
            </div>
            <?php endif; ?>

                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                        <input type="hidden" name="page" value="1">
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="moduleSearch">Search (all fields)</label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                </div>
                <div class="card" style="overflow:auto;">
                    <table data-itm-db-import-endpoint="index.php">
                        <thead>

                           
                        <tr>
                            <?php foreach ($visibleFieldColumns as $col): ?>
                                <?php $field = (string)$col['Field']; ?>
                                <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize(cr_humanize_field($field)); ?>
                                        <?php if ($sort === $field): ?>
                                            <?php echo $dir === 'ASC' ? '▲' : '▼'; ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <?php if ($field === 'port_number'): ?>
                                    <th>Rack</th>
                                <?php endif; ?>
                            <?php endforeach; ?>
                                <th style="display:none;">
                                    <input type="checkbox" id="select-all-rows" aria-label="Select all rows">
                                </th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <?php foreach ($visibleFieldColumns as $col): $f = $col['Field']; ?>
                                    <td>
                                        <?php echo cr_render_cell_value($crud_table, $f, $row[$f] ?? ''); ?>
                                    </td>
                                    <?php if ($f === 'port_number'): ?>
                                        <td><?php echo sanitize(cr_switch_port_rack_label($row['equipment_id'] ?? 0)); ?></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <td style="display:none;">
                                    <input
                                        type="checkbox"
                                        name="ids[]"
                                        value="<?php echo (int)$row['id']; ?>"
                                        form="bulk-delete-form"
                                        aria-label="Select row <?php echo (int)$row['id']; ?> for deletion"
                                    >
                                </td>

                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="bulk_action" value="single_delete">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?php echo count($visibleFieldColumns) + ($hasRackDisplayColumn ? 1 : 0) + 2; ?>" style="text-align:center;">No records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($hasCompany && $company_id > 0 && $totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="◀️ Previous">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="▶️ Next">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <h1><?php echo $crud_action === 'create' ? 'New ' : 'Edit '; ?><?php echo sanitize($crud_title); ?></h1>
                <form method="POST" class="form-grid" style="max-width:980px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <?php foreach ($visibleFieldColumns as $col): $name = $col['Field'];
                        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');
                        $isDate = str_starts_with($col['Type'], 'date');
                        $isDateTime = str_starts_with($col['Type'], 'datetime');
                        $isText = str_contains($col['Type'], 'text');
                        $displayVal = cr_form_display_value($data[$name] ?? '');
                    ?>
                        <div class="form-group<?php echo ($name === 'to_location_id') ? ' js-to-location-field' : ''; ?>"<?php echo ($name === 'to_location_id') ? ' data-to-location-wrapper="1"' : ''; ?>>
                            <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                            <?php if ($name === 'company_id' && $company_id > 0): ?>
                                <input type="hidden" name="company_id" value="<?php echo (int)$company_id; ?>">
                            <?php elseif ($isTinyInt): ?>
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="<?php echo sanitize($name); ?>" value="1" <?php echo ((int)$displayVal === 1) ? 'checked' : ''; ?>>
                                    <span><?php echo sanitize(cr_humanize_field($name)); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)$displayVal === 1) ? '✅' : '❌'; ?></span></span>
                                </label>
                            <?php elseif (isset($fkMap[$name])): ?>
                                <?php
                                    $opts = cr_fk_options($conn, $fkMap[$name], (int)$company_id);
                                    $fkMeta = cr_fk_metadata($conn, $fkMap[$name]['REFERENCED_TABLE_NAME']);
                                    $isCompanyScoped = in_array('company_id', $fkMeta['available'], true) ? 1 : 0;
                                ?>
                                <?php
                                    $addExtraFieldsJson = '';
                                    if ($name === 'rack_id') {
                                        $addExtraFieldsJson = htmlspecialchars(json_encode([
                                            ['name' => 'status_id', 'label' => 'Status', 'type' => 'hidden', 'value' => 'Active'],
                                        ]), ENT_QUOTES, 'UTF-8');
                                    }
                                ?>
                                <select
                                    name="<?php echo sanitize($name); ?>"
                                    data-addable-select="1"
                                    data-add-table="<?php echo sanitize($fkMap[$name]['REFERENCED_TABLE_NAME']); ?>"
                                    data-add-id-col="<?php echo sanitize($fkMap[$name]['REFERENCED_COLUMN_NAME']); ?>"
                                    data-add-label-col="<?php echo sanitize($fkMeta['label_col']); ?>"
                                    data-add-company-scoped="<?php echo $isCompanyScoped; ?>"
                                    data-add-friendly="<?php echo sanitize(strtolower(cr_humanize_field($name))); ?>"
                                    <?php if ($addExtraFieldsJson !== ''): ?>data-add-extra-fields='<?php echo $addExtraFieldsJson; ?>'<?php endif; ?>
                                >
                                    <option value="">-- Select --</option>
                                    <?php foreach ($opts as $opt): ?>
                                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((string)$displayVal === (string)$opt['id']) ? 'selected' : ''; ?>><?php echo sanitize($opt['label']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">➕</option>
                                </select>
                            <?php elseif ($isDateTime): ?>
                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
                            <?php elseif ($isDate): ?>
                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
                            <?php elseif ($isText): ?>
                                <textarea name="<?php echo sanitize($name); ?>" rows="4"><?php echo sanitize($displayVal); ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>

            <?php elseif ($crud_action === 'view'): ?>
                <h1>View <?php echo sanitize($crud_title); ?></h1>
                <div class="card">
                    <table>
                        <tbody>
                        <?php foreach ($visibleFieldColumns as $col): $f = $col['Field']; ?>
                            <tr>
                                <th style="width:240px;"><?php echo sanitize(cr_humanize_field($f)); ?></th>
                                <td><?php echo cr_render_cell_value($crud_table, $f, $data[$f] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;"><a href="index.php" class="btn">🔙</a> <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>">✏️</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script>
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
</script>
<script src="../../js/select-add-option.js"></script>

<script>
(function () {
    const portTypeSelect = document.querySelector('select[name="port_type"]');
    const toLocationWrapper = document.querySelector('[data-to-location-wrapper="1"]');
    const toLocationSelect = toLocationWrapper ? toLocationWrapper.querySelector('select[name="to_location_id"]') : null;

    function toggleToLocationField() {
        if (!toLocationWrapper || !portTypeSelect) return;
        const isRj45 = String(portTypeSelect.value || '').toUpperCase() === 'RJ45';
        toLocationWrapper.style.display = isRj45 ? '' : 'none';
        if (!isRj45 && toLocationSelect) {
            toLocationSelect.value = '';
        }
    }

    if (portTypeSelect && toLocationWrapper) {
        portTypeSelect.addEventListener('change', toggleToLocationField);
        toggleToLocationField();
    }
})();

document.addEventListener('click', function (event) {
    const link = event.target.closest('a[data-outlook-link="1"]');
    if (!link) return;
    const outlookHref = link.getAttribute('data-outlook-href');
    if (outlookHref) {
        window.location.href = outlookHref;
    }
});

document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) {
        indicator.textContent = event.target.checked ? '✅' : '❌';
    }
});
</script>

</body>
</html>
