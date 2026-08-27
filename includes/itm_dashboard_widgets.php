<?php
/**
 * Role-based smart dashboard widget registry, RBAC gates, and batch data fetch.
 */

require_once __DIR__ . '/itm_dashboard_queries.php';

if (!function_exists('itm_dashboard_widget_registry')) {
    function itm_dashboard_widget_registry()
    {
        return [
            'my_open_tickets' => [
                'slug' => 'my_open_tickets',
                'title' => 'My open tickets',
                'icon' => '🎟️',
                'module_slug' => 'tickets',
                'sort_order' => 10,
                'requires_it' => false,
            ],
            'expiring_30d' => [
                'slug' => 'expiring_30d',
                'title' => 'Expiring in 30 days',
                'icon' => '⏳',
                'module_slug' => 'expiring',
                'sort_order' => 20,
                'requires_it' => false,
            ],
            'patches_due_30d' => [
                'slug' => 'patches_due_30d',
                'title' => 'Patches due in 30 days',
                'icon' => '🛠️',
                'module_slug' => 'patches_updates',
                'sort_order' => 25,
                'requires_it' => false,
            ],
            'visitors_today' => [
                'slug' => 'visitors_today',
                'title' => "Today's visitors",
                'icon' => '🚪',
                'module_slug' => 'visitors_access_log',
                'sort_order' => 30,
                'requires_it' => false,
            ],
            'backup_tape_gaps' => [
                'slug' => 'backup_tape_gaps',
                'title' => 'Backup tape gaps',
                'icon' => '💾',
                'module_slug' => 'backup_tape_log',
                'sort_order' => 40,
                'requires_it' => true,
            ],
        ];
    }
}

if (!function_exists('itm_dashboard_widget_module_view_allowed')) {
    function itm_dashboard_widget_module_view_allowed($conn, $companyId, $employeeId, $moduleSlug)
    {
        $moduleSlug = trim((string)$moduleSlug);
        if ($moduleSlug === '') {
            return false;
        }
        if (!function_exists('has_module_access') || !has_module_access($conn, (int)$companyId, $moduleSlug)) {
            return false;
        }
        if (!function_exists('itm_resolve_rbac_module_name_for_slug')) {
            return true;
        }
        $moduleName = itm_resolve_rbac_module_name_for_slug($conn, $moduleSlug);
        if ($moduleName === '') {
            return false;
        }
        if (!function_exists('itm_user_has_role_module_permission')) {
            return true;
        }

        return itm_user_has_role_module_permission($conn, (int)$employeeId, (int)$companyId, $moduleName, 'view');
    }
}

if (!function_exists('itm_dashboard_widget_can_show')) {
    function itm_dashboard_widget_can_show($conn, $companyId, $employeeId, $slug)
    {
        $registry = itm_dashboard_widget_registry();
        $slug = trim((string)$slug);
        if (!isset($registry[$slug])) {
            return false;
        }
        $def = $registry[$slug];
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if ($companyId <= 0 || $employeeId <= 0) {
            return false;
        }

        if (!itm_dashboard_widget_module_view_allowed($conn, $companyId, $employeeId, (string)($def['module_slug'] ?? ''))) {
            return false;
        }

        if (!empty($def['requires_it']) && !itm_dashboard_query_it_department_employee($conn, $companyId, $employeeId)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('itm_normalize_dashboard_widget_prefs')) {
    /**
     * @return array<string,int> slug => 1 enabled, 0 disabled
     */
    function itm_normalize_dashboard_widget_prefs($raw)
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $registry = itm_dashboard_widget_registry();
        $normalized = [];
        foreach ($registry as $slug => $def) {
            $slug = trim((string)$slug);
            if ($slug === '') {
                continue;
            }
            if (!array_key_exists($slug, $raw)) {
                continue;
            }
            $value = $raw[$slug];
            $normalized[$slug] = ((string)$value === '0' || $value === 0 || $value === false) ? 0 : 1;
        }

        return $normalized;
    }
}

if (!function_exists('itm_dashboard_widget_pref_enabled')) {
    function itm_dashboard_widget_pref_enabled(array $prefs, $slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            return false;
        }
        if (!array_key_exists($slug, $prefs)) {
            return true;
        }

        return (int)($prefs[$slug] ?? 1) === 1;
    }
}

if (!function_exists('itm_dashboard_widgets_for_user_config')) {
    /**
     * Widgets the signed-in employee may pin/unpin on user-config.php (RBAC-allowed only).
     *
     * @return array<int,array<string,mixed>>
     */
    function itm_dashboard_widgets_for_user_config($conn, $companyId, $employeeId, array $prefs = null)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if ($prefs === null) {
            $prefs = [];
        }
        $prefs = itm_normalize_dashboard_widget_prefs($prefs);

        $items = [];
        foreach (itm_dashboard_widget_registry() as $slug => $def) {
            if (!itm_dashboard_widget_can_show($conn, $companyId, $employeeId, $slug)) {
                continue;
            }
            $items[] = [
                'slug' => $slug,
                'title' => (string)($def['title'] ?? $slug),
                'icon' => (string)($def['icon'] ?? ''),
                'module_slug' => (string)($def['module_slug'] ?? ''),
                'sort_order' => (int)($def['sort_order'] ?? 0),
                'enabled' => itm_dashboard_widget_pref_enabled($prefs, $slug),
                'deep_link' => itm_dashboard_widget_build_deep_link($slug, $companyId, $employeeId),
            ];
        }

        usort($items, static function ($a, $b) {
            return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
        });

        return $items;
    }
}

if (!function_exists('itm_dashboard_resolve_widgets_for_employee')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function itm_dashboard_resolve_widgets_for_employee($conn, $companyId, $employeeId)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $prefs = [];
        if (function_exists('itm_get_ui_configuration')) {
            $uiConfig = itm_get_ui_configuration($conn, $companyId, $employeeId);
            $prefs = itm_normalize_dashboard_widget_prefs($uiConfig['dashboard_widget_prefs'] ?? []);
        }

        $visible = [];
        foreach (itm_dashboard_widget_registry() as $slug => $def) {
            if (!itm_dashboard_widget_can_show($conn, $companyId, $employeeId, $slug)) {
                continue;
            }
            if (!itm_dashboard_widget_pref_enabled($prefs, $slug)) {
                continue;
            }
            $visible[] = $def;
        }

        usort($visible, static function ($a, $b) {
            return (int)($a['sort_order'] ?? 0) <=> (int)($b['sort_order'] ?? 0);
        });

        return $visible;
    }
}

if (!function_exists('itm_dashboard_widget_build_deep_link')) {
    function itm_dashboard_widget_build_deep_link($slug, $companyId, $employeeId)
    {
        $slug = trim((string)$slug);
        $employeeId = (int)$employeeId;
        $base = defined('BASE_URL') ? BASE_URL : '';

        switch ($slug) {
            case 'my_open_tickets':
                return $base . 'modules/tickets/index.php?assigned_to_employee_id=' . $employeeId . '&open_only=1';
            case 'expiring_30d':
                return $base . 'modules/expiring/index.php';
            case 'patches_due_30d':
                return $base . 'modules/patches_updates/index.php?sort=due_date&dir=ASC';
            case 'visitors_today':
                return $base . 'modules/visitors_access_log/index.php';
            case 'backup_tape_gaps':
                return $base . 'modules/backup_tape_log/index.php?year=' . (int)date('Y') . '&month=' . (int)date('n');
            default:
                return $base . 'dashboard.php';
        }
    }
}

if (!function_exists('itm_dashboard_widget_fetch_data')) {
    /**
     * @return array{metric:int,subtitle:string,sparkline:array{labels:array<int,string>,data:array<int,int>}}
     */
    function itm_dashboard_widget_fetch_data($conn, $companyId, $employeeId, $slug)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $slug = trim((string)$slug);
        $emptySpark = ['labels' => [], 'data' => []];

        switch ($slug) {
            case 'my_open_tickets':
                $metric = itm_dashboard_query_my_open_tickets_count($conn, $companyId, $employeeId);
                $sparkline = itm_dashboard_query_my_open_tickets_trend($conn, $companyId, $employeeId);
                return [
                    'metric' => $metric,
                    'subtitle' => 'Assigned to you · open status',
                    'sparkline' => $sparkline,
                ];
            case 'expiring_30d':
                $metric = itm_dashboard_query_expiring_within_days_count($conn, $companyId, 30);
                $sparkline = itm_dashboard_query_expiring_trend($conn, $companyId);
                return [
                    'metric' => $metric,
                    'subtitle' => 'Certificate or warranty within 30 days',
                    'sparkline' => $sparkline,
                ];
            case 'patches_due_30d':
                require_once __DIR__ . '/itm_patches_updates_integrations.php';
                $metric = itm_patches_updates_due_within_days_count($conn, $companyId, 30);
                $sparkline = itm_patches_updates_due_trend($conn, $companyId);
                return [
                    'metric' => $metric,
                    'subtitle' => 'Open patch rows with due date within 30 days',
                    'sparkline' => $sparkline,
                ];
            case 'visitors_today':
                $metric = itm_dashboard_query_visitors_today_count($conn, $companyId);
                $sparkline = itm_dashboard_query_visitors_trend($conn, $companyId);
                return [
                    'metric' => $metric,
                    'subtitle' => 'Visitor log entries today',
                    'sparkline' => $sparkline,
                ];
            case 'backup_tape_gaps':
                $metric = itm_dashboard_query_backup_tape_gaps_mtd($conn, $companyId);
                $sparkline = itm_dashboard_query_backup_tape_gaps_trend($conn, $companyId);
                return [
                    'metric' => $metric,
                    'subtitle' => 'Missing or failed backups MTD · all servers',
                    'sparkline' => $sparkline,
                ];
            default:
                return [
                    'metric' => 0,
                    'subtitle' => '',
                    'sparkline' => $emptySpark,
                ];
        }
    }
}

if (!function_exists('itm_dashboard_fetch_widgets_data_batch')) {
    /**
     * @param array<int,string> $slugs
     * @return array<string,array<string,mixed>>
     */
    function itm_dashboard_fetch_widgets_data_batch($conn, $companyId, $employeeId, array $slugs)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $out = [];
        foreach ($slugs as $slug) {
            $slug = trim((string)$slug);
            if ($slug === '' || !itm_dashboard_widget_can_show($conn, $companyId, $employeeId, $slug)) {
                continue;
            }
            $data = itm_dashboard_widget_fetch_data($conn, $companyId, $employeeId, $slug);
            $data['deep_link'] = itm_dashboard_widget_build_deep_link($slug, $companyId, $employeeId);
            $out[$slug] = $data;
        }

        return $out;
    }
}

if (!function_exists('itm_dashboard_load_smart_widgets')) {
    /**
     * Resolve visible widgets and fetch all metrics in one call for dashboard.php.
     *
     * @return array{widgets:array<int,array<string,mixed>>,data:array<string,array<string,mixed>>}
     */
    function itm_dashboard_load_smart_widgets($conn, $companyId, $employeeId)
    {
        $widgets = itm_dashboard_resolve_widgets_for_employee($conn, $companyId, $employeeId);
        $slugs = [];
        foreach ($widgets as $widget) {
            $slugs[] = (string)($widget['slug'] ?? '');
        }
        $data = itm_dashboard_fetch_widgets_data_batch($conn, $companyId, $employeeId, $slugs);

        return [
            'widgets' => $widgets,
            'data' => $data,
        ];
    }
}
