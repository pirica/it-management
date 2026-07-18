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
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $files = itm_fields_missing_module_file_bundle('role_module_permissions', $root);
        $formPaths = itm_fields_missing_merge_bespoke_form_paths(
            $files,
            itm_fields_missing_resolve_form_paths($files)
        );
        $passes = [];
        $failures = [];

        itm_fields_missing_audit_bespoke_scaffold_hybrid_contract(
            'role_module_permissions',
            $files,
            $formPaths,
            $passes,
            $failures
        );

        $this->assertNotSame([], $failures);
        $this->assertStringContainsString('audit meta filter', $failures[0]['message'] ?? '');
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
}
