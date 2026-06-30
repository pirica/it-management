<?php
/**
 * Role Assignment Rights Helpers
 *
 * Manages the registry of which roles are permitted to assign other roles.
 */

/**
 * Retrieves the list of role IDs that a given granter role can assign.
 *
 * @param mysqli $conn
 * @param int $companyId
 * @param int $granterRoleId
 * @return int[]
 */
function itm_get_assignable_role_ids($conn, $companyId, $granterRoleId) {
    $companyId = (int)$companyId;
    $granterRoleId = (int)$granterRoleId;
    if ($companyId <= 0 || $granterRoleId <= 0) {
        return [];
    }

    $sql = "SELECT can_assign_role_id FROM role_assignment_rights
            WHERE company_id = ? AND role_id = ? AND active = 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $granterRoleId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $roleIds = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $roleIds[] = (int)$row['can_assign_role_id'];
    }
    mysqli_stmt_close($stmt);

    return $roleIds;
}

/**
 * Validates if a granter role has the right to assign a specific target role.
 *
 * @param mysqli $conn
 * @param int $companyId
 * @param int $granterRoleId
 * @param int $targetRoleId
 * @return bool
 */
function itm_can_assign_role($conn, $companyId, $granterRoleId, $targetRoleId) {
    $companyId = (int)$companyId;
    $granterRoleId = (int)$granterRoleId;
    $targetRoleId = (int)$targetRoleId;

    if ($companyId <= 0 || $granterRoleId <= 0 || $targetRoleId <= 0) {
        return false;
    }

    $sql = "SELECT 1 FROM role_assignment_rights
            WHERE company_id = ? AND role_id = ? AND can_assign_role_id = ? AND active = 1
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'iii', $companyId, $granterRoleId, $targetRoleId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $canAssign = (mysqli_num_rows($result) > 0);
    mysqli_stmt_close($stmt);

    return $canAssign;
}
