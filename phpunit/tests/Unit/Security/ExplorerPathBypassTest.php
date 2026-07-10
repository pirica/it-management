<?php

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for Explorer path validation bypass.
 */
class ExplorerPathBypassTest extends TestCase
{
    use \ItmExtractFunctionTestTrait;

    protected function setUp(): void
    {
        $this->requireExtractedFunction(ROOT_PATH . 'modules/explorer/api.php', 'get_full_path');
    }

    public function testGetFullPathBypass()
    {
        if (!function_exists('get_full_path')) {
            $this->markTestSkipped('get_full_path function not defined.');
        }

        $storage_root = __DIR__ . '/storage_root';

        $user_id = 123;
        $dept_id = 456;
        $username = 'testuser';

        $this->assertNotNull(get_full_path($storage_root, 'Common', $user_id, $dept_id, $username));
        $this->assertNull(get_full_path($storage_root, 'Private', $user_id, $dept_id, $username));
        $this->assertNull(get_full_path($storage_root, './Private', $user_id, $dept_id, $username));
        $this->assertNull(get_full_path($storage_root, './Private/other_user_1', $user_id, $dept_id, $username));
    }
}
