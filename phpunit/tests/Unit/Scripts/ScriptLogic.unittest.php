<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

class ScriptLogicUnittest extends TestCase {
    protected function setUp(): void {
        if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
    }

    private function safeRequire(string $path) {
        ob_start();
        @require_once $path;
        ob_end_clean();
    }

    public function testUtf8FileLib(): void {
        $this->safeRequire(__DIR__ . '/../../../../scripts/lib/utf8_file.php');
        $this->assertTrue(function_exists('itm_write_utf8_text_file'));
    }

    public function testSqlInjectionDetectorLib(): void {
        $this->safeRequire(__DIR__ . '/../../../../scripts/lib/sql_injection_detector.php');
        $this->assertTrue(function_exists('itm_has_sql_injection_signature'));
    }

    public function testEquipmentTypeModulesLib(): void {
        $this->safeRequire(__DIR__ . '/../../../../scripts/lib/equipment_type_modules.php');
        $this->assertTrue(function_exists('itm_canonical_equipment_is_module_names'));
    }

    public function testScriptBrowserNavLib(): void {
        $this->safeRequire(__DIR__ . '/../../../../scripts/lib/script_browser_nav.php');
        $this->assertTrue(function_exists('itm_script_is_cli_sapi'));
    }

    public function testMbqaStepDisplayLib(): void {
        $this->safeRequire(__DIR__ . '/../../../../scripts/lib/mbqa_step_display.php');
        $this->assertTrue(function_exists('mbqa_step_human_result'));
    }
}
