<?php
/**
 * Role Assignment Rights Helpers
 *
 * Manages the registry of which roles are permitted to assign other roles.
 */

/**
 * Retrieves the role ID for a given employee with static caching.
 *
 * @param mysqli $conn
 * @param int $employeeId
 * @return int
 */
function itm_get_employee_role_id($conn, $employeeId) {
    static $cache = [];
    $employeeId = (int)$employeeId;
    if ($employeeId <= 0) {
        return 0;
    }
    if (isset($cache[$employeeId])) {
        return $cache[$employeeId];
    }

    $sql = "SELECT role_id FROM employees WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $employeeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $roleId = $row ? (int)$row['role_id'] : 0;
    $cache[$employeeId] = $roleId;
    return $roleId;
}

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

    // Admins can assign any role by default if it belongs to their company
    if (function_exists('itm_is_admin') && itm_is_admin($conn, $_SESSION['employee_id'] ?? 0)) {
        if ($companyId <= 0) {
            return [];
        }
        $sql = "SELECT id FROM employee_roles WHERE company_id = ? AND active = 1";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $ids = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $ids[] = (int)$row['id'];
            }
            mysqli_stmt_close($stmt);
            return $ids;
        }
    }

    if ($companyId <= 0 || $granterRoleId <= 0) {
        return [];
    }

    $sql = "SELECT rar.can_assign_role_id
            FROM role_assignment_rights rar
            JOIN employee_roles er ON er.id = rar.can_assign_role_id
            WHERE rar.company_id = ? AND rar.role_id = ? AND rar.active = 1 AND er.active = 1 AND er.company_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, 'iii', $companyId, $granterRoleId, $companyId);
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

    // Admins can assign any role by default if it belongs to their company
    if (function_exists('itm_is_admin') && itm_is_admin($conn, $_SESSION['employee_id'] ?? 0)) {
        if ($companyId <= 0 || $targetRoleId <= 0) {
            return false;
        }
        $sql = "SELECT 1 FROM employee_roles WHERE company_id = ? AND id = ? AND active = 1 LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $targetRoleId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $canAssign = ($result && mysqli_num_rows($result) > 0);
        mysqli_stmt_close($stmt);
        return $canAssign;
    }

    if ($companyId <= 0 || $granterRoleId <= 0 || $targetRoleId <= 0) {
        return false;
    }

    $sql = "SELECT 1 FROM role_assignment_rights rar
            JOIN employee_roles er ON er.id = rar.can_assign_role_id
            WHERE rar.company_id = ? AND rar.role_id = ? AND rar.can_assign_role_id = ? AND rar.active = 1
              AND er.active = 1 AND er.company_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'iiii', $companyId, $granterRoleId, $targetRoleId, $companyId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $canAssign = (mysqli_num_rows($result) > 0);
    mysqli_stmt_close($stmt);

    return $canAssign;
}

/**
 * Ranks roles so users can only invite at or below their own role.
 */
function itm_role_rank($roleLabel) {
    $normalized = strtolower(trim((string)$roleLabel));
    $rankMap = [
        'admin' => 500,
        'it manager' => 400,
        'it assistant' => 300,
        'helpdesk' => 200,
        'user' => 100,
    ];
    return $rankMap[$normalized] ?? 0;
}

/**
 * Ranks access levels so users can only invite at or below their own level.
 */
function itm_access_level_rank($accessLevelLabel) {
    $normalized = strtolower(trim((string)$accessLevelLabel));
    $rankMap = [
        'full' => 300,
        'read only' => 200,
        'limited' => 100,
    ];
    return $rankMap[$normalized] ?? 0;
}

/**
 * Validates posted role/access selections against current user hierarchy and assignment rights.
 *
 * @param mysqli $conn
 * @param int $company_id
 * @param array $permissionProfile
 * @param string $fieldName
 * @param int $postedId
 * @param array $allowedOptions
 * @param array &$errors
 */
function itm_validate_invitation_hierarchy_selection($conn, $company_id, $permissionProfile, $fieldName, $postedId, $allowedOptions, &$errors) {
    if (!in_array($fieldName, ['role_id', 'access_level_id'], true)) {
        return;
    }

    $selectionId = (int)$postedId;
    if ($selectionId <= 0) {
        return;
    }

    if ($fieldName === 'role_id') {
        if (!itm_can_assign_role($conn, $company_id, (int)($permissionProfile['role_id'] ?? 0), $selectionId)) {
            $errors[] = 'You do not have permission to assign this role.';
            return;
        }
    }

    foreach ($allowedOptions as $option) {
        if ((int)($option['id'] ?? 0) === $selectionId) {
            return;
        }
    }

    $errors[] = $fieldName === 'role_id'
        ? 'You do not have permission to assign this role.'
        : 'You cannot assign an access level above your own access level.';
}

/**
 * Filters role/access options based on logged user hierarchy and assignment rights.
 *
 * @param mysqli $conn
 * @param int $company_id
 * @param string $fieldName
 * @param array $options
 * @param array $permissionProfile
 * @return array
 */
function itm_filter_invitation_hierarchy_options($conn, $company_id, $fieldName, $options, $permissionProfile) {
    if (!in_array($fieldName, ['role_id', 'access_level_id'], true) || !is_array($options)) {
        return $options;
    }

    $filtered = [];
    if ($fieldName === 'role_id') {
        $assignableIds = itm_get_assignable_role_ids($conn, $company_id, (int)($permissionProfile['role_id'] ?? 0));
        foreach ($options as $option) {
            if (in_array((int)($option['id'] ?? 0), $assignableIds, true)) {
                $filtered[] = $option;
            }
        }
    } else {
        $maxRank = itm_access_level_rank((string)($permissionProfile['access_level_name'] ?? ''));
        if ($maxRank <= 0) {
            return [];
        }
        foreach ($options as $option) {
            $label = (string)($option['label'] ?? '');
            $rank = itm_access_level_rank($label);
            if ($rank > 0 && $rank <= $maxRank) {
                $filtered[] = $option;
            }
        }
    }

    return $filtered;
}
