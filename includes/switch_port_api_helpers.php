<?php
/**
 * Shared helpers for switch port AJAX endpoints (get_ports.php, update_port.php).
 *
 * Why: Both endpoints are loaded during PHPUnit coverage; shared functions must live
 * in one file to avoid "Cannot redeclare" fatals (PHP registers functions at compile time).
 */

if (!function_exists('itm_mysqli_stmt_fetch_all_assoc')) {
    require_once __DIR__ . '/itm_role_module_permissions.php';
}

if (!function_exists('fetch_lookup_map')) {
    function fetch_lookup_map(mysqli $conn, string $table, string $labelColumn): array
    {
        $rows = [];
        if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($labelColumn)) {
            return $rows;
        }

        $hasCompanyId = itm_table_has_column($conn, $table, 'company_id');
        $companyId = isset($GLOBALS['company_id']) ? (int)$GLOBALS['company_id'] : 0;

        if ($hasCompanyId && $companyId > 0) {
            $sql = "SELECT id, `{$labelColumn}` AS label";
            if ($table === 'cable_colors' && itm_table_has_column($conn, $table, 'hex_color')) {
                $sql .= ", `hex_color`";
            }
            $sql .= " FROM `{$table}` WHERE company_id = ? ORDER BY id ASC";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $companyId);
                mysqli_stmt_execute($stmt);
                $stmtRows = itm_mysqli_stmt_fetch_all_assoc($stmt);
                mysqli_stmt_close($stmt);
                foreach ($stmtRows as $row) {
                    $entry = ['id' => (int)$row['id'], 'name' => (string)$row['label']];
                    if ($table === 'cable_colors' && array_key_exists('hex_color', $row)) {
                        $entry['hex_color'] = (string)($row['hex_color'] ?? '');
                    }
                    $rows[] = $entry;
                }
            }
        }

        if ($rows === []) {
            $sql = "SELECT id, `{$labelColumn}` AS label";
            if ($table === 'cable_colors' && itm_table_has_column($conn, $table, 'hex_color')) {
                $sql .= ", `hex_color`";
            }
            $sql .= " FROM `{$table}` ORDER BY id ASC";
            $res = mysqli_query($conn, $sql);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $entry = ['id' => (int)$row['id'], 'name' => (string)$row['label']];
                if ($table === 'cable_colors' && array_key_exists('hex_color', $row)) {
                    $entry['hex_color'] = (string)($row['hex_color'] ?? '');
                }
                $rows[] = $entry;
            }
        }

        return $rows;
    }
}

if (!function_exists('fetch_company_vlans')) {
    function fetch_company_vlans(mysqli $conn, int $companyId): array
    {
        $rows = [];
        $sql = 'SELECT id, vlan_name, vlan_color FROM vlans WHERE company_id = ? ORDER BY vlan_number ASC, id ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        if (mysqli_stmt_execute($stmt)) {
            foreach (itm_mysqli_stmt_fetch_all_assoc($stmt) as $row) {
                $rows[] = [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['vlan_name'],
                    'color' => (string)($row['vlan_color'] ?? ''),
                ];
            }
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('find_lookup_id')) {
    function find_lookup_id(array $rows, $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_numeric($value)) {
            $id = (int)$value;
            foreach ($rows as $row) {
                if ((int)$row['id'] === $id) {
                    return $id;
                }
            }

            return 0;
        }
        $wanted = strtolower(trim((string)$value));
        foreach ($rows as $row) {
            if (strtolower(trim((string)$row['name'])) === $wanted) {
                return (int)$row['id'];
            }
        }

        return 0;
    }
}
