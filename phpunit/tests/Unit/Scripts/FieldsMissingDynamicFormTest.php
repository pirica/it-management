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
        $path = $root . '/modules/employee_companies/edit.php';

        foreach (['deleted_by', 'deleted_at', 'created_by', 'updated_by'] as $field) {
            $this->assertFalse(
                itm_fields_missing_dynamic_form_exposes_field($field, [$path]),
                $field . ' should be hidden on create/edit forms'
            );
        }
    }

    public function testCableColorsIndexDoesNotExposeAuditMetaDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $path = $root . '/modules/cable_colors/index.php';

        foreach (['deleted_by', 'deleted_at', 'created_by', 'updated_by'] as $field) {
            $this->assertFalse(
                itm_fields_missing_dynamic_form_exposes_field($field, [$path]),
                $field . ' should be hidden on create/edit forms'
            );
        }
    }

    public function testSwitchPortsIndexDoesNotExposeAuditMetaDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $path = $root . '/modules/switch_ports/index.php';

        foreach (['deleted_by', 'deleted_at', 'created_by', 'created_at', 'updated_by', 'updated_at'] as $field) {
            $this->assertFalse(
                itm_fields_missing_dynamic_form_exposes_field($field, [$path]),
                $field . ' should be hidden on create/edit forms'
            );
        }
    }

    public function testVlansEditDoesNotExposeAuditMetaDynamically(): void
    {
        $root = realpath(__DIR__ . '/../../../../');
        $this->assertNotFalse($root);
        $path = $root . '/modules/vlans/edit.php';

        foreach (['deleted_by', 'deleted_at', 'created_by', 'updated_by'] as $field) {
            $this->assertFalse(
                itm_fields_missing_dynamic_form_exposes_field($field, [$path]),
                $field . ' should be hidden on create/edit forms'
            );
        }
    }
}
