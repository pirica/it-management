<?php

declare(strict_types=1);

class ReproAuditDisclosureTest extends ItmScriptCliTestCase
{
    /** @var mysqli|null */
    private $conn;

    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }

        require_once ROOT_PATH . 'config/config.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_user.php';

        global $conn;
        if (!$conn || !($conn instanceof mysqli)) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        $this->conn = $conn;
    }

    public function testReproDoesNotMutateSeedAdminResetFields(): void
    {
        $adminSnapshot = itm_script_test_user_snapshot($this->conn, 1, [
            'reset_token',
            'reset_token_hash',
            'reset_token_expires_at',
        ]);

        $result = $this->runRepoScript('scripts/repro_audit_disclosure.php');
        $this->assertSame(0, $result['exit'], $result['output']);
        $this->assertStringNotContainsString('user ID 1', $result['output']);
        $this->assertStringContainsString('disposable user ID', $result['output']);

        $adminAfter = itm_script_test_user_snapshot($this->conn, 1, [
            'reset_token',
            'reset_token_hash',
            'reset_token_expires_at',
        ]);
        $this->assertSame($adminSnapshot, $adminAfter);

        $res = mysqli_query($this->conn, "SELECT id FROM users WHERE username LIKE 'script-repro-audit-disclosure-%'");
        $this->assertSame(0, $res ? mysqli_num_rows($res) : -1);
    }
}
