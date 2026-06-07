<?php
use PHPUnit\Framework\TestCase;

class ApiFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        // Require the file containing functions to test
        // We use a mock or include the file. Since api.php has side effects (HTML output),
        // we might need to extract the functions or use a wrapper.
        // For now, we will include it and capture output if needed.
        if (!function_exists("itmDocCollectModuleImportEndpoints")) {
            require_once __DIR__ . "/../../scripts/api.php";
        }
    }

    public function testCollectModuleImportEndpoints()
    {
        $endpoints = itmDocCollectModuleImportEndpoints(__DIR__ . "/..");
        $this->assertIsArray($endpoints);
        $this->assertNotEmpty($endpoints);

        // Verify alerts module is detected
        $foundAlerts = false;
        foreach ($endpoints as $e) {
            if ($e["module"] === "alerts") {
                $foundAlerts = true;
                break;
            }
        }
        $this->assertTrue($foundAlerts, "Alerts module import endpoint not detected");
    }

    public function testCollectModulesWithoutImportEndpoint()
    {
        $endpoints = itmDocCollectModuleImportEndpoints(__DIR__ . "/..");
        $missing = itmDocCollectModulesWithoutImportEndpoint(__DIR__ . "/..", $endpoints);
        $this->assertIsArray($missing);

        // explorer and audit_logs should be in missing list (no import_excel_rows)
        $this->assertContains("explorer", $missing);
        $this->assertContains("audit_logs", $missing);
    }

    public function testCollectIdfApiEndpoints()
    {
        $endpoints = itmDocCollectIdfApiEndpoints(__DIR__ . "/..");
        $this->assertIsArray($endpoints);
        $this->assertNotEmpty($endpoints);

        foreach ($endpoints as $e) {
            $this->assertStringContainsString("modules/idfs/api/", $e["path"]);
        }
    }
}
