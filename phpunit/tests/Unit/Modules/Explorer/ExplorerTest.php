<?php

namespace Tests\Unit\Modules\Explorer;

use PHPUnit\Framework\TestCase;

class ExplorerTest extends TestCase
{
    use \ItmExtractFunctionTestTrait;

    private $storageRoot;
    private $employeeId = 123;
    private $deptCode = 'TESTDEPT';
    private $username = 'testuser';
    private $safeUsername = 'testuser';

    protected function setUp(): void
    {
        $this->storageRoot = __DIR__ . '/storage_root';

        $this->requireExtractedFunction(ROOT_PATH . 'modules/explorer/api.php', 'get_full_path');
        $this->requireExtractedFunction(ROOT_PATH . 'modules/explorer/api.php', 'explorer_is_hidden_system_entry');
        $this->requireExtractedFunction(ROOT_PATH . 'modules/explorer/api.php', 'explorer_resolve_preview_mode');
        $this->requireExtractedFunction(ROOT_PATH . 'modules/explorer/api.php', 'explorer_filter_trash_list_to_leaf_items');
    }

    public function testGetFullPathSecurity()
    {
        if (!function_exists('get_full_path')) {
            $this->markTestSkipped('get_full_path function could not be loaded.');
        }

        $userPrivateDir = "{$this->safeUsername}_{$this->employeeId}";

        // Root and Common
        $this->assertNotNull(get_full_path($this->storageRoot, '', $this->employeeId, $this->deptCode, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, 'Common', $this->employeeId, $this->deptCode, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, 'Common/sub', $this->employeeId, $this->deptCode, $this->username));

        // Private - Own (root is blocked, subfolder is allowed)
        $this->assertNull(get_full_path($this->storageRoot, 'Private', $this->employeeId, $this->deptCode, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, "Private/$userPrivateDir", $this->employeeId, $this->deptCode, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, "Private/$userPrivateDir/file.txt", $this->employeeId, $this->deptCode, $this->username));

        // Private - Other (blocked)
        $this->assertNull(get_full_path($this->storageRoot, 'Private/otheruser_999', $this->employeeId, $this->deptCode, $this->username));

        // Departments - root listable when assigned; subfolders scoped to own code
        $this->assertNotNull(get_full_path($this->storageRoot, 'Departments', $this->employeeId, $this->deptCode, $this->username));
        $this->assertNull(get_full_path($this->storageRoot, 'Departments', $this->employeeId, '', $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, "Departments/{$this->deptCode}", $this->employeeId, $this->deptCode, $this->username));

        // Departments - Other (blocked)
        $this->assertNull(get_full_path($this->storageRoot, 'Departments/OTHERDEPT', $this->employeeId, $this->deptCode, $this->username));

        // Directory Traversal (blocked)
        $this->assertNull(get_full_path($this->storageRoot, '../secrets', $this->employeeId, $this->deptCode, $this->username));

        // ./ prefix bypass attempts (blocked after normalization)
        $this->assertNull(get_full_path($this->storageRoot, './Private', $this->employeeId, $this->deptCode, $this->username));
        $this->assertNull(get_full_path($this->storageRoot, './Private/otheruser_999', $this->employeeId, $this->deptCode, $this->username));
        $this->assertNotNull(get_full_path($this->storageRoot, './Departments', $this->employeeId, $this->deptCode, $this->username));
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

    public function testTrashListFiltersAncestorFolders()
    {
        if (!function_exists('explorer_filter_trash_list_to_leaf_items')) {
            $this->markTestSkipped('explorer_filter_trash_list_to_leaf_items function could not be loaded.');
        }

        $items = [
            ['name' => 'Private', 'type' => 'folder'],
            ['name' => 'Private/Admin_1', 'type' => 'folder'],
            ['name' => 'Private/Admin_1/24.png', 'type' => 'file'],
        ];

        $filtered = explorer_filter_trash_list_to_leaf_items($items);

        $this->assertCount(1, $filtered);
        $this->assertSame('Private/Admin_1/24.png', $filtered[0]['name']);
        $this->assertSame('file', $filtered[0]['type']);
    }
}
