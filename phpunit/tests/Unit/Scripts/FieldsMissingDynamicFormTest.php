<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class FieldsMissingDynamicFormTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once __DIR__ . '/../../../../scripts/lib/itm_fields_missing_report.php';
    }

    public function testParseManageableColumnExclusionsWithNullCoalesce(): void
    {
        $content = <<<'PHP'
function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        return ($c['Field'] ?? '') !== 'id';
    }));
}
PHP;

        $this->assertSame(['id'], itm_fields_missing_parse_manageable_column_exclusions($content));
    }

    public function testParseManageableColumnExclusionsWithFieldAlias(): void
    {
        $content = <<<'PHP'
function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        $field = $c['Field'];
        return !in_array($field, ['id', 'created_at', 'updated_at'], true);
    }));
}
PHP;

        $this->assertSame(['id', 'created_at', 'updated_at'], itm_fields_missing_parse_manageable_column_exclusions($content));
    }

    public function testParseManageableColumnExclusionsWithExcludeVariable(): void
    {
        $content = <<<'PHP'
$crud_table = 'attempts';
function cr_manageable_columns($columns) {
    $exclude = ['id', 'updated_at'];
    if (!in_array((string)($GLOBALS['crud_table'] ?? ''), ['attempts'], true)) {
        $exclude[] = 'created_at';
    }

    return array_values(array_filter($columns, function ($c) use ($exclude) {
        return !in_array($c['Field'], $exclude, true);
    }));
}
PHP;

        $this->assertSame(['id', 'updated_at'], itm_fields_missing_parse_manageable_column_exclusions($content));
    }

    public function testAttemptsIdNotExposedOnCreateEditDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('attempts', $root);
        $paths = itm_fields_missing_resolve_form_paths($files);

        $this->assertFalse(itm_fields_missing_dynamic_form_exposes_field('id', $paths));
    }

    public function testWorkstationModesCreateDoesNotExposeIdDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $path = $root . '/modules/workstation_modes/create.php';

        $this->assertFalse(itm_fields_missing_dynamic_form_exposes_field('id', [$path]));
    }

    public function testManufacturersIndexDoesNotExposeIdOrCompanyIdDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $path = $root . '/modules/manufacturers/index.php';

        $this->assertFalse(itm_fields_missing_dynamic_form_exposes_field('id', [$path]));
        $this->assertFalse(itm_fields_missing_dynamic_form_exposes_field('company_id', [$path]));
    }

    public function testEmployeeCompaniesEditDoesNotExposeCompanyIdDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $path = $root . '/modules/employee_companies/edit.php';

        $this->assertFalse(itm_fields_missing_dynamic_form_exposes_field('company_id', [$path]));
    }

    public function testEmployeeCompaniesEditDoesNotExposeAuditMetaDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('employee_companies', $root);
        $paths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );

        foreach (['deleted_by', 'deleted_at', 'created_by', 'updated_by'] as $field) {
            $this->assertFalse(
                itm_fields_missing_dynamic_form_exposes_field($field, $paths),
                $field . ' should be hidden on create/edit forms'
            );
        }
    }

    public function testCableColorsIndexDoesNotExposeAuditMetaDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('cable_colors', $root);
        $paths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );

        foreach (['deleted_by', 'deleted_at', 'created_by', 'updated_by'] as $field) {
            $this->assertFalse(
                itm_fields_missing_dynamic_form_exposes_field($field, $paths),
                $field . ' should be hidden on create/edit forms'
            );
        }
    }

    public function testSwitchPortsIndexDoesNotExposeAuditMetaDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('switch_ports', $root);
        $paths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );

        foreach (['deleted_by', 'deleted_at', 'created_by', 'created_at', 'updated_by', 'updated_at'] as $field) {
            $this->assertFalse(
                itm_fields_missing_dynamic_form_exposes_field($field, $paths),
                $field . ' should be hidden on create/edit forms'
            );
        }
    }

    public function testVlansEditDoesNotExposeAuditMetaDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('vlans', $root);
        $paths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );

        foreach (['deleted_by', 'deleted_at', 'created_by', 'updated_by'] as $field) {
            $this->assertFalse(
                itm_fields_missing_dynamic_form_exposes_field($field, $paths),
                $field . ' should be hidden on create/edit forms'
            );
        }
    }

    public function testManufacturersCreateWrapperResolvesFormPathsToIndex(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('manufacturers', $root);
        $formPaths = itm_fields_missing_resolve_form_paths($files);

        $this->assertContains(realpath($root . '/modules/manufacturers/index.php'), array_map('realpath', $formPaths));
        $this->assertTrue(itm_fields_missing_form_exposes_visible_field('name', $formPaths));
    }

    public function testCatalogsFormColumnsLoopExposesBusinessFields(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('catalogs', $root);
        $formPaths = itm_fields_missing_resolve_form_paths($files);

        $this->assertTrue(itm_fields_missing_form_exposes_visible_field('active', $formPaths));
        $this->assertTrue(itm_fields_missing_form_exposes_visible_field('name', $formPaths));
    }

    public function testEquipmentFiberPatchAuditedColumnsPresentOnForms(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('equipment_fiber_patch', $root);
        $formPaths = itm_fields_missing_resolve_form_paths($files);

        foreach (['name', 'active'] as $field) {
            $this->assertTrue(
                itm_fields_missing_form_exposes_visible_field($field, $formPaths),
                $field . ' should be present on create/edit forms'
            );
        }
    }

    public function testEquipmentFiberPatchAuditedColumnsPresentOnView(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('equipment_fiber_patch', $root);

        foreach (['name', 'active'] as $field) {
            $this->assertTrue(
                itm_fields_missing_module_view_covers_field($field, $files, 'equipment_fiber_patch'),
                $field . ' should be present on view'
            );
        }
    }

    public function testWorkstationModesListExposesAuditedColumnsDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $path = $root . '/modules/workstation_modes/index.php';

        foreach (['description', 'mode_code', 'monitor_count'] as $field) {
            $this->assertTrue(
                itm_fields_missing_dynamic_list_exposes_field($field, $path),
                $field . ' should be covered by the uiColumns list loop'
            );
        }
    }

    public function testBespokeScaffoldHybridMissingAuditFilterFailsGate(): void
    {
        $content = <<<'PHP'
function cr_manageable_columns($columns) { return $columns; }
$uiColumns = array_values(array_filter($fieldColumns, function ($col) {
    if (function_exists('itm_crud_is_list_hidden_audit_field') && itm_crud_is_list_hidden_audit_field($col['Field'])) {
        return false;
    }
    return true;
}));
<?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
<?php foreach ($uiColumns as $col): $name = $col['Field']; ?>
<input name="<?php echo sanitize($name); ?>">
PHP;
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $tmpDir = $root . '/modules/_fields_missing_test_hybrid';
        $indexPath = $tmpDir . '/index.php';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir);
        }
        file_put_contents($indexPath, $content);
        $files = itm_fields_missing_module_file_bundle('_fields_missing_test_hybrid', $root);
        $formPaths = [$indexPath];
        $passes = [];
        $failures = [];

        try {
            itm_fields_missing_audit_bespoke_scaffold_hybrid_contract(
                '_fields_missing_test_hybrid',
                $files,
                $formPaths,
                $passes,
                $failures
            );

            $this->assertNotSame([], $failures);
            $this->assertStringContainsString('audit meta filter', $failures[0]['message'] ?? '');
        } finally {
            if (is_file($indexPath)) {
                unlink($indexPath);
            }
            if (is_dir($tmpDir)) {
                rmdir($tmpDir);
            }
        }
    }

    public function testOpsReportDeferredUiAuditReportsSkipFails(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('ops_report', $root);
        $formPaths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );
        $passes = [];
        $failures = [];

        itm_fields_missing_audit_bespoke_deferred_ui_coverage(
            'ops_report',
            ['id', 'company_id', 'report_date', 'room_revenue', 'active', 'created_by'],
            $formPaths,
            $files,
            $passes,
            $failures
        );

        $this->assertNotSame([], $failures);
        $this->assertGreaterThanOrEqual(3, count($failures));
    }

    public function testCableColorsBespokeGatePassesHybridContract(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('cable_colors', $root);
        $formPaths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );
        $passes = [];
        $failures = [];

        itm_fields_missing_audit_bespoke_scaffold_hybrid_contract(
            'cable_colors',
            $files,
            $formPaths,
            $passes,
            $failures
        );

        $this->assertSame([], $failures);
    }

    public function testAuditAuditedUiColumnsEmitsPerSurfacePasses(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('equipment_fiber_patch', $root);
        $formPaths = itm_fields_missing_resolve_form_paths($files);
        $passes = [];
        $failures = [];

        itm_fields_missing_audit_audited_ui_columns(
            'equipment_fiber_patch',
            ['name', 'active'],
            $formPaths,
            $files,
            $passes,
            $failures,
            true
        );

        $this->assertSame([], $failures);
        $this->assertContains(
            'equipment_fiber_patch audited UI column name: present on create/edit forms',
            $passes
        );
    }

    public function testIpAddressesBespokeGateHidesAuditMetaOnForms(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('ip_addresses', $root);
        $formPaths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );
        $passes = [];
        $failures = [];

        itm_fields_missing_apply_skipped_ui_coverage_gate(
            'ip_addresses',
            [
                'id', 'company_id', 'subnet_id', 'ip_text', 'status', 'equipment_id', 'hostname',
                'is_gateway', 'is_dns', 'dhcp_managed', 'notes', 'active',
                'deleted_by', 'deleted_at', 'created_by', 'created_at', 'updated_by', 'updated_at',
            ],
            $formPaths,
            $files,
            $passes,
            $failures,
            false
        );

        $this->assertSame([], $failures, implode('; ', array_map(static function ($row) {
            return (string) ($row['message'] ?? '');
        }, $failures)));
        $this->assertContains(
            'ip_addresses excluded UI column deleted_by: hidden or absent on create/edit forms',
            $passes
        );
        $this->assertContains(
            'ip_addresses bespoke gate: hidden audit inputs on create/edit',
            $passes
        );
    }

    public function testExtractCreateEditFormBlockIgnoresNestedFieldElseif(): void
    {
        $content = <<<'PHP'
<?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
<form>
<?php foreach ($fieldColumns as $col): $name = $col['Field']; ?>
<?php if ($name === 'company_id'): ?>
<?php elseif ($isTinyInt): ?>
<input name="<?php echo sanitize($name); ?>">
<?php endif; ?>
<?php endforeach; ?>
</form>
<?php elseif ($crud_action === 'view'): ?>
PHP;

        $block = itm_fields_missing_extract_create_edit_form_block($content);
        $this->assertNotNull($block);
        $this->assertStringContainsString('sanitize($name)', $block);
        $this->assertStringContainsString('foreach ($fieldColumns', $block);
    }

    public function testFieldColumnsLoopInNestedPartialDetectsAuditMetaExposure(): void
    {
        $content = <<<'PHP'
<?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
<?php foreach ($fieldColumns as $col): $name = $col['Field']; ?>
<?php if ($name === 'company_id'): ?>
<?php elseif ($isTinyInt): ?>
<input name="<?php echo sanitize($name); ?>">
<?php endif; ?>
<?php endforeach; ?>
<?php elseif ($crud_action === 'view'): ?>
PHP;
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $tmpDir = $root . '/modules/_fields_missing_test_nested';
        $renderPath = $tmpDir . '/includes/partials/render.php';
        if (!is_dir($tmpDir . '/includes/partials')) {
            mkdir($tmpDir . '/includes/partials', 0777, true);
        }
        file_put_contents($renderPath, $content);

        try {
            $this->assertTrue(
                itm_fields_missing_dynamic_form_exposes_field('deleted_by', [$renderPath]),
                'fieldColumns create/edit loops must expose globally excluded audit columns to the detector'
            );
        } finally {
            if (is_file($renderPath)) {
                unlink($renderPath);
            }
            @rmdir($tmpDir . '/includes/partials');
            @rmdir($tmpDir . '/includes');
            @rmdir($tmpDir);
        }
    }

    public function testIpSubnetsBespokeGateHidesAuditMetaOnForms(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('ip_subnets', $root);
        $formPaths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );
        $passes = [];
        $failures = [];

        itm_fields_missing_apply_skipped_ui_coverage_gate(
            'ip_subnets',
            [
                'id', 'company_id', 'vlan_id', 'cidr', 'gateway_ip', 'dns1_ip', 'dns2_ip', 'dhcp_enabled',
                'notes', 'active', 'deleted_by', 'deleted_at', 'created_by', 'created_at', 'updated_by', 'updated_at',
            ],
            $formPaths,
            $files,
            $passes,
            $failures,
            false
        );

        $this->assertSame([], $failures, implode('; ', array_map(static function ($row) {
            return (string) ($row['message'] ?? '');
        }, $failures)));
        $this->assertContains(
            'ip_subnets excluded UI column deleted_by: hidden or absent on create/edit forms',
            $passes
        );
        $this->assertContains(
            'ip_subnets bespoke gate: hidden audit inputs on create/edit',
            $passes
        );
    }

    public function testScrapedHtmlDetectsDisabledAuditLabelWithoutName(): void
    {
        $html = <<<'HTML'
<form method="POST">
    <div class="form-group">
        <label>Created At</label>
        <input value="2024-01-01" disabled>
    </div>
</form>
HTML;

        $this->assertTrue(itm_fields_missing_scraped_html_exposes_audit_meta_field('created_at', $html));
        $this->assertFalse(itm_fields_missing_scraped_html_exposes_audit_meta_field('updated_at', $html));
    }

    public function testScrapedHtmlAllowsHiddenAuditInput(): void
    {
        $html = '<form><input type="hidden" name="created_at" value="2024-01-01"></form>';

        $this->assertFalse(itm_fields_missing_scraped_html_exposes_audit_meta_field('created_at', $html));
    }

    public function testScrapedHtmlDetectsNamedDatetimeLocalAuditField(): void
    {
        $html = '<form><input type="datetime-local" name="created_at" value="2024-01-01T10:00"></form>';

        $this->assertTrue(itm_fields_missing_scraped_html_exposes_audit_meta_field('created_at', $html));
    }

    public function testPseudoHtmlScrapeFlagsLegacyInventoryAuditDisplay(): void
    {
        $content = <<<'PHP'
<form method="POST">
    <label>Created At</label>
    <input value="<?php echo sanitize((string)($data['created_at'] ?? '')); ?>" disabled>
</form>
PHP;

        $pseudo = itm_fields_missing_strip_php_for_form_scan($content);
        $this->assertTrue(itm_fields_missing_scraped_html_exposes_audit_meta_field('created_at', $pseudo));
    }

    public function testInventoryItemsAuditMetaGatePassesAfterModuleFix(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('inventory_items', $root);
        $formPaths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );
        $passes = [];
        $failures = [];

        itm_fields_missing_audit_excluded_ui_columns(
            'inventory_items',
            ['created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_at', 'deleted_by'],
            $formPaths,
            $passes,
            $failures,
            false,
            $files
        );

        $this->assertSame([], $failures, implode('; ', array_map(static function ($row) {
            return (string) ($row['message'] ?? '');
        }, $failures)));
    }

    public function testTicketsCreateExposesCreatedAtToAuditMetaDetector(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('tickets', $root);
        $formPaths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );

        $this->assertTrue(
            itm_fields_missing_form_exposes_audit_meta_on_form('created_at', $formPaths, 'tickets', $files)
        );
    }
}
