<?php
use PHPUnit\Framework\TestCase;

class ApiFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        if (defined('ITM_CLI_SCRIPT') === false) {
            define('ITM_CLI_SCRIPT', true);
        }
        if (defined('ITM_SKIP_DB_TESTS') === false) {
            define('ITM_SKIP_DB_TESTS', true);
        }

        if (!function_exists("itmDocCollectModuleImportEndpoints")) {
            ob_start();
            @require_once __DIR__ . "/../../../../scripts/api.php";
            ob_end_clean();
        }
    }

    public function testCollectModuleImportEndpoints()
    {
        $rootPath = realpath(__DIR__ . "/../../../../");
        $endpoints = itmDocCollectModuleImportEndpoints($rootPath);
        $this->assertIsArray($endpoints);
        $this->assertNotEmpty($endpoints);

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
        $rootPath = realpath(__DIR__ . "/../../../../");
        $endpoints = itmDocCollectModuleImportEndpoints($rootPath);
        $missing = itmDocCollectModulesWithoutImportEndpoint($rootPath, $endpoints);
        $this->assertIsArray($missing);

        $this->assertContains("explorer", $missing);
        $this->assertContains("audit_logs", $missing);
    }

    public function testCollectIdfApiEndpoints()
    {
        $rootPath = realpath(__DIR__ . "/../../../../");
        $endpoints = itmDocCollectIdfApiEndpoints($rootPath);
        $this->assertIsArray($endpoints);
        $this->assertNotEmpty($endpoints);

        foreach ($endpoints as $e) {
            $this->assertStringContainsString("modules/idfs/api/", $e["path"]);
            $this->assertArrayHasKey("purpose", $e);
        }
    }

    public function testCollectExplorerApiActions()
    {
        $rootPath = realpath(__DIR__ . "/../../../../");
        $actions = itmDocCollectExplorerApiActions($rootPath);
        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);

        $actionNames = array_column($actions, "action");
        $required = [
            "list", "upload", "rename", "delete", "restore",
            "copy", "move", "zip", "unzip",
            "createYear", "createMonths", "createDays", "createYearMonthDay",
            "listRecycle", "emptyRecycle",
        ];
        foreach ($required as $name) {
            $this->assertContains($name, $actionNames, "Explorer action missing from docs: " . $name);
        }
    }

    public function testExplorerDownloadEndpoints()
    {
        $endpoints = itmDocExplorerDownloadEndpoints();
        $this->assertCount(2, $endpoints);
        $paths = array_column($endpoints, "path");
        $this->assertTrue(
            (bool) preg_grep('/downloadZip/', $paths),
            "downloadZip endpoint not documented"
        );
        $this->assertTrue(
            (bool) preg_grep('/file\.php/', $paths),
            "file.php download endpoint not documented"
        );
    }

    public function testProjectJsonEndpointsIncludesSelectOptions()
    {
        $endpoints = itmDocProjectJsonEndpoints();
        $this->assertIsArray($endpoints);
        $paths = array_column($endpoints, "path");
        $this->assertContains("modules/select_options_api.php", $paths);
    }

    public function testPasswordsApiActionsCatalog()
    {
        $actions = itmDocPasswordsApiActions();
        $this->assertNotEmpty($actions);
        $names = array_column($actions, "action");
        $this->assertContains("save_entry", $names);
        $this->assertContains("list_entries", $names);
    }
}
