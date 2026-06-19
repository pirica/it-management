<?php

namespace Tests\Unit\Modules\Passwords;

use PHPUnit\Framework\TestCase;

/**
 * Passwords functional tests — AJAX save_folder and save_entry via ajax_handler.php.
 */
class PasswordsFunctionalTest extends TestCase
{
    private $employeeId = 1;
    private $csrfToken;

    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once __DIR__ . '/../../../../../config/config.php';

        if (!$GLOBALS['conn']) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        $_SESSION['employee_id'] = $this->employeeId;
        $this->csrfToken = itm_get_csrf_token();
    }

    public function testSaveFolderRootAndChild()
    {
        $rootParams = [
            'action' => 'save_folder',
            'csrf_token' => $this->csrfToken,
            'name' => 'Root Test Folder ' . uniqid(),
            'parent_id' => '0',
        ];
        $rootRes = $this->callAjax($rootParams);
        $this->assertIsArray($rootRes);
        $this->assertTrue($rootRes['ok'] ?? false, $rootRes['message'] ?? 'save_folder root failed');

        $childParams = [
            'action' => 'save_folder',
            'csrf_token' => $this->csrfToken,
            'name' => 'Child Test Folder ' . uniqid(),
            'parent_id' => '1',
        ];
        $childRes = $this->callAjax($childParams);
        $this->assertIsArray($childRes);
        $this->assertTrue($childRes['ok'] ?? false, $childRes['message'] ?? 'save_folder child failed');
    }

    public function testSaveEntry()
    {
        $_SESSION['vault_key'] = hash('sha256', 'test_key');
        $params = [
            'action' => 'save_entry',
            'csrf_token' => $this->csrfToken,
            'account' => 'Functional Test Account',
            'password' => 'test_password',
            'folder_id' => '0',
        ];
        $res = $this->callAjax($params);
        $this->assertIsArray($res);
        $this->assertTrue($res['ok'] ?? false, $res['message'] ?? 'save_entry failed');
    }

    /**
     * Why: Exercise ajax_handler.php in isolation without HTTP or echo side effects.
     */
    private function callAjax(array $params)
    {
        $_POST = $params;
        $oldDir = getcwd();
        chdir(__DIR__ . '/../../../../../modules/passwords');
        ob_start();
        include 'ajax_handler.php';
        $output = ob_get_clean();
        chdir($oldDir);

        return json_decode($output, true);
    }
}
