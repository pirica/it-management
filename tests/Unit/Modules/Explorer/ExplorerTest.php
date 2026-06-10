<?php

namespace Tests\Unit\Modules\Explorer;

use PHPUnit\Framework\TestCase;

class ExplorerTest extends TestCase
{
    private $storageRoot;
    private $userId = 123;
    private $deptId = 456;
    private $username = 'testuser';
    private $safeUsername = 'testuser';

    protected function setUp(): void
    {
        $this->storageRoot = sys_get_temp_dir() . '/itm_explorer_test_' . uniqid();
        mkdir($this->storageRoot, 0777, true);

        if (!function_exists('get_full_path')) {
            $apiPath = ROOT_PATH . 'modules/explorer/api.php';
            $content = file_get_contents($apiPath);
            
            // Extract get_full_path function
            if (preg_match('/function get_full_path.*?return \$full;\s*\}/s', $content, $matches)) {
                eval($matches[0]);
            }
        }
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->storageRoot);
    }

    private function rrmdir($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->rrmdir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    public function testGetFullPathSecurity()
    {
        if (!function_exists('get_full_path')) {
            $this->markTestSkipped('get_full_path function could not be loaded.');
        }

        $userPrivateDir = "{$this->safeUsername}_{$this->userId}";

        // Root and Common
        $this->assertNotNull(get_full_path($this->storageRoot, '', $this->userId, $this->deptId, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, 'Common', $this->userId, $this->deptId, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, 'Common/sub', $this->userId, $this->deptId, $this->username));

        // Private - Own
        $this->assertNotNull(get_full_path($this->storageRoot, 'Private', $this->userId, $this->deptId, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, "Private/$userPrivateDir", $this->userId, $this->deptId, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, "Private/$userPrivateDir/file.txt", $this->userId, $this->deptId, $this->username));

        // Private - Other (blocked)
        $this->assertNull(get_full_path($this->storageRoot, 'Private/otheruser_999', $this->userId, $this->deptId, $this->username));

        // Departments - Own
        $this->assertNotNull(get_full_path($this->storageRoot, 'Departments', $this->userId, $this->deptId, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, "Departments/{$this->deptId}", $this->userId, $this->deptId, $this->username));

        // Departments - Other (blocked)
        $this->assertNull(get_full_path($this->storageRoot, 'Departments/999', $this->userId, $this->deptId, $this->username));

        // Directory Traversal (blocked)
        $this->assertNull(get_full_path($this->storageRoot, '../secrets', $this->userId, $this->deptId, $this->username));
    }
}
