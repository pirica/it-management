<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Bespoke / non-CRUD fields_missing gate — real UI contract checks only (no business-column scrape).
 */
class FieldsMissingBespokeGateTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once __DIR__ . '/../../../../scripts/lib/itm_fields_missing_report.php';
    }

    /**
     * @param list<string> $expectedColumns
     * @return array{passes:list<string>,failures:list<array{code:string,message:string}>}
     */
    private function runBespokeGate(string $moduleSlug, array $expectedColumns): array
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle($moduleSlug, $root);
        $formPaths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );
        $passes = [];
        $failures = [];
        $statusDriven = in_array($moduleSlug, itm_fields_missing_status_driven_slugs(), true);

        itm_fields_missing_apply_skipped_ui_coverage_gate(
            $moduleSlug,
            $expectedColumns,
            $formPaths,
            $files,
            $passes,
            $failures,
            $statusDriven
        );

        return ['passes' => $passes, 'failures' => $failures];
    }

    /**
     * @return array{create:string,edit:string,view:string,index:string,includes:string,list_all:string,delete:string}
     */
    private function makeFixtureFiles(string $indexContent, string $deleteContent = ''): array
    {
        $dir = sys_get_temp_dir() . '/itm_fm_' . uniqid('', true);
        mkdir($dir);
        $index = $dir . '/index.php';
        file_put_contents($index, $indexContent);
        $delete = '';
        if ($deleteContent !== '') {
            $delete = $dir . '/delete.php';
            file_put_contents($delete, $deleteContent);
        }

        return [
            'index' => $index,
            'delete' => $delete,
            'create' => '',
            'edit' => '',
            'view' => '',
            'list_all' => '',
            'includes' => '',
        ];
    }

    public function testBespokeGateDoesNotAuditBusinessColumnUiCoverage(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);

        foreach (['vlans', 'ops_report', 'cable_colors', 'bookmarks'] as $slug) {
            $columns = $schema['vlans'] ?? [];
            if ($slug !== 'vlans') {
                $table = $slug === 'bookmarks' ? 'bookmarks' : ($slug === 'ops_report' ? 'ops_report' : 'cable_colors');
                $columns = $schema[$table] ?? [];
            }
            if ($columns === []) {
                continue;
            }

            $result = $this->runBespokeGate($slug, $columns);
            foreach ($result['failures'] as $failure) {
                $message = (string) ($failure['message'] ?? '');
                $this->assertStringNotContainsString('missing on view', $message, $slug);
                $this->assertStringNotContainsString('missing on create/edit forms', $message, $slug);
                $this->assertStringNotContainsString('missing on index list/import', $message, $slug);
            }
        }
    }

    public function testStatusDrivenTicketsPassesCenteredListHeadingGate(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['tickets'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('tickets', $columns);
        $passes = implode('|', $result['passes']);
        $this->assertStringContainsString('List heading layout OK', $passes);
        $this->assertStringContainsString('List heading emoji OK', $passes);
        $this->assertStringContainsString('New button position OK', $passes);
        $this->assertStringContainsString('Actions layout OK', $passes);
    }

    public function testPureBespokeOpsReportPassesHeadFaviconHelper(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['ops_report'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('ops_report', $columns);
        $passes = implode('|', $result['passes']);
        $this->assertStringContainsString('Favicon OK', $passes);
    }

    public function testFaviconGatePassesItmRenderHeadFaviconHelperWithoutLiteralLinkTag(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $index = <<<'HTML'
<head>
<title>Test</title>
<?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
</head>
HTML;
        $check = itm_check_module_favicon_link($index);
        $this->assertSame('pass', $check['status'] ?? '');
    }

    public function testHybridScaffoldVlansPassesListUiSearchSortPagination(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['vlans'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('vlans', $columns);
        $passes = implode('|', $result['passes']);
        $this->assertStringContainsString('Search OK', $passes);
        $this->assertStringContainsString('Sort OK', $passes);
        $this->assertStringContainsString('Pagination OK', $passes);
    }

    public function testHybridScaffoldVlansFailsHardDeleteContract(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['vlans'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('vlans', $columns);
        $messages = array_map(static function (array $failure): string {
            return (string) ($failure['message'] ?? '');
        }, $result['failures']);

        $this->assertTrue(
            $this->messagesContainAny($messages, ['hard DELETE', 'deleted_at IS NULL']),
            'vlans hybrid scaffold should fail soft-delete contract: ' . implode(' | ', $messages)
        );
    }

    public function testSyntheticListHeadingContractFailsWhenNotCentered(): void
    {
        $content = <<<'PHP'
<!DOCTYPE html><html><head><title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title></head>
<body>
<div data-itm-new-button-managed="server" style="display:flex;justify-content:space-between;">
<a href="create.php" class="btn btn-primary">➕</a>
<h1>Hardcoded Title</h1>
</div>
<table></table>
PHP;
        $passes = [];
        $failures = [];
        itm_fields_missing_audit_bespoke_page_ui_contract(
            'fixture_heading',
            $this->makeFixtureFiles($content),
            $passes,
            $failures
        );

        $this->assertNotSame([], $failures);
        $messages = array_map(static function (array $failure): string {
            return (string) ($failure['message'] ?? '');
        }, $failures);
        $this->assertTrue(
            $this->messagesContainAny($messages, ['List heading layout', 'List heading emoji']),
            'expected List heading failure: ' . implode(' | ', $messages)
        );
    }

    public function testSyntheticListHeadingEmojiFailsWithoutSidebarLabelSource(): void
    {
        $content = <<<'PHP'
<!DOCTYPE html><html><head><title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title></head>
<body>
<?php $moduleListHeading = $crud_title; $newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right'); ?>
<div data-itm-new-button-managed="server" style="position:relative;display:flex;">
<?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?><a href="create.php" class="btn btn-primary">➕</a><?php endif; ?>
<h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
<?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?><a href="create.php" class="btn btn-primary">➕</a><?php endif; ?>
</div>
<table></table>
PHP;
        $passes = [];
        $failures = [];
        itm_fields_missing_audit_bespoke_page_ui_contract(
            'fixture_heading_emoji',
            $this->makeFixtureFiles($content),
            $passes,
            $failures
        );

        $messages = array_map(static function (array $failure): string {
            return (string) ($failure['message'] ?? '');
        }, $failures);
        $this->assertTrue(
            $this->messagesContainAny($messages, ['List heading emoji', 'emoji source']),
            'expected List heading emoji failure: ' . implode(' | ', $messages)
        );
    }

    public function testSyntheticListHeadingEmojiPassesWithSidebarLabel(): void
    {
        $content = <<<'PHP'
<!DOCTYPE html><html><head><title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title></head>
<body>
<?php
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
?>
<div data-itm-new-button-managed="server" style="position:relative;display:flex;">
<?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?><a href="create.php" class="btn btn-primary">➕</a><?php endif; ?>
<h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
<?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?><a href="create.php" class="btn btn-primary">➕</a><?php endif; ?>
</div>
<table></table>
PHP;
        $passes = [];
        $failures = [];
        itm_fields_missing_audit_bespoke_page_ui_contract(
            'fixture_heading_emoji_ok',
            $this->makeFixtureFiles($content),
            $passes,
            $failures
        );

        $passesText = implode('|', $passes);
        $this->assertStringContainsString('List heading emoji OK', $passesText);
        $this->assertStringContainsString('List heading layout OK', $passesText);
    }

    public function testNewButtonPositionGatePassesCanonicalManagedHeader(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $index = <<<'PHP'
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
<div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;">
<?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
<a href="create.php" class="btn btn-primary" title="Create">➕</a>
<?php else: ?><span></span><?php endif; ?>
<h1 style="position:absolute;left:50%;transform:translateX(-50%);"><?php echo sanitize($moduleListHeading); ?></h1>
<?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
<a href="create.php" class="btn btn-primary" title="Create">➕</a>
<?php else: ?><span></span><?php endif; ?>
</div>
PHP;
        $check = itm_check_new_button_position($index, true, '<?php // create form');
        $this->assertSame('pass', $check['status'] ?? '');
    }

    public function testNewButtonPositionGateFailsWhenCreateLinkIsNotSettingsGated(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $index = <<<'PHP'
<div data-itm-new-button-managed="server" style="position:relative;">
<a href="create.php" class="btn btn-primary">➕</a>
<h1 style="position:absolute;left:50%;transform:translateX(-50%);">Title</h1>
</div>
PHP;
        $check = itm_check_new_button_position($index, true, '<?php // create form');
        $this->assertSame('fail', $check['status'] ?? '');
        $this->assertStringContainsString('new_button_position', $check['details'] ?? '');
    }

    public function testNewButtonPositionGateIsNaWhenUiLayoutRelocatesClientSide(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $index = <<<'PHP'
<div style="display:flex;">
<a href="create.php" class="btn btn-primary">➕</a>
<h1>Title</h1>
</div>
PHP;
        $check = itm_check_new_button_position($index, true, '<?php // create form');
        $this->assertSame('n/a', $check['status'] ?? '');
    }

    public function testNewButtonStyleGateFailsWhenCreateUsesBtnSm(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $index = <<<'PHP'
<div data-itm-new-button-managed="server" style="position:relative;min-height:40px;">
<a href="create.php" class="btn btn-sm btn-primary">➕</a>
<h1 style="position:absolute;left:50%;transform:translateX(-50%);">Title</h1>
</div>
PHP;
        $check = itm_check_new_button_style($index, true, '<?php // create form');
        $this->assertSame('fail', $check['status'] ?? '');
    }

    public function testNewButtonStyleGatePassesForCanonicalManagedHeader(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $index = <<<'PHP'
<div data-itm-new-button-managed="server" style="position:relative;display:flex;min-height:40px;">
<a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
<h1 style="position:absolute;left:50%;transform:translateX(-50%);">Title</h1>
<a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
</div>
PHP;
        $check = itm_check_new_button_style($index, true, '<?php // create form');
        $this->assertSame('pass', $check['status'] ?? '');
    }

    public function testNewButtonStyleGateFailsWhenTitleMissing(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $index = <<<'PHP'
<a href="create.php" class="btn btn-primary">➕</a>
PHP;
        $check = itm_check_new_button_style($index, true, '<?php // create form');
        $this->assertSame('fail', $check['status'] ?? '');
    }

    public function testSyntheticSoftDeleteContractPassesWhenCompliant(): void
    {
        $content = <<<'PHP'
function cr_manageable_columns($columns) { return $columns; }
$deleteSql = itm_crud_build_soft_delete_sql($crud_table, $where, (int)$_SESSION['employee_id']);
$where = itm_crud_append_not_deleted_predicate($where);
$rows = mysqli_query($conn, 'SELECT * FROM vlans' . $where);
PHP;
        $passes = [];
        $failures = [];
        itm_fields_missing_audit_bespoke_soft_delete_contract(
            'fixture_ok',
            $this->makeFixtureFiles($content),
            ['id', 'deleted_at', 'deleted_by'],
            $passes,
            $failures
        );

        $this->assertSame([], $failures);
        $this->assertNotSame([], $passes);
    }

    public function testSyntheticSoftDeleteContractFailsOnHardDelete(): void
    {
        $content = <<<'PHP'
function cr_manageable_columns($columns) { return $columns; }
$deleteSql = 'DELETE FROM vlans' . $where;
$rows = mysqli_query($conn, 'SELECT * FROM vlans' . $where);
PHP;
        $passes = [];
        $failures = [];
        itm_fields_missing_audit_bespoke_soft_delete_contract(
            'fixture_bad',
            $this->makeFixtureFiles($content),
            ['id', 'deleted_at', 'deleted_by'],
            $passes,
            $failures
        );

        $this->assertNotSame([], $failures);
        $this->assertStringContainsString('hard DELETE', $failures[0]['message'] ?? '');
    }

    public function testStatusDrivenEmployeesRunsListUiGate(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['employees'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('employees', $columns);
        $passes = implode('|', $result['passes']);
        $this->assertStringContainsString('List heading layout OK', $passes);
        $this->assertStringContainsString('List heading emoji OK', $passes);
        $this->assertStringContainsString('Search OK', $passes);
        $this->assertStringContainsString('row active: hidden on create/edit forms', $passes);
    }

    public function testBespokeGateFailuresExcludedFromActionableFailureCount(): void
    {
        $moduleReports = [
            [
                'module' => 'ops_report',
                'ui_mode' => 'bespoke_skip',
                'ui_coverage_audit_skipped' => true,
                'failures' => [
                    ['code' => 'bespoke_page_ui_favicon', 'message' => 'ops_report bespoke gate: Favicon NOT OK — missing'],
                ],
            ],
            [
                'module' => 'manufacturers',
                'ui_mode' => 'dynamic_scaffold',
                'ui_coverage_audit_skipped' => false,
                'failures' => [],
            ],
        ];

        $this->assertSame(0, itm_fields_missing_count_actionable_failures($moduleReports));
        $this->assertSame(1, itm_fields_missing_count_skip_gate_failures($moduleReports));
    }

    public function testModuleFileBundleIncludesDeletePath(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('system_access', $root);
        $this->assertArrayHasKey('delete', $files);
        $this->assertStringEndsWith('modules/system_access/delete.php', str_replace('\\', '/', $files['delete']));
        $this->assertTrue(is_readable($files['delete']));
    }

    public function testBespokeGateDoesNotDuplicateNewButtonPositionRows(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['system_access'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('system_access', $columns);
        $positionPassCount = 0;
        $styleFailCount = 0;
        foreach ($result['passes'] as $passLine) {
            if (stripos($passLine, 'New button position OK') !== false) {
                $positionPassCount++;
            }
        }
        foreach ($result['failures'] as $failure) {
            if (stripos((string) ($failure['message'] ?? ''), 'New button style NOT OK') !== false) {
                $styleFailCount++;
            }
        }

        $this->assertSame(1, $positionPassCount, 'New button position must appear once (page gate only)');
        $this->assertSame(1, $styleFailCount, 'New button style must appear once (page gate only)');
    }

    public function testSystemAccessBulkDeleteGatePassesWhenDeletePhpExists(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['system_access'] ?? [];
        $result = $this->runBespokeGate('system_access', $columns);
        $passes = implode('|', $result['passes']);
        $this->assertStringContainsString('Bulk delete OK', $passes);
    }

    public function testCompanyModuleAccessFailsListHeadingEmojiForPlainCrudTitleH1(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['company_module_access'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('company_module_access', $columns);
        $messages = array_map(static function (array $failure): string {
            return (string) ($failure['message'] ?? '');
        }, $result['failures']);

        $this->assertTrue(
            $this->messagesContainAny($messages, ['List heading emoji', 'sanitize($crud_title)']),
            'company_module_access must fail list heading emoji when h1 uses bare $crud_title: ' . implode(' | ', $messages)
        );
        $this->assertTrue(
            $this->messagesContainAny($messages, ['List heading layout', 'data-itm-new-button-managed']),
            'company_module_access must fail list heading layout for non-managed header: ' . implode(' | ', $messages)
        );
    }

    public function testPlainCrudTitleListH1FailsEmojiGate(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $index = <<<'PHP'
<div style="display:flex;">
<h1 style="margin:0;"><?= sanitize($crud_title) ?></h1>
</div>
<table></table>
PHP;
        $emoji = itm_check_list_heading_emoji($index);
        $layout = itm_check_list_heading_layout($index);
        $this->assertSame('fail', $emoji['status'] ?? '');
        $this->assertSame('fail', $layout['status'] ?? '');
    }

    public function testOpsReportListHeadingEmojiStaysNaForCustomHeadingMarkup(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $index = <<<'PHP'
<h1 class="opr-page-title"><?= opr_render_editable_ui_text($title) ?></h1>
PHP;
        $emoji = itm_check_list_heading_emoji($index);
        $this->assertSame('n/a', $emoji['status'] ?? '');
    }

    public function testBespokeGateFailMessagesUseNotOkSuffix(): void
    {
        $passes = [];
        $failures = [];
        itm_fields_missing_record_bespoke_ui_check_results(
            'fixture_gate',
            [
                'List heading emoji' => [
                    'status' => 'fail',
                    'details' => 'List h1 missing emoji source',
                ],
            ],
            $passes,
            $failures
        );

        $this->assertSame([], $passes);
        $this->assertCount(1, $failures);
        $this->assertStringContainsString('List heading emoji NOT OK', $failures[0]['message'] ?? '');
    }

    public function testEchoModuleCheckLinesPrintsPassAndFailForBespokeGate(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/script_cli_output.php';
        $moduleReport = [
            'module' => 'vlans',
            'ui_coverage_audit_skipped' => true,
            'passes' => ['vlans bespoke gate: List heading emoji OK'],
            'failures' => [
                [
                    'code' => 'bespoke_list_ui_bulk_cancel',
                    'message' => 'vlans bespoke gate: Bulk cancel NOT OK — bulk-delete-selection.js missing in HTML',
                ],
            ],
        ];

        ob_start();
        itm_fields_missing_echo_module_check_lines($moduleReport, "\n");
        $output = (string) ob_get_clean();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

        $this->assertStringContainsString('[SKIP][fail] vlans bespoke gate: Bulk cancel NOT OK', $plain);
        $this->assertStringContainsString('[SKIP][pass] vlans bespoke gate: List heading emoji OK', $plain);
        $this->assertLessThan(
            strpos($plain, '[SKIP][pass] vlans bespoke gate: List heading emoji OK'),
            strpos($plain, '[SKIP][fail] vlans bespoke gate: Bulk cancel NOT OK'),
            'fail lines must print before pass lines'
        );
    }

    public function testSkipGateFailureSummaryBlockListsBespokeFailures(): void
    {
        $report = [
            'modules' => [
                [
                    'module' => 'companies',
                    'ui_coverage_audit_skipped' => true,
                    'failures' => [
                        ['message' => 'companies bespoke gate: List heading emoji NOT OK — missing emoji source'],
                    ],
                ],
                [
                    'module' => 'manufacturers',
                    'ui_coverage_audit_skipped' => false,
                    'failures' => [
                        ['message' => 'manufacturers audited UI column name: missing on view'],
                    ],
                ],
            ],
        ];

        $block = itm_fields_missing_format_skip_gate_failure_summary_block($report, "\n");
        $this->assertStringContainsString('List heading emoji NOT OK', $block);
        $this->assertStringNotContainsString('manufacturers audited UI column', $block);
    }

    public function testBackupTapeLogSearchFailureMatchesReviewedRegistry(): void
    {
        $failure = [
            'code' => 'bespoke_list_ui_search',
            'message' => 'backup_tape_log bespoke gate: Search NOT OK — Table in index.php missing search wiring',
        ];
        $this->assertTrue(itm_fields_missing_failure_is_reviewed('backup_tape_log', $failure));
    }

    public function testEchoModuleCheckLinesPrintsReviewedLabelForRegistryMatch(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/script_cli_output.php';
        $moduleReport = [
            'module' => 'backup_tape_log',
            'ui_coverage_audit_skipped' => true,
            'passes' => [],
            'failures' => [
                [
                    'code' => 'bespoke_list_ui_search',
                    'message' => 'backup_tape_log bespoke gate: Search NOT OK — Table in index.php missing search wiring',
                ],
            ],
        ];

        ob_start();
        itm_fields_missing_echo_module_check_lines($moduleReport, "\n");
        $output = (string) ob_get_clean();
        $plain = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

        $this->assertStringContainsString('[SKIP][fail][reviewed] backup_tape_log bespoke gate: Search NOT OK', $plain);
    }

    public function testSkipGateFailureSummaryUsesFailureCodeForReviewedTag(): void
    {
        $report = [
            'modules' => [
                [
                    'module' => 'switch_ports',
                    'ui_coverage_audit_skipped' => true,
                    'failures' => [
                        [
                            'code' => 'bespoke_hard_delete',
                            'message' => 'switch_ports bespoke gate: delete uses hard DELETE (expected itm_crud_build_soft_delete_sql soft-delete)',
                        ],
                    ],
                ],
                [
                    'module' => 'tickets',
                    'ui_coverage_audit_skipped' => true,
                    'failures' => [
                        [
                            'code' => 'ui_excluded_exposed',
                            'message' => 'tickets excluded UI column created_by: visible on create/edit forms',
                        ],
                    ],
                ],
            ],
        ];
        itm_fields_missing_apply_reviewed_flags_to_report($report);

        $block = itm_fields_missing_format_skip_gate_failure_summary_block($report, "\n");

        $this->assertStringContainsString('[SKIP][fail][reviewed] switch_ports bespoke gate: delete uses hard DELETE', $block);
        $this->assertStringContainsString('[SKIP][fail][reviewed] tickets excluded UI column created_by', $block);
        $this->assertStringNotContainsString("\n[SKIP][fail] switch_ports", $block);
        $this->assertStringNotContainsString("\n[SKIP][fail] tickets excluded", $block);
    }

    public function testFormatFailureMessageWithModuleLinkLeavesCliOutputUnchanged(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/script_browser_nav.php';
        $message = 'backup_tape_log bespoke gate: Search NOT OK — missing search wiring';
        $formatted = itm_fields_missing_format_failure_message_with_module_link($message, 'backup_tape_log');
        $this->assertSame($message, $formatted);
    }

    public function testFormatStatusLineWithModuleLinkLeavesCliOutputUnchanged(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/script_browser_nav.php';
        $line = '[SKIP][fail][reviewed] tickets excluded UI column created_by: visible on create/edit forms';
        $formatted = itm_fields_missing_format_status_line_with_module_link($line, 'tickets');
        $this->assertSame($line, $formatted);
    }

    public function testSkipGateFailureSummaryDoesNotEmbedModuleLinkInCli(): void
    {
        $report = [
            'modules' => [
                [
                    'module' => 'backup_tape_log',
                    'ui_coverage_audit_skipped' => true,
                    'failures' => [
                        [
                            'code' => 'bespoke_list_ui_search',
                            'message' => 'backup_tape_log bespoke gate: Search NOT OK — missing search wiring',
                        ],
                    ],
                ],
            ],
        ];

        $block = itm_fields_missing_format_skip_gate_failure_summary_block($report, "\n");
        $this->assertStringContainsString('backup_tape_log bespoke gate: Search NOT OK', $block);
        $this->assertStringNotContainsString('<a href=', $block);
    }

    public function testStatusLineContainsModuleLinkDetectsAnchor(): void
    {
        $this->assertTrue(
            itm_fields_missing_status_line_contains_module_link(
                '[SKIP][fail][reviewed] <a href="../modules/tickets/index.php">tickets</a> excluded UI column created_by'
            )
        );
        $this->assertFalse(
            itm_fields_missing_status_line_contains_module_link(
                '[SKIP][fail][reviewed] tickets excluded UI column created_by'
            )
        );
    }

    public function testResolveStatusLineColorTypeUsesWarnForReviewedSkipFail(): void
    {
        $this->assertSame(
            'warn',
            itm_fields_missing_resolve_status_line_color_type('[SKIP][fail][reviewed] backup_tape_log bespoke gate: Search NOT OK')
        );
        $this->assertSame(
            'fail',
            itm_fields_missing_resolve_status_line_color_type('[SKIP][fail] manufacturers audited UI column name')
        );
    }

    public function testEscapeStatusLineBodyPreservesModuleLinkAndLeavesLiteralAnchorInCli(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/script_cli_output.php';
        $body = '<a href="../modules/ops_report/index.php">ops_report</a> bespoke gate: Search NOT OK — reset (emoji-only 🔙 on <a>, not plain Clear)';
        $escaped = itm_fields_missing_escape_status_line_body_preserving_module_link($body);
        $this->assertStringContainsString('<a href="../modules/ops_report/index.php">ops_report</a>', $escaped);
        $this->assertStringContainsString('on <a>, not plain Clear', $escaped);
    }

    public function testEscapeStatusLineBodyPreservesHardDeleteMessageText(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/script_cli_output.php';
        $body = '<a href="../modules/switch_ports/index.php">switch_ports</a> bespoke gate: delete uses hard DELETE (expected itm_crud_build_soft_delete_sql soft-delete)';
        $escaped = itm_fields_missing_escape_status_line_body_preserving_module_link($body);
        $this->assertStringContainsString('switch_ports</a> bespoke gate: delete uses hard DELETE', $escaped);
        $this->assertStringContainsString('itm_crud_build_soft_delete_sql', $escaped);
    }

    public function testReviewedRegistryValidationPasses(): void
    {
        $registry = itm_fields_missing_load_reviewed_registry();
        $validation = itm_fields_missing_validate_reviewed_registry($registry);
        $this->assertTrue($validation['ok']);
        $this->assertSame([], $validation['errors']);
    }

    public function testStrictGateFailsOnUnreviewedSkipGateFailures(): void
    {
        $report = [
            'failure_count' => 0,
            'modules' => [
                [
                    'module' => 'fixture_gate',
                    'ui_coverage_audit_skipped' => true,
                    'failures' => [
                        [
                            'code' => 'bespoke_list_ui_search',
                            'message' => 'fixture_gate bespoke gate: Search NOT OK — missing search wiring',
                        ],
                    ],
                ],
            ],
        ];
        itm_fields_missing_apply_reviewed_flags_to_report($report);
        itm_fields_missing_compute_skip_gate_review_counts($report);

        $this->assertSame(1, (int) ($report['unreviewed_skip_gate_failure_count'] ?? 0));
        $this->assertTrue(itm_fields_missing_strict_gate_failed($report));
        $this->assertSame(1, itm_fields_missing_resolve_exit_code($report, true));
        $this->assertSame(0, itm_fields_missing_resolve_exit_code($report, false));
    }

    public function testStrictGatePassesWhenBackupTapeLogFailuresAreReviewed(): void
    {
        $report = [
            'failure_count' => 0,
            'modules' => [
                [
                    'module' => 'backup_tape_log',
                    'ui_coverage_audit_skipped' => true,
                    'failures' => [
                        [
                            'code' => 'bespoke_list_ui_search',
                            'message' => 'backup_tape_log bespoke gate: Search NOT OK — missing search wiring',
                        ],
                        [
                            'code' => 'bespoke_list_ui_sort',
                            'message' => 'backup_tape_log bespoke gate: Sort NOT OK — missing sort wiring',
                        ],
                        [
                            'code' => 'bespoke_list_ui_pagination',
                            'message' => 'backup_tape_log bespoke gate: Pagination NOT OK — missing pagination wiring',
                        ],
                    ],
                ],
            ],
        ];
        itm_fields_missing_apply_reviewed_flags_to_report($report);
        itm_fields_missing_compute_skip_gate_review_counts($report);

        $this->assertSame(3, (int) ($report['reviewed_skip_gate_failure_count'] ?? 0));
        $this->assertSame(0, (int) ($report['unreviewed_skip_gate_failure_count'] ?? 0));
        $this->assertFalse(itm_fields_missing_strict_gate_failed($report));
        $this->assertSame(0, itm_fields_missing_resolve_exit_code($report, true));
    }

    public function testBulkDeleteGateIsNaWhenBulkFormOmitted(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $index = <<<'HTML'
<table>
<tr><td>x</td></tr>
</table>
HTML;
        $check = itm_check_bulk_delete_actions($index, 'fixture.php', true);
        $this->assertSame('n/a', $check['status'] ?? '');
        $this->assertStringContainsString('Bulk toolbar intentionally omitted', (string) ($check['details'] ?? ''));
    }

    public function testEmployeeSidebarPreferencesBespokeGatePassesReadOnlyContracts(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['employee_sidebar_preferences'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('employee_sidebar_preferences', $columns);
        $passes = implode('|', $result['passes']);
        $messages = array_map(static function (array $failure): string {
            return (string) ($failure['message'] ?? '');
        }, $result['failures']);

        $this->assertStringContainsString('Search OK', $passes);
        $this->assertFalse(
            $this->messagesContainAny($messages, ['Bulk delete NOT OK', 'Import Excel NOT OK']),
            'read-only employee_sidebar_preferences should not fail bulk/import gates: ' . implode(' | ', $messages)
        );
    }

    public function testSearchContractFailsPlainClearResetLink(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $content = <<<'HTML'
<?php $searchRaw = trim((string)($_GET['search'] ?? '')); ?>
<?php if ($searchRaw !== '') { $sql = " AND name LIKE ?"; } ?>
<form method="get">
<input name="search" value="">
<button type="submit">Search</button>
<a class="btn" href="index.php">Clear</a>
</form>
<table></table>
HTML;
        $check = itm_check_search($content, 'index.php');
        $this->assertSame('fail', $check['status'] ?? '');
        $this->assertStringContainsString('emoji-only 🔙', (string) ($check['details'] ?? ''));
    }

    public function testSearchContractAcceptsEmojiOnlyBackResetLink(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $content = <<<'HTML'
<?php $searchRaw = trim((string)($_GET['search'] ?? '')); ?>
<?php if ($searchRaw !== '') { $sql = " AND name LIKE ?"; } ?>
<form method="get">
<input name="search" value="">
<button type="submit">Search</button>
<a href="index.php" class="btn">🔙</a>
</form>
<table><tr><td>x</td></tr></table>
HTML;
        $check = itm_check_search($content, 'index.php');
        $this->assertSame('pass', $check['status'] ?? '');
    }

    public function testSearchContractFailsWithoutResetControl(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $content = <<<'HTML'
<?php $searchRaw = trim((string)($_GET['search'] ?? '')); ?>
<?php if ($searchRaw !== '') { $sql = " AND name LIKE ?"; } ?>
<form method="get">
<input name="search" value="">
<button type="submit">Search</button>
</form>
<table></table>
HTML;
        $check = itm_check_search($content, 'index.php');
        $this->assertSame('fail', $check['status'] ?? '');
        $this->assertStringContainsString('search reset control', (string) ($check['details'] ?? ''));
    }

    public function testSearchContractAcceptsBookmarksInMemoryListHelper(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $content = <<<'PHP'
<?php
$searchRaw = trim((string)($_GET['search'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'title');
$listResult = bkm_query_bookmarks_for_list($conn, [
    'search' => $searchRaw,
    'sort' => $sort,
    'dir' => $dir,
]);
?>
<form method="get">
<input name="search" value="">
<a href="index.php" class="btn">🔙</a>
</form>
<table></table>
PHP;
        $searchCheck = itm_check_search($content, 'index.php');
        $this->assertSame('pass', $searchCheck['status'] ?? '', (string) ($searchCheck['details'] ?? ''));
    }

    public function testBookmarksBespokeGatePassesSearchAndSort(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['bookmarks'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('bookmarks', $columns);
        $passes = implode('|', $result['passes']);
        $messages = array_map(static function (array $failure): string {
            return (string) ($failure['message'] ?? '');
        }, $result['failures']);

        $this->assertStringContainsString('Search OK', $passes, implode(' | ', $messages));
        $this->assertStringContainsString('Sort OK', $passes, implode(' | ', $messages));
        $this->assertFalse(
            $this->messagesContainAny($messages, ['Search NOT OK', 'Sort NOT OK']),
            'bookmarks bespoke gate Search/Sort: ' . implode(' | ', $messages)
        );
    }

    public function testBookmarksBespokeGatePassesViewAuditMeta(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['bookmarks'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('bookmarks', $columns);
        $passes = implode('|', $result['passes']);
        $messages = array_map(static function (array $failure): string {
            return (string) ($failure['message'] ?? '');
        }, $result['failures']);

        foreach (['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'] as $field) {
            $this->assertStringContainsString(
                "excluded UI column {$field}: present on view",
                $passes,
                'bookmarks view audit meta passes'
            );
        }
        $this->assertFalse(
            $this->messagesContainAny($messages, ['missing on view']),
            'bookmarks view audit meta: ' . implode(' | ', $messages)
        );
    }

    public function testSortContractAcceptsBookmarksInMemoryListHelper(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_ui_list_contract_checks.php';
        $content = <<<'PHP'
<?php
$sort = (string)($_GET['sort'] ?? 'title');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
$listResult = bkm_query_bookmarks_for_list($conn, [
    'search' => $searchRaw,
    'sort' => $sort,
    'dir' => $dir,
]);
$nextDir = ($sort === 'title' && $dir === 'ASC') ? 'DESC' : 'ASC';
?>
<table><th><a href="?sort=title">Title <?php if ($sort === 'title') { echo $dir === 'ASC' ? '▲' : '▼'; } ?></a></th></table>
PHP;
        $sortCheck = itm_check_sort($content, 'index.php');
        $this->assertSame('pass', $sortCheck['status'] ?? '', (string) ($sortCheck['details'] ?? ''));
    }

    public function testIndexListAuditMetaFailsWhenVisibleFieldColumnsLoopUnfiltered(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_fields_missing_report.php';
        $content = <<<'PHP'
<?php
$visibleFieldColumns = array_values(array_filter($fieldColumns, function ($col) {
    return $col['Field'] !== 'company_id';
}));
?>
<table>
<thead><tr><?php foreach ($visibleFieldColumns as $col): ?><th></th><?php endforeach; ?></tr></thead>
</table>
PHP;
        $this->assertTrue(itm_fields_missing_index_list_exposes_audit_meta_field('created_by', $this->writeTempIndex($content)));
        $this->assertTrue(itm_fields_missing_index_list_exposes_audit_meta_field('deleted_at', $this->writeTempIndex($content)));
    }

    public function testIndexListAuditMetaPassesWhenUiColumnsFilterListHiddenAudit(): void
    {
        require_once __DIR__ . '/../../../../scripts/lib/itm_fields_missing_report.php';
        $content = <<<'PHP'
<?php
$uiColumns = array_values(array_filter($visibleFieldColumns, function ($col) {
    $fieldName = (string)($col['Field'] ?? '');
    if (function_exists('itm_crud_is_list_hidden_audit_field') && itm_crud_is_list_hidden_audit_field($fieldName)) {
        return false;
    }
    return true;
}));
?>
<table>
<thead><tr><?php foreach ($uiColumns as $col): ?><th></th><?php endforeach; ?></tr></thead>
</table>
PHP;
        $path = $this->writeTempIndex($content);
        $this->assertFalse(itm_fields_missing_index_list_exposes_audit_meta_field('created_by', $path));
        $this->assertFalse(itm_fields_missing_index_list_exposes_audit_meta_field('deleted_at', $path));
    }

    public function testCableColorsBespokeGateHidesAuditMetaOnIndexList(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['cable_colors'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('cable_colors', $columns);
        $passes = implode('|', $result['passes']);
        $messages = array_map(static function (array $failure): string {
            return (string) ($failure['message'] ?? '');
        }, $result['failures']);

        $this->assertStringContainsString('excluded UI column created_by: hidden or absent on index list', $passes);
        $this->assertStringContainsString('excluded UI column deleted_at: hidden or absent on index list', $passes);
        $this->assertFalse(
            $this->messagesContainAny($messages, ['visible on index list']),
            'cable_colors audit meta on index list: ' . implode(' | ', $messages)
        );
    }

    public function testBespokeViewAuditMetaFailsWhenFieldAbsent(): void
    {
        $viewContent = <<<'PHP'
<?php
?>
<table><tbody>
<tr><th>Company</th><td><?php echo sanitize($data['company']); ?></td></tr>
</tbody></table>
PHP;
        $files = [
            'create' => '',
            'edit' => '',
            'view' => $this->writeTempView($viewContent),
            'index' => '',
            'includes' => '',
            'list_all' => '',
            'delete' => '',
        ];
        $this->assertFalse(itm_fields_missing_module_view_covers_field('created_by', $files, 'fixture_module'));
        $this->assertFalse(itm_fields_missing_module_view_covers_field('deleted_at', $files, 'fixture_module'));
    }

    public function testBespokeViewAuditMetaPassesWithViewColumnsLoop(): void
    {
        $viewContent = <<<'PHP'
<?php
foreach ($viewColumns as $col): $f = $col['Field']; ?>
<tr><td><?php echo cr_render_cell_value($crud_table, $f, $data[$f] ?? ''); ?></td></tr>
<?php endforeach; ?>
PHP;
        $files = [
            'create' => '',
            'edit' => '',
            'view' => $this->writeTempView($viewContent),
            'index' => '',
            'includes' => '',
            'list_all' => '',
            'delete' => '',
        ];
        $this->assertTrue(itm_fields_missing_module_view_covers_audit_meta_field('created_by', $files, 'fixture_module'));
        $this->assertTrue(itm_fields_missing_module_view_covers_audit_meta_field('deleted_at', $files, 'fixture_module'));
    }

    public function testBespokeViewAuditMetaPassesWithAuditCellRenderer(): void
    {
        $viewContent = <<<'PHP'
<?php
echo itm_crud_render_audit_cell_value($crud_table, 'created_by', $data['created_by'] ?? '');
echo itm_crud_render_audit_cell_value($crud_table, 'created_at', $data['created_at'] ?? '');
?>
PHP;
        $files = [
            'create' => '',
            'edit' => '',
            'view' => $this->writeTempView($viewContent),
            'index' => '',
            'includes' => '',
            'list_all' => '',
            'delete' => '',
        ];
        $this->assertTrue(itm_fields_missing_module_view_covers_audit_meta_field('created_by', $files, 'fixture_module'));
        $this->assertTrue(itm_fields_missing_module_view_covers_audit_meta_field('created_at', $files, 'fixture_module'));
    }

    public function testBespokeViewAuditMetaRejectsRawAliasHelperOnly(): void
    {
        $viewContent = <<<'PHP'
<?php
echo sanitize(itm_company_view_value($itemNormalized, ['created_at', 'created']));
echo sanitize(itm_company_view_value($itemNormalized, ['updated_at', 'updated']));
?>
PHP;
        $files = [
            'create' => '',
            'edit' => '',
            'view' => $this->writeTempView($viewContent),
            'index' => '',
            'includes' => '',
            'list_all' => '',
            'delete' => '',
        ];
        $this->assertFalse(itm_fields_missing_module_view_covers_audit_meta_field('created_at', $files, 'companies'));
        $this->assertFalse(itm_fields_missing_module_view_covers_audit_meta_field('updated_at', $files, 'companies'));
    }

    public function testCompaniesBespokeGatePassesViewAuditMeta(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['companies'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('companies', $columns);
        $passes = implode('|', $result['passes']);
        $messages = array_map(static function (array $failure): string {
            return (string) ($failure['message'] ?? '');
        }, $result['failures']);

        foreach (['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_by', 'deleted_at'] as $field) {
            $this->assertStringContainsString(
                "excluded UI column {$field}: present on view",
                $passes,
                'companies view audit meta passes'
            );
        }
        $this->assertFalse(
            $this->messagesContainAny($messages, ['missing on view']),
            'companies view audit meta: ' . implode(' | ', $messages)
        );
    }

    private function writeTempIndex(string $content): string
    {
        $path = sys_get_temp_dir() . '/itm_fm_index_' . uniqid('', true) . '.php';
        file_put_contents($path, $content);

        return $path;
    }

    private function writeTempView(string $content): string
    {
        $path = sys_get_temp_dir() . '/itm_fm_view_' . uniqid('', true) . '.php';
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * @param list<string> $messages
     * @param list<string> $needles
     */
    private function messagesContainAny(array $messages, array $needles): bool
    {
        foreach ($messages as $message) {
            foreach ($needles as $needle) {
                if (stripos($message, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
