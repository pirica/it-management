<?php
/**
 * API v2 REST gateway — PATH_INFO router, scoped API keys, JSON envelope.
 */

require_once __DIR__ . '/itm_api_v2_scopes.php';

if (!function_exists('itm_api_v2_send_json')) {
    function itm_api_v2_send_json($httpCode, array $payload)
    {
        if (!headers_sent()) {
            http_response_code((int)$httpCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('itm_api_v2_error')) {
    function itm_api_v2_error($httpCode, $message, array $extra = [])
    {
        $payload = array_merge([
            'ok' => false,
            'error' => (string)$message,
            'code' => (int)$httpCode,
        ], $extra);
        itm_api_v2_send_json((int)$httpCode, $payload);
    }
}

if (!function_exists('itm_api_v2_success')) {
    function itm_api_v2_success(array $data, $httpCode = 200)
    {
        itm_api_v2_send_json((int)$httpCode, [
            'ok' => true,
            'data' => $data,
        ]);
    }
}

if (!function_exists('itm_api_v2_read_json_body')) {
    /**
     * @return array<string,mixed>
     */
    function itm_api_v2_read_json_body()
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('itm_api_v2_resolve_path_info')) {
    function itm_api_v2_resolve_path_info()
    {
        $pathInfo = isset($_SERVER['PATH_INFO']) ? (string)$_SERVER['PATH_INFO'] : '';
        if ($pathInfo !== '') {
            return $pathInfo;
        }

        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if ($requestUri !== '' && $scriptName !== '' && strpos($requestUri, $scriptName) === 0) {
            $suffix = substr($requestUri, strlen($scriptName));
            $suffix = explode('?', $suffix, 2)[0];
            if ($suffix !== '' && $suffix[0] === '/') {
                return $suffix;
            }
        }

        return '';
    }
}

if (!function_exists('itm_api_v2_parse_request')) {
    /**
     * @return array{method:string,resource:string,id:int,query:array<string,string>,body:array<string,mixed>}
     */
    function itm_api_v2_parse_request()
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $pathInfo = trim(itm_api_v2_resolve_path_info(), '/');
        $segments = $pathInfo === '' ? [] : explode('/', $pathInfo);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || strpos($segment, '..') !== false) {
                itm_api_v2_error(400, 'Invalid path.');
            }
        }

        $resource = isset($segments[0]) ? strtolower((string)$segments[0]) : '';
        $id = isset($segments[1]) ? (int)$segments[1] : 0;
        if (isset($segments[2])) {
            itm_api_v2_error(404, 'Route not found.');
        }

        $query = [];
        foreach ($_GET as $key => $value) {
            if (is_string($key) && $key !== '') {
                $query[$key] = is_scalar($value) ? (string)$value : '';
            }
        }

        $body = [];
        if (in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
            $body = itm_api_v2_read_json_body();
        }

        return [
            'method' => $method,
            'resource' => $resource,
            'id' => $id,
            'query' => $query,
            'body' => $body,
        ];
    }
}

if (!function_exists('itm_api_v2_route_registry')) {
    /**
     * @return list<array<string,mixed>>
     */
    function itm_api_v2_route_registry()
    {
        return [
            ['method' => 'GET', 'resource' => 'probe', 'scope' => '', 'module' => '', 'rbac' => ''],
            ['method' => 'GET', 'resource' => 'tickets', 'scope' => 'tickets.read', 'module' => 'tickets', 'rbac' => 'view'],
            ['method' => 'GET', 'resource' => 'tickets', 'scope' => 'tickets.read', 'module' => 'tickets', 'rbac' => 'view', 'id' => true],
            ['method' => 'POST', 'resource' => 'tickets', 'scope' => 'tickets.write', 'module' => 'tickets', 'rbac' => 'create'],
            ['method' => 'PATCH', 'resource' => 'tickets', 'scope' => 'tickets.write', 'module' => 'tickets', 'rbac' => 'edit', 'id' => true],
            ['method' => 'GET', 'resource' => 'equipment', 'scope' => 'equipment.read', 'module' => 'equipment', 'rbac' => 'view'],
            ['method' => 'GET', 'resource' => 'equipment', 'scope' => 'equipment.read', 'module' => 'equipment', 'rbac' => 'view', 'id' => true],
            ['method' => 'POST', 'resource' => 'equipment', 'scope' => 'equipment.write', 'module' => 'equipment', 'rbac' => 'create'],
            ['method' => 'PATCH', 'resource' => 'equipment', 'scope' => 'equipment.write', 'module' => 'equipment', 'rbac' => 'edit', 'id' => true],
        ];
    }
}

if (!function_exists('itm_api_v2_match_route')) {
    /**
     * @param array{method:string,resource:string,id:int} $request
     * @return array<string,mixed>|null
     */
    function itm_api_v2_match_route(array $request)
    {
        $method = (string)($request['method'] ?? 'GET');
        $resource = (string)($request['resource'] ?? '');
        $id = (int)($request['id'] ?? 0);

        if ($resource === '' && $method === 'GET') {
            $resource = 'probe';
        }

        foreach (itm_api_v2_route_registry() as $route) {
            if (strcasecmp((string)($route['method'] ?? ''), $method) !== 0) {
                continue;
            }
            if ((string)($route['resource'] ?? '') !== $resource) {
                continue;
            }
            $requiresId = !empty($route['id']);
            if ($requiresId && $id <= 0) {
                continue;
            }
            if (!$requiresId && $id > 0) {
                continue;
            }

            return $route;
        }

        return null;
    }
}

if (!function_exists('itm_api_v2_bootstrap_session_from_row')) {
    function itm_api_v2_bootstrap_session_from_row(array $uiRow)
    {
        $companyId = (int)($uiRow['company_id'] ?? 0);
        $employeeId = (int)($uiRow['employee_id'] ?? 0);
        if ($companyId <= 0 || $employeeId <= 0) {
            itm_api_v2_error(401, 'Invalid API key context.');
        }

        $_SESSION['company_id'] = $companyId;
        $_SESSION['employee_id'] = $employeeId;

        global $company_id;
        $company_id = $companyId;
    }
}

if (!function_exists('itm_api_v2_authenticate_or_exit')) {
    /**
     * @return array<string,mixed>
     */
    function itm_api_v2_authenticate_or_exit($conn)
    {
        if (!($conn instanceof mysqli)) {
            itm_api_v2_error(500, 'Database connection failed.');
        }

        if (!function_exists('itm_api_extract_request_key') || !function_exists('itm_api_lookup_configuration_by_key')) {
            require_once __DIR__ . '/itm_api_rate_limit.php';
        }

        $apiKey = itm_api_extract_request_key();
        if ($apiKey === '') {
            itm_api_v2_error(401, 'API key required. Send X-API-Key header or api_key parameter.');
        }

        $row = itm_api_lookup_configuration_by_key($conn, $apiKey);
        if ($row === null) {
            itm_api_v2_error(401, 'Invalid API key.');
        }

        $tier = function_exists('itm_api_normalize_tier')
            ? itm_api_normalize_tier($row['tier'] ?? 'Free')
            : (string)($row['tier'] ?? 'Free');
        if (function_exists('itm_api_tier_requires_api_key') && !itm_api_tier_requires_api_key($tier)) {
            itm_api_v2_error(403, 'API v2 requires a paid integration tier with an API key.');
        }

        if ((int)($row['api_key_is_active'] ?? 0) !== 1) {
            itm_api_v2_error(403, 'API key is inactive.');
        }

        itm_api_v2_bootstrap_session_from_row($row);

        if (!function_exists('itm_api_consume_rate_limit')) {
            require_once __DIR__ . '/itm_api_rate_limit.php';
        }

        $rateResult = itm_api_consume_rate_limit($conn, $row);
        if (empty($rateResult['allowed'])) {
            itm_api_v2_error(429, (string)($rateResult['error'] ?? 'Rate limit exceeded.'), [
                'tier' => $rateResult['tier'] ?? $tier,
                'limit' => (int)($rateResult['limit'] ?? 0),
                'remaining' => (int)($rateResult['remaining'] ?? 0),
                'reset_at' => (int)($rateResult['reset_at'] ?? 0),
            ]);
        }

        return array_merge($row, $rateResult);
    }
}

if (!function_exists('itm_api_v2_require_scope')) {
    function itm_api_v2_require_scope($conn, array $uiRow, $scopeSlug)
    {
        $scopeSlug = itm_api_v2_normalize_scope_slug($scopeSlug);
        if ($scopeSlug === '') {
            return;
        }

        $companyId = (int)($uiRow['company_id'] ?? 0);
        $uiConfigurationId = (int)($uiRow['id'] ?? 0);
        if (!itm_api_v2_configuration_has_scope($conn, $companyId, $uiConfigurationId, $scopeSlug)) {
            itm_api_v2_error(403, 'Missing required scope: ' . $scopeSlug . '.');
        }
    }
}

if (!function_exists('itm_api_v2_require_module_permission')) {
    function itm_api_v2_require_module_permission($conn, array $uiRow, $moduleSlug, $action)
    {
        if (!function_exists('itm_user_has_role_module_permission')) {
            require_once __DIR__ . '/itm_role_module_permissions.php';
        }

        $employeeId = (int)($uiRow['employee_id'] ?? 0);
        $companyId = (int)($uiRow['company_id'] ?? 0);
        if (!itm_user_has_role_module_permission($conn, $employeeId, $companyId, (string)$moduleSlug, (string)$action)) {
            itm_api_v2_error(403, 'Insufficient module permission.');
        }
    }
}

if (!function_exists('itm_api_v2_handle_probe')) {
    function itm_api_v2_handle_probe($conn, array $uiRow)
    {
        $scopes = itm_api_v2_list_scopes_for_configuration(
            $conn,
            (int)($uiRow['company_id'] ?? 0),
            (int)($uiRow['id'] ?? 0)
        );

        itm_api_v2_success([
            'service' => 'api_v2',
            'company_id' => (int)($uiRow['company_id'] ?? 0),
            'employee_id' => (int)($uiRow['employee_id'] ?? 0),
            'tier' => function_exists('itm_api_normalize_tier')
                ? itm_api_normalize_tier($uiRow['tier'] ?? 'Basic')
                : (string)($uiRow['tier'] ?? 'Basic'),
            'scopes' => $scopes,
            'routes' => array_values(array_filter(itm_api_v2_route_registry(), static function ($route) {
                return (string)($route['resource'] ?? '') !== 'probe';
            })),
        ]);
    }
}

if (!function_exists('itm_api_v2_dispatch')) {
    function itm_api_v2_dispatch($conn)
    {
        $request = itm_api_v2_parse_request();
        $uiRow = itm_api_v2_authenticate_or_exit($conn);
        $route = itm_api_v2_match_route($request);

        if ($route === null) {
            itm_api_v2_error(404, 'Route not found.');
        }

        if ((string)($route['resource'] ?? '') === 'probe') {
            itm_api_v2_handle_probe($conn, $uiRow);
        }

        $scope = (string)($route['scope'] ?? '');
        if ($scope !== '') {
            itm_api_v2_require_scope($conn, $uiRow, $scope);
        }

        $module = (string)($route['module'] ?? '');
        $rbac = (string)($route['rbac'] ?? '');
        if ($module !== '' && $rbac !== '') {
            itm_api_v2_require_module_permission($conn, $uiRow, $module, $rbac);
        }

        require_once __DIR__ . '/itm_api_v2_handlers/tickets.php';
        require_once __DIR__ . '/itm_api_v2_handlers/equipment.php';

        $companyId = (int)($uiRow['company_id'] ?? 0);
        $employeeId = (int)($uiRow['employee_id'] ?? 0);
        $resource = (string)($request['resource'] ?? '');
        $method = (string)($request['method'] ?? 'GET');
        $id = (int)($request['id'] ?? 0);
        $body = is_array($request['body'] ?? null) ? $request['body'] : [];
        $query = is_array($request['query'] ?? null) ? $request['query'] : [];

        if ($resource === 'tickets') {
            if ($method === 'GET' && $id <= 0) {
                itm_api_v2_success(itm_api_v2_tickets_list($conn, $companyId, $query));
            }
            if ($method === 'GET' && $id > 0) {
                itm_api_v2_success(itm_api_v2_tickets_get($conn, $companyId, $id));
            }
            if ($method === 'POST' && $id <= 0) {
                itm_api_v2_success(itm_api_v2_tickets_create($conn, $companyId, $employeeId, $body), 201);
            }
            if ($method === 'PATCH' && $id > 0) {
                itm_api_v2_success(itm_api_v2_tickets_patch($conn, $companyId, $employeeId, $id, $body));
            }
        }

        if ($resource === 'equipment') {
            if ($method === 'GET' && $id <= 0) {
                itm_api_v2_success(itm_api_v2_equipment_list($conn, $companyId, $query));
            }
            if ($method === 'GET' && $id > 0) {
                itm_api_v2_success(itm_api_v2_equipment_get($conn, $companyId, $id));
            }
            if ($method === 'POST' && $id <= 0) {
                itm_api_v2_success(itm_api_v2_equipment_create($conn, $companyId, $employeeId, $body), 201);
            }
            if ($method === 'PATCH' && $id > 0) {
                itm_api_v2_success(itm_api_v2_equipment_patch($conn, $companyId, $employeeId, $id, $body));
            }
        }

        itm_api_v2_error(405, 'Method not allowed.');
    }
}
