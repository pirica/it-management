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

    public function testStorageHelperFormatsBytesAndScansDirectory(): void
    {
        require_once dirname(__DIR__, 4) . '/includes/itm_system_status_storage.php';

        $this->assertSame('512 B', itm_system_status_format_bytes(512));
        $this->assertSame('1.00 KB', itm_system_status_format_bytes(1024));
        $this->assertSame('1.00 MB', itm_system_status_format_bytes(1048576));

        $tempDir = sys_get_temp_dir() . '/itm-ss-storage-' . uniqid('', true);
        mkdir($tempDir, 0775, true);
        file_put_contents($tempDir . '/sample.txt', str_repeat('a', 128));

        $metrics = itm_system_status_directory_metrics($tempDir);
        $this->assertSame(128, $metrics['bytes']);
        $this->assertSame(1, $metrics['files']);

        unlink($tempDir . '/sample.txt');
        rmdir($tempDir);
    }

    public function testCacheTabKeysAndPhpSettingsCollector(): void
    {
        require_once dirname(__DIR__, 4) . '/includes/itm_system_status_cache.php';

        $this->assertSame(
            ['monitoring', 'php_settings', 'database'],
            itm_system_status_cache_tab_keys()
        );

        $payload = itm_system_status_collect_php_settings_payload();
        $this->assertArrayHasKey('version', $payload);
        $this->assertArrayHasKey('extensions', $payload);
        $this->assertContains('Core', $payload['extensions']);
    }
}
