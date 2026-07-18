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
        $this->assertStringContainsString('List heading OK', $passes);
        $this->assertStringContainsString('Actions layout OK', $passes);
    }

    public function testPureBespokeOpsReportFailsMissingHeadFavicon(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $schema = itm_fields_missing_parse_database_sql_table_columns($root);
        $columns = $schema['ops_report'] ?? [];
        $this->assertNotSame([], $columns);

        $result = $this->runBespokeGate('ops_report', $columns);
        $messages = array_map(static function (array $failure): string {
            return (string) ($failure['message'] ?? '');
        }, $result['failures']);

        $this->assertTrue(
            $this->messagesContainAny($messages, ['Favicon', 'favicon', 'rel="icon"']),
            'ops_report should fail page UI favicon gate: ' . implode(' | ', $messages)
        );
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
            $this->messagesContainAny($messages, ['List heading']),
            'expected List heading failure: ' . implode(' | ', $messages)
        );
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
        $this->assertStringContainsString('List heading OK', $passes);
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
                    ['code' => 'bespoke_page_ui_favicon', 'message' => 'ops_report bespoke gate: Favicon — missing'],
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
