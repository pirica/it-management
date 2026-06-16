<?php

namespace Tests\Unit\Modules\Explorer;

use PHPUnit\Framework\TestCase;

class ExplorerTest extends TestCase
{
    use \ItmExtractFunctionTestTrait;

    private $storageRoot;
    private $userId = 123;
    private $deptId = 456;
    private $username = 'testuser';
    private $safeUsername = 'testuser';

    protected function setUp(): void
    {
        $this->storageRoot = sys_get_temp_dir() . '/itm_explorer_test_' . uniqid();
        mkdir($this->storageRoot, 0777, true);

        $this->requireExtractedFunction(ROOT_PATH . 'modules/explorer/api.php', 'get_full_path');
        $this->requireExtractedFunction(ROOT_PATH . 'modules/explorer/api.php', 'explorer_is_hidden_system_entry');
        $this->requireExtractedFunction(ROOT_PATH . 'modules/explorer/api.php', 'explorer_resolve_preview_mode');
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

        // Private - Own (root is blocked, subfolder is allowed)
        $this->assertNull(get_full_path($this->storageRoot, 'Private', $this->userId, $this->deptId, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, "Private/$userPrivateDir", $this->userId, $this->deptId, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, "Private/$userPrivateDir/file.txt", $this->userId, $this->deptId, $this->username));

        // Private - Other (blocked)
        $this->assertNull(get_full_path($this->storageRoot, 'Private/otheruser_999', $this->userId, $this->deptId, $this->username));

        // Departments - Own (root is blocked, subfolder is allowed)
        $this->assertNull(get_full_path($this->storageRoot, 'Departments', $this->userId, $this->deptId, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, "Departments/{$this->deptId}", $this->userId, $this->deptId, $this->username));

        // Departments - Other (blocked)
        $this->assertNull(get_full_path($this->storageRoot, 'Departments/999', $this->userId, $this->deptId, $this->username));

        // Directory Traversal (blocked)
        $this->assertNull(get_full_path($this->storageRoot, '../secrets', $this->userId, $this->deptId, $this->username));
    }

    public function testHiddenSystemEntries()
    {
        if (!function_exists('explorer_is_hidden_system_entry')) {
            $this->markTestSkipped('explorer_is_hidden_system_entry function could not be loaded.');
        }

        $this->assertTrue(explorer_is_hidden_system_entry('.htaccess'));
        $this->assertTrue(explorer_is_hidden_system_entry('index.html'));
        $this->assertTrue(explorer_is_hidden_system_entry('INDEX.HTML'));
        $this->assertTrue(explorer_is_hidden_system_entry('Common/sub/.htaccess'));
        $this->assertFalse(explorer_is_hidden_system_entry('readme.txt'));
        $this->assertFalse(explorer_is_hidden_system_entry('index.html.bak'));
    }

    public function testPreviewModeRouting()
    {
        if (!function_exists('explorer_resolve_preview_mode')) {
            $this->markTestSkipped('explorer_resolve_preview_mode function could not be loaded.');
        }

        $this->assertSame('image', explorer_resolve_preview_mode('image (3).jpg'));
        $this->assertSame('image', explorer_resolve_preview_mode('photo.JPEG'));
        $this->assertSame('image', explorer_resolve_preview_mode('logo.png'));
        $this->assertSame('pdf', explorer_resolve_preview_mode('manual.pdf'));
        $this->assertSame('text', explorer_resolve_preview_mode('notes.txt'));
        $this->assertSame('unsupported', explorer_resolve_preview_mode('archive.zip'));
        $this->assertSame('unsupported', explorer_resolve_preview_mode('.htaccess'));
        $this->assertSame('unsupported', explorer_resolve_preview_mode('image.jpg.bak'));
    }
}
