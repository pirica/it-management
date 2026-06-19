<?php

declare(strict_types=1);

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class ItmMbqaTestUserTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once ROOT_PATH . 'includes/itm_mbqa_test_user.php';
    }

    public function testRunnerRowTagShape(): void
    {
        $tag = itm_mbqa_runner_row_tag('employees', 1, 2);
        $this->assertMatchesRegularExpression('/^MBQA-employees-1-2-[a-f0-9]{6}$/', $tag);
        $this->assertSame($tag, itm_mbqa_runner_row_tag('employees', 1, 2));
    }

    public function testRunnerRowTagRejectsInvalidTable(): void
    {
        $this->assertSame('', itm_mbqa_runner_row_tag('', 1, 1));
        $this->assertSame('', itm_mbqa_runner_row_tag('Users!', 1, 1));
    }

    public function testUsernameIsMbqaRunnerSeededStrict(): void
    {
        $tag = strtolower(itm_mbqa_runner_row_tag('employees', 4, 0));
        $this->assertTrue(itm_username_is_mbqa_runner_seeded($tag));
        $this->assertFalse(itm_username_is_mbqa_runner_seeded('mbqa-jane'));
        $this->assertFalse(itm_username_is_mbqa_runner_seeded('MBQA-employees-1-2-short'));
    }

    public function testReservedQaImportMarker(): void
    {
        $this->assertTrue(itm_username_is_reserved_qa_import_marker('qa-import-batch-001'));
        $this->assertFalse(itm_username_is_reserved_qa_import_marker('qa-import'));
        $this->assertFalse(itm_username_is_reserved_qa_import_marker('mbqa-users-1-0-abc123'));
    }

    public function testAdminDeleteGuardBypass(): void
    {
        $runnerUser = strtolower(itm_mbqa_runner_row_tag('employees', 1, 1));
        $this->assertTrue(itm_user_company_assignment_bypasses_admin_delete_guard($runnerUser));
        $this->assertTrue(itm_user_company_assignment_bypasses_admin_delete_guard('qa-import-seed-row-01'));
        $this->assertFalse(itm_user_company_assignment_bypasses_admin_delete_guard('regular.user'));
    }
}
