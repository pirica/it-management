<?php
/**
 * Disposable test-user sessions for PHPUnit — never seed Admin employee id 1.
 *
 * Why: Mirrors browser scripts/* isolation (itm_script_begin_browser_isolated_session) for
 * in-process and subprocess tests that need $_SESSION company_id / employee_id / username.
 */
trait ItmPhpunitTestSessionTrait
{
    /** @var array<string,mixed>|null */
    private $itmPhpunitTestSessionBackup = null;

    /** @var mysqli|null */
    private $itmPhpunitTestSessionConn = null;

    /** @var int[] */
    private $itmPhpunitDisposableEmployeeIds = [];

    /**
     * @return array<string,mixed>
     */
    protected function itmPhpunitBeginTestSession(mysqli $conn, int $companyId = 1, bool $asAdmin = true, string $scriptSlug = 'phpunit'): array
    {
        $row = $this->itmPhpunitCreateDisposableSessionActor($conn, $companyId, $asAdmin, $scriptSlug);
        if ($this->itmPhpunitTestSessionBackup === null) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            $this->itmPhpunitTestSessionBackup = $_SESSION;
        }

        $_SESSION = array_merge(
            is_array($this->itmPhpunitTestSessionBackup) ? $this->itmPhpunitTestSessionBackup : [],
            $this->itmPhpunitSessionArrayFromActor($row, $companyId)
        );

        if (function_exists('itm_script_test_employee_set_audit_context')) {
            itm_script_test_employee_set_audit_context($conn, (int)$row['id'], (string)$row['username'], $companyId);
        }

        return $row;
    }

    /**
     * @return array<string,mixed>
     */
    protected function itmPhpunitCreateDisposableSessionActor(mysqli $conn, int $companyId = 1, bool $asAdmin = true, string $scriptSlug = 'phpunit'): array
    {
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';

        $this->itmPhpunitTestSessionConn = $conn;
        $row = itm_script_test_employee_create_session_actor($conn, $companyId, [
            'as_admin' => $asAdmin,
            'script_slug' => $scriptSlug,
        ]);
        if (!is_array($row) || (int)($row['id'] ?? 0) <= 0) {
            $this->fail('Unable to create disposable PHPUnit session actor.');
        }

        $this->itmPhpunitDisposableEmployeeIds[] = (int)$row['id'];

        return $row;
    }

    /**
     * @param array<string,mixed> $actorRow
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    protected function itmPhpunitSessionArrayFromActor(array $actorRow, int $companyId, array $extra = []): array
    {
        return array_merge([
            'company_id' => $companyId,
            'employee_id' => (int)$actorRow['id'],
            'username' => (string)$actorRow['username'],
        ], $extra);
    }

    protected function itmPhpunitTestSessionEmployeeId(): int
    {
        return (int)($_SESSION['employee_id'] ?? 0);
    }

    protected function itmPhpunitEndTestSession(): void
    {
        if ($this->itmPhpunitTestSessionConn instanceof mysqli) {
            require_once ROOT_PATH . 'scripts/lib/itm_script_test_employee.php';
            foreach (array_unique($this->itmPhpunitDisposableEmployeeIds) as $employeeId) {
                if ($employeeId > 0) {
                    itm_script_test_employee_delete($this->itmPhpunitTestSessionConn, $employeeId);
                }
            }
        }

        $this->itmPhpunitDisposableEmployeeIds = [];

        if ($this->itmPhpunitTestSessionBackup !== null && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = $this->itmPhpunitTestSessionBackup;
            if ($this->itmPhpunitTestSessionConn instanceof mysqli) {
                if (!function_exists('itm_script_sync_audit_session_from_php_session')) {
                    require_once ROOT_PATH . 'scripts/lib/itm_script_bootstrap.php';
                }
                if (function_exists('itm_script_sync_audit_session_from_php_session')) {
                    itm_script_sync_audit_session_from_php_session($this->itmPhpunitTestSessionConn);
                }
            }
        }

        $this->itmPhpunitTestSessionBackup = null;
        $this->itmPhpunitTestSessionConn = null;
    }
}
