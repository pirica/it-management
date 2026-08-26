<?php
/**
 * Static audit: module PHP date display should use UK dd/mmm/yyyy helpers.
 *
 * Why: MySQL stores Y-m-d; list/view must route through itm_format_date_display() /
 * itm_format_cell_scalar_display() (or explicit date('d/M/Y')) — not raw ISO echo or
 * browser-native type="date" values shown to users without UK formatting.
 */

if (!function_exists('itm_module_date_format_display_audit_prefix_exempt_slug')) {
    /**
     * Prefix SKIP rules (canonical list: reports, ops_report, settings, backup_tape_log,
     * birthdays, resignations, calendar, explorer, hotel* — see exempt_module_slugs()).
     *
     * @return list<string> slug prefix patterns (hotel*, ops_report_* child modules)
     */
    function itm_module_date_format_display_audit_prefix_exempt_patterns(): array
    {
        return [
            'hotel',
            'ops_report_',
        ];
    }

    function itm_module_date_format_display_audit_prefix_exempt_slug(string $slug): bool
    {
        foreach (itm_module_date_format_display_audit_prefix_exempt_patterns() as $prefix) {
            if (strpos($slug, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_module_date_format_display_audit_exempt_module_slugs')) {
    /**
     * Bespoke / alternate date-contract modules excluded from UK dd/mmm/yyyy display audit.
     *
     * @return list<string>
     */
    function itm_module_date_format_display_audit_exempt_module_slugs(): array
    {
        return [
            'backup_tape_log',
            'birthdays',
            'resignations',
            'calendar',
            'explorer',
            'ops_report',
            'reports',
            'settings',
        ];
    }
}

if (!function_exists('itm_module_date_format_display_audit_is_exempt_module')) {
    function itm_module_date_format_display_audit_is_exempt_module(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        if (in_array($slug, itm_module_date_format_display_audit_exempt_module_slugs(), true)) {
            return true;
        }

        // Why: hotel* and ops_report_* child modules use bespoke date UX — out of flattened UK display audit scope.
        return itm_module_date_format_display_audit_prefix_exempt_slug($slug);
    }
}

if (!function_exists('itm_module_date_format_display_audit_exempt_module_notes')) {
    function itm_module_date_format_display_audit_exempt_module_notes(string $slug): string
    {
        if (strpos($slug, 'hotel') === 0) {
            return 'Hospitality module — d/M/Y contract; see check_hospitality_date_format.php';
        }

        if (strpos($slug, 'ops_report_') === 0) {
            return 'Ops report child table — bespoke daily grid; parent ops_report exempt';
        }

        $notes = [
            'backup_tape_log' => 'Bespoke monthly grid with custom log_date UX',
            'birthdays' => 'Bespoke read-only list — out of flattened date-display scope',
            'resignations' => 'Bespoke read-only list — out of flattened date-display scope',
            'calendar' => 'Integrated calendar grid — alternate date presentation',
            'explorer' => 'File browser — no standard list/view date column contract',
            'ops_report' => 'Bespoke daily ops grid — report_date selectors and d.m.y UI suffix',
            'reports' => 'Reports hub — bespoke dashboards and saved-report widgets',
            'settings' => 'Admin UI configuration — no flattened list/view date column contract',
        ];

        return $notes[$slug] ?? 'Module exempt from UK dd/mmm/yyyy display audit';
    }
}

if (!function_exists('itm_module_date_format_display_audit_build_module_skip_row')) {
    /**
     * @return array<string,mixed>
     */
    function itm_module_date_format_display_audit_build_module_skip_row(string $slug): array
    {
        return [
            'status' => 'skip',
            'module' => $slug,
            'file' => 'modules/' . $slug,
            'line' => 0,
            'pattern' => 'module_skip',
            'format' => 'n/a',
            'notes' => itm_module_date_format_display_audit_exempt_module_notes($slug),
            'snippet' => '',
        ];
    }
}

if (!function_exists('itm_module_date_format_display_audit_collect_module_files')) {
    /**
     * @return list<string> Absolute paths
     */
    function itm_module_date_format_display_audit_collect_module_files(string $repoRoot, string $slug): array
    {
        $paths = [];
        $moduleDir = rtrim($repoRoot, '/\\') . '/modules/' . $slug;
        if (!is_dir($moduleDir)) {
            return [];
        }

        foreach (glob($moduleDir . '/*.php') ?: [] as $path) {
            if (is_file($path)) {
                $paths[$path] = $path;
            }
        }

        foreach (glob($moduleDir . '/includes/*.php') ?: [] as $path) {
            if (is_file($path)) {
                $paths[$path] = $path;
            }
        }

        // Why: partials live under includes/partials/ (e.g. modules/short-url/).
        $includesDir = $moduleDir . '/includes';
        if (is_dir($includesDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($includesDir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
                    $paths[$fileInfo->getPathname()] = $fileInfo->getPathname();
                }
            }
        }

        $apiDir = $moduleDir . '/api';
        if (is_dir($apiDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($apiDir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
                    $paths[$fileInfo->getPathname()] = $fileInfo->getPathname();
                }
            }
        }

        return array_values($paths);
    }
}

if (!function_exists('itm_module_date_format_display_audit_list_module_slugs')) {
    /**
     * @return list<string>
     */
    function itm_module_date_format_display_audit_list_module_slugs(string $repoRoot): array
    {
        $modulesDir = rtrim($repoRoot, '/\\') . '/modules';
        if (!is_dir($modulesDir)) {
            return [];
        }

        $slugs = [];
        foreach (scandir($modulesDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $modulesDir . '/' . $entry;
            if (is_dir($path)) {
                $slugs[] = $entry;
            }
        }

        sort($slugs);

        return $slugs;
    }
}

if (!function_exists('itm_module_date_format_display_audit_rel_path')) {
    function itm_module_date_format_display_audit_rel_path(string $repoRoot, string $absPath): string
    {
        $root = rtrim(str_replace('\\', '/', $repoRoot), '/');
        $path = str_replace('\\', '/', $absPath);

        if (strpos($path, $root . '/') === 0) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }
}

if (!function_exists('itm_module_date_format_display_audit_trim_snippet')) {
    function itm_module_date_format_display_audit_trim_snippet(string $line): string
    {
        $snippet = trim($line);
        if (strlen($snippet) > 120) {
            return substr($snippet, 0, 117) . '...';
        }

        return $snippet;
    }
}

if (!function_exists('itm_module_date_format_display_audit_line_rules')) {
    /**
     * @return list<array{status:string,pattern:string,format:string,regex:string,notes:string}>
     */
    function itm_module_date_format_display_audit_line_rules(bool $isHospitality, bool $includeInputs = false): array
    {
        $inputStatus = $includeInputs ? 'warn' : 'skip';
        $rules = [
            [
                'status' => 'ok',
                'pattern' => 'itm_format_date_display',
                'format' => 'dd/mmm/yyyy',
                'regex' => '/itm_format_date_display\s*\(/',
                'notes' => 'UK date helper (d/M/Y)',
            ],
            [
                'status' => 'ok',
                'pattern' => 'itm_format_cell_scalar_display',
                'format' => 'dd/mmm/yyyy (routed)',
                'regex' => '/itm_format_cell_scalar_display\s*\(/',
                'notes' => 'Cell scalar routes date/datetime fields',
            ],
            [
                'status' => 'ok',
                'pattern' => 'itm_format_datetime_display',
                'format' => 'dd/mmm/yyyy HH:MM',
                'regex' => '/itm_format_datetime_display\s*\(/',
                'notes' => 'UK datetime helper',
            ],
            [
                'status' => 'ok',
                'pattern' => 'itm_format_audit_timestamp_display',
                'format' => 'd-m-Y - H:i:s (audit)',
                'regex' => '/itm_format_audit_timestamp_display\s*\(/',
                'notes' => 'Audit stamp contract (not list date)',
            ],
            [
                'status' => 'ok',
                'pattern' => 'itm_format_hotel_date_display',
                'format' => 'dd/mmm/yyyy',
                'regex' => '/itm_format_hotel_date_display\s*\(/',
                'notes' => 'Hospitality stay-date helper (same d/M/Y contract)',
            ],
            [
                'status' => 'ok',
                'pattern' => 'date_dmy_mon_slash',
                'format' => 'dd/mmm/yyyy',
                'regex' => '/date\s*\(\s*[\'"]d\/M\/Y/i',
                'notes' => 'Explicit UK date() format with abbreviated month',
            ],
            [
                'status' => 'warn',
                'pattern' => 'date_dmy_slash',
                'format' => 'dd/mm/yyyy (legacy)',
                'regex' => '/date\s*\(\s*[\'"]d\/m\/Y/i',
                'notes' => 'Legacy numeric-month date() — use itm_format_date_display() or date(\'d/M/Y\')',
            ],
            [
                'status' => 'ok',
                'pattern' => 'itm_render_uk_date_input',
                'format' => 'dd/mmm/yyyy (UK widget)',
                'regex' => '/itm_render_uk_date_input\s*\(/',
                'notes' => 'Shared UK text + calendar input helper',
            ],
            [
                'status' => $inputStatus,
                'pattern' => 'html_date_input',
                'format' => 'browser ISO (Y-m-d)',
                'regex' => '/type\s*=\s*[\'"]date[\'"]/i',
                'notes' => 'Native date input (ISO value) — skipped by default; pass --include-inputs to WARN',
            ],
            [
                'status' => $inputStatus,
                'pattern' => 'html_datetime_local_input',
                'format' => 'browser ISO (Y-m-dTH:i)',
                'regex' => '/type\s*=\s*[\'"]datetime-local[\'"]/i',
                'notes' => 'Native datetime-local — skipped by default; pass --include-inputs to WARN',
            ],
            [
                'status' => 'warn',
                'pattern' => 'date_iso_storage',
                'format' => 'Y-m-d',
                'regex' => '/date\s*\(\s*[\'"]Y-m-d/i',
                'notes' => 'date() with ISO pattern — OK for storage; WARN when echoed to UI',
            ],
            [
                'status' => 'warn',
                'pattern' => 'datetime_iso_storage',
                'format' => 'Y-m-d H:i:s',
                'regex' => '/date\s*\(\s*[\'"]Y-m-d[\s\\\\T]/i',
                'notes' => 'date() with ISO datetime — OK for storage; WARN when echoed to UI',
            ],
            [
                'status' => 'warn',
                'pattern' => 'datetime_format_iso',
                'format' => 'Y-m-d',
                'regex' => '/->format\s*\(\s*[\'"]Y-m-d/i',
                'notes' => 'DateTime::format ISO',
            ],
            [
                'status' => 'warn',
                'pattern' => 'date_us_slash',
                'format' => 'm/d/Y',
                'regex' => '/date\s*\(\s*[\'"]m\/d\/Y/i',
                'notes' => 'US date format',
            ],
            [
                'status' => 'warn',
                'pattern' => 'date_text_month_name',
                'format' => 'text month',
                'regex' => '/date\s*\(\s*[\'"][^\'"]*F[^\'"]*[\'"]/i',
                'notes' => 'date() with full month name (F)',
            ],
            [
                'status' => 'warn',
                'pattern' => 'raw_iso_date_echo',
                'format' => 'Y-m-d (raw)',
                'regex' => '/(?:echo|sanitize)\s*\([^;]*\[\s*[\'"](?:due_date|[^\'"]*_date|created_at|updated_at|deleted_at|resolved_at|completed_at|issued_at|submitted_at|first_response_at|sla_[a-z_]+_at|csat_submitted_at|expiry_date|purchase_date|certificate_expiry|warranty_expiry|log_date|report_date|date_from|date_to)[\'"]\s*\]/i',
                'notes' => 'Date-like field echoed without itm_format_* helper on the same line',
            ],
        ];

        return $rules;
    }
}

if (!function_exists('itm_module_date_format_display_audit_line_has_ok_helper')) {
    function itm_module_date_format_display_audit_line_has_ok_helper(string $line): bool
    {
        return (bool) preg_match(
            '/itm_format_(?:date_display|cell_scalar_display|datetime_display|audit_timestamp_display|hotel_date_display)\s*\(|appt_format_date_display\s*\(|myactivity_format_display_datetime\s*\(|itm_render_uk_date_input\s*\(|date\s*\(\s*[\'"]d\/M\/Y/i',
            $line
        );
    }
}

if (!function_exists('itm_module_date_format_display_audit_line_is_form_value_binding')) {
    function itm_module_date_format_display_audit_line_is_form_value_binding(string $line): bool
    {
        return (bool) preg_match('/<input\b|value\s*=\s*[\'"]<\?php|name\s*=\s*[\'"](?:date_|due_date|created_at)/i', $line);
    }
}

if (!function_exists('itm_module_date_format_display_audit_scan_file')) {
    /**
     * @return list<array<string,mixed>>
     */
    function itm_module_date_format_display_audit_scan_file(string $repoRoot, string $slug, string $absPath, bool $includeInputs = false): array
    {
        $content = file_get_contents($absPath);
        if ($content === false) {
            return [[
                'status' => 'skip',
                'module' => $slug,
                'file' => itm_module_date_format_display_audit_rel_path($repoRoot, $absPath),
                'line' => 0,
                'pattern' => 'unreadable',
                'format' => '',
                'notes' => 'Could not read file',
                'snippet' => '',
            ]];
        }

        $isHospitality = strpos($slug, 'hotel') === 0;
        $rules = itm_module_date_format_display_audit_line_rules($isHospitality, $includeInputs);
        $rel = itm_module_date_format_display_audit_rel_path($repoRoot, $absPath);
        $rows = [];

        $lines = preg_split('/\R/', $content) ?: [];
        foreach ($lines as $index => $line) {
            $lineNo = $index + 1;
            $trimmed = trim((string) $line);
            if ($trimmed === '' || strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) {
                continue;
            }

            foreach ($rules as $rule) {
                if (!preg_match($rule['regex'], $line)) {
                    continue;
                }

                $status = (string) $rule['status'];
                if ($rule['pattern'] === 'raw_iso_date_echo' && itm_module_date_format_display_audit_line_has_ok_helper($line)) {
                    continue;
                }
                if ($rule['pattern'] === 'raw_iso_date_echo' && itm_module_date_format_display_audit_line_is_form_value_binding($line)) {
                    continue;
                }
                if ($status === 'warn' && in_array($rule['pattern'], ['date_iso_storage', 'datetime_iso_storage'], true)) {
                    if (preg_match('/\becho\b|<\?=\s*sanitize|>\s*<\?php\s+echo/i', $line)) {
                        $status = 'warn';
                    } else {
                        continue;
                    }
                }

                $rows[] = [
                    'status' => $status,
                    'module' => $slug,
                    'file' => $rel,
                    'line' => $lineNo,
                    'pattern' => (string) $rule['pattern'],
                    'format' => (string) $rule['format'],
                    'notes' => (string) $rule['notes'],
                    'snippet' => itm_module_date_format_display_audit_trim_snippet($line),
                ];
            }
        }

        return $rows;
    }
}

if (!function_exists('itm_module_date_format_display_audit_pass_module_slugs')) {
    /**
     * @param list<string> $slugs
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    function itm_module_date_format_display_audit_pass_module_slugs(array $slugs, array $rows): array
    {
        $warnModules = [];
        foreach ($rows as $row) {
            if ((string) ($row['status'] ?? '') === 'warn') {
                $warnModules[(string) ($row['module'] ?? '')] = true;
            }
        }

        $pass = [];
        foreach ($slugs as $slug) {
            if ($slug !== '' && !isset($warnModules[$slug]) && !itm_module_date_format_display_audit_is_exempt_module($slug)) {
                $pass[] = $slug;
            }
        }

        sort($pass);

        return $pass;
    }
}

if (!function_exists('itm_module_date_format_display_audit_run')) {
    /**
     * @param array{root:string,module?:string,only_warn?:bool,all?:bool,include_inputs?:bool,include_skips?:bool,show_pass?:bool,show_module_skips?:bool} $options
     * @return list<array<string,mixed>>
     */
    function itm_module_date_format_display_audit_run(array $options): array
    {
        $repoRoot = rtrim((string) ($options['root'] ?? ''), '/\\');
        $moduleFilter = trim((string) ($options['module'] ?? ''));
        $onlyWarn = !empty($options['only_warn']);
        $showAll = !empty($options['all']);
        $includeInputs = !empty($options['include_inputs']);
        $includeSkips = !empty($options['include_skips']);
        $showPass = !empty($options['show_pass']);
        $showModuleSkips = !array_key_exists('show_module_skips', $options) || !empty($options['show_module_skips']);

        $slugs = $moduleFilter !== ''
            ? [$moduleFilter]
            : itm_module_date_format_display_audit_list_module_slugs($repoRoot);

        $scannedSlugs = [];
        $rows = [];
        foreach ($slugs as $slug) {
            if (itm_module_date_format_display_audit_is_exempt_module($slug)) {
                if ($showModuleSkips) {
                    $rows[] = itm_module_date_format_display_audit_build_module_skip_row($slug);
                }
                continue;
            }

            $files = itm_module_date_format_display_audit_collect_module_files($repoRoot, $slug);
            if ($files === []) {
                if ($moduleFilter !== '') {
                    $rows[] = [
                        'status' => 'skip',
                        'module' => $slug,
                        'file' => 'modules/' . $slug,
                        'line' => 0,
                        'pattern' => 'missing_module',
                        'format' => '',
                        'notes' => 'Module folder not found or has no PHP entry files',
                        'snippet' => '',
                    ];
                }
                continue;
            }

            $scannedSlugs[] = $slug;

            foreach ($files as $filePath) {
                foreach (itm_module_date_format_display_audit_scan_file($repoRoot, $slug, $filePath, $includeInputs) as $row) {
                    $status = (string) ($row['status'] ?? 'ok');
                    if ($onlyWarn && $status !== 'warn') {
                        continue;
                    }
                    if (!$showAll && !$onlyWarn && $status === 'ok') {
                        continue;
                    }
                    if (!$includeSkips && $status === 'skip') {
                        continue;
                    }
                    $rows[] = $row;
                }
            }
        }

        if ($showPass) {
            foreach (itm_module_date_format_display_audit_pass_module_slugs($scannedSlugs, $rows) as $passSlug) {
                if ($onlyWarn || $showAll) {
                    $rows[] = [
                        'status' => 'ok',
                        'module' => $passSlug,
                        'file' => 'modules/' . $passSlug,
                        'line' => 0,
                        'pattern' => 'module_pass',
                        'format' => 'dd/mmm/yyyy',
                        'notes' => 'No WARN date display patterns in scanned PHP',
                        'snippet' => '',
                    ];
                }
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $moduleCmp = strcmp((string) ($a['module'] ?? ''), (string) ($b['module'] ?? ''));
            if ($moduleCmp !== 0) {
                return $moduleCmp;
            }
            $fileCmp = strcmp((string) ($a['file'] ?? ''), (string) ($b['file'] ?? ''));
            if ($fileCmp !== 0) {
                return $fileCmp;
            }
            return ((int) ($a['line'] ?? 0)) <=> ((int) ($b['line'] ?? 0));
        });

        return $rows;
    }
}
