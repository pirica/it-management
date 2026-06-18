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

    public function testProjectJsonEndpointsIncludesSwitchPortHandlers()
    {
        $endpoints = itmDocProjectJsonEndpoints();
        $paths = array_column($endpoints, "path");
        $this->assertContains("includes/get_ports.php", $paths);
        $this->assertContains("includes/update_port.php", $paths);
    }

    public function testSwitchPortApiEndpointsCatalog()
    {
        $endpoints = itmDocSwitchPortApiEndpoints();
        // Why: Catalog currently documents exactly get_ports + update_port; allow growth without brittle exact count.
        $this->assertGreaterThanOrEqual(
            2,
            count($endpoints),
            'Expected at least the core switch-port endpoints in itmDocSwitchPortApiEndpoints().'
        );
        $paths = array_column($endpoints, "path");
        $this->assertContains(
            "includes/get_ports.php",
            $paths,
            'Switch-port get_ports.php endpoint not documented in itmDocSwitchPortApiEndpoints().'
        );
        $this->assertContains(
            "includes/update_port.php",
            $paths,
            'Switch-port update_port.php endpoint not documented in itmDocSwitchPortApiEndpoints().'
        );
        foreach ($endpoints as $row) {
            $this->assertSame("POST", $row["method"]);
            $this->assertArrayHasKey("response", $row);
            $this->assertNotSame("", trim((string)$row["purpose"]));
            $this->assertStringContainsString(
                "company_id",
                (string)$row["params"],
                'Switch-port endpoint params should document session-derived company_id (not client payload).'
            );
        }
    }

    public function testPasswordsApiActionsCatalog()
    {
        $actions = itmDocPasswordsApiActions();
        $this->assertNotEmpty($actions);
        $names = array_column($actions, "action");
        $this->assertContains("save_entry", $names);
        $this->assertContains("list_entries", $names);
    }

    public function testCollectApiExamplesListsEveryPhpFile()
    {
        $rootPath = realpath(__DIR__ . "/../../../../");
        $examples = itmDocCollectApiExamples($rootPath);
        $this->assertIsArray($examples);
        $this->assertCount(14, $examples, "Expected every api-examples/*.php file to be documented");

        $files = array_column($examples, "file");
        $expected = [
            "api-examples/authenticate.php",
            "api-examples/catalog_delete.php",
            "api-examples/catalogs.php",
            "api-examples/catalogs_listall_active.php",
            "api-examples/csrfToken.php",
            "api-examples/employees.php",
            "api-examples/employees_singleview.php",
            "api-examples/equipment.php",
            "api-examples/equipment_edit.php",
            "api-examples/events.php",
            "api-examples/sessionCookie.php",
            "api-examples/ticket_archive.php",
            "api-examples/tickets.php",
            "api-examples/tickets_listall_open.php",
        ];
        foreach ($expected as $path) {
            $this->assertContains($path, $files, "Missing api-examples doc row: " . $path);
        }

        foreach ($examples as $row) {
            $this->assertArrayHasKey("title", $row);
            $this->assertArrayHasKey("category", $row);
            $this->assertArrayHasKey("purpose", $row);
            $this->assertNotSame("", trim((string)$row["title"]));
        }
    }
}
