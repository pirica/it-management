<?php
/**
 * Room Types FK helpers — upgrade target shows booking_rooms_types.code (not name).
 */
require_once ROOT_PATH . 'includes/fk_dropdown_helpers.php';

if (!function_exists('brt_fk_label_column_for_field')) {
    function brt_fk_label_column_for_field($fieldName, mysqli $conn, string $refTable): string
    {
        $available = itm_fk_table_column_names($conn, $refTable);
        if ($fieldName === 'upgrade_to_room_type_id' && in_array('code', $available, true)) {
            return 'code';
        }
        if ($fieldName === 'connecting_room_type_id' && in_array('code', $available, true)) {
            return 'code';
        }

        return itm_fk_label_column_for_table($available);
    }
}

if (!function_exists('brt_fk_label_by_id')) {
    function brt_fk_label_by_id(mysqli $conn, array $fk, int $companyId, int $rawId, string $fieldName = ''): string
    {
        $id = (int) $rawId;
        if ($id <= 0) {
            return '';
        }

        $refTable = (string) ($fk['REFERENCED_TABLE_NAME'] ?? '');
        $refColumn = (string) ($fk['REFERENCED_COLUMN_NAME'] ?? 'id');
        if ($refTable === '' || !itm_is_safe_identifier($refTable) || !itm_is_safe_identifier($refColumn)) {
            return '';
        }

        $labelCol = brt_fk_label_column_for_field($fieldName, $conn, $refTable);
        if (!itm_is_safe_identifier($labelCol)) {
            return '';
        }

        if ($companyId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT `' . $labelCol . '` AS label FROM `' . $refTable . '` WHERE `' . $refColumn . '`=? AND company_id=? LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = ($res) ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if (is_array($row) && isset($row['label'])) {
                    return (string) $row['label'];
                }
            }
        }

        $fallback = mysqli_query(
            $conn,
            'SELECT `' . $labelCol . '` AS label FROM `' . $refTable . '` WHERE `' . $refColumn . '`=' . $id . ' LIMIT 1'
        );
        $fallbackRow = ($fallback) ? mysqli_fetch_assoc($fallback) : null;
        if (is_array($fallbackRow) && isset($fallbackRow['label'])) {
            return (string) $fallbackRow['label'];
        }

        return '';
    }
}

if (!function_exists('brt_fk_options')) {
    function brt_fk_options(mysqli $conn, array $fk, int $companyId, string $fieldName = ''): array
    {
        $table = $fk['REFERENCED_TABLE_NAME'];
        $col = $fk['REFERENCED_COLUMN_NAME'];
        if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($col)) {
            return [];
        }

        $labelCol = brt_fk_label_column_for_field($fieldName, $conn, (string) $table);
        $available = itm_fk_table_column_names($conn, (string) $table);

        $where = '';
        if (in_array('company_id', $available, true) && $companyId > 0) {
            $where = ' WHERE company_id=' . (int) $companyId;
        }

        $sql = 'SELECT `' . $col . '` AS id, `' . $labelCol . '` AS label FROM `' . $table . '`' . $where . ' ORDER BY label';
        $rows = [];
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }

        return $rows;
    }
}
