<?php
/**
 * Seed demo employees (demo1–demo5) and reusable account helpers.
 *
 * Why: db/02_data.sql, fast_create_acc.php, and verify_demo_module_restrictions.php
 * share one implementation for roles, RBAC rows, employees, and sidebar prefs.
 */

require_once __DIR__ . '/itm_demo_module_restrictions_contract.php';

if (!function_exists('itm_demo_module_users_sidebar_section_for_slug')) {
    function itm_demo_module_users_sidebar_section_for_slug($moduleSlug)
    {
        $moduleSlug = strtolower(trim((string)$moduleSlug));
        if ($moduleSlug === 'audit_logs') {
            return 'reference_data';
        }

        return 'management';
    }
}

if (!function_exists('itm_demo_module_users_resolve_module_name')) {
    function itm_demo_module_users_resolve_module_name(mysqli $conn, $moduleSlug)
    {
        if (function_exists('itm_resolve_rbac_module_name_for_slug')) {
            return itm_resolve_rbac_module_name_for_slug($conn, $moduleSlug);
        }

        $moduleSlug = strtolower(trim((string)$moduleSlug));
        $stmt = mysqli_prepare(
            $conn,
            'SELECT module_name FROM modules_registry WHERE module_slug = ? AND active = 1 LIMIT 1'
        );
        if (!$stmt) {
            return ucwords(str_replace('_', ' ', $moduleSlug));
        }

        mysqli_stmt_bind_param($stmt, 's', $moduleSlug);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        $name = trim((string)($row['module_name'] ?? ''));
        return $name !== '' ? $name : ucwords(str_replace('_', ' ', $moduleSlug));
    }
}

if (!function_exists('itm_demo_module_users_lookup_fk_id')) {
    function itm_demo_module_users_lookup_fk_id(mysqli $conn, $table, $companyId, $nameColumn, $nameValue)
    {
        if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($nameColumn)) {
            return 0;
        }

        $companyId = (int)$companyId;
        $nameValue = trim((string)$nameValue);
        if ($companyId <= 0 || $nameValue === '') {
            return 0;
        }

        $sql = 'SELECT id FROM `' . $table . '` WHERE company_id = ? AND ' . $nameColumn . ' = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, 'is', $companyId, $nameValue);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_demo_module_users_normalize_module_slugs')) {
    /**
     * @param array<string,mixed> $spec
     * @return string[]
     */
    function itm_demo_module_users_normalize_module_slugs(array $spec)
    {
        if (!empty($spec['module_slugs']) && is_array($spec['module_slugs'])) {
            $slugs = $spec['module_slugs'];
        } elseif (!empty($spec['primary_slug'])) {
            $slugs = [(string)$spec['primary_slug']];
        } else {
            return [];
        }

        $skip = ['settings', 'dashboard', 'dashboard_link'];
        $normalized = [];
        foreach ($slugs as $slug) {
            $slug = strtolower(trim((string)$slug));
            if ($slug === '' || in_array($slug, $skip, true)) {
                continue;
            }
            $normalized[$slug] = $slug;
        }

        return array_values($normalized);
    }
}

if (!function_exists('itm_demo_module_users_apply_role_module_permissions')) {
    /**
     * @param string[] $moduleSlugs
     */
    function itm_demo_module_users_apply_role_module_permissions(mysqli $conn, $companyId, $roleId, array $moduleSlugs)
    {
        $companyId = (int)$companyId;
        $roleId = (int)$roleId;
        if ($companyId <= 0 || $roleId <= 0 || $moduleSlugs === []) {
            return;
        }

        foreach ($moduleSlugs as $moduleSlug) {
            $moduleName = itm_demo_module_users_resolve_module_name($conn, $moduleSlug);
            $permStmt = mysqli_prepare(
                $conn,
                'SELECT id FROM role_module_permissions
                 WHERE company_id = ? AND role_id = ? AND module_name = ? LIMIT 1'
            );
            if (!$permStmt) {
                continue;
            }

            mysqli_stmt_bind_param($permStmt, 'iis', $companyId, $roleId, $moduleName);
            mysqli_stmt_execute($permStmt);
            $permRes = mysqli_stmt_get_result($permStmt);
            $permRow = $permRes ? mysqli_fetch_assoc($permRes) : null;
            mysqli_stmt_close($permStmt);

            if (!is_array($permRow)) {
                $insertPerm = mysqli_prepare(
                    $conn,
                    'INSERT INTO role_module_permissions
                     (company_id, role_id, module_name, can_view, can_create, can_edit, can_delete, can_import, can_export, created_at)
                     VALUES (?, ?, ?, 1, 1, 1, 1, 1, 1, NOW())'
                );
                if ($insertPerm) {
                    mysqli_stmt_bind_param($insertPerm, 'iis', $companyId, $roleId, $moduleName);
                    mysqli_stmt_execute($insertPerm);
                    mysqli_stmt_close($insertPerm);
                }
            }
        }
    }
}

if (!function_exists('itm_demo_module_users_lookup_role_id_by_name')) {
    function itm_demo_module_users_lookup_role_id_by_name(mysqli $conn, $companyId, $roleName)
    {
        $companyId = (int)$companyId;
        $roleName = trim((string)$roleName);
        if ($companyId <= 0 || $roleName === '') {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM employee_roles WHERE company_id = ? AND name = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, 'is', $companyId, $roleName);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) ? (int)($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_demo_module_users_ensure_role_by_id')) {
    /**
     * @param string|string[] $moduleSlugs
     * @return array{role_id:int,created:bool,error:string}
     */
    function itm_demo_module_users_ensure_role_by_id(mysqli $conn, $companyId, $roleId, $moduleSlugs)
    {
        $companyId = (int)$companyId;
        $roleId = (int)$roleId;
        if (is_string($moduleSlugs)) {
            $moduleSlugs = [$moduleSlugs];
        }
        $moduleSlugs = itm_demo_module_users_normalize_module_slugs(['module_slugs' => $moduleSlugs]);
        $result = ['role_id' => 0, 'created' => false, 'error' => ''];

        if ($companyId <= 0 || $roleId <= 0 || $moduleSlugs === []) {
            $result['error'] = 'Invalid role id seed parameters.';
            return $result;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM employee_roles WHERE company_id = ? AND id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            $result['error'] = 'Could not query employee_roles.';
            return $result;
        }

        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $roleId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!is_array($row)) {
            $result['error'] = 'Selected role is not valid for this company.';
            return $result;
        }

        itm_demo_module_users_apply_role_module_permissions($conn, $companyId, $roleId, $moduleSlugs);
        $result['role_id'] = $roleId;
        return $result;
    }
}

if (!function_exists('itm_demo_module_users_ensure_role')) {
    /**
     * @param string|string[] $moduleSlugs One slug or list of module slugs for RBAC rows.
     * @return array{role_id:int,created:bool,error:string}
     */
    function itm_demo_module_users_ensure_role(mysqli $conn, $companyId, $roleName, $moduleSlugs)
    {
        $companyId = (int)$companyId;
        $roleName = trim((string)$roleName);
        if (is_string($moduleSlugs)) {
            $moduleSlugs = [$moduleSlugs];
        }
        $moduleSlugs = itm_demo_module_users_normalize_module_slugs(['module_slugs' => $moduleSlugs]);
        $result = ['role_id' => 0, 'created' => false, 'error' => ''];

        if ($companyId <= 0 || $roleName === '' || $moduleSlugs === []) {
            $result['error'] = 'Invalid role seed parameters.';
            return $result;
        }

        $roleId = itm_demo_module_users_lookup_role_id_by_name($conn, $companyId, $roleName);
        if ($roleId <= 0) {
            $insert = mysqli_prepare(
                $conn,
                'INSERT INTO employee_roles (company_id, name, active, created_at) VALUES (?, ?, 1, NOW())'
            );
            if (!$insert) {
                $result['error'] = 'Could not insert employee_roles row.';
                return $result;
            }
            mysqli_stmt_bind_param($insert, 'is', $companyId, $roleName);
            if (!mysqli_stmt_execute($insert)) {
                mysqli_stmt_close($insert);
                $result['error'] = 'employee_roles insert failed.';
                return $result;
            }
            $roleId = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($insert);
            $result['created'] = true;
        }

        if ($roleId <= 0) {
            $result['error'] = 'Role id missing after ensure.';
            return $result;
        }

        itm_demo_module_users_apply_role_module_permissions($conn, $companyId, $roleId, $moduleSlugs);

        $result['role_id'] = $roleId;
        return $result;
    }
}

if (!function_exists('itm_demo_module_users_save_sidebar_prefs')) {
    /**
     * @param string|string[] $moduleSlugs
     */
    function itm_demo_module_users_save_sidebar_prefs(mysqli $conn, $companyId, $employeeId, $moduleSlugs)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if (is_string($moduleSlugs)) {
            $moduleSlugs = [$moduleSlugs];
        }
        $moduleSlugs = itm_demo_module_users_normalize_module_slugs(['module_slugs' => $moduleSlugs]);
        if ($companyId <= 0 || $employeeId <= 0 || $moduleSlugs === []) {
            return false;
        }

        if (!function_exists('itm_ensure_employee_sidebar_preferences_table')) {
            return false;
        }
        itm_ensure_employee_sidebar_preferences_table($conn);

        $delete = mysqli_prepare(
            $conn,
            'DELETE FROM employee_sidebar_preferences WHERE company_id = ? AND employee_id = ?'
        );
        if ($delete) {
            mysqli_stmt_bind_param($delete, 'ii', $companyId, $employeeId);
            mysqli_stmt_execute($delete);
            mysqli_stmt_close($delete);
        }

        $rows = [
            ['section', 'dashboard', null, 0],
            ['item', 'dashboard_link', 'dashboard', 0],
            ['item', 'settings', 'dashboard', 1],
        ];

        $sectionsAdded = [];
        $sectionOrder = 1;
        $itemOrderBySection = [];
        foreach ($moduleSlugs as $moduleSlug) {
            $sectionId = itm_demo_module_users_sidebar_section_for_slug($moduleSlug);
            if (!isset($sectionsAdded[$sectionId])) {
                $rows[] = ['section', $sectionId, null, $sectionOrder];
                $sectionsAdded[$sectionId] = true;
                $sectionOrder++;
                $itemOrderBySection[$sectionId] = 0;
            }
            $rows[] = ['item', $moduleSlug, $sectionId, $itemOrderBySection[$sectionId]];
            $itemOrderBySection[$sectionId]++;
        }

        $insert = mysqli_prepare(
            $conn,
            'INSERT INTO employee_sidebar_preferences
             (company_id, employee_id, entry_type, entry_id, section_id, display_order, is_visible, active)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1)'
        );
        if (!$insert) {
            return false;
        }

        foreach ($rows as $row) {
            $entryType = $row[0];
            $entryId = $row[1];
            $parentSection = $row[2];
            $displayOrder = (int)$row[3];
            mysqli_stmt_bind_param(
                $insert,
                'iisssi',
                $companyId,
                $employeeId,
                $entryType,
                $entryId,
                $parentSection,
                $displayOrder
            );
            if (!mysqli_stmt_execute($insert)) {
                mysqli_stmt_close($insert);
                return false;
            }
        }

        mysqli_stmt_close($insert);
        return true;
    }
}

if (!function_exists('itm_demo_module_users_ensure_ui_configuration')) {
    function itm_demo_module_users_ensure_ui_configuration(mysqli $conn, $companyId, $employeeId)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if ($companyId <= 0 || $employeeId <= 0) {
            return false;
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO ui_configuration
             (company_id, employee_id, table_actions_position, new_button_position, export_buttons_position,
              back_save_position, enable_all_error_reporting, enable_audit_logs, enable_chatbot,
              enable_auto_scaffolding, records_per_page, app_name, favicon_path, equipment_type_sidebar_visibility, active)
             VALUES (?, ?, \'left\', \'left\', \'left\', \'left\', 1, 1, 0, 0, \'25\', \'⚙️ IT Controls\', ?, ?, 1)
             ON DUPLICATE KEY UPDATE employee_id = employee_id'
        );
        if (!$stmt) {
            return false;
        }

        $favicon = 'images/favicons/company_' . $companyId . '.ico';
        $equipmentVisibility = '{"is_access_point":1,"is_cctv":1,"is_firewall":1,"is_other":1,"is_phone":1,"is_port_patch_panel":1,"is_printer":1,"is_router":1,"is_server":1,"is_switch":1,"is_workstation":1}';
        mysqli_stmt_bind_param($stmt, 'iiss', $companyId, $employeeId, $favicon, $equipmentVisibility);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_demo_module_users_ensure_company_grant')) {
    function itm_demo_module_users_ensure_company_grant(mysqli $conn, $employeeId, $companyId, $grantedByEmployeeId = 0)
    {
        $employeeId = (int)$employeeId;
        $companyId = (int)$companyId;
        $grantedByEmployeeId = (int)$grantedByEmployeeId;
        if ($employeeId <= 0 || $companyId <= 0) {
            return false;
        }

        $grantedBy = $grantedByEmployeeId > 0 ? $grantedByEmployeeId : null;
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO employee_companies (employee_id, company_id, granted_by_employee_id, active, created_at)
             VALUES (?, ?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE active = 1'
        );
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'iii', $employeeId, $companyId, $grantedBy);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_demo_module_users_upsert_employee')) {
    /**
     * @param array<string,mixed> $spec
     * @return array{ok:bool,employee_id:int,role_id:int,created:bool,messages:string[],errors:string[]}
     */
    function itm_demo_module_users_upsert_employee(mysqli $conn, array $spec)
    {
        $result = [
            'ok' => false,
            'employee_id' => 0,
            'role_id' => 0,
            'created' => false,
            'messages' => [],
            'errors' => [],
        ];

        $companyId = (int)($spec['company_id'] ?? 0);
        $username = trim((string)($spec['username'] ?? ''));
        $passwordPlain = (string)($spec['password'] ?? '');
        $firstName = trim((string)($spec['first_name'] ?? ''));
        $lastName = trim((string)($spec['last_name'] ?? ''));
        $workEmail = trim((string)($spec['work_email'] ?? ''));
        $personalEmail = trim((string)($spec['personal_email'] ?? ''));
        $roleId = (int)($spec['role_id'] ?? 0);
        $roleName = trim((string)($spec['role_name'] ?? ''));
        $moduleSlugs = itm_demo_module_users_normalize_module_slugs($spec);
        $accessLevelId = (int)($spec['access_level_id'] ?? 0);
        $employmentStatusId = (int)($spec['employment_status_id'] ?? 0);
        $departmentId = isset($spec['department_id']) ? (int)$spec['department_id'] : 0;
        $employeePositionId = isset($spec['employee_position_id']) ? (int)$spec['employee_position_id'] : 0;
        $grantedByEmployeeId = (int)($spec['granted_by_employee_id'] ?? 0);

        if ($companyId <= 0 || $username === '' || $passwordPlain === '' || $moduleSlugs === []) {
            $result['errors'][] = 'company_id, username, password, and at least one module slug are required.';
            return $result;
        }

        if ($roleId <= 0 && $roleName === '') {
            $result['errors'][] = 'role_id or role_name is required.';
            return $result;
        }

        if ($firstName === '') {
            $firstName = ucfirst($username);
        }
        if ($lastName === '') {
            $lastName = 'Demo';
        }
        if ($workEmail === '') {
            $workEmail = strtolower($username) . '@demo.example.com';
        }

        $emailError = itm_employee_validate_contact_email_or_error($workEmail, $personalEmail);
        if ($emailError !== null) {
            $result['errors'][] = $emailError;
            return $result;
        }

        $personalEmailParam = $personalEmail !== '' ? $personalEmail : null;

        if ($accessLevelId <= 0) {
            $accessLevelId = itm_demo_module_users_lookup_fk_id($conn, 'access_levels', $companyId, 'name', 'Limited');
            if ($accessLevelId <= 0) {
                $accessLevelId = itm_demo_module_users_lookup_fk_id($conn, 'access_levels', $companyId, 'name', 'Full');
            }
        }
        if ($employmentStatusId <= 0) {
            $employmentStatusId = itm_demo_module_users_lookup_fk_id($conn, 'employee_statuses', $companyId, 'name', 'Active');
        }
        if ($accessLevelId <= 0 || $employmentStatusId <= 0) {
            $result['errors'][] = 'Could not resolve access_level_id or employment_status_id for company ' . $companyId . '.';
            return $result;
        }

        if ($roleId > 0) {
            $roleEnsure = itm_demo_module_users_ensure_role_by_id($conn, $companyId, $roleId, $moduleSlugs);
        } else {
            $roleEnsure = itm_demo_module_users_ensure_role($conn, $companyId, $roleName, $moduleSlugs);
        }
        if ($roleEnsure['role_id'] <= 0) {
            $result['errors'][] = $roleEnsure['error'] !== '' ? $roleEnsure['error'] : 'Could not ensure demo role.';
            return $result;
        }
        $result['role_id'] = (int)$roleEnsure['role_id'];

        $existing = itm_demo_module_restrictions_load_employee($conn, $username);
        $passwordHash = password_hash($passwordPlain, PASSWORD_BCRYPT);
        $employeeId = is_array($existing) ? (int)($existing['id'] ?? 0) : 0;

        if ($employeeId > 0) {
            $sql = 'UPDATE employees SET company_id = ?, first_name = ?, last_name = ?, display_name = ?, work_email = ?, personal_email = ?,
                    password = ?, role_id = ?, access_level_id = ?, employment_status_id = ?';
            $types = 'issssssiii';
            $params = [
                $companyId,
                $firstName,
                $lastName,
                trim($firstName . ' ' . $lastName),
                $workEmail,
                $personalEmailParam,
                $passwordHash,
                $result['role_id'],
                $accessLevelId,
                $employmentStatusId,
            ];

            if ($departmentId > 0) {
                $sql .= ', department_id = ?';
                $types .= 'i';
                $params[] = $departmentId;
            }
            if ($employeePositionId > 0) {
                $sql .= ', employee_position_id = ?';
                $types .= 'i';
                $params[] = $employeePositionId;
            }

            $sql .= ' WHERE id = ? LIMIT 1';
            $types .= 'i';
            $params[] = $employeeId;

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                $result['errors'][] = 'Could not prepare employee UPDATE.';
                return $result;
            }
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $result['errors'][] = 'Employee UPDATE failed.';
                return $result;
            }
            mysqli_stmt_close($stmt);
            $result['messages'][] = 'Updated existing employee ' . $username . '.';
        } else {
            if (function_exists('itm_script_test_employee_clear_audit_context')) {
                itm_script_test_employee_clear_audit_context($conn);
            }

            $departmentParam = $departmentId > 0 ? $departmentId : null;
            $positionParam = $employeePositionId > 0 ? $employeePositionId : null;
            $displayName = trim($firstName . ' ' . $lastName);

            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO employees
                 (company_id, first_name, last_name, display_name, work_email, personal_email, password, role_id,
                  access_level_id, employment_status_id, username, department_id, employee_position_id, active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
            );
            if (!$stmt) {
                $result['errors'][] = 'Could not prepare employee INSERT.';
                return $result;
            }

            mysqli_stmt_bind_param(
                $stmt,
                'issssssiiisii',
                $companyId,
                $firstName,
                $lastName,
                $displayName,
                $workEmail,
                $personalEmailParam,
                $passwordHash,
                $result['role_id'],
                $accessLevelId,
                $employmentStatusId,
                $username,
                $departmentParam,
                $positionParam
            );

            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $result['errors'][] = 'Employee INSERT failed: ' . mysqli_error($conn);
                return $result;
            }
            $employeeId = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            $result['created'] = true;
            $result['messages'][] = 'Created employee ' . $username . '.';
        }

        if ($employeeId <= 0) {
            $result['errors'][] = 'Employee id missing after upsert.';
            return $result;
        }

        $result['employee_id'] = $employeeId;
        itm_demo_module_users_ensure_company_grant($conn, $employeeId, $companyId, $grantedByEmployeeId);
        itm_demo_module_users_ensure_ui_configuration($conn, $companyId, $employeeId);
        itm_demo_module_users_save_sidebar_prefs($conn, $companyId, $employeeId, $moduleSlugs);

        $result['ok'] = true;
        $result['messages'][] = 'Sidebar + ui_configuration synced for ' . $username . '.';

        return $result;
    }
}

if (!function_exists('itm_demo_module_users_seed_bundle')) {
    /**
     * @return array{ok:bool,messages:string[],errors:string[]}
     */
    function itm_demo_module_users_seed_bundle(mysqli $conn, $companyId = 1, $grantedByEmployeeId = 0)
    {
        $summary = ['ok' => true, 'messages' => [], 'errors' => []];
        $companyId = (int)$companyId;

        foreach (itm_demo_module_restrictions_demo_users() as $demoSpec) {
            if ((int)($demoSpec['company_id'] ?? 0) !== $companyId) {
                continue;
            }

            $payload = $demoSpec;
            $payload['module_slugs'] = itm_demo_module_restrictions_module_slugs_for_user($demoSpec);
            $payload['granted_by_employee_id'] = $grantedByEmployeeId;
            $payload['first_name'] = ucfirst((string)$demoSpec['username']);
            $payload['last_name'] = 'Demo';
            $payload['work_email'] = strtolower((string)$demoSpec['username']) . '@demo.example.com';

            $upsert = itm_demo_module_users_upsert_employee($conn, $payload);
            $summary['messages'] = array_merge($summary['messages'], $upsert['messages']);
            $summary['errors'] = array_merge($summary['errors'], $upsert['errors']);
            if (!$upsert['ok']) {
                $summary['ok'] = false;
            }
        }

        return $summary;
    }
}

if (!function_exists('itm_demo_module_users_fetch_fk_options')) {
    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    function itm_demo_module_users_fetch_fk_options(mysqli $conn, $companyId)
    {
        $companyId = (int)$companyId;
        $options = [
            'companies' => [],
            'employee_roles' => [],
            'employee_statuses' => [],
            'access_levels' => [],
            'departments' => [],
            'employee_positions' => [],
            'modules' => [],
        ];

        $res = mysqli_query($conn, 'SELECT id, company, incode FROM companies ORDER BY company ASC');
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $options['companies'][] = $row;
        }

        if ($companyId > 0) {
            $queries = [
                'employee_roles' => 'SELECT id, name FROM employee_roles WHERE company_id = ' . $companyId . ' AND deleted_at IS NULL ORDER BY name ASC',
                'employee_statuses' => 'SELECT id, name FROM employee_statuses WHERE company_id = ' . $companyId . ' ORDER BY name ASC',
                'access_levels' => 'SELECT id, name FROM access_levels WHERE company_id = ' . $companyId . ' ORDER BY name ASC',
                'departments' => 'SELECT id, name, code FROM departments WHERE company_id = ' . $companyId . ' AND deleted_at IS NULL ORDER BY name ASC',
                'employee_positions' => 'SELECT id, name FROM employee_positions WHERE company_id = ' . $companyId . ' AND deleted_at IS NULL ORDER BY name ASC',
            ];
            foreach ($queries as $key => $sql) {
                $fkRes = mysqli_query($conn, $sql);
                while ($fkRes && ($fkRow = mysqli_fetch_assoc($fkRes))) {
                    $options[$key][] = $fkRow;
                }
            }
        }

        $modRes = mysqli_query(
            $conn,
            'SELECT module_slug, module_name FROM modules_registry WHERE active = 1 ORDER BY module_name ASC'
        );
        while ($modRes && ($modRow = mysqli_fetch_assoc($modRes))) {
            $options['modules'][] = $modRow;
        }

        return $options;
    }
}
