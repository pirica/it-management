<?php
/**
 * Shared helpers for modules/myactivity/ (employee-scoped audit activity).
 */

if (!function_exists('myactivity_build_query')) {
    function myactivity_build_query(array $params)
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $normalized[$key] = $value;
        }

        return http_build_query($normalized);
    }
}

if (!function_exists('myactivity_resolve_list_sort')) {
    /**
     * @return array{sort:string,dir:string}
     */
    function myactivity_resolve_list_sort(string $sort, string $dir): array
    {
        $sortable = ['created_at', 'action', 'table_name', 'record_id'];
        $sort = trim($sort);
        if (!in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }
        $dir = strtoupper(trim($dir)) === 'ASC' ? 'ASC' : 'DESC';

        return ['sort' => $sort, 'dir' => $dir];
    }
}

if (!function_exists('myactivity_allowed_actions')) {
    /**
     * @return string[]
     */
    function myactivity_allowed_actions()
    {
        return ['INSERT', 'UPDATE', 'DELETE'];
    }
}

if (!function_exists('myactivity_resolve_module_href')) {
    /**
     * Map audit table_name to a module href (matches user-config Recent Activity links).
     */
    function myactivity_resolve_module_href($tableName)
    {
        $tableName = trim((string)$tableName);
        if ($tableName === '' || !function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($tableName)) {
            return '';
        }

        if (function_exists('itm_sidebar_item_catalog')) {
            foreach (itm_sidebar_item_catalog() as $catalogId => $catalogItem) {
                if ($catalogId === $tableName || (($catalogItem['match_dir'] ?? '') === $tableName)) {
                    if (!empty($catalogItem['href'])) {
                        return (string)$catalogItem['href'];
                    }
                    break;
                }
            }
        }

        return 'modules/' . $tableName . '/';
    }
}

if (!function_exists('myactivity_format_display_datetime')) {
    function myactivity_format_display_datetime($createdAt)
    {
        if (function_exists('itm_format_datetime_display')) {
            $formatted = itm_format_datetime_display($createdAt);
            if ($formatted !== '') {
                return $formatted;
            }
        }

        return trim((string)$createdAt) === '' ? '—' : (string)$createdAt;
    }
}

if (!function_exists('myactivity_normalize_payload')) {
    function myactivity_normalize_payload($text)
    {
        $text = trim((string)$text);
        if ($text === '' || strcasecmp($text, 'null') === 0) {
            return '—';
        }

        return $text;
    }
}

if (!function_exists('myactivity_describe_payload')) {
    function myactivity_describe_payload($action, $normalizedValue, $isOldValue)
    {
        if ($normalizedValue !== '—') {
            return $normalizedValue;
        }

        $action = strtoupper(trim((string)$action));
        if ($isOldValue && $action === 'INSERT') {
            return '— Not applicable for INSERT events.';
        }
        if (!$isOldValue && $action === 'DELETE') {
            return '— Not applicable for DELETE events.';
        }

        return '—';
    }
}

if (!function_exists('myactivity_preview_text')) {
    function myactivity_preview_text($text, $limit = 120)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return '—';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) <= $limit) {
                return $text;
            }

            return mb_substr($text, 0, $limit) . '...';
        }
        if (strlen($text) <= $limit) {
            return $text;
        }

        return substr($text, 0, $limit) . '...';
    }
}

if (!function_exists('myactivity_action_chip_class')) {
    function myactivity_action_chip_class($action)
    {
        $action = strtoupper(trim((string)$action));
        if ($action === 'INSERT') {
            return 'insert';
        }
        if ($action === 'DELETE') {
            return 'delete';
        }

        return 'update';
    }
}

if (!function_exists('myactivity_build_search_conditions')) {
    /**
     * Employee-scoped audit list search (scalar audit_logs columns + registry module labels).
     *
     * @return array{sql:string,types:string,params:array<int,string>}
     */
    function myactivity_build_search_conditions($searchRaw)
    {
        $searchRaw = trim((string)$searchRaw);
        if ($searchRaw === '') {
            return ['sql' => '', 'types' => '', 'params' => []];
        }

        $searchPattern = (strpos($searchRaw, '%') !== false || strpos($searchRaw, '_') !== false)
            ? $searchRaw
            : '%' . $searchRaw . '%';

        $parts = [
            'al.table_name LIKE ?',
            'al.module_name LIKE ?',
            'al.action LIKE ?',
            'CAST(al.record_id AS CHAR) LIKE ?',
            'al.old_values LIKE ?',
            'al.new_values LIKE ?',
            'CAST(al.created_at AS CHAR) LIKE ?',
            'EXISTS (SELECT 1 FROM modules_registry mr WHERE mr.module_slug = al.table_name AND mr.module_name LIKE ?)',
        ];

        return [
            'sql' => '(' . implode(' OR ', $parts) . ')',
            'types' => str_repeat('s', count($parts)),
            'params' => array_fill(0, count($parts), $searchPattern),
        ];
    }
}

if (!function_exists('myactivity_private_audit_exempt_labels')) {
    /**
     * User-facing modules/data with no audit trail (AGENTS.md → Private data — no audit trail;
     * keep aligned with audit_logs_private_data_tables() in scripts/check_audit_logs_coverage.php).
     *
     * @return string[]
     */
    function myactivity_private_audit_exempt_labels()
    {
        return [
            'Passwords',
            'Private Contacts',
            'Notes',
            'Bookmarks',
            'Bookmark Folders',
            'To-Do',
            'Events',
            'Emails (send log only)',
            'Temporary share sessions (QR / code shares)',
        ];
    }
}

if (!function_exists('myactivity_private_audit_exempt_note')) {
    /**
     * Clarify audited Email Management areas excluded from the private list above.
     */
    function myactivity_private_audit_exempt_note()
    {
        return 'Within Email Management, only the send log is private; SMTP configurations and alert rules are audited like other shared settings.';
    }
}
