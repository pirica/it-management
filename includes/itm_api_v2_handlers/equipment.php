<?php
/**
 * API v2 equipment resource handlers.
 */

if (!function_exists('itm_api_v2_equipment_fk_label')) {
    function itm_api_v2_equipment_fk_label($conn, $companyId, $table, $id, $labelColumn = 'name')
    {
        if (!($conn instanceof mysqli) || !itm_is_safe_identifier($table) || !itm_is_safe_identifier($labelColumn)) {
            return null;
        }

        $id = (int)$id;
        $companyId = (int)$companyId;
        if ($id <= 0) {
            return null;
        }

        $sql = 'SELECT `' . $labelColumn . '` AS label FROM `' . $table . '` WHERE id = ? AND company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
        mysqli_stmt_execute($stmt);
        $row = function_exists('itm_mysqli_stmt_fetch_assoc') ? itm_mysqli_stmt_fetch_assoc($stmt) : null;
        if ($row === null && function_exists('mysqli_stmt_get_result')) {
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
        }
        mysqli_stmt_close($stmt);

        return is_array($row) ? (string)($row['label'] ?? '') : null;
    }
}

if (!function_exists('itm_api_v2_equipment_format_row')) {
    function itm_api_v2_equipment_format_row($conn, $companyId, array $row)
    {
        $typeId = (int)($row['equipment_type_id'] ?? 0);
        $statusId = (int)($row['status_id'] ?? 0);
        $manufacturerId = (int)($row['manufacturer_id'] ?? 0);

        return [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'hostname' => (string)($row['hostname'] ?? ''),
            'serial_number' => (string)($row['serial_number'] ?? ''),
            'model' => (string)($row['model'] ?? ''),
            'ip_address' => (string)($row['ip_address'] ?? ''),
            'equipment_type_id' => $typeId,
            'equipment_type' => $typeId > 0 ? itm_api_v2_equipment_fk_label($conn, $companyId, 'equipment_types', $typeId) : null,
            'status_id' => $statusId,
            'status' => $statusId > 0 ? itm_api_v2_equipment_fk_label($conn, $companyId, 'equipment_statuses', $statusId) : null,
            'manufacturer_id' => $manufacturerId > 0 ? $manufacturerId : null,
            'manufacturer' => $manufacturerId > 0 ? itm_api_v2_equipment_fk_label($conn, $companyId, 'manufacturers', $manufacturerId) : null,
            'purchase_date' => (string)($row['purchase_date'] ?? ''),
            'purchase_cost' => $row['purchase_cost'] ?? null,
            'warranty_expiry' => (string)($row['warranty_expiry'] ?? ''),
            'eol_date' => (string)($row['eol_date'] ?? ''),
            'extended_date' => (string)($row['extended_date'] ?? ''),
            'esu_date' => (string)($row['esu_date'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('itm_api_v2_equipment_list')) {
    function itm_api_v2_equipment_list($conn, $companyId, array $query)
    {
        $companyId = (int)$companyId;
        $limit = isset($query['limit']) ? (int)$query['limit'] : 50;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $search = trim((string)($query['search'] ?? ''));
        $sql = 'SELECT id, name, hostname, serial_number, model, ip_address, equipment_type_id, status_id,
                       manufacturer_id, purchase_date, purchase_cost, warranty_expiry, eol_date, extended_date, esu_date, created_at, updated_at
                FROM equipment
                WHERE company_id = ? AND deleted_at IS NULL';
        $types = 'i';
        $params = [$companyId];

        if ($search !== '') {
            $sql .= ' AND (name LIKE ? OR hostname LIKE ? OR serial_number LIKE ? OR model LIKE ?)';
            $like = '%' . $search . '%';
            $types .= 'ssss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . (int)$limit;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            itm_api_v2_error(500, 'Unable to list equipment.');
        }

        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $rows = function_exists('itm_mysqli_stmt_fetch_all_assoc') ? itm_mysqli_stmt_fetch_all_assoc($stmt) : [];
        if ($rows === [] && function_exists('mysqli_stmt_get_result')) {
            $result = mysqli_stmt_get_result($stmt);
            if ($result instanceof mysqli_result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            }
        }
        mysqli_stmt_close($stmt);

        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = itm_api_v2_equipment_format_row($conn, $companyId, $row);
            }
        }

        return ['items' => $items, 'count' => count($items)];
    }
}

if (!function_exists('itm_api_v2_equipment_get')) {
    function itm_api_v2_equipment_get($conn, $companyId, $equipmentId)
    {
        $companyId = (int)$companyId;
        $equipmentId = (int)$equipmentId;
        $sql = 'SELECT id, name, hostname, serial_number, model, ip_address, equipment_type_id, status_id,
                       manufacturer_id, purchase_date, purchase_cost, warranty_expiry, eol_date, extended_date, esu_date, created_at, updated_at
                FROM equipment WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            itm_api_v2_error(500, 'Unable to load equipment.');
        }

        mysqli_stmt_bind_param($stmt, 'ii', $equipmentId, $companyId);
        mysqli_stmt_execute($stmt);
        $row = function_exists('itm_mysqli_stmt_fetch_assoc') ? itm_mysqli_stmt_fetch_assoc($stmt) : null;
        if ($row === null && function_exists('mysqli_stmt_get_result')) {
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
        }
        mysqli_stmt_close($stmt);

        if (!is_array($row)) {
            itm_api_v2_error(404, 'Equipment not found.');
        }

        return ['item' => itm_api_v2_equipment_format_row($conn, $companyId, $row)];
    }
}

if (!function_exists('itm_api_v2_equipment_create')) {
    function itm_api_v2_equipment_create($conn, $companyId, $employeeId, array $body)
    {
        $name = trim((string)($body['name'] ?? ''));
        $equipmentTypeId = (int)($body['equipment_type_id'] ?? 0);
        $statusId = (int)($body['status_id'] ?? 0);
        if ($name === '') {
            itm_api_v2_error(422, 'name is required.');
        }
        if ($equipmentTypeId <= 0) {
            itm_api_v2_error(422, 'equipment_type_id is required.');
        }
        if ($statusId <= 0) {
            itm_api_v2_error(422, 'status_id is required.');
        }

        $hostname = trim((string)($body['hostname'] ?? ''));
        $serialNumber = trim((string)($body['serial_number'] ?? ''));
        $model = trim((string)($body['model'] ?? ''));
        $ipAddress = trim((string)($body['ip_address'] ?? ''));
        $manufacturerId = isset($body['manufacturer_id']) ? (int)$body['manufacturer_id'] : 0;
        $purchaseDate = trim((string)($body['purchase_date'] ?? ''));
        $purchaseCost = array_key_exists('purchase_cost', $body) ? (string)$body['purchase_cost'] : '';
        $warrantyExpiry = trim((string)($body['warranty_expiry'] ?? ''));

        $sql = 'INSERT INTO equipment
            (company_id, equipment_type_id, status_id, name, hostname, serial_number, model, ip_address,
             manufacturer_id, purchase_date, purchase_cost, warranty_expiry, active, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'), 1, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            itm_api_v2_error(500, 'Unable to create equipment.');
        }

        mysqli_stmt_bind_param(
            $stmt,
            'iiisssssissssii',
            $companyId,
            $equipmentTypeId,
            $statusId,
            $name,
            $hostname,
            $serialNumber,
            $model,
            $ipAddress,
            $manufacturerId,
            $purchaseDate,
            $purchaseCost,
            $warrantyExpiry,
            $employeeId,
            $employeeId
        );

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            itm_api_v2_error(500, 'Unable to create equipment.');
        }
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        return itm_api_v2_equipment_get($conn, $companyId, $newId)['item'];
    }
}

if (!function_exists('itm_api_v2_equipment_patch')) {
    function itm_api_v2_equipment_patch($conn, $companyId, $employeeId, $equipmentId, array $body)
    {
        $existing = itm_api_v2_equipment_get($conn, $companyId, $equipmentId);
        $item = is_array($existing['item'] ?? null) ? $existing['item'] : [];

        $name = array_key_exists('name', $body) ? trim((string)$body['name']) : (string)($item['name'] ?? '');
        $equipmentTypeId = array_key_exists('equipment_type_id', $body) ? (int)$body['equipment_type_id'] : (int)($item['equipment_type_id'] ?? 0);
        $statusId = array_key_exists('status_id', $body) ? (int)$body['status_id'] : (int)($item['status_id'] ?? 0);
        if ($name === '' || $equipmentTypeId <= 0 || $statusId <= 0) {
            itm_api_v2_error(422, 'name, equipment_type_id, and status_id are required.');
        }

        $hostname = array_key_exists('hostname', $body) ? trim((string)$body['hostname']) : (string)($item['hostname'] ?? '');
        $serialNumber = array_key_exists('serial_number', $body) ? trim((string)$body['serial_number']) : (string)($item['serial_number'] ?? '');
        $model = array_key_exists('model', $body) ? trim((string)$body['model']) : (string)($item['model'] ?? '');
        $ipAddress = array_key_exists('ip_address', $body) ? trim((string)$body['ip_address']) : (string)($item['ip_address'] ?? '');
        $manufacturerId = array_key_exists('manufacturer_id', $body) ? (int)$body['manufacturer_id'] : (int)($item['manufacturer_id'] ?? 0);
        $purchaseDate = array_key_exists('purchase_date', $body) ? trim((string)$body['purchase_date']) : (string)($item['purchase_date'] ?? '');
        $purchaseCost = array_key_exists('purchase_cost', $body) ? (string)$body['purchase_cost'] : (string)($item['purchase_cost'] ?? '');
        $warrantyExpiry = array_key_exists('warranty_expiry', $body) ? trim((string)$body['warranty_expiry']) : (string)($item['warranty_expiry'] ?? '');

        $sql = 'UPDATE equipment SET equipment_type_id = ?, status_id = ?, name = ?, hostname = ?, serial_number = ?,
                model = ?, ip_address = ?, manufacturer_id = NULLIF(?, 0), purchase_date = NULLIF(?, \'\'),
                purchase_cost = NULLIF(?, \'\'), warranty_expiry = NULLIF(?, \'\'), updated_by = ?
                WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            itm_api_v2_error(500, 'Unable to update equipment.');
        }

        mysqli_stmt_bind_param(
            $stmt,
            'iisssssissssiii',
            $equipmentTypeId,
            $statusId,
            $name,
            $hostname,
            $serialNumber,
            $model,
            $ipAddress,
            $manufacturerId,
            $purchaseDate,
            $purchaseCost,
            $warrantyExpiry,
            $employeeId,
            $equipmentId,
            $companyId
        );

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            itm_api_v2_error(500, 'Unable to update equipment.');
        }
        mysqli_stmt_close($stmt);

        return itm_api_v2_equipment_get($conn, $companyId, (int)$equipmentId)['item'];
    }
}
