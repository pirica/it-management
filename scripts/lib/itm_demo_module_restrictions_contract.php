<?php
/**
 * Canonical contract for demo users with single-module access (company 1).
 *
 * Why: verify_demo_module_restrictions.php and fast_create_acc.php share one
 * definition of usernames, passwords, allowed modules, and probe slugs.
 */

if (!function_exists('itm_demo_module_restrictions_demo_users')) {
    /**
     * @return array<int,array{username:string,password:string,company_id:int,primary_slug:string,allowed_slugs:string[],role_name:string}>
     */
    function itm_demo_module_restrictions_demo_users()
    {
        return [
            [
                'username' => 'demo1',
                'password' => 'demo1',
                'company_id' => 1,
                'primary_slug' => 'tickets',
                'allowed_slugs' => ['tickets', 'settings'],
                'role_name' => 'Demo Tickets',
            ],
            [
                'username' => 'demo2',
                'password' => 'demo2',
                'company_id' => 1,
                'primary_slug' => 'audit_logs',
                'allowed_slugs' => ['audit_logs', 'settings'],
                'role_name' => 'Demo Audit',
            ],
            [
                'username' => 'demo3',
                'password' => 'demo3',
                'company_id' => 1,
                'primary_slug' => 'visitors_access_log',
                'allowed_slugs' => ['visitors_access_log', 'settings'],
                'role_name' => 'Demo Visitors',
            ],
            [
                'username' => 'demo4',
                'password' => 'demo4',
                'company_id' => 1,
                'primary_slug' => 'request_password',
                'allowed_slugs' => ['request_password', 'settings'],
                'role_name' => 'Demo Request Password',
            ],
            [
                'username' => 'demo5',
                'password' => 'demo5',
                'company_id' => 1,
                'primary_slug' => 'equipment',
                'allowed_slugs' => ['equipment', 'settings'],
                'role_name' => 'Demo Equipment',
            ],
        ];
    }
}

if (!function_exists('itm_demo_module_restrictions_seed_admins')) {
    /**
     * @return array<int,array{username:string,password:string,company_id:int}>
     */
    function itm_demo_module_restrictions_seed_admins()
    {
        return [
            ['username' => 'Admin', 'password' => 'Admin', 'company_id' => 1],
            ['username' => 'Admin2', 'password' => 'Admin', 'company_id' => 2],
            ['username' => 'Admin3', 'password' => 'Admin', 'company_id' => 3],
            ['username' => 'Admin4', 'password' => 'Admin', 'company_id' => 4],
            ['username' => 'Admin5', 'password' => 'Admin', 'company_id' => 5],
        ];
    }
}

if (!function_exists('itm_demo_module_restrictions_probe_denied_slugs')) {
    /**
     * Modules each demo user must not reach (company gate + RBAC).
     *
     * @return string[]
     */
    function itm_demo_module_restrictions_probe_denied_slugs()
    {
        return [
            'tickets',
            'audit_logs',
            'visitors_access_log',
            'request_password',
            'equipment',
            'employees',
            'departments',
        ];
    }
}

if (!function_exists('itm_demo_module_restrictions_module_index_path')) {
    function itm_demo_module_restrictions_module_index_path($moduleSlug)
    {
        $moduleSlug = strtolower(trim((string)$moduleSlug));
        if ($moduleSlug === '') {
            return '';
        }

        $path = ROOT_PATH . 'modules/' . $moduleSlug . '/index.php';
        return is_file($path) ? $path : '';
    }
}

if (!function_exists('itm_demo_module_restrictions_load_employee')) {
    /**
     * @return array<string,mixed>|null
     */
    function itm_demo_module_restrictions_load_employee(mysqli $conn, $username)
    {
        $username = trim((string)$username);
        if ($username === '') {
            return null;
        }

        $sql = 'SELECT e.id, e.company_id, e.username, e.password, e.role_id, e.employment_status_id,
                       er.name AS role_name
                FROM employees e
                LEFT JOIN employee_roles er ON er.id = e.role_id
                WHERE LOWER(e.username) = LOWER(?)
                  AND e.deleted_at IS NULL
                LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_demo_module_restrictions_denied_slugs_for_user')) {
    /**
     * @param array{allowed_slugs?:string[]} $demoUser
     * @return string[]
     */
    function itm_demo_module_restrictions_denied_slugs_for_user(array $demoUser)
    {
        $allowed = array_map('strtolower', (array)($demoUser['allowed_slugs'] ?? []));
        $denied = [];
        foreach (itm_demo_module_restrictions_probe_denied_slugs() as $slug) {
            if (!in_array(strtolower($slug), $allowed, true)) {
                $denied[] = $slug;
            }
        }

        return $denied;
    }
}
