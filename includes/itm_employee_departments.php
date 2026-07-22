<?php
/**
 * Employee department assignments (Explorer folder access and multi-select on employees forms).
 *
 * Why: employees.department_id remains the primary HR/org-chart department; employee_departments
 * stores one or more department grants per tenant for Explorer ACL.
 */

if (!function_exists('itm_employee_departments_table_exists')) {
    function itm_employee_departments_table_exists($conn)
    {
        if (!$conn) {
            return false;
        }
        $res = mysqli_query($conn, "SHOW TABLES LIKE 'employee_departments'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('itm_employee_normalize_department_ids')) {
    function itm_employee_normalize_department_ids($departmentIds)
    {
        if (!is_array($departmentIds)) {
            $departmentIds = $departmentIds === '' || $departmentIds === null ? [] : [(int)$departmentIds];
        }
        $normalized = [];
        foreach ($departmentIds as $departmentId) {
            if (is_string($departmentId) && $departmentId === '__add_new__') {
                continue;
            }
            $departmentId = (int)$departmentId;
            if ($departmentId > 0) {
                $normalized[$departmentId] = $departmentId;
            }
        }

        return array_values($normalized);
    }
}

if (!function_exists('itm_employee_allowed_department_ids')) {
    function itm_employee_allowed_department_ids($conn, $company_id, $employee_id)
    {
        $company_id = (int)$company_id;
        $employee_id = (int)$employee_id;
        if ($company_id <= 0 || $employee_id <= 0 || !$conn) {
            return [];
        }

        $ids = [];

        $primaryStmt = mysqli_prepare(
            $conn,
            'SELECT department_id FROM employees WHERE id = ? AND company_id = ? LIMIT 1'
        );
        if ($primaryStmt) {
            mysqli_stmt_bind_param($primaryStmt, 'ii', $employee_id, $company_id);
            mysqli_stmt_execute($primaryStmt);
            $primaryRes = mysqli_stmt_get_result($primaryStmt);
            if ($primaryRes && ($primaryRow = mysqli_fetch_assoc($primaryRes))) {
                $primaryId = (int)($primaryRow['department_id'] ?? 0);
                if ($primaryId > 0) {
                    $ids[$primaryId] = $primaryId;
                }
            }
            mysqli_stmt_close($primaryStmt);
        }

        if (itm_employee_departments_table_exists($conn)) {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT ed.department_id
                 FROM employee_departments ed
                 INNER JOIN departments d ON d.id = ed.department_id AND d.company_id = ed.company_id
                 WHERE ed.company_id = ?
                   AND ed.employee_id = ?
                   AND ed.active = 1
                   AND ed.deleted_at IS NULL
                   AND d.active = 1
                   AND d.deleted_at IS NULL'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $company_id, $employee_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $departmentId = (int)($row['department_id'] ?? 0);
                    if ($departmentId > 0) {
                        $ids[$departmentId] = $departmentId;
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }

        return array_values($ids);
    }
}

if (!function_exists('itm_employee_allowed_department_codes')) {
    function itm_employee_allowed_department_codes($conn, $company_id, $employee_id)
    {
        $company_id = (int)$company_id;
        $employee_id = (int)$employee_id;
        $departmentIds = itm_employee_allowed_department_ids($conn, $company_id, $employee_id);
        if ($departmentIds === [] || !$conn) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
        $types = str_repeat('i', count($departmentIds) + 1);
        $sql = 'SELECT code FROM departments WHERE company_id = ? AND id IN (' . $placeholders . ') AND active = 1 AND deleted_at IS NULL';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }

        $params = array_merge([$company_id], $departmentIds);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        $codes = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $code = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', trim((string)($row['code'] ?? '')));
            if ($code !== '') {
                $codes[$code] = $code;
            }
        }
        mysqli_stmt_close($stmt);

        return array_values($codes);
    }
}

if (!function_exists('itm_employee_sync_department_assignments')) {
    function itm_employee_sync_department_assignments($conn, $company_id, $employee_id, $departmentIds, $actor_id)
    {
        $company_id = (int)$company_id;
        $employee_id = (int)$employee_id;
        $actor_id = (int)$actor_id;
        $departmentIds = itm_employee_normalize_department_ids($departmentIds);

        if ($company_id <= 0 || $employee_id <= 0 || !$conn || !itm_employee_departments_table_exists($conn)) {
            return false;
        }

        $validIds = [];
        if ($departmentIds !== []) {
            $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
            $types = str_repeat('i', count($departmentIds) + 1);
            $sql = 'SELECT id FROM departments WHERE company_id = ? AND id IN (' . $placeholders . ') AND active = 1 AND deleted_at IS NULL';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                $params = array_merge([$company_id], $departmentIds);
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $validIds[] = (int)$row['id'];
                }
                mysqli_stmt_close($stmt);
            }
        }

        $softDeleteStmt = mysqli_prepare(
            $conn,
            'UPDATE employee_departments
             SET active = 0, deleted_by = ?, deleted_at = NOW(), updated_by = ?, updated_at = NOW()
             WHERE company_id = ? AND employee_id = ? AND active = 1 AND deleted_at IS NULL'
        );
        if ($softDeleteStmt) {
            mysqli_stmt_bind_param($softDeleteStmt, 'iiii', $actor_id, $actor_id, $company_id, $employee_id);
            mysqli_stmt_execute($softDeleteStmt);
            mysqli_stmt_close($softDeleteStmt);
        }

        foreach ($validIds as $departmentId) {
            $upsertStmt = mysqli_prepare(
                $conn,
                'INSERT INTO employee_departments (company_id, employee_id, department_id, active, created_by, updated_by)
                 VALUES (?, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE active = 1, deleted_by = NULL, deleted_at = NULL, updated_by = ?, updated_at = NOW()'
            );
            if ($upsertStmt) {
                mysqli_stmt_bind_param($upsertStmt, 'iiiiii', $company_id, $employee_id, $departmentId, $actor_id, $actor_id, $actor_id);
                mysqli_stmt_execute($upsertStmt);
                mysqli_stmt_close($upsertStmt);
            }
        }

        return true;
    }
}
