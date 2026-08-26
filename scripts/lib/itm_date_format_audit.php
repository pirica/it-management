<?php
/**
 * Shared date-format audit helpers for check_date_format.php and check_hospitality_date_format.php.
 */

if (!function_exists('itm_date_format_audit_repo_root')) {
    function itm_date_format_audit_repo_root()
    {
        return dirname(__DIR__, 2);
    }
}

if (!function_exists('itm_date_format_audit_pass')) {
    function itm_date_format_audit_pass($message)
    {
        echo '[PASS] ' . $message . "\n";
    }
}

if (!function_exists('itm_date_format_audit_fail')) {
    function itm_date_format_audit_fail($message, &$failCount)
    {
        $failCount++;
        echo '[FAIL] ' . $message . "\n";
    }
}

if (!function_exists('itm_date_format_audit_hospitality_roots')) {
    /**
     * @return string[]
     */
    function itm_date_format_audit_hospitality_roots()
    {
        $repoRoot = itm_date_format_audit_repo_root();
        $roots = [$repoRoot . '/booking'];
        $modulesDir = $repoRoot . '/modules';
        if (!is_dir($modulesDir)) {
            return $roots;
        }
        foreach (scandir($modulesDir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (strpos($entry, 'hotel') === 0 || strpos($entry, 'booking_') === 0) {
                $path = $modulesDir . '/' . $entry;
                if (is_dir($path)) {
                    $roots[] = $path;
                }
            }
        }
        return $roots;
    }
}

if (!function_exists('itm_date_format_audit_run_helper_contracts')) {
    /**
     * Runtime contract for includes/itm_date_format.php (UK + hospitality + audit stamps).
     *
     * @return int Failure count
     */
    function itm_date_format_audit_run_helper_contracts()
    {
        $fail = 0;
        $repoRoot = itm_date_format_audit_repo_root();
        require_once $repoRoot . '/includes/itm_date_format.php';

        if (itm_format_date_display('2026-08-31') === '31/Aug/2026') {
            itm_date_format_audit_pass('itm_format_date_display UK d/M/Y');
        } else {
            itm_date_format_audit_fail('itm_format_date_display expected 31/Aug/2026 got ' . itm_format_date_display('2026-08-31'), $fail);
        }

        if (itm_format_hotel_date_display('2026-08-31') === '31/Aug/2026'
            && itm_format_hotel_date_display('2026-10-01') === '01/Oct/2026') {
            itm_date_format_audit_pass('itm_format_hotel_date_display hospitality d/M/Y');
        } else {
            itm_date_format_audit_fail('itm_format_hotel_date_display hospitality contract broken', $fail);
        }

        if (itm_parse_date_input('31/08/2026') === '2026-08-31'
            && itm_parse_date_input('2026-08-31') === '2026-08-31'
            && itm_parse_date_input('31/Aug/2026') === '2026-08-31'
            && itm_parse_date_input('01/Oct/2026') === '2026-10-01') {
            itm_date_format_audit_pass('itm_parse_date_input UK + ISO + hospitality');
        } else {
            itm_date_format_audit_fail('itm_parse_date_input contract broken', $fail);
        }

        $dtDisplay = itm_format_datetime_display('2026-08-31 14:30:00');
        if ($dtDisplay === '31/Aug/2026 14:30') {
            itm_date_format_audit_pass('itm_format_datetime_display UK');
        } else {
            itm_date_format_audit_fail('itm_format_datetime_display expected 31/Aug/2026 14:30 got ' . $dtDisplay, $fail);
        }

        $auditDisplay = itm_format_audit_timestamp_display('2026-08-31 14:30:00');
        if ($auditDisplay === '31-08-2026 - 14:30:00') {
            itm_date_format_audit_pass('itm_format_audit_timestamp_display d-m-Y - H:i:s');
        } else {
            itm_date_format_audit_fail('itm_format_audit_timestamp_display expected 31-08-2026 - 14:30:00 got ' . $auditDisplay, $fail);
        }

        if (itm_format_cell_scalar_display('due_date', '2026-08-31') === '31/Aug/2026'
            && itm_format_cell_scalar_display('from_date', '2026-10-01') === '01/Oct/2026'
            && itm_format_cell_scalar_display('start_date', '2026-10-01', 'hotel_booking_room_type_blocks') === '01/Oct/2026'
            && itm_format_cell_scalar_display('start_date', '2026-10-01', 'events') === '01/10/2026') {
            itm_date_format_audit_pass('itm_format_cell_scalar_display UK vs hospitality routing');
        } else {
            itm_date_format_audit_fail('itm_format_cell_scalar_display routing broken', $fail);
        }

        if (itm_is_hospitality_date_field_name('check_in')
            && itm_is_hospitality_date_field_name('from_date')
            && !itm_is_hospitality_date_field_name('start_date')
            && itm_is_hospitality_date_field_name('start_date', 'hotel_booking_room_type_blocks')) {
            itm_date_format_audit_pass('itm_is_hospitality_date_field_name');
        } else {
            itm_date_format_audit_fail('itm_is_hospitality_date_field_name contract broken', $fail);
        }

        return $fail;
    }
}

if (!function_exists('itm_date_format_audit_run_hospitality_static')) {
    /**
     * Static scan: modules/hotel* + booking/ stay-date widgets and anti-patterns.
     *
     * @return int Failure count
     */
    function itm_date_format_audit_run_hospitality_static()
    {
        $fail = 0;
        $repoRoot = itm_date_format_audit_repo_root();
        $stayFieldPattern = '/\b(check_in|check_out|from_date|through_date|start_date|end_date)\b/';
        $badPatterns = [
            'label_format_example' => '/\(31\/Aug\/2026\)/',
            'placeholder_hospitality' => '/placeholder=["\']31\/Aug\/2026["\']/',
            'placeholder_ddmm' => '/placeholder=["\']dd\/mm\/yyyy["\']/',
        ];

        $scanned = 0;
        foreach (itm_date_format_audit_hospitality_roots() as $root) {
            if (!is_dir($root)) {
                itm_date_format_audit_fail('missing directory ' . str_replace($repoRoot . '/', '', $root), $fail);
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }
                $path = $fileInfo->getPathname();
                $ext = strtolower($fileInfo->getExtension());
                if (!in_array($ext, ['php', 'js'], true)) {
                    continue;
                }
                $rel = str_replace('\\', '/', substr($path, strlen($repoRoot) + 1));
                $content = file_get_contents($path);
                if ($content === false) {
                    itm_date_format_audit_fail('unreadable ' . $rel, $fail);
                    continue;
                }
                $scanned++;

                foreach ($badPatterns as $label => $pattern) {
                    if (preg_match($pattern, $content)) {
                        itm_date_format_audit_fail($rel . ' — ' . $label, $fail);
                    }
                }

                if ($ext === 'php' && preg_match($stayFieldPattern, $content)) {
                    if (preg_match('/itm_format_date_display\s*\(/', $content)
                        && preg_match('/\b(check_in|check_out|from_date|through_date)\b/', $content)) {
                        itm_date_format_audit_fail($rel . ' — uses itm_format_date_display on hospitality stay fields', $fail);
                    }
                }
            }
        }

        if ($scanned > 0) {
            itm_date_format_audit_pass('hospitality static scan: ' . $scanned . ' PHP/JS files');
        } else {
            itm_date_format_audit_fail('hospitality static scan: no files scanned', $fail);
        }

        return $fail;
    }
}

if (!function_exists('itm_date_format_audit_run_project_static')) {
    /**
     * Informational: scaffold modules with cr_render_cell_value should delegate dates to itm_format_cell_scalar_display().
     *
     * @return int Failure count (always 0 — informational only)
     */
    function itm_date_format_audit_run_project_static()
    {
        $repoRoot = itm_date_format_audit_repo_root();
        $modulesDir = $repoRoot . '/modules';
        if (!is_dir($modulesDir)) {
            itm_date_format_audit_pass('project scaffold scan: modules/ missing (skipped)');
            return 0;
        }

        $checked = 0;
        $info = 0;
        foreach (glob($modulesDir . '/*/index.php') as $path) {
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($path, strlen($repoRoot) + 1));
            if (strpos($content, 'function cr_render_cell_value') === false) {
                continue;
            }
            $checked++;
            if (strpos($content, 'itm_format_cell_scalar_display') === false) {
                echo '[INFO] ' . $rel . ' — cr_render_cell_value without itm_format_cell_scalar_display (review if date columns exist)' . "\n";
                $info++;
            }
        }

        itm_date_format_audit_pass('project scaffold scan: ' . $checked . ' index.php files (' . $info . ' without cell scalar hook)');
        return 0;
    }
}
