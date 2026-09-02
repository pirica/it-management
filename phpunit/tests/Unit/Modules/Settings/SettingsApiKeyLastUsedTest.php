<?php

namespace Tests\Unit\Modules\Settings;

use PHPUnit\Framework\TestCase;

class SettingsApiKeyLastUsedTest extends TestCase
{
    /** @var \mysqli|null */
    private $conn;

    private $companyId = 1;

    /** @var array<int,int> */
    private $createdEmployeeIds = [];

    /** @var array<int,array{company_id:int,employee_id:int}> */
    private $seededConfigurations = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';
        require_once ROOT_PATH . 'scripts/lib/itm_api_tier_test_helpers.php';

        $this->conn = $GLOBALS['conn'] ?? null;
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        mysqli_query($this->conn, 'SET @app_company_id = ' . (int) $this->companyId);
    }

    protected function tearDown(): void
    {
        if (!$this->conn) {
            return;
        }

        foreach ($this->seededConfigurations as $seed) {
            itm_apitest_cleanup_configuration(
                $this->conn,
                (int) $seed['company_id'],
                (int) $seed['employee_id']
            );
        }
        $this->seededConfigurations = [];

        foreach ($this->createdEmployeeIds as $employeeId) {
            itm_script_test_employee_delete($this->conn, (int) $employeeId);
        }
        $this->createdEmployeeIds = [];
    }

    /**
     * @return array{id:int,company_id:int,employee_id:int}
     */
    private function createDisposableEmployee(): array
    {
        $row = itm_script_test_employee_create($this->conn, $this->companyId, [
            'script_slug' => 'phpunit-settings-api-key-last-used',
        ]);
        if (!is_array($row)) {
            $this->fail('Could not create disposable employee for Settings API key last-used tests.');
        }

        $employeeId = (int) $row['id'];
        $this->createdEmployeeIds[] = $employeeId;

        return [
            'id' => $employeeId,
            'company_id' => (int) $row['company_id'],
            'employee_id' => $employeeId,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function seedConfiguration(int $employeeId, string $tier, array $overrides = []): array
    {
        $row = itm_apitest_seed_configuration($this->conn, $this->companyId, $employeeId, $tier, $overrides);
        if (!is_array($row)) {
            $this->fail('Could not seed disposable ui_configuration row.');
        }

        $this->seededConfigurations[] = [
            'company_id' => $this->companyId,
            'employee_id' => $employeeId,
        ];

        return $row;
    }

    public function testSettingsModuleRendersApiKeyLastUsedField(): void
    {
        $settingsIndex = dirname(__DIR__, 5) . '/modules/settings/index.php';
        $this->assertFileExists($settingsIndex);

        $source = (string) file_get_contents($settingsIndex);
        $this->assertStringContainsString('id="api_key_last_used_at_display"', $source);
        $this->assertStringContainsString('API Key Last Used', $source);
        $this->assertStringContainsString('itm_api_format_key_last_used_display_label', $source);
    }

    public function testUiConfigurationNullLastUsedFormatsAsNeverForSettings(): void
    {
        $employee = $this->createDisposableEmployee();
        $config = $this->seedConfiguration($employee['employee_id'], 'Basic');

        $reloaded = itm_apitest_reload_configuration(
            $this->conn,
            (int) $config['id'],
            $this->companyId,
            $employee['employee_id']
        );
        $this->assertIsArray($reloaded);
        $this->assertNull($reloaded['api_key_last_used_at']);

        $this->assertSame(
            'Never',
            itm_api_format_key_last_used_display_label($reloaded['api_key_last_used_at'] ?? null)
        );
    }

    public function testFreeTierConsumeTouchesApiKeyLastUsedAt(): void
    {
        $employee = $this->createDisposableEmployee();
        $config = $this->seedConfiguration($employee['employee_id'], 'Free', [
            'rate_limit_enabled' => 0,
        ]);

        $consume = itm_api_consume_rate_limit($this->conn, $config);
        $this->assertNotEmpty($consume['allowed']);

        $reloaded = itm_apitest_reload_configuration(
            $this->conn,
            (int) $config['id'],
            $this->companyId,
            $employee['employee_id']
        );
        $this->assertIsArray($reloaded);
        $this->assertNotEmpty($reloaded['api_key_last_used_at']);
        $this->assertNotSame(
            'Never',
            itm_api_format_key_last_used_display_label($reloaded['api_key_last_used_at'])
        );
    }

    public function testBasicTierConsumeTouchesApiKeyLastUsedAt(): void
    {
        $employee = $this->createDisposableEmployee();
        $config = $this->seedConfiguration($employee['employee_id'], 'Basic', [
            'rate_limit_enabled' => 1,
            'rate_limit_window_start' => time(),
            'rate_limit_request_count' => 0,
        ]);

        $consume = itm_api_consume_rate_limit($this->conn, $config);
        $this->assertNotEmpty($consume['allowed']);

        $reloaded = itm_apitest_reload_configuration(
            $this->conn,
            (int) $config['id'],
            $this->companyId,
            $employee['employee_id']
        );
        $this->assertIsArray($reloaded);
        $this->assertNotEmpty($reloaded['api_key_last_used_at']);
    }

    public function testBasicTierBlockedConsumeDoesNotTouchApiKeyLastUsedAt(): void
    {
        $basicLimit = itm_api_tier_hourly_limit('Basic');
        $employee = $this->createDisposableEmployee();
        $config = $this->seedConfiguration($employee['employee_id'], 'Basic', [
            'rate_limit_enabled' => 1,
            'rate_limit_window_start' => time(),
            'rate_limit_request_count' => $basicLimit,
        ]);

        $consume = itm_api_consume_rate_limit($this->conn, $config);
        $this->assertEmpty($consume['allowed']);

        $reloaded = itm_apitest_reload_configuration(
            $this->conn,
            (int) $config['id'],
            $this->companyId,
            $employee['employee_id']
        );
        $this->assertIsArray($reloaded);
        $this->assertNull($reloaded['api_key_last_used_at']);
        $this->assertSame(
            'Never',
            itm_api_format_key_last_used_display_label($reloaded['api_key_last_used_at'] ?? null)
        );
    }

    public function testTouchKeyLastUsedAtUpdatesPersistedRow(): void
    {
        $employee = $this->createDisposableEmployee();
        $config = $this->seedConfiguration($employee['employee_id'], 'Free');

        $this->assertTrue(itm_api_touch_key_last_used_at($this->conn, $config));

        $reloaded = itm_api_reload_rate_limit_row($this->conn, $config);
        $this->assertIsArray($reloaded);
        $this->assertNotEmpty($reloaded['api_key_last_used_at']);
        $this->assertNotSame(
            'Never',
            itm_api_format_key_last_used_display_label($reloaded['api_key_last_used_at'])
        );
    }

    public function testCrossTenantAdminResolvesTenantSeedAdminConfiguration(): void
    {
        unset($_SESSION['company_id'], $_SESSION['employee_id'], $_SESSION['login_employee_id']);

        $this->assertTrue(function_exists('itm_seed_resolve_tenant_seed_admin_employee_id'));
        $admin4Id = itm_seed_resolve_tenant_seed_admin_employee_id($this->conn, 4);
        if ($admin4Id <= 0) {
            $this->markTestSkipped('Admin4 seed employee missing for company 4.');
        }

        $resolvedId = itm_api_resolve_configuration_employee_id($this->conn, 4, 1);
        $this->assertSame($admin4Id, $resolvedId);

        $_SESSION['company_id'] = 4;
        $_SESSION['employee_id'] = 1;
        $_SESSION['login_employee_id'] = 1;

        $row = itm_api_resolve_rate_limit_row($this->conn);
        $this->assertIsArray($row);
        $this->assertSame(4, (int)($row['company_id'] ?? 0));
        $this->assertSame($admin4Id, (int)($row['employee_id'] ?? 0));
        $this->assertGreaterThan(0, (int)($row['id'] ?? 0));

        unset($_SESSION['company_id'], $_SESSION['employee_id'], $_SESSION['login_employee_id']);
    }
}
