<?php
/**
 * Shared list-table UI contract checks (Search, Sort, Pagination, bulk actions, Actions column).
 *
 * Why: check_ui_configuration_coverage.php and fields_missing bespoke gate must share one
 * canonical implementation so excluded bespoke modules still get the same Settings contract.
 */

if (!function_exists('itm_ui_resolve_list_table_screen')) {
    /**
     * @return array{content:string,source:string}
     */
    function itm_ui_resolve_list_table_screen(string $indexContent, string $listAllContent): array
    {
        if ($indexContent !== '' && stripos($indexContent, '<table') !== false) {
            return ['content' => $indexContent, 'source' => 'index.php'];
        }

        if ($listAllContent !== '' && stripos($listAllContent, '<table') !== false) {
            return ['content' => $listAllContent, 'source' => 'list_all.php'];
        }

        return ['content' => $indexContent, 'source' => 'index.php'];
    }
}

if (!function_exists('itm_check_table_actions')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_table_actions(string $indexContent): array
    {
        if ($indexContent === '' || stripos($indexContent, '<table') === false) {
            return ['status' => 'n/a', 'details' => 'No table in index.php'];
        }

        $hasActionHeader = preg_match('/<th[^>]*>\s*(Actions?|Table\s+Actions|Options)\s*<\/th>/i', $indexContent) === 1;
        $hasMappedCell = stripos($indexContent, 'data-itm-actions-origin="1"') !== false
            || stripos($indexContent, 'data-itm-actions-origin=\'1\'') !== false;

        if ($hasActionHeader || $hasMappedCell) {
            return ['status' => 'pass', 'details' => 'Action column discoverable'];
        }

        return ['status' => 'fail', 'details' => 'Table exists but action column marker/header was not detected'];
    }
}

if (!function_exists('itm_ui_search_reset_control_detected')) {
    /**
     * Search-row reset must be emoji-only 🔙 on <a> (flattened CRUD contract — departments pattern).
     *
     * Why: plain "Clear" or other words fail; href may embed PHP — match visible link text only.
     */
    function itm_ui_search_reset_control_detected(string $content): bool
    {
        return preg_match('#>\s*🔙\s*</a>#u', $content) === 1;
    }
}

if (!function_exists('itm_ui_bookmarks_in_memory_list_search_detected')) {
    /**
     * Bookmarks decrypt private fields in PHP then filter in bkm_query_bookmarks_for_list() — no SQL LIKE in index.php.
     */
    function itm_ui_bookmarks_in_memory_list_search_detected(string $content): bool
    {
        return stripos($content, 'bkm_query_bookmarks_for_list(') !== false
            && stripos($content, '$searchRaw') !== false
            && preg_match('#[\'"]search[\'"]\s*=>#', $content) === 1;
    }
}

if (!function_exists('itm_ui_bookmarks_in_memory_list_sort_detected')) {
    /**
     * Bookmarks sort in PHP via bkm_query_bookmarks_for_list() — no SQL ORDER BY in index.php.
     */
    function itm_ui_bookmarks_in_memory_list_sort_detected(string $content): bool
    {
        return stripos($content, 'bkm_query_bookmarks_for_list(') !== false
            && preg_match('#[\'"]sort[\'"]\s*=>#', $content) === 1
            && stripos($content, '$sort') !== false
            && (preg_match('#[\'"]dir[\'"]\s*=>#', $content) === 1 || stripos($content, '$dir') !== false);
    }
}

if (!function_exists('itm_check_search')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_search(string $listContent, string $sourceLabel): array
    {
        if ($listContent === '' || stripos($listContent, '<table') === false) {
            return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
        }

        $hasSearchParam = preg_match('#\$_GET\s*\[\s*[\'"]search[\'"]\s*\]#', $listContent) === 1
            || stripos($listContent, '$searchRaw') !== false
            || preg_match('#\$search\s*=.*\$_GET\s*\[\s*[\'"]search[\'"]\s*\]#s', $listContent) === 1;
        $hasSearchInput = preg_match('#name\s*=\s*["\']search["\']#i', $listContent) === 1;
        $hasSearchQuery = stripos($listContent, 'searchConditions') !== false
            || itm_ui_bookmarks_in_memory_list_search_detected($listContent)
            || (stripos($listContent, 'LIKE') !== false && preg_match('#search(Raw|Pattern|Value|Esc|Like)|\$search\s*!==\s*[\'"][\s]*[\'"]#i', $listContent) === 1);
        $hasSearchReset = itm_ui_search_reset_control_detected($listContent);

        if ($hasSearchParam && $hasSearchInput && $hasSearchQuery && $hasSearchReset) {
            $details = 'Search input, server-side query, and emoji-only 🔙 reset detected in ' . $sourceLabel;
            if (itm_ui_bookmarks_in_memory_list_search_detected($listContent)) {
                $details = 'Search via bkm_query_bookmarks_for_list() (in-memory decrypt filter) and emoji-only 🔙 reset in ' . $sourceLabel;
            }

            return ['status' => 'pass', 'details' => $details];
        }

        $missing = [];
        if (!$hasSearchParam) {
            $missing[] = 'GET/search variable';
        }
        if (!$hasSearchInput) {
            $missing[] = 'search input';
        }
        if (!$hasSearchQuery) {
            $missing[] = 'server-side search conditions';
        }
        if (!$hasSearchReset) {
            $missing[] = 'search reset control (emoji-only 🔙 on <a>, not plain Clear or other text)';
        }

        return [
            'status' => 'fail',
            'details' => 'Table in ' . $sourceLabel . ' missing search wiring: ' . implode(', ', $missing),
        ];
    }
}

if (!function_exists('itm_check_sort')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_sort(string $listContent, string $sourceLabel): array
    {
        if ($listContent === '' || stripos($listContent, '<table') === false) {
            return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
        }

        $hasSortParam = preg_match('#\$_GET\s*\[\s*[\'"]sort[\'"]\s*\]#', $listContent) === 1
            || preg_match('#\$sort\s*=.*\$_GET\s*\[\s*[\'"]sort[\'"]\s*\]#s', $listContent) === 1;
        $hasDirParam = preg_match('#\$_GET\s*\[\s*[\'"]dir[\'"]\s*\]#', $listContent) === 1
            || preg_match('#\$dir\s*=.*\$_GET\s*\[\s*[\'"]dir[\'"]\s*\]#s', $listContent) === 1
            || preg_match('#\[\'ASC\'\s*,\s*\'DESC\'\]#', $listContent) === 1;
        $hasOrderBySort = preg_match('#ORDER\s+BY[^\n;]*(\$sortSql|sortableColumns|\$sort\b)#i', $listContent) === 1
            || itm_ui_bookmarks_in_memory_list_sort_detected($listContent);
        $hasSortUi = strpos($listContent, '▲') !== false
            || strpos($listContent, '▼') !== false
            || preg_match('#\$nextDir\s*=#', $listContent) === 1;

        if ($hasSortParam && $hasDirParam && $hasOrderBySort && $hasSortUi) {
            $details = 'Column sort (ASC/DESC) detected in ' . $sourceLabel;
            if (itm_ui_bookmarks_in_memory_list_sort_detected($listContent)) {
                $details = 'Column sort via bkm_query_bookmarks_for_list() (in-memory) in ' . $sourceLabel;
            }

            return [
                'status' => 'pass',
                'details' => $details,
            ];
        }

        $missing = [];
        if (!$hasSortParam) {
            $missing[] = 'GET/sort variable';
        }
        if (!$hasDirParam) {
            $missing[] = 'ASC/DESC direction';
        }
        if (!$hasOrderBySort) {
            $missing[] = 'ORDER BY sort wiring';
        }
        if (!$hasSortUi) {
            $missing[] = 'sortable header indicators (▲/▼ or $nextDir)';
        }

        return [
            'status' => 'fail',
            'details' => 'Table in ' . $sourceLabel . ' missing sort wiring: ' . implode(', ', $missing),
        ];
    }
}

if (!function_exists('itm_check_pagination')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_pagination(string $listContent, string $sourceLabel): array
    {
        if ($listContent === '' || stripos($listContent, '<table') === false) {
            return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
        }

        $usesRecordsPerPage = stripos($listContent, 'itm_resolve_records_per_page') !== false;
        $hasPerPageVar = preg_match('#\$perPage\s*=#', $listContent) === 1;
        $hasLimitPaging = preg_match('#LIMIT\s+[^\n;]*\$perPage#i', $listContent) === 1
            || (stripos($listContent, 'LIMIT') !== false && stripos($listContent, '$offset') !== false);
        $hasPageState = preg_match('#\$_GET\s*\[\s*[\'"]page[\'"]\s*\]#', $listContent) === 1
            || preg_match('#\$page\s*=#', $listContent) === 1;
        $hasPageNav = (stripos($listContent, 'Previous') !== false
                || stripos($listContent, 'Prev') !== false
                || stripos($listContent, '«') !== false)
            && stripos($listContent, 'Next') !== false;
        $hasRowTotal = stripos($listContent, '$totalPages') !== false || stripos($listContent, '$totalRows') !== false;

        if ($usesRecordsPerPage && $hasPerPageVar && $hasLimitPaging && $hasPageState && $hasPageNav && $hasRowTotal) {
            return [
                'status' => 'pass',
                'details' => 'Pagination uses itm_resolve_records_per_page() in ' . $sourceLabel,
            ];
        }

        $missing = [];
        if (!$usesRecordsPerPage) {
            $missing[] = 'itm_resolve_records_per_page()';
        }
        if (!$hasPerPageVar) {
            $missing[] = '$perPage';
        }
        if (!$hasLimitPaging) {
            $missing[] = 'LIMIT/OFFSET paging';
        }
        if (!$hasPageState) {
            $missing[] = 'page query state';
        }
        if (!$hasPageNav) {
            $missing[] = 'page navigation controls';
        }
        if (!$hasRowTotal) {
            $missing[] = 'total row/page count';
        }

        return [
            'status' => 'fail',
            'details' => 'Table in ' . $sourceLabel . ' missing pagination wiring: ' . implode(', ', $missing),
        ];
    }
}

if (!function_exists('itm_has_bulk_actions_records_per_page_gate')) {
    /**
     * Bulk actions must only be visible when row count meets Settings records_per_page.
     *
     * @return bool
     */
    function itm_has_bulk_actions_records_per_page_gate(string $listContent): bool
    {
        if (preg_match(
            '#\$showBulkActions\s*=.*\$perPage\s*(>=|>)\s*\$totalRows#is',
            $listContent
        ) === 1) {
            return false;
        }

        if (preg_match(
            '#if\s*\(\s*\$perPage\s*(>=|>)\s*\$totalRows[^)]*\)[\s\S]{0,2500}bulk[-_]delete#i',
            $listContent
        ) === 1) {
            return false;
        }

        if (preg_match(
            '#\$showBulkActions\s*=.*\$totalRows\s*(>=|>)\s*\$perPage#is',
            $listContent
        ) === 1) {
            return true;
        }

        if (preg_match(
            '#if\s*\(\s*\$totalRows\s*(>=|>)\s*\$perPage[^)]*\)[\s\S]{0,2500}bulk[-_]delete#i',
            $listContent
        ) === 1) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_bulk_row_checkbox_cells_gated')) {
    /**
     * When bulk UI is gated by records_per_page, tbody checkbox cells must use the same gate.
     *
     * @return bool
     */
    function itm_bulk_row_checkbox_cells_gated(string $listContent): bool
    {
        if (!itm_has_bulk_actions_records_per_page_gate($listContent)) {
            return true;
        }

        if (stripos($listContent, 'form="bulk-delete-form"') === false
            || stripos($listContent, 'name="ids[]"') === false) {
            return true;
        }

        $lines = preg_split('/\R/', $listContent) ?: [];
        $lineCount = count($lines);
        for ($i = 0; $i < $lineCount; $i++) {
            $line = $lines[$i];
            $probe = $line;
            if (stripos($probe, 'name="ids[]"') === false && $i > 0 && stripos($lines[$i - 1], '<td') !== false) {
                $probe = $lines[$i - 1] . ' ' . $line;
            }
            if (stripos($probe, 'name="ids[]"') === false || stripos($probe, 'bulk-delete-form') === false) {
                continue;
            }
            if (stripos($probe, '<td') === false) {
                continue;
            }
            if (stripos($probe, 'display:none') !== false) {
                continue;
            }
            if (stripos($probe, 'showBulkActions') !== false) {
                continue;
            }
            for ($j = max(0, $i - 6); $j < $i; $j++) {
                if (stripos($lines[$j], 'if ($showBulkActions)') !== false) {
                    continue 2;
                }
            }
            return false;
        }

        return true;
    }
}

if (!function_exists('itm_check_bulk_delete_actions')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_bulk_delete_actions(string $listContent, string $sourceLabel, bool $hasDeleteFile): array
    {
        if ($listContent === '' || stripos($listContent, '<table') === false) {
            return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
        }

        if (!$hasDeleteFile) {
            return ['status' => 'n/a', 'details' => 'Module has no delete.php'];
        }

        $hasBulkForm = stripos($listContent, 'bulk-delete-form') !== false
            || stripos($listContent, 'department-bulk-form') !== false;
        if (!$hasBulkForm) {
            return ['status' => 'n/a', 'details' => 'Bulk toolbar intentionally omitted'];
        }

        $hasBulkDelete = stripos($listContent, 'bulk_delete') !== false;
        $hasClearTable = stripos($listContent, 'clear_table') !== false;
        $hasSelectControl = stripos($listContent, 'Select to Delete') !== false
            || stripos($listContent, 'bulk-delete-toggle') !== false;
        $hasRecordsPerPageGate = itm_has_bulk_actions_records_per_page_gate($listContent);
        $hasGatedRowCheckboxes = itm_bulk_row_checkbox_cells_gated($listContent);
        $hasProgressiveBulkSelection = stripos($listContent, 'setSelectionVisibility') !== false
            && stripos($listContent, 'bulk-delete-toggle') !== false
            && stripos($listContent, 'Delete Selected') !== false;

        if ($hasBulkDelete && $hasClearTable && $hasSelectControl && $hasRecordsPerPageGate
            && ($hasGatedRowCheckboxes || $hasProgressiveBulkSelection)) {
            return [
                'status' => 'pass',
                'details' => 'Select to Delete and Clear Table controls gated when count >= records_per_page in ' . $sourceLabel,
            ];
        }

        $missing = [];
        if (!$hasBulkDelete) {
            $missing[] = 'bulk_delete action';
        }
        if (!$hasClearTable) {
            $missing[] = 'clear_table action';
        }
        if (!$hasSelectControl) {
            $missing[] = 'Select to Delete control';
        }
        if (!$hasRecordsPerPageGate) {
            $missing[] = 'records_per_page visibility gate (showBulkActions = $totalRows >= $perPage or equivalent)';
        }
        if (!$hasGatedRowCheckboxes && !$hasProgressiveBulkSelection) {
            $missing[] = 'tbody ids[] checkbox cells gated with showBulkActions (column alignment) or progressive bulk selection UI';
        }

        return [
            'status' => 'fail',
            'details' => 'Table in ' . $sourceLabel . ' missing bulk actions: ' . implode(', ', $missing),
        ];
    }
}

if (!function_exists('itm_ui_has_import_opt_out')) {
    function itm_ui_has_import_opt_out(string $content): bool
    {
        return preg_match('/data-itm-no-import-excel\s*=\s*(["\'])1\1/i', $content) === 1;
    }
}

if (!function_exists('itm_ui_has_export_opt_out')) {
    function itm_ui_has_export_opt_out(string $content, string $kind): bool
    {
        $attr = $kind === 'pdf' ? 'data-itm-no-export-pdf' : 'data-itm-no-export-excel';

        return preg_match('/' . preg_quote($attr, '/') . '\s*=\s*(["\'])1\1/i', $content) === 1;
    }
}

if (!function_exists('itm_check_import_excel_contract')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_import_excel_contract(string $listContent, string $indexContent, string $sourceLabel): array
    {
        if ($listContent === '' || stripos($listContent, '<table') === false) {
            return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
        }

        $combined = $listContent . "\n" . $indexContent;
        if (itm_ui_has_import_opt_out($combined)) {
            return [
                'status' => 'pass',
                'details' => 'Import Excel opted out via data-itm-no-import-excel in ' . $sourceLabel,
            ];
        }

        $hasEndpoint = preg_match(
            '/data-itm-db-import-endpoint\s*=\s*(["\'])([^"\']+)\1/i',
            $combined
        ) === 1;
        $hasHandler = stripos($indexContent, 'import_excel_rows') !== false;

        if ($hasEndpoint && $hasHandler) {
            return [
                'status' => 'pass',
                'details' => 'Import endpoint and import_excel_rows handler detected in ' . $sourceLabel,
            ];
        }

        $missing = [];
        if (!$hasEndpoint) {
            $missing[] = 'data-itm-db-import-endpoint on list table';
        }
        if (!$hasHandler) {
            $missing[] = 'import_excel_rows JSON handler in index.php';
        }

        return [
            'status' => 'fail',
            'details' => 'Table in ' . $sourceLabel . ' missing Import Excel wiring: ' . implode(', ', $missing),
        ];
    }
}

if (!function_exists('itm_check_export_toolbar_support')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_export_toolbar_support(string $primaryContent, ?string $extraContent = null): array
    {
        $content = $extraContent !== null ? ($primaryContent . "\n" . $extraContent) : $primaryContent;
        if ($content === '' || stripos($content, '<table') === false) {
            return ['status' => 'n/a', 'details' => 'No table in index.php'];
        }

        if (itm_ui_has_export_opt_out($content, 'excel') && itm_ui_has_export_opt_out($content, 'pdf')) {
            return [
                'status' => 'pass',
                'details' => 'Export toolbar opted out via data-itm-no-export-* attributes',
            ];
        }

        $hasCard = preg_match('/class\s*=\s*["\'][^"\']*card[^"\']*["\']/i', $content) === 1;
        if ($hasCard) {
            return ['status' => 'pass', 'details' => 'Card wrapper present for table-tools.js'];
        }

        return ['status' => 'fail', 'details' => 'Table exists but no .card wrapper was detected'];
    }
}

if (!function_exists('itm_check_sample_data')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_sample_data(string $indexContent): array
    {
        if (strpos($indexContent, 'cr_manageable_columns(') === false) {
            return ['status' => 'n/a', 'details' => 'Not a scaffold hybrid index.php'];
        }

        if (stripos($indexContent, '<table') === false) {
            return ['status' => 'n/a', 'details' => 'No table in index.php'];
        }

        $hasHandler = preg_match('/\$_POST\s*\[\s*[\'"]add_sample_data[\'"]\s*\]/', $indexContent) === 1
            || stripos($indexContent, "isset(\$_POST['add_sample_data'])") !== false;
        $hasButton = preg_match('/name\s*=\s*["\']add_sample_data["\']/i', $indexContent) === 1
            || stripos($indexContent, 'Add sample data') !== false;
        $hasCsrf = preg_match(
            '/itm_require_post_csrf\s*\(|cr_require_valid_csrf_token\s*\(|require_valid_csrf\s*\(/i',
            $indexContent
        ) === 1;

        if ($hasHandler && $hasButton && $hasCsrf) {
            return [
                'status' => 'pass',
                'details' => 'add_sample_data POST handler, button, and CSRF guard detected',
            ];
        }

        $missing = [];
        if (!$hasHandler) {
            $missing[] = 'add_sample_data POST handler';
        }
        if (!$hasButton) {
            $missing[] = 'Add sample data button';
        }
        if (!$hasCsrf) {
            $missing[] = 'CSRF guard on POST mutations';
        }

        return [
            'status' => 'fail',
            'details' => 'index.php missing sample data wiring: ' . implode(', ', $missing),
        ];
    }
}

if (!function_exists('itm_ui_extract_title_block')) {
    function itm_ui_extract_title_block(string $content): string
    {
        if (preg_match('~<title\b[^>]*>(.*?)</title>~is', $content, $matches) !== 1) {
            return '';
        }

        return '<title>' . trim((string) $matches[1]) . '</title>';
    }
}

if (!function_exists('itm_check_module_browser_title')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_module_browser_title(string $indexContent): array
    {
        if (stripos($indexContent, '<head') === false) {
            return ['status' => 'n/a', 'details' => 'No HTML head in index.php'];
        }

        $titleBlock = itm_ui_extract_title_block($indexContent);
        if ($titleBlock === '') {
            return ['status' => 'fail', 'details' => 'index.php missing <title> tag'];
        }

        if (!function_exists('itm_titles_list_title_matches_canonical')) {
            require_once __DIR__ . '/itm_titles_list_audit.php';
        }

        if (itm_titles_list_title_matches_canonical($titleBlock)) {
            return [
                'status' => 'pass',
                'details' => 'Canonical browser title uses sanitize($crud_title) and app name suffix',
            ];
        }

        $hasDynamicEmoji = stripos($indexContent, 'itm_resolve_module_sidebar_icon') !== false;
        $hasCrudTitleInTag = stripos($titleBlock, 'sanitize($crud_title)') !== false
            || stripos($titleBlock, 'sanitize( $crud_title )') !== false;
        $hasAppSuffix = stripos($titleBlock, 'itm_ui_config_app_name') !== false
            || stripos($titleBlock, '$app_name') !== false;

        if ($hasDynamicEmoji && $hasCrudTitleInTag && $hasAppSuffix) {
            return [
                'status' => 'pass',
                'details' => 'Dynamic module emoji title with sanitize($crud_title) and app name suffix',
            ];
        }

        return [
            'status' => 'fail',
            'details' => 'Browser <title> must use sanitize($crud_title) with app name suffix (see titles_list.php canonical pattern)',
        ];
    }
}

if (!function_exists('itm_check_module_favicon_link')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_module_favicon_link(string $indexContent): array
    {
        if (stripos($indexContent, '<head') === false) {
            return ['status' => 'n/a', 'details' => 'No HTML head in index.php'];
        }

        $headSection = $indexContent;
        if (preg_match('~<head\b[^>]*>(.*?)</head>~is', $indexContent, $matches) === 1) {
            $headSection = (string) $matches[1];
        }

        $hasFaviconLink = preg_match(
            '/<link\b[^>]*\brel\s*=\s*["\'](?:shortcut\s+icon|icon)["\']/i',
            $headSection
        ) === 1;
        $usesConfiguredFavicon = stripos($headSection, '$favicon_url') !== false
            || stripos($headSection, 'itm_ui_config_favicon_url') !== false
            || stripos($headSection, 'itm_render_head_favicon_link') !== false;

        if (stripos($headSection, 'itm_render_head_favicon_link') !== false) {
            return [
                'status' => 'pass',
                'details' => 'Server-side favicon via itm_render_head_favicon_link() (Settings favicon_url)',
            ];
        }

        if ($hasFaviconLink && $usesConfiguredFavicon) {
            return [
                'status' => 'pass',
                'details' => 'Server-side favicon link in head uses Settings favicon URL',
            ];
        }

        if ($hasFaviconLink) {
            return [
                'status' => 'fail',
                'details' => 'head favicon link must use $favicon_url or itm_render_head_favicon_link() from ui_configuration',
            ];
        }

        return [
            'status' => 'fail',
            'details' => 'Missing server-side favicon link rel=icon in head (header.php JS alone leaves the default tab icon)',
        ];
    }
}

if (!function_exists('itm_ui_index_has_server_managed_list_header')) {
    function itm_ui_index_has_server_managed_list_header(string $indexContent): bool
    {
        return stripos($indexContent, 'data-itm-new-button-managed') !== false;
    }
}

if (!function_exists('itm_ui_index_has_list_h1')) {
    function itm_ui_index_has_list_h1(string $indexContent): bool
    {
        return preg_match('/<h1\b/i', $indexContent) === 1;
    }
}

if (!function_exists('itm_ui_index_h1_is_create_edit_view_screen')) {
    function itm_ui_index_h1_is_create_edit_view_screen(string $h1Fragment): bool
    {
        return preg_match('/(?:New |Edit |View )\s*<\?php/i', $h1Fragment) === 1
            || preg_match('/(?:New |Edit |View )\s*<\?=/i', $h1Fragment) === 1
            || preg_match('/\$crud_action\s*===/i', $h1Fragment) === 1;
    }
}

if (!function_exists('itm_ui_index_h1_echoes_crud_title_only')) {
    /**
     * List/matrix index h1 that prints bare $crud_title (not scaffold create/edit/view headings).
     */
    function itm_ui_index_h1_echoes_crud_title_only(string $indexContent): bool
    {
        if (!itm_ui_index_has_list_h1($indexContent)) {
            return false;
        }

        if (!preg_match_all('/<h1\b[^>]*>[\s\S]*?<\/h1>/i', $indexContent, $matches)) {
            return false;
        }

        foreach ($matches[0] as $h1Block) {
            if (itm_ui_index_h1_is_create_edit_view_screen($h1Block)) {
                continue;
            }
            if (preg_match('/sanitize\s*\(\s*\$crud_title\s*\)/i', $h1Block) === 1
                && stripos($h1Block, '$moduleListHeading') === false
            ) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_ui_index_h1_echoes_module_list_heading')) {
    function itm_ui_index_h1_echoes_module_list_heading(string $indexContent): bool
    {
        if (!preg_match_all('/<h1\b[^>]*>[\s\S]*?<\/h1>/i', $indexContent, $matches)) {
            return false;
        }

        foreach ($matches[0] as $h1Block) {
            if (itm_ui_index_h1_is_create_edit_view_screen($h1Block)) {
                continue;
            }
            if (stripos($h1Block, '$moduleListHeading') !== false
                || preg_match('/sanitize\s*\(\s*\$moduleListHeading\s*\)/i', $h1Block) === 1
            ) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_ui_index_list_heading_emoji_source_present')) {
    function itm_ui_index_list_heading_emoji_source_present(string $indexContent): bool
    {
        if (stripos($indexContent, 'itm_sidebar_label_for_module(') !== false) {
            return true;
        }
        if (stripos($indexContent, 'itm_resolve_module_sidebar_icon(') !== false) {
            return true;
        }

        return preg_match(
            '/\$moduleListHeading\s*=[^;]*[\'"][^\'"\r\n]*\p{Extended_Pictographic}/u',
            $indexContent
        ) === 1;
    }
}

if (!function_exists('itm_check_list_heading_layout')) {
    /**
     * List index headers with Settings-managed new buttons must center the module h1.
     *
     * @return array{status:string,details:string}
     */
    function itm_check_list_heading_layout(string $indexContent): array
    {
        if (!itm_ui_index_has_list_h1($indexContent)) {
            return ['status' => 'n/a', 'details' => 'No list h1 heading in index.php'];
        }

        if (!itm_ui_index_has_server_managed_list_header($indexContent)) {
            if (itm_ui_index_h1_echoes_crud_title_only($indexContent)
                || itm_ui_index_h1_echoes_module_list_heading($indexContent)
            ) {
                return [
                    'status' => 'fail',
                    'details' => 'List index h1 must use data-itm-new-button-managed header row with centered sanitize($moduleListHeading)',
                ];
            }

            return ['status' => 'n/a', 'details' => 'No server-managed list header row'];
        }

        $hasRelativeWrapper = preg_match(
            '/data-itm-new-button-managed[^>]*style\s*=\s*["\'][^"\']*position\s*:\s*relative/i',
            $indexContent
        ) === 1;
        $hasCenteredHeading = preg_match(
            '/<h1\b[^>]*style\s*=\s*["\'][^"\']*position\s*:\s*absolute[^"\']*left\s*:\s*50%[^"\']*transform\s*:\s*translateX\s*\(\s*-50%\s*\)/i',
            $indexContent
        ) === 1;
        $usesDynamicHeading = stripos($indexContent, '$moduleListHeading') !== false
            || stripos($indexContent, 'itm_sidebar_label_for_module') !== false;
        $readsNewButtonSetting = stripos($indexContent, '$newButtonPosition') !== false
            && stripos($indexContent, 'new_button_position') !== false;
        $gatesLeftCreate = preg_match(
            '/in_array\s*\(\s*\$newButtonPosition\s*,\s*\[\s*[\'"]left[\'"]\s*,\s*[\'"]left_right[\'"]\s*\]/i',
            $indexContent
        ) === 1;
        $gatesRightCreate = preg_match(
            '/in_array\s*\(\s*\$newButtonPosition\s*,\s*\[\s*[\'"]right[\'"]\s*,\s*[\'"]left_right[\'"]\s*\]/i',
            $indexContent
        ) === 1;

        if ($hasRelativeWrapper && $hasCenteredHeading && $usesDynamicHeading
            && $readsNewButtonSetting && $gatesLeftCreate && $gatesRightCreate
        ) {
            return [
                'status' => 'pass',
                'details' => 'List h1 centered; new_button_position gates left/right create controls',
            ];
        }

        $missing = [];
        if (!$hasRelativeWrapper) {
            $missing[] = 'position:relative wrapper on data-itm-new-button-managed row';
        }
        if (!$hasCenteredHeading) {
            $missing[] = 'centered list h1 (position:absolute; left:50%; transform:translateX(-50%))';
        }
        if (!$usesDynamicHeading) {
            $missing[] = '$moduleListHeading from itm_sidebar_label_for_module()';
        }
        if (!$readsNewButtonSetting) {
            $missing[] = '$newButtonPosition from ui_configuration new_button_position';
        }
        if (!$gatesLeftCreate || !$gatesRightCreate) {
            $missing[] = 'left/right create buttons gated by $newButtonPosition (Settings)';
        }

        return [
            'status' => 'fail',
            'details' => 'List header layout mismatch: ' . implode(', ', $missing),
        ];
    }
}

if (!function_exists('itm_check_list_heading_emoji')) {
    /**
     * Index list h1 must resolve sidebar label/icon so Settings emoji overrides apply.
     *
     * @return array{status:string,details:string}
     */
    function itm_check_list_heading_emoji(string $indexContent): array
    {
        if (!itm_ui_index_has_list_h1($indexContent)) {
            return ['status' => 'n/a', 'details' => 'No list h1 heading in index.php'];
        }

        if (!itm_ui_index_has_server_managed_list_header($indexContent)) {
            if (itm_ui_index_h1_echoes_crud_title_only($indexContent)) {
                return [
                    'status' => 'fail',
                    'details' => 'List index h1 uses sanitize($crud_title) without sidebar emoji — assign $moduleListHeading via itm_sidebar_label_for_module()',
                ];
            }
            if (itm_ui_index_h1_echoes_module_list_heading($indexContent)) {
                if (itm_ui_index_list_heading_emoji_source_present($indexContent)) {
                    return [
                        'status' => 'pass',
                        'details' => 'List h1 uses Settings sidebar label/icon (emoji from catalog or overrides)',
                    ];
                }

                return [
                    'status' => 'fail',
                    'details' => 'List h1 missing emoji source — assign $moduleListHeading via itm_sidebar_label_for_module() or itm_resolve_module_sidebar_icon()',
                ];
            }

            return ['status' => 'n/a', 'details' => 'No list index h1 contract (custom heading markup)'];
        }

        $listH1UsesModuleHeading = preg_match(
            '/<h1\b[^>]*style\s*=\s*["\'][^"\']*position\s*:\s*absolute[^"\']*left\s*:\s*50%[^"\']*transform\s*:\s*translateX\s*\(\s*-50%\s*\)[^"\']*["\'][^>]*>\s*<\?php\s+echo\s+sanitize\s*\(\s*\$moduleListHeading\s*\)/i',
            $indexContent
        ) === 1;

        if (!$listH1UsesModuleHeading) {
            return [
                'status' => 'fail',
                'details' => 'List h1 must echo sanitize($moduleListHeading) in the centered index header row',
            ];
        }

        if (itm_ui_index_list_heading_emoji_source_present($indexContent)) {
            return [
                'status' => 'pass',
                'details' => 'List h1 uses Settings sidebar label/icon (emoji from catalog or overrides)',
            ];
        }

        return [
            'status' => 'fail',
            'details' => 'List h1 missing emoji source — assign $moduleListHeading via itm_sidebar_label_for_module() or itm_resolve_module_sidebar_icon()',
        ];
    }
}

if (!function_exists('itm_ui_index_has_actions_column')) {
    function itm_ui_index_has_actions_column(string $content): bool
    {
        if (preg_match('/<th\b[^>]*>[^<]*\bActions\b[^<]*<\/th>/i', $content) === 1) {
            return true;
        }
        if (preg_match('/\bitm-actions-cell\b/i', $content) === 1) {
            return true;
        }

        return preg_match('/data-itm-actions-origin\s*=/i', $content) === 1;
    }
}

if (!function_exists('itm_ui_index_has_csrf_guard')) {
    function itm_ui_index_has_csrf_guard(string $content): bool
    {
        foreach (['itm_require_post_csrf', 'cr_require_valid_csrf_token', 'require_valid_csrf'] as $pattern) {
            if (strpos($content, $pattern . '(') !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_ui_create_file_is_redirect_stub')) {
    /**
     * Why: Immutable/read-only modules keep create.php as a permission-gated redirect to index.
     */
    function itm_ui_create_file_is_redirect_stub(string $createContent): bool
    {
        $content = trim($createContent);
        if ($content === '') {
            return false;
        }

        $hasRedirect = preg_match(
            '/header\s*\(\s*[\'"]Location:\s*index\.php/i',
            $content
        ) === 1;
        if (!$hasRedirect) {
            return false;
        }

        $hasForm = preg_match('/<form\b/i', $content) === 1
            || preg_match('/\$_(POST|GET)\s*\[/', $content) === 1
            || preg_match('/\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]/', $content) === 1;

        return !$hasForm;
    }
}

if (!function_exists('itm_check_new_button')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_new_button(string $indexContent, bool $hasCreateFile, string $createContent = ''): array
    {
        if (!$hasCreateFile) {
            return ['status' => 'n/a', 'details' => 'Module has no create.php'];
        }

        if ($createContent !== '' && itm_ui_create_file_is_redirect_stub($createContent)) {
            return [
                'status' => 'n/a',
                'details' => 'create.php redirects to index (read-only / immutable module — no create action expected)',
            ];
        }

        $hasCreateLink = preg_match('/<a[^>]*href\s*=\s*["\'][^"\']*(create\.php|new|add)[^"\']*["\'][^>]*>/i', $indexContent) === 1
            && preg_match('/<a[^>]*class\s*=\s*["\'][^"\']*btn[^"\']*btn-primary[^"\']*["\'][^>]*href\s*=\s*["\'][^"\']*(create\.php|new|add)[^"\']*["\'][^>]*>|<a[^>]*href\s*=\s*["\'][^"\']*(create\.php|new|add)[^"\']*["\'][^>]*class\s*=\s*["\'][^"\']*btn[^"\']*btn-primary[^"\']*["\'][^>]*>/i', $indexContent) === 1;

        if ($hasCreateLink) {
            return ['status' => 'pass', 'details' => 'Primary create/add control detected'];
        }

        return ['status' => 'fail', 'details' => 'create.php exists but index.php has no detectable primary create/add action'];
    }
}

if (!function_exists('itm_ui_index_primary_create_link_pattern')) {
    function itm_ui_index_primary_create_link_pattern(): string
    {
        return '/<a\b(?=[^>]*\bhref\s*=\s*["\']create\.php["\'])(?=[^>]*\bclass\s*=\s*["\'][^"\']*\bbtn-primary\b)[^>]*>/i';
    }
}

if (!function_exists('itm_ui_index_split_managed_header_regions')) {
    /**
     * Split the server-managed list header row into left/right regions around the centered h1.
     *
     * @return array{left:string,right:string}|null
     */
    function itm_ui_index_split_managed_header_regions(string $indexContent): ?array
    {
        $managedPos = stripos($indexContent, 'data-itm-new-button-managed');
        if ($managedPos === false) {
            return null;
        }

        $h1Pos = stripos($indexContent, '<h1', $managedPos);
        if ($h1Pos === false) {
            return null;
        }

        $h1Close = stripos($indexContent, '</h1>', $h1Pos);
        if ($h1Close === false) {
            return null;
        }

        return [
            'left' => substr($indexContent, $managedPos, $h1Pos - $managedPos),
            'right' => substr($indexContent, $h1Close, 2500),
        ];
    }
}

if (!function_exists('itm_ui_index_region_has_new_button_position_gate')) {
    function itm_ui_index_region_has_new_button_position_gate(string $region, string $side): bool
    {
        if ($side === 'left') {
            return preg_match(
                "/in_array\s*\(\s*\\\$newButtonPosition\s*,\s*\[\s*['\"]left['\"]\s*,\s*['\"]left_right['\"]\s*\]/",
                $region
            ) === 1;
        }

        return preg_match(
            "/in_array\s*\(\s*\\\$newButtonPosition\s*,\s*\[\s*['\"]right['\"]\s*,\s*['\"]left_right['\"]\s*\]/",
            $region
        ) === 1;
    }
}

if (!function_exists('itm_check_new_button_position')) {
    /**
     * Settings new_button_position (left / right / left_right) must gate create.php slots in the managed header row.
     *
     * @return array{status:string,details:string}
     */
    function itm_check_new_button_position(string $indexContent, bool $hasCreateFile, string $createContent = ''): array
    {
        if (!$hasCreateFile) {
            return ['status' => 'n/a', 'details' => 'Module has no create.php'];
        }

        if ($createContent !== '' && itm_ui_create_file_is_redirect_stub($createContent)) {
            return [
                'status' => 'n/a',
                'details' => 'create.php redirects to index (read-only / immutable module — no create action expected)',
            ];
        }

        if (stripos($indexContent, 'data-itm-new-button-managed') === false) {
            return [
                'status' => 'n/a',
                'details' => 'No server-managed new-button row (ui-layout.js may relocate at runtime)',
            ];
        }

        $regions = itm_ui_index_split_managed_header_regions($indexContent);
        if ($regions === null) {
            return ['status' => 'n/a', 'details' => 'No centered list h1 inside server-managed header row'];
        }

        $readsSettings = preg_match(
            '/\$newButtonPosition\s*=.*?new_button_position/s',
            $indexContent
        ) === 1;
        $createPattern = itm_ui_index_primary_create_link_pattern();
        $leftGate = itm_ui_index_region_has_new_button_position_gate($regions['left'], 'left');
        $rightGate = itm_ui_index_region_has_new_button_position_gate($regions['right'], 'right');
        $leftCreate = preg_match($createPattern, $regions['left']) === 1;
        $rightCreate = preg_match($createPattern, $regions['right']) === 1;

        if ($readsSettings && $leftGate && $rightGate && $leftCreate && $rightCreate) {
            return [
                'status' => 'pass',
                'details' => 'create.php btn-primary gated by Settings new_button_position (left / right / left_right)',
            ];
        }

        $missing = [];
        if (!$readsSettings) {
            $missing[] = '$newButtonPosition from $ui_config[\'new_button_position\']';
        }
        if (!$leftGate) {
            $missing[] = 'left slot in_array($newButtonPosition, [\'left\', \'left_right\'])';
        }
        if (!$rightGate) {
            $missing[] = 'right slot in_array($newButtonPosition, [\'right\', \'left_right\'])';
        }
        if (!$leftCreate) {
            $missing[] = 'create.php btn-primary in left slot';
        }
        if (!$rightCreate) {
            $missing[] = 'create.php btn-primary in right slot';
        }

        return [
            'status' => 'fail',
            'details' => 'New button position contract mismatch: ' . implode(', ', $missing),
        ];
    }
}

if (!function_exists('itm_ui_index_has_btn_sm_create_link')) {
    function itm_ui_index_has_btn_sm_create_link(string $indexContent): bool
    {
        return preg_match(
            '/<a\b(?=[^>]*\bhref\s*=\s*["\'][^"\']*create\.php[^"\']*["\'])(?=[^>]*\bbtn-sm\b)[^>]*>/i',
            $indexContent
        ) === 1;
    }
}

if (!function_exists('itm_ui_index_canonical_list_new_button_pattern')) {
    function itm_ui_index_canonical_list_new_button_pattern(): string
    {
        return '/<a\b(?=[^>]*\bhref\s*=\s*["\']create\.php(?:\?[^"\']*)?["\'])(?=[^>]*\bclass\s*=\s*["\'][^"\']*\bbtn\b[^"\']*\bbtn-primary\b[^"\']*["\'])(?=[^>]*\btitle\s*=\s*["\']Create["\'])(?![^>]*\bbtn-sm\b)[^>]*>\s*➕\s*<\/a>/i';
    }
}

if (!function_exists('itm_ui_index_collect_list_new_button_anchors')) {
    /**
     * @return list<string>
     */
    function itm_ui_index_collect_list_new_button_anchors(string $indexContent): array
    {
        if (!preg_match_all(
            '/<a\b[^>]*\bhref\s*=\s*["\'][^"\']*create\.php[^"\']*["\'][^>]*>.*?<\/a>/is',
            $indexContent,
            $matches
        )) {
            return [];
        }

        return $matches[0];
    }
}

if (!function_exists('itm_ui_index_anchor_is_emoji_only_list_new_button')) {
    function itm_ui_index_anchor_is_emoji_only_list_new_button(string $anchorHtml): bool
    {
        if (!preg_match('/<a\b[^>]*>(.*?)<\/a>/is', $anchorHtml, $innerMatch)) {
            return false;
        }

        $inner = trim(html_entity_decode(strip_tags($innerMatch[1]), ENT_QUOTES, 'UTF-8'));

        return $inner === '➕';
    }
}

if (!function_exists('itm_check_new_button_style')) {
    /**
     * List-header create controls: canonical btn-primary ➕ markup, title="Create", 40×40 CSS footprint.
     *
     * @return array{status:string,details:string}
     */
    function itm_check_new_button_style(string $indexContent, bool $hasCreateFile, string $createContent = ''): array
    {
        if (!$hasCreateFile) {
            return ['status' => 'n/a', 'details' => 'Module has no create.php'];
        }

        if ($createContent !== '' && itm_ui_create_file_is_redirect_stub($createContent)) {
            return [
                'status' => 'n/a',
                'details' => 'create.php redirects to index (read-only / immutable module — no create action expected)',
            ];
        }

        $anchors = itm_ui_index_collect_list_new_button_anchors($indexContent);
        if ($anchors === []) {
            return ['status' => 'n/a', 'details' => 'No create.php link on index.php'];
        }

        if (itm_ui_index_has_btn_sm_create_link($indexContent)) {
            return [
                'status' => 'fail',
                'details' => 'create.php uses btn-sm; list-header ➕ must use btn btn-primary (40×40 footprint from styles.css)',
            ];
        }

        $canonicalPattern = itm_ui_index_canonical_list_new_button_pattern();
        $emojiButtons = [];
        foreach ($anchors as $anchorHtml) {
            if (!itm_ui_index_anchor_is_emoji_only_list_new_button($anchorHtml)) {
                continue;
            }
            $emojiButtons[] = $anchorHtml;
            if (preg_match($canonicalPattern, $anchorHtml) !== 1) {
                if (preg_match('/\bbtn-sm\b/i', $anchorHtml) === 1) {
                    return [
                        'status' => 'fail',
                        'details' => 'List-header ➕ uses btn-sm; expected btn btn-primary title="Create"',
                    ];
                }
                if (preg_match('/\btitle\s*=\s*["\']Create["\']/i', $anchorHtml) !== 1) {
                    return [
                        'status' => 'fail',
                        'details' => 'List-header ➕ missing title="Create" (emoji-only visible label)',
                    ];
                }
                if (preg_match('/\bclass\s*=\s*["\'][^"\']*\bbtn-primary\b/i', $anchorHtml) !== 1) {
                    return [
                        'status' => 'fail',
                        'details' => 'List-header ➕ must use btn btn-primary',
                    ];
                }

                return [
                    'status' => 'fail',
                    'details' => 'List-header ➕ markup is not canonical (href create.php, btn btn-primary, title="Create", visible ➕)',
                ];
            }
        }

        if ($emojiButtons === []) {
            return [
                'status' => 'n/a',
                'details' => 'No emoji-only list-header create.php control on index.php',
            ];
        }

        if (stripos($indexContent, 'data-itm-new-button-managed') !== false) {
            $hasRowMinHeight = preg_match(
                '/data-itm-new-button-managed[^>]*min-height:\s*40px/i',
                $indexContent
            ) === 1;
            if (!$hasRowMinHeight) {
                return [
                    'status' => 'fail',
                    'details' => 'Server-managed header row missing min-height:40px for uniform list ➕ controls',
                ];
            }
        }

        return [
            'status' => 'pass',
            'details' => 'List-header ➕ uses canonical btn btn-primary, title="Create", 40×40 footprint',
        ];
    }
}

if (!function_exists('itm_check_new_button_size')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_new_button_size(string $indexContent, bool $hasCreateFile, string $createContent = ''): array
    {
        return itm_check_new_button_style($indexContent, $hasCreateFile, $createContent);
    }
}

if (!function_exists('itm_check_table_actions_layout')) {
    /**
     * MBQA ui_check / index table compliance: Actions column layout markers on th and td.
     *
     * @return array{status:string,details:string}
     */
    function itm_check_table_actions_layout(string $listContent, string $sourceLabel): array
    {
        if ($listContent === '' || stripos($listContent, '<table') === false) {
            return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
        }
        if (!itm_ui_index_has_actions_column($listContent)) {
            return ['status' => 'n/a', 'details' => 'No Actions column in ' . $sourceLabel];
        }

        $headerOk = preg_match(
            '/<th\b(?=[^>]*\bitm-actions-cell\b)(?=[^>]*data-itm-actions-origin\s*=\s*(["\'])1\1)[^>]*>/i',
            $listContent
        ) === 1;
        if (!$headerOk) {
            return [
                'status' => 'fail',
                'details' => 'Actions header missing itm-actions-cell + data-itm-actions-origin="1" in ' . $sourceLabel,
            ];
        }

        $bodyOk = preg_match(
            '/<td\b(?=[^>]*\bitm-actions-cell\b)(?=[^>]*data-itm-actions-origin\s*=\s*(["\'])1\1)[^>]*>/i',
            $listContent
        ) === 1;
        if (!$bodyOk) {
            return [
                'status' => 'fail',
                'details' => 'Actions body cell missing itm-actions-cell + data-itm-actions-origin="1" in ' . $sourceLabel,
            ];
        }

        return [
            'status' => 'pass',
            'details' => 'Actions column mapped for ui-layout.js in ' . $sourceLabel,
        ];
    }
}

if (!function_exists('itm_check_bulk_cancel_contract')) {
    /**
     * MBQA bulk_cancel step: shared bulk-delete-selection.js contract in index HTML.
     *
     * @return array{status:string,details:string}
     */
    function itm_check_bulk_cancel_contract(string $indexContent): array
    {
        $hasBulkForm = stripos($indexContent, 'bulk-delete-form') !== false
            || stripos($indexContent, 'department-bulk-form') !== false;
        if (!$hasBulkForm) {
            return ['status' => 'n/a', 'details' => 'No bulk-delete form in index.php'];
        }

        $issues = [];
        if (stripos($indexContent, 'bulk-delete-selection.js') === false) {
            $issues[] = 'bulk-delete-selection.js missing in HTML';
        }
        if (stripos($indexContent, 'name="bulk_action"') === false && stripos($indexContent, "name='bulk_action'") === false) {
            $issues[] = 'bulk_action control missing';
        }
        if (stripos($indexContent, 'bulk_delete') === false && stripos($indexContent, 'Select to Delete') === false) {
            $issues[] = 'Select to Delete / bulk_delete missing';
        }
        if (preg_match('/let\s+selectionMode\s*=\s*false/i', $indexContent)) {
            $issues[] = 'inline selectionMode script (use shared bulk-delete-selection.js)';
        }

        $hasStaticCancel = stripos($indexContent, 'data-itm-bulk-cancel') !== false;
        if ($hasStaticCancel) {
            $cancelOk = preg_match('/<button[^>]*data-itm-bulk-cancel\s*=\s*["\']1["\'][^>]*type\s*=\s*["\']button["\']/i', $indexContent) === 1
                || preg_match('/<button[^>]*type\s*=\s*["\']button["\'][^>]*data-itm-bulk-cancel\s*=\s*["\']1["\']/i', $indexContent) === 1;
            if (!$cancelOk) {
                $issues[] = 'data-itm-bulk-cancel button must be type="button"';
            }
        }

        if ($issues !== []) {
            return [
                'status' => 'fail',
                'details' => implode('; ', $issues),
            ];
        }

        return [
            'status' => 'pass',
            'details' => 'bulk-delete-form + bulk-delete-selection.js + Select to Delete contract',
        ];
    }
}

if (!function_exists('itm_check_pagination_nav_titles')) {
    /**
     * MBQA pagination step: Previous/Next anchors use emoji title tooltips (not Search).
     *
     * @return array{status:string,details:string}
     */
    function itm_check_pagination_nav_titles(string $listContent, string $sourceLabel): array
    {
        if ($listContent === '' || stripos($listContent, '<table') === false) {
            return ['status' => 'n/a', 'details' => 'No table in ' . $sourceLabel];
        }

        $hasNext = stripos($listContent, '>Next<') !== false || preg_match('/>\s*Next\s*<\/a>/i', $listContent) === 1;
        $hasPrev = stripos($listContent, '>Previous<') !== false
            || preg_match('/>\s*Previous\s*<\/a>/i', $listContent) === 1
            || preg_match('/>\s*Prev\s*<\/a>/i', $listContent) === 1;
        if (!$hasNext && !$hasPrev) {
            return ['status' => 'n/a', 'details' => 'No pagination navigation in ' . $sourceLabel];
        }

        $missing = [];
        if ($hasPrev
            && stripos($listContent, 'title="◀️ Previous"') === false
            && stripos($listContent, "title='◀️ Previous'") === false
        ) {
            $missing[] = 'title="◀️ Previous" on Previous link';
        }
        if ($hasNext
            && stripos($listContent, 'title="▶️ Next"') === false
            && stripos($listContent, "title='▶️ Next'") === false
        ) {
            $missing[] = 'title="▶️ Next" on Next link';
        }
        if (preg_match('/title="🔎\s*Search"/i', $listContent)) {
            $missing[] = 'pagination link must not use title="🔎 Search" (use ◀️ Previous / ▶️ Next)';
        }

        if ($missing === []) {
            return [
                'status' => 'pass',
                'details' => 'Pagination Previous/Next title attributes in ' . $sourceLabel,
            ];
        }

        return [
            'status' => 'fail',
            'details' => 'Pagination navigation in ' . $sourceLabel . ' missing: ' . implode(', ', $missing),
        ];
    }
}

if (!function_exists('itm_check_index_mutation_csrf')) {
    /**
     * index table compliance: POST mutation surfaces require CSRF guard + form tokens.
     *
     * @return array{status:string,details:string}
     */
    function itm_check_index_mutation_csrf(string $indexContent): array
    {
        if (strpos($indexContent, 'index-table-compliance: skip') !== false) {
            return ['status' => 'n/a', 'details' => 'index-table-compliance: skip marker'];
        }

        $postSurface = preg_match(
            '/REQUEST_METHOD\s*[\"\']?\]\s*={2,3}\s*[\"\']POST[\"\']|\$_POST\s*\[/i',
            $indexContent
        ) === 1;
        if (!$postSurface) {
            return ['status' => 'n/a', 'details' => 'No POST handlers in index.php'];
        }

        $stateMutation = preg_match(
            '/\b(INSERT|UPDATE|DELETE)\b|\b(mysqli_query|mysqli_prepare|mysqli_stmt_execute)\s*\(/i',
            $indexContent
        ) === 1;

        $issues = [];
        if ($stateMutation && !itm_ui_index_has_csrf_guard($indexContent)) {
            $issues[] = 'POST mutation without itm_require_post_csrf() (or equivalent)';
        }

        if (preg_match_all('/<form\b[^>]*method\s*=\s*(["\'])post\1/i', $indexContent, $formMatches) > 0) {
            $formCount = count($formMatches[0]);
            $tokenCount = preg_match_all('/name\s*=\s*(["\'])csrf_token\1/i', $indexContent);
            if ($tokenCount < $formCount) {
                $issues[] = 'POST form(s) without enough csrf_token hidden fields';
            }
        }

        if (stripos($indexContent, 'add_sample_data') !== false && !itm_ui_index_has_csrf_guard($indexContent)) {
            $issues[] = 'add_sample_data handler without CSRF guard';
        }

        if ($issues === []) {
            return ['status' => 'pass', 'details' => 'POST mutations and forms guarded with CSRF'];
        }

        return [
            'status' => 'fail',
            'details' => implode('; ', $issues),
        ];
    }
}
