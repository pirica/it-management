<?php
/**
 * Change request helpers — link blast-radius configuration items from CMDB impact graph.
 */

require_once __DIR__ . '/itm_cmdb.php';

if (!function_exists('itm_change_request_statuses')) {
    /**
     * @return array<string,string>
     */
    function itm_change_request_statuses(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'implemented' => 'Implemented',
            'cancelled' => 'Cancelled',
        ];
    }
}

if (!function_exists('itm_change_request_status_label')) {
    function itm_change_request_status_label(string $status): string
    {
        $map = itm_change_request_statuses();
        return $map[$status] ?? ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('itm_change_request_list_affected_ci_ids')) {
    /**
     * @return array<int,int>
     */
    function itm_change_request_list_affected_ci_ids(mysqli $conn, int $companyId, int $changeRequestId): array
    {
        if ($companyId <= 0 || $changeRequestId <= 0) {
            return [];
        }
        $ids = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT configuration_item_id FROM change_request_configuration_items
             WHERE company_id = ? AND change_request_id = ? AND deleted_at IS NULL AND active = 1'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $changeRequestId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $id = (int)($row['configuration_item_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        mysqli_stmt_close($stmt);
        return array_values($ids);
    }
}

if (!function_exists('itm_change_request_list_affected_rows')) {
    function itm_change_request_list_affected_rows(mysqli $conn, int $companyId, int $changeRequestId): array
    {
        if ($companyId <= 0 || $changeRequestId <= 0) {
            return [];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT ci.id, ci.name, cit.name AS ci_type_name, cit.icon AS ci_type_icon
             FROM change_request_configuration_items crci
             INNER JOIN configuration_items ci ON ci.id = crci.configuration_item_id AND ci.company_id = crci.company_id
             INNER JOIN configuration_item_types cit ON cit.id = ci.ci_type_id AND cit.company_id = ci.company_id
             WHERE crci.company_id = ? AND crci.change_request_id = ? AND crci.deleted_at IS NULL AND crci.active = 1
               AND ci.deleted_at IS NULL
             ORDER BY ci.name'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $changeRequestId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_change_request_replace_affected_cis')) {
    /**
     * Soft-delete prior links and upsert the selected blast-radius CI set.
     */
    function itm_change_request_replace_affected_cis(
        mysqli $conn,
        int $companyId,
        int $changeRequestId,
        array $configurationItemIds,
        int $employeeId = 0
    ): void {
        if ($companyId <= 0 || $changeRequestId <= 0) {
            return;
        }

        $normalized = [];
        foreach ($configurationItemIds as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        $soft = mysqli_prepare(
            $conn,
            'UPDATE change_request_configuration_items
             SET active = 0, deleted_by = ?, deleted_at = NOW()
             WHERE company_id = ? AND change_request_id = ? AND deleted_at IS NULL'
        );
        if ($soft) {
            mysqli_stmt_bind_param($soft, 'iii', $employeeId, $companyId, $changeRequestId);
            mysqli_stmt_execute($soft);
            mysqli_stmt_close($soft);
        }

        foreach ($normalized as $ciId) {
            $check = mysqli_prepare(
                $conn,
                'SELECT id FROM change_request_configuration_items
                 WHERE company_id = ? AND change_request_id = ? AND configuration_item_id = ?
                 LIMIT 1'
            );
            if (!$check) {
                continue;
            }
            mysqli_stmt_bind_param($check, 'iii', $companyId, $changeRequestId, $ciId);
            mysqli_stmt_execute($check);
            $cRes = mysqli_stmt_get_result($check);
            $existing = $cRes ? mysqli_fetch_assoc($cRes) : null;
            mysqli_stmt_close($check);

            if ($existing) {
                $linkId = (int)($existing['id'] ?? 0);
                $upd = mysqli_prepare(
                    $conn,
                    'UPDATE change_request_configuration_items
                     SET active = 1, deleted_by = NULL, deleted_at = NULL, updated_by = ?, updated_at = NOW()
                     WHERE id = ? AND company_id = ?'
                );
                if ($upd) {
                    mysqli_stmt_bind_param($upd, 'iii', $employeeId, $linkId, $companyId);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }
                continue;
            }

            $ins = mysqli_prepare(
                $conn,
                'INSERT INTO change_request_configuration_items
                 (company_id, change_request_id, configuration_item_id, active, created_by)
                 VALUES (?, ?, ?, 1, ?)'
            );
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'iiii', $companyId, $changeRequestId, $ciId, $employeeId);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
            }
        }
    }
}
