<?php
declare(strict_types=1);

namespace Tests\Unit\Modules;

use PHPUnit\Framework\TestCase;

class SystemStatusApiTest extends TestCase
{
    public function testPowerShellScriptsExist(): void
    {
        $includesDir = dirname(__DIR__, 4) . '/includes/';
        $scripts = [
            'system_info.ps1', 'cpu_usage.ps1', 'ram_usage.ps1', 'disk_usage.ps1', 'uptime.ps1',
            'php_version.ps1', 'php_extensions.ps1', 'php_ini_values.ps1',
            'mysql_status.ps1', 'mysql_version.ps1', 'mysql_databases.ps1', 'mysql_size.ps1'
        ];

        foreach ($scripts as $script) {
            $this->assertFileExists($includesDir . $script, "PowerShell script $script should exist.");
        }
    }

    public function testApiDispatcherFileExists(): void
    {
        $apiPath = dirname(__DIR__, 4) . '/scripts/system_status_api.php';
        $this->assertFileExists($apiPath);
    }

    public function testPrefersNativeForPhpAndMysqlActions(): void
    {
        require_once dirname(__DIR__, 4) . '/includes/itm_system_status_powershell.php';

        $nativeActions = [
            'php_version', 'php_extensions', 'php_ini_values',
            'mysql_status', 'mysql_version', 'mysql_databases', 'mysql_size',
        ];
        foreach ($nativeActions as $action) {
            $this->assertTrue(
                itm_system_status_prefers_native($action),
                "Action {$action} should use native PHP/mysqli runtime."
            );
        }

        $hardwareActions = ['system_info', 'cpu_usage', 'ram_usage', 'disk_usage', 'uptime'];
        foreach ($hardwareActions as $action) {
            $this->assertFalse(
                itm_system_status_prefers_native($action),
                "Action {$action} should not be forced to native on Windows."
            );
        }
    }
}
