<?php
/**
 * Tenant-scoped VLAN lookup defaults for create forms and inserts.
 */

/**
 * Resolves a company lookup row id by unique name (live rows only).
 */
function itm_vlan_lookup_id_by_name($conn, $table, $companyId, $name)
{
    if (!$conn instanceof mysqli || $companyId <= 0 || $name === '') {
        return 0;
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$table)) {
        return 0;
    }

    $tableEsc = mysqli_real_escape_string($conn, (string)$table);
    $nameEsc = mysqli_real_escape_string($conn, (string)$name);
    $sql = 'SELECT id FROM `' . $tableEsc . '` WHERE company_id = ' . (int)$companyId
        . ' AND name = \'' . $nameEsc . '\' AND deleted_at IS NULL LIMIT 1';
    $res = mysqli_query($conn, $sql);
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return (int)($row['id'] ?? 0);
    }

    return 0;
}

/**
 * Default FK map for modules/vlans create.
 */
function itm_vlans_default_lookup_field_map()
{
    return [
        'config_id' => ['table' => 'vlans_config', 'name' => 'Manual'],
        'uplink_id' => ['table' => 'vlans_uplink', 'name' => 'Any'],
        'group_policy_id' => ['table' => 'vlans_group_policy', 'name' => 'None'],
        'vpn_mode_id' => ['table' => 'vlans_vpn_mode', 'name' => 'Enabled'],
    ];
}

/**
 * Default FK map for modules/vlans_per_port create.
 */
function itm_vlans_per_port_default_lookup_field_map()
{
    return [
        'module_id' => ['table' => 'vlans_per_port_modules', 'name' => 'Built-in'],
        'type_id' => ['table' => 'vlans_per_port_types', 'name' => 'Access'],
        'access_policy_id' => ['table' => 'vlans_per_port_access_policies', 'name' => 'Open'],
    ];
}

/**
 * Pre-fills create form $data for VLAN lookup FKs when empty.
 */
function itm_vlans_apply_create_form_defaults($conn, $companyId, &$data)
{
    if (!$conn instanceof mysqli || $companyId <= 0 || !is_array($data)) {
        return;
    }

    foreach (itm_vlans_default_lookup_field_map() as $field => $spec) {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            $resolvedId = itm_vlan_lookup_id_by_name($conn, $spec['table'], $companyId, $spec['name']);
            if ($resolvedId > 0) {
                $data[$field] = (string)$resolvedId;
            }
        }
    }
}

/**
 * Applies VLAN lookup defaults to SQL value map before INSERT when still NULL.
 */
function itm_vlans_apply_create_sql_defaults($conn, $companyId, &$data, &$sqlValues)
{
    if (!$conn instanceof mysqli || $companyId <= 0 || !is_array($data) || !is_array($sqlValues)) {
        return;
    }

    foreach (itm_vlans_default_lookup_field_map() as $field => $spec) {
        $currentSql = $sqlValues[$field] ?? 'NULL';
        if ($currentSql === 'NULL' || $currentSql === '' || $currentSql === null) {
            $resolvedId = itm_vlan_lookup_id_by_name($conn, $spec['table'], $companyId, $spec['name']);
            if ($resolvedId > 0) {
                $data[$field] = (string)$resolvedId;
                $sqlValues[$field] = (string)$resolvedId;
            }
        }
    }
}

/**
 * Pre-fills create form $data for VLAN per-port lookup FKs when empty.
 */
function itm_vlans_per_port_apply_create_form_defaults($conn, $companyId, &$data)
{
    if (!$conn instanceof mysqli || $companyId <= 0 || !is_array($data)) {
        return;
    }

    foreach (itm_vlans_per_port_default_lookup_field_map() as $field => $spec) {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            $resolvedId = itm_vlan_lookup_id_by_name($conn, $spec['table'], $companyId, $spec['name']);
            if ($resolvedId > 0) {
                $data[$field] = (string)$resolvedId;
            }
        }
    }

    if (!array_key_exists('active', $data) || $data['active'] === '' || $data['active'] === null) {
        $data['active'] = '1';
    }
}

/**
 * Applies VLAN per-port lookup defaults to SQL value map before INSERT when still NULL.
 */
function itm_vlans_per_port_apply_create_sql_defaults($conn, $companyId, &$data, &$sqlValues)
{
    if (!$conn instanceof mysqli || $companyId <= 0 || !is_array($data) || !is_array($sqlValues)) {
        return;
    }

    foreach (itm_vlans_per_port_default_lookup_field_map() as $field => $spec) {
        $currentSql = $sqlValues[$field] ?? 'NULL';
        if ($currentSql === 'NULL' || $currentSql === '' || $currentSql === null) {
            $resolvedId = itm_vlan_lookup_id_by_name($conn, $spec['table'], $companyId, $spec['name']);
            if ($resolvedId > 0) {
                $data[$field] = (string)$resolvedId;
                $sqlValues[$field] = (string)$resolvedId;
            }
        }
    }
}
