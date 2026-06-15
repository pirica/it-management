<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class EquipmentTypeModulesTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once __DIR__ . '/../../../scripts/lib/equipment_type_modules.php';
    }

    public function testCanonicalEquipmentIsModuleNames(): void
    {
        $names = itm_canonical_equipment_is_module_names();
        $this->assertIsArray($names);
        $this->assertContains('is_switch', $names);
        $this->assertContains('is_server', $names);
    }

    public function testCanonicalEquipmentTypeNames(): void
    {
        $names = itm_canonical_equipment_type_names();
        $this->assertIsArray($names);
        $this->assertContains('Switch', $names);
        $this->assertContains('Server', $names);
    }

    public function testIsEquipmentRegressionTestModuleDir(): void
    {
        $this->assertTrue(itm_is_equipment_regression_test_module_dir('is_itm_eqdct_test'));
        $this->assertTrue(itm_is_equipment_regression_test_module_dir('is_mbqa_equipment_types_1'));
        $this->assertFalse(itm_is_equipment_regression_test_module_dir('is_switch'));
        $this->assertFalse(itm_is_equipment_regression_test_module_dir(''));
    }

    public function testEquipmentTypeNameIsMbqaRunnerSeeded(): void
    {
        $this->assertTrue(itm_equipment_type_name_is_mbqa_runner_seeded('mbqa-equipment_types-1-2-abcdef'));
        $this->assertFalse(itm_equipment_type_name_is_mbqa_runner_seeded('Normal Type'));
        $this->assertFalse(itm_equipment_type_name_is_mbqa_runner_seeded(''));
    }

    public function testSqlPatterns(): void
    {
        $this->assertNotEmpty(itm_mbqa_equipment_type_name_pattern_sql());
        $this->assertNotEmpty(itm_qa_import_equipment_type_name_pattern_sql());
        $this->assertNotEmpty(itm_mbqa_equipment_type_scaffold_entry_id_pattern_sql());
        $this->assertNotEmpty(itm_qa_import_equipment_type_scaffold_entry_id_pattern_sql());
    }
}
