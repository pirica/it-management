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
        $ts = strtotime((string)$createdAt);
        if ($ts === false) {
            return (string)$createdAt;
        }

        return date('d M Y, H:i', $ts);
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
        return 'Email Management SMTP configurations and alert rules are not private — those changes are still audited.';
    }
}
