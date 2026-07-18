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
            || (stripos($listContent, 'LIKE') !== false && preg_match('#search(Raw|Pattern|Value|Esc|Like)|\$search\s*!==\s*[\'"][\s]*[\'"]#i', $listContent) === 1);

        if ($hasSearchParam && $hasSearchInput && $hasSearchQuery) {
            return ['status' => 'pass', 'details' => 'Search input and server-side query detected in ' . $sourceLabel];
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
        $hasOrderBySort = preg_match('#ORDER\s+BY[^\n;]*(\$sortSql|sortableColumns|\$sort\b)#i', $listContent) === 1;
        $hasSortUi = strpos($listContent, '▲') !== false
            || strpos($listContent, '▼') !== false
            || preg_match('#\$nextDir\s*=#', $listContent) === 1;

        if ($hasSortParam && $hasDirParam && $hasOrderBySort && $hasSortUi) {
            return [
                'status' => 'pass',
                'details' => 'Column sort (ASC/DESC) detected in ' . $sourceLabel,
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

        if ($hasFaviconLink && $usesConfiguredFavicon) {
            return [
                'status' => 'pass',
                'details' => 'Server-side favicon link in <head> uses Settings favicon URL',
            ];
        }

        if ($hasFaviconLink) {
            return [
                'status' => 'fail',
                'details' => '<head> favicon link must use $favicon_url or itm_render_head_favicon_link() from ui_configuration',
            ];
        }

        return [
            'status' => 'fail',
            'details' => 'Missing server-side <link rel="icon"> in <head> (header.php JS alone leaves the default tab icon)',
        ];
    }
}

if (!function_exists('itm_check_list_heading_layout')) {
    /**
     * List index headers with Settings-managed new buttons must center the module <h1>.
     *
     * @return array{status:string,details:string}
     */
    function itm_check_list_heading_layout(string $indexContent): array
    {
        if (stripos($indexContent, 'data-itm-new-button-managed') === false) {
            return ['status' => 'n/a', 'details' => 'No server-managed list header row'];
        }

        if (preg_match('/<h1\b/i', $indexContent) !== 1) {
            return ['status' => 'n/a', 'details' => 'No list <h1> heading in index.php'];
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

        if ($hasRelativeWrapper && $hasCenteredHeading && $usesDynamicHeading) {
            return [
                'status' => 'pass',
                'details' => 'List <h1> centered with $moduleListHeading / itm_sidebar_label_for_module()',
            ];
        }

        $missing = [];
        if (!$hasRelativeWrapper) {
            $missing[] = 'position:relative wrapper on data-itm-new-button-managed row';
        }
        if (!$hasCenteredHeading) {
            $missing[] = 'centered list <h1> (position:absolute; left:50%; transform:translateX(-50%))';
        }
        if (!$usesDynamicHeading) {
            $missing[] = '$moduleListHeading from itm_sidebar_label_for_module()';
        }

        return [
            'status' => 'fail',
            'details' => 'List header layout mismatch: ' . implode(', ', $missing),
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

if (!function_exists('itm_check_new_button')) {
    /**
     * @return array{status:string,details:string}
     */
    function itm_check_new_button(string $indexContent, bool $hasCreateFile): array
    {
        if (!$hasCreateFile) {
            return ['status' => 'n/a', 'details' => 'Module has no create.php'];
        }

        $hasCreateLink = preg_match('/<a[^>]*href\s*=\s*["\'][^"\']*(create\.php|new|add)[^"\']*["\'][^>]*>/i', $indexContent) === 1
            && preg_match('/<a[^>]*class\s*=\s*["\'][^"\']*btn[^"\']*btn-primary[^"\']*["\'][^>]*href\s*=\s*["\'][^"\']*(create\.php|new|add)[^"\']*["\'][^>]*>|<a[^>]*href\s*=\s*["\'][^"\']*(create\.php|new|add)[^"\']*["\'][^>]*class\s*=\s*["\'][^"\']*btn[^"\']*btn-primary[^"\']*["\'][^>]*>/i', $indexContent) === 1;

        if ($hasCreateLink) {
            return ['status' => 'pass', 'details' => 'Primary create/add control detected'];
        }

        return ['status' => 'fail', 'details' => 'create.php exists but index.php has no detectable primary create/add action'];
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
