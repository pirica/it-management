<?php
/**
 * Live DB checks for db/migrations/*.sql (schema/data probes — applied history is migrate.php + schema_migrations).
 *
 * Discovers every *.sql file under db/migrations/ (filesystem glob, filename order).
 * Per-file probes: optional custom handler, else generic parse of CREATE TABLE / CREATE TRIGGER.
 */

if (!function_exists('itm_verify_db_migrations_legacy_share_tables')) {
    /**
     * @return list<string>
     */
    function itm_verify_db_migrations_legacy_share_tables()
    {
        return [
            'note_share_sessions',
            'password_share_sessions',
            'bookmark_share_sessions',
            'todo_share_sessions',
            'event_share_sessions',
            'private_contact_share_sessions',
            'explorer_share_sessions',
            'floor_plan_share_sessions',
            'rack_planner_share_sessions',
        ];
    }
}

if (!function_exists('itm_verify_db_migrations_table_exists')) {
    function itm_verify_db_migrations_table_exists($conn, $tableName)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $tableName = trim((string)$tableName);
        if ($tableName === '' || !function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($tableName)) {
            return false;
        }
        $schema = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $schemaEsc = mysqli_real_escape_string($conn, $schema);
        $tableEsc = mysqli_real_escape_string($conn, $tableName);
        $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = '{$schemaEsc}' AND table_name = '{$tableEsc}' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return false;
        }
        $exists = (bool)mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return $exists;
    }
}

if (!function_exists('itm_verify_db_migrations_column_exists')) {
    function itm_verify_db_migrations_column_exists($conn, $tableName, $columnName)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $tableName = trim((string)$tableName);
        $columnName = trim((string)$columnName);
        if ($tableName === '' || $columnName === '') {
            return false;
        }
        $schema = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $schemaEsc = mysqli_real_escape_string($conn, $schema);
        $tableEsc = mysqli_real_escape_string($conn, $tableName);
        $columnEsc = mysqli_real_escape_string($conn, $columnName);
        $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = '{$schemaEsc}' AND table_name = '{$tableEsc}' AND column_name = '{$columnEsc}' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return false;
        }
        $exists = (bool)mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return $exists;
    }
}

if (!function_exists('itm_verify_db_migrations_column_type')) {
    function itm_verify_db_migrations_column_type($conn, $tableName, $columnName)
    {
        if (!($conn instanceof mysqli)) {
            return '';
        }
        $tableName = trim((string)$tableName);
        $columnName = trim((string)$columnName);
        if ($tableName === '' || $columnName === '') {
            return '';
        }
        $schema = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $schemaEsc = mysqli_real_escape_string($conn, $schema);
        $tableEsc = mysqli_real_escape_string($conn, $tableName);
        $columnEsc = mysqli_real_escape_string($conn, $columnName);
        $sql = "SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = '{$schemaEsc}' AND table_name = '{$tableEsc}' AND column_name = '{$columnEsc}' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return '';
        }
        $type = '';
        if ($row = mysqli_fetch_assoc($res)) {
            $type = (string)($row['COLUMN_TYPE'] ?? $row['column_type'] ?? '');
        }
        mysqli_free_result($res);

        return $type;
    }
}

if (!function_exists('itm_verify_db_migrations_trigger_statement')) {
    function itm_verify_db_migrations_trigger_statement($conn, $triggerName)
    {
        if (!($conn instanceof mysqli)) {
            return '';
        }
        $triggerName = trim((string)$triggerName);
        if ($triggerName === '' || !function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($triggerName)) {
            return '';
        }
        $schema = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $schemaEsc = mysqli_real_escape_string($conn, $schema);
        $triggerEsc = mysqli_real_escape_string($conn, $triggerName);
        $sql = "SELECT action_statement FROM information_schema.triggers WHERE trigger_schema = '{$schemaEsc}' AND trigger_name = '{$triggerEsc}' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return '';
        }
        $body = '';
        if ($row = mysqli_fetch_assoc($res)) {
            $body = (string)($row['action_statement'] ?? $row['ACTION_STATEMENT'] ?? '');
        }
        mysqli_free_result($res);

        return $body;
    }
}

if (!function_exists('itm_verify_db_migrations_scalar_count')) {
    function itm_verify_db_migrations_scalar_count($conn, $sql)
    {
        if (!($conn instanceof mysqli)) {
            return -1;
        }
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return -1;
        }
        $count = -1;
        if ($row = mysqli_fetch_assoc($res)) {
            $count = (int)(reset($row) ?: 0);
        }
        mysqli_free_result($res);

        return $count;
    }
}

if (!function_exists('itm_verify_db_migrations_row')) {
    /**
     * @return array{file:string,status:string,label:string,detail:string}
     */
    function itm_verify_db_migrations_row($file, $status, $label, $detail)
    {
        return [
            'file' => (string)$file,
            'status' => (string)$status,
            'label' => (string)$label,
            'detail' => (string)$detail,
        ];
    }
}

if (!function_exists('itm_verify_db_migrations_dir')) {
    function itm_verify_db_migrations_dir()
    {
        return rtrim((string)ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'migrations';
    }
}

if (!function_exists('itm_verify_db_migrations_discover_files')) {
    /**
     * @return list<string> basenames sorted naturally
     */
    function itm_verify_db_migrations_discover_files()
    {
        $dir = itm_verify_db_migrations_dir();
        if (!is_dir($dir)) {
            return [];
        }
        $paths = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
        if ($paths === false) {
            return [];
        }
        $skipBootstrap = ['schema_migrations.sql'];
        $files = [];
        foreach ($paths as $path) {
            $base = basename($path);
            if ($base === '' || strtolower($base) === 'index.sql' || in_array($base, $skipBootstrap, true)) {
                continue;
            }
            $files[] = $base;
        }
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }
}

if (!function_exists('itm_verify_db_migrations_normalize_column_type')) {
    function itm_verify_db_migrations_normalize_column_type($type)
    {
        $type = strtolower(trim((string)$type));
        $type = preg_replace('/\s+/', ' ', $type) ?? $type;
        $type = str_replace('`', '', $type);

        if (preg_match('/^(enum\s*\([^)]+\))/i', $type, $enumMatch)) {
            return preg_replace('/\s+/', '', $enumMatch[1]) ?? $enumMatch[1];
        }

        if (preg_match('/^(bigint|int|tinyint|smallint|mediumint|varchar|char|text|timestamp|datetime|decimal|double|float|json)/i', $type, $baseMatch)) {
            $base = strtolower($baseMatch[1]);
            if ($base === 'decimal' || $base === 'double' || $base === 'float') {
                if (preg_match('/^(decimal|double|float)\(\s*[^)]+\)/i', $type, $precMatch)) {
                    return preg_replace('/\s+/', '', strtolower($precMatch[0])) ?? strtolower($precMatch[0]);
                }
            }

            return $base;
        }

        return trim($type);
    }
}

if (!function_exists('itm_verify_db_migrations_parse_sql')) {
    /**
     * @return array{
     *   triggers:list<string>,
     *   tables:array<string,array<string,string>>,
     *   trigger_markers:array<string,string>,
     *   is_dml_only:bool
     * }
     */
    function itm_verify_db_migrations_parse_sql($sql)
    {
        $sql = (string)$sql;
        $triggers = [];
        if (preg_match_all('/CREATE\s+TRIGGER\s+`([^`]+)`/i', $sql, $matches)) {
            foreach ($matches[1] as $triggerName) {
                $triggers[] = (string)$triggerName;
            }
        }

        $triggerMarkers = [];
        foreach ($triggers as $triggerName) {
            $pattern = '/CREATE\s+TRIGGER\s+`' . preg_quote($triggerName, '/') . '`.*?BEGIN\s*(.*?)\s*END/si';
            if (preg_match($pattern, $sql, $blockMatch)) {
                $body = preg_replace('/\s+/', ' ', (string)$blockMatch[1]) ?? '';
                if (stripos($body, 'COALESCE(@app_company_id') !== false) {
                    $triggerMarkers[$triggerName] = 'COALESCE(@app_company_id';
                }
            }
        }

        $tables = [];
        if (preg_match_all('/CREATE\s+TABLE\s+`([^`]+)`\s*\((.*?)\)\s*(?:ENGINE|DEFAULT\s+CHARSET|;)/si', $sql, $tableMatches, PREG_SET_ORDER)) {
            foreach ($tableMatches as $tableMatch) {
                $tableName = (string)$tableMatch[1];
                if (strpos($tableName, '_itm_') === 0 || preg_match('/_new$/i', $tableName)) {
                    continue;
                }
                $body = (string)$tableMatch[2];
                $columns = [];
                if (preg_match_all('/`([a-zA-Z0-9_]+)`\s+((?:bigint|int|tinyint|smallint|mediumint|varchar|char|text|enum|timestamp|datetime|decimal|double|float|json)(?:\([^)]*\))?)/i', $body, $colMatches, PREG_SET_ORDER)) {
                    foreach ($colMatches as $colMatch) {
                        $colName = (string)$colMatch[1];
                        if (in_array(strtoupper($colName), ['PRIMARY', 'KEY', 'UNIQUE', 'CONSTRAINT', 'INDEX'], true)) {
                            continue;
                        }
                        $columns[$colName] = trim((string)$colMatch[2]);
                    }
                }
                $tables[$tableName] = $columns;
            }
        }

        $hasDdl = ($triggers !== [] || $tables !== []);
        $hasDml = (bool)preg_match('/\b(UPDATE|INSERT\s+INTO|DELETE\s+FROM)\b/i', $sql);
        $isDmlOnly = $hasDml && !$hasDdl;

        return [
            'triggers' => $triggers,
            'tables' => $tables,
            'trigger_markers' => $triggerMarkers,
            'is_dml_only' => $isDmlOnly,
        ];
    }
}

if (!function_exists('itm_verify_db_migrations_probe_generic')) {
    /**
     * @param array<string, mixed> $parsed
     * @return array{file:string,status:string,label:string,detail:string}
     */
    function itm_verify_db_migrations_probe_generic($conn, $filename, $sql, array $parsed)
    {
        if (!empty($parsed['is_dml_only'])) {
            return itm_verify_db_migrations_row(
                $filename,
                'info',
                'DML only',
                'Idempotent data migration — no schema probe (verify manually or rely on fresh db/ import).'
            );
        }

        $failures = [];
        $passNotes = [];

        foreach ($parsed['triggers'] as $triggerName) {
            $body = itm_verify_db_migrations_trigger_statement($conn, $triggerName);
            if ($body === '') {
                $failures[] = $triggerName . ' missing';
                continue;
            }
            if (!empty($parsed['trigger_markers'][$triggerName])) {
                $marker = (string)$parsed['trigger_markers'][$triggerName];
                if (stripos($body, $marker) === false) {
                    $failures[] = $triggerName . ' body missing ' . $marker;
                }
            }
        }
        if ($parsed['triggers'] !== [] && $failures === []) {
            $passNotes[] = count($parsed['triggers']) . ' trigger(s) present';
        }

        foreach ($parsed['tables'] as $tableName => $columns) {
            if (!itm_verify_db_migrations_table_exists($conn, $tableName)) {
                $failures[] = 'table `' . $tableName . '` missing';
                continue;
            }
            $tableOk = true;
            $tableWarnings = [];
            foreach ($columns as $columnName => $expectedType) {
                if (!itm_verify_db_migrations_column_exists($conn, $tableName, $columnName)) {
                    $tableWarnings[] = $columnName . ' absent (may be superseded by later migration or fresh db/)';
                    continue;
                }
                $liveType = itm_verify_db_migrations_column_type($conn, $tableName, $columnName);
                $expectedNorm = itm_verify_db_migrations_normalize_column_type($expectedType);
                $liveNorm = itm_verify_db_migrations_normalize_column_type($liveType);
                if ($expectedNorm !== '' && $liveNorm !== '' && $expectedNorm !== $liveNorm) {
                    $failures[] = $tableName . '.' . $columnName . ' type ' . $liveType . ' (expected ' . $expectedNorm . ')';
                    $tableOk = false;
                }
            }
            if ($tableOk) {
                $note = '`' . $tableName . '` present';
                if ($tableWarnings !== []) {
                    $note .= '; ' . implode('; ', $tableWarnings);
                } else {
                    $note .= ' schema matches migration';
                }
                $passNotes[] = $note;
            }
        }

        if ($parsed['triggers'] === [] && $parsed['tables'] === []) {
            return itm_verify_db_migrations_row(
                $filename,
                'info',
                'Manual review',
                'Could not parse CREATE TABLE / CREATE TRIGGER expectations from file.'
            );
        }

        if ($failures !== []) {
            return itm_verify_db_migrations_row(
                $filename,
                'fail',
                'Not applied',
                implode('; ', $failures)
            );
        }

        $detail = $passNotes !== [] ? implode('; ', $passNotes) : 'Live schema matches migration file.';

        return itm_verify_db_migrations_row(
            $filename,
            'pass',
            'Applied',
            $detail
        );
    }
}

if (!function_exists('itm_verify_db_migrations_probe_custom')) {
    /**
     * @return array{file:string,status:string,label:string,detail:string}|null
     */
    function itm_verify_db_migrations_probe_custom($conn, $filename, $sql, array $parsed)
    {
        if ($filename === 'explorer_share.sql') {
            $legacyPresent = [];
            foreach (itm_verify_db_migrations_legacy_share_tables() as $legacyTable) {
                if (itm_verify_db_migrations_table_exists($conn, $legacyTable)) {
                    $legacyPresent[] = $legacyTable;
                }
            }
            $shareSessionsOk = itm_verify_db_migrations_table_exists($conn, 'share_sessions')
                && itm_verify_db_migrations_table_exists($conn, 'company_module_share')
                && itm_verify_db_migrations_column_exists($conn, 'share_sessions', 'module_slug')
                && itm_verify_db_migrations_column_exists($conn, 'share_sessions', 'scope_path')
                && itm_verify_db_migrations_column_exists($conn, 'share_sessions', 'scope_path_hash');
            $unifiedOk = $shareSessionsOk && $legacyPresent === [];

            if ($unifiedOk && !itm_verify_db_migrations_table_exists($conn, 'explorer_share_sessions')) {
                return itm_verify_db_migrations_row(
                    $filename,
                    'superseded',
                    'Superseded',
                    'unified share_sessions + company_module_share in place; legacy explorer_share_sessions absent.'
                );
            }
            if (itm_verify_db_migrations_table_exists($conn, 'explorer_share_sessions')) {
                return itm_verify_db_migrations_row(
                    $filename,
                    'fail',
                    'Stale',
                    'explorer_share_sessions still exists — apply unified share migration or fresh db/ import.'
                );
            }
            if ($unifiedOk) {
                return itm_verify_db_migrations_row(
                    $filename,
                    'superseded',
                    'Superseded',
                    'Unified share schema present; legacy explorer table never created.'
                );
            }

            return itm_verify_db_migrations_probe_generic($conn, $filename, $sql, $parsed);
        }

        if ($filename === 'employee_roles_admin_sidebar_personalize.sql') {
            $adminWrong = itm_verify_db_migrations_scalar_count(
                $conn,
                "SELECT COUNT(*) FROM employee_roles WHERE LOWER(TRIM(`name`)) = 'admin' AND `sidebar_show` <> 0"
            );
            $helpdeskWrong = itm_verify_db_migrations_scalar_count(
                $conn,
                "SELECT COUNT(*) FROM employee_roles WHERE LOWER(TRIM(`name`)) = 'helpdesk' AND `sidebar_show` <> 1"
            );
            $ok = ($adminWrong === 0 && $helpdeskWrong === 0);

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'Admin sidebar_show=0 and Helpdesk sidebar_show=1.'
                    : 'admin_wrong=' . (int)$adminWrong . ' helpdesk_wrong=' . (int)$helpdeskWrong
            );
        }

        if ($filename === 'demo_module_users.sql') {
            $missing = itm_verify_db_migrations_scalar_count(
                $conn,
                "SELECT 5 - COUNT(DISTINCT username) FROM employees WHERE username IN ('demo1','demo2','demo3','demo4','demo5') AND deleted_at IS NULL"
            );
            $ok = ($missing === 0);

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'info',
                $ok ? 'Applied' : 'DML only',
                $ok
                    ? 'demo1–demo5 employees present.'
                    : 'demo users incomplete (' . (int)$missing . ' missing) — run seed or fresh import.'
            );
        }

        if ($filename === 'hotel_bookings_auth2.sql') {
            [$strongSatisfied, $strongDetail] = itm_database_migrations_schema_satisfied($conn, 'hotel_bookings_auth2_strong.sql');
            if ($strongSatisfied) {
                return itm_verify_db_migrations_row(
                    $filename,
                    'superseded',
                    'Superseded',
                    'hotel_bookings.auth2 widened to varchar(12) via hotel_bookings_auth2_strong.sql'
                    . ($strongDetail !== '' ? ' (' . $strongDetail . ')' : '')
                );
            }

            return itm_verify_db_migrations_probe_generic($conn, $filename, $sql, $parsed);
        }

        if ($filename === 'appointment_type_label_allowed_json.sql') {
            $ok = itm_verify_db_migrations_column_exists($conn, 'appointment_type', 'label')
                && itm_verify_db_migrations_column_exists($conn, 'appointment_business_hours', 'allowed_types_json');

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'appointment_type.label and appointment_business_hours.allowed_types_json present.'
                    : 'Missing label or allowed_types_json — apply migration or fresh db/ import.'
            );
        }

        if ($filename === 'hotel_booking_portal_occupancy.sql') {
            $ok = itm_verify_db_migrations_table_exists($conn, 'hotel_booking_special_rates');

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'hotel_booking_special_rates table present.'
                    : 'hotel_booking_special_rates missing — apply migration or fresh db/ import.'
            );
        }

        if ($filename === 'hotel_booking_registry_icons.sql') {
            $badIcons = itm_verify_db_migrations_scalar_count(
                $conn,
                "SELECT COUNT(*) FROM modules_registry WHERE module_slug IN (
                    'hotel_bookings','hotel_booking_hotels','booking_rooms_types','hotel_booking_rooms',
                    'hotel_booking_amenities','hotel_booking_special_rates','hotel_booking_portal_rate_plans',
                    'hotel_booking_room_utilities','hotel_booking_housekeeping_statuses','hotel_bookings_future',
                    'hotel_bookings_present','hotel_bookings_history','hotel_booking_settings'
                ) AND (icon IS NULL OR TRIM(icon) = '' OR icon = '?')"
            );
            $ok = ($badIcons === 0);

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'Hospitality modules_registry icons populated.'
                    : (int)$badIcons . ' hospitality registry row(s) still missing icons.'
            );
        }

        if ($filename === 'hotel_booking_room_type_base_prices.sql') {
            $tableOk = itm_verify_db_migrations_table_exists($conn, 'hotel_booking_room_type_base_prices');
            $legacyPriceCol = itm_verify_db_migrations_column_exists($conn, 'booking_rooms_types', 'price_per_night');
            $ok = $tableOk && !$legacyPriceCol;

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'hotel_booking_room_type_base_prices present; booking_rooms_types.price_per_night removed.'
                    : 'Base prices table missing or legacy price_per_night column still on booking_rooms_types.'
            );
        }

        if ($filename === 'hotel_booking_settings_reviews_url_example.sql') {
            $emptyReviews = itm_verify_db_migrations_scalar_count(
                $conn,
                "SELECT COUNT(*) FROM hotel_booking_settings
                 WHERE deleted_at IS NULL
                   AND (reviews_url IS NULL OR TRIM(reviews_url) = '' OR reviews_url = 'https://www.tripadvisor.com/')"
            );
            $ok = ($emptyReviews === 0);

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'info',
                $ok ? 'Applied' : 'DML only',
                $ok
                    ? 'hotel_booking_settings.reviews_url populated for active rows.'
                    : (int)$emptyReviews . ' active settings row(s) still need reviews_url.'
            );
        }

        if ($filename === 'hotel_booking_settings_tourist_tax_seed.sql') {
            $unsetTax = itm_verify_db_migrations_scalar_count(
                $conn,
                'SELECT COUNT(*) FROM hotel_booking_settings
                 WHERE deleted_at IS NULL
                   AND (tourist_tax_per_person_per_night IS NULL OR tourist_tax_per_person_per_night = 0)'
            );
            $ok = ($unsetTax === 0);

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'info',
                $ok ? 'Applied' : 'DML only',
                $ok
                    ? 'tourist_tax_per_person_per_night set on active hotel_booking_settings rows.'
                    : (int)$unsetTax . ' active settings row(s) still have NULL/0 tourist tax.'
            );
        }

        if ($filename === 'hotel_booking_accessibility_portal.sql') {
            $ok = itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_accessible_banner_enabled')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_accessibility_options_enabled')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'urlaccessibilitypep')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_rooms', 'accessible_room');

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'hotel_booking_settings accessibility portal columns and hotel_booking_rooms.accessible_room present.'
                    : 'Missing accessibility portal columns — apply migration or fresh db/ import.'
            );
        }

        if ($filename === 'hotel_booking_portal_money_tax_labels.sql') {
            $ok = itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_max_discount_percent')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_tourist_tax_label')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_default_pet_max_weight_kg')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_hotels', 'portal_breakfast_child_age_min')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_hotels', 'portal_breakfast_child_age_max');

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'Portal money/tax label settings and hotel breakfast child age columns present.'
                    : 'Missing portal money/tax columns — apply migration or fresh db/ import.'
            );
        }

        if ($filename === 'hotel_booking_portal_display_config.sql') {
            $ok = itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_maps_base_url')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_calendar_month_horizon')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_occupancy_max_rooms')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_default_included_adults_per_room');

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'Portal display/maps/occupancy settings columns present.'
                    : 'Missing portal display config columns — apply migration or fresh db/ import.'
            );
        }

        if ($filename === 'hotel_booking_portal_cancellation_404.sql') {
            $ok = itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_cancellation_policy_not_found_url');

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'Portal cancellation policy not-found URL column present.'
                    : 'Missing portal_cancellation_policy_not_found_url — apply migration or fresh db/ import.'
            );
        }

        if ($filename === 'hotel_booking_portal_guest_copy.sql') {
            $ok = itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_manage_booking_label')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_accessible_room_banner_text')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_disabled_message')
                && itm_verify_db_migrations_column_exists($conn, 'hotel_booking_settings', 'portal_step_progress_template');

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'Portal guest copy settings columns present.'
                    : 'Missing portal guest copy columns — apply migration or fresh db/ import.'
            );
        }

        if ($filename === 'news_module_registry.sql') {
            $newsRows = itm_verify_db_migrations_scalar_count(
                $conn,
                "SELECT COUNT(*) FROM modules_registry WHERE module_slug = 'news'"
            );
            $ok = ($newsRows > 0);

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'fail',
                $ok ? 'Applied' : 'Not applied',
                $ok
                    ? 'modules_registry news slug present.'
                    : 'modules_registry row for news missing — apply migration or sync_modules_registry.'
            );
        }

        if ($filename === 'seed_replicate_location_rack_supplier_fk_remap.sql') {
            $crossFk = itm_verify_db_migrations_scalar_count(
                $conn,
                'SELECT COUNT(*) FROM it_locations child
                 INNER JOIN location_types src ON src.id = child.type_id AND src.company_id <> child.company_id'
            );
            $ok = ($crossFk === 0);

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'info',
                $ok ? 'Applied' : 'DML only',
                $ok
                    ? 'No cross-tenant location_types FK mismatches on it_locations.'
                    : (int)$crossFk . ' it_locations row(s) still reference foreign-company location_types.'
            );
        }

        if ($filename === 'ui_configuration_enable_chatbot_active.sql') {
            $disabled = itm_verify_db_migrations_scalar_count(
                $conn,
                'SELECT COUNT(*) FROM ui_configuration WHERE enable_chatbot = 0'
            );
            $ok = ($disabled === 0);

            return itm_verify_db_migrations_row(
                $filename,
                $ok ? 'pass' : 'info',
                $ok ? 'Applied' : 'DML only',
                $ok
                    ? 'All ui_configuration rows have enable_chatbot = 1.'
                    : (int)$disabled . ' ui_configuration row(s) still have enable_chatbot = 0.'
            );
        }

        return null;
    }
}

if (!function_exists('itm_verify_db_migrations_probe_file')) {
    /**
     * @return array{file:string,status:string,label:string,detail:string}
     */
    function itm_verify_db_migrations_probe_file($conn, $filename)
    {
        $path = itm_verify_db_migrations_dir() . DIRECTORY_SEPARATOR . $filename;
        if (!is_readable($path)) {
            return itm_verify_db_migrations_row(
                $filename,
                'fail',
                'Unreadable',
                'Migration file not readable on disk.'
            );
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            return itm_verify_db_migrations_row(
                $filename,
                'fail',
                'Unreadable',
                'Could not read migration file.'
            );
        }

        $parsed = itm_verify_db_migrations_parse_sql($sql);
        $custom = itm_verify_db_migrations_probe_custom($conn, $filename, $sql, $parsed);
        if ($custom !== null) {
            return $custom;
        }

        return itm_verify_db_migrations_probe_generic($conn, $filename, $sql, $parsed);
    }
}

if (!function_exists('itm_verify_db_migrations_report')) {
    /**
     * @return array{
     *   ok:bool,
     *   database:string,
     *   failures:int,
     *   file_count:int,
     *   summary:array{pass:int,fail:int,superseded:int,info:int},
     *   migrations:array<int,array{file:string,status:string,label:string,detail:string}>
     * }
     */
    function itm_verify_db_migrations_report($conn)
    {
        $database = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $rows = [];
        $failures = 0;
        $summary = ['pass' => 0, 'fail' => 0, 'superseded' => 0, 'info' => 0];

        $add = static function (array $row) use (&$rows, &$failures, &$summary) {
            $rows[] = $row;
            if ($row['status'] === 'fail') {
                $failures++;
                $summary['fail']++;
            } elseif ($row['status'] === 'superseded') {
                $summary['superseded']++;
            } elseif ($row['status'] === 'info') {
                $summary['info']++;
            } else {
                $summary['pass']++;
            }
        };

        if (!($conn instanceof mysqli)) {
            $add(itm_verify_db_migrations_row(
                '(database)',
                'fail',
                'No connection',
                'mysqli connection required.'
            ));

            return [
                'ok' => false,
                'database' => $database,
                'failures' => $failures,
                'file_count' => 0,
                'summary' => $summary,
                'migrations' => $rows,
            ];
        }

        $files = itm_verify_db_migrations_discover_files();
        if ($files === []) {
            $add(itm_verify_db_migrations_row(
                '(migrations)',
                'pass',
                'No incremental files',
                'db/migrations/ has no runner-scoped *.sql — canonical schema is db/01_schema.sql; history in schema_migrations table.'
            ));
        }

        foreach ($files as $filename) {
            $add(itm_verify_db_migrations_probe_file($conn, $filename));
        }

        return [
            'ok' => ($failures === 0),
            'database' => $database,
            'failures' => $failures,
            'file_count' => count($files),
            'summary' => $summary,
            'migrations' => $rows,
        ];
    }
}
