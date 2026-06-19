<?php

namespace Tests\Unit\Modules\BackupTapeLog;

use PHPUnit\Framework\TestCase;

class BackupTapeLogPermissionsTest extends TestCase
{
    private $conn;
    private $companyId = 1;
    private $serverId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        // Ensure we have a server
        $res = mysqli_query($this->conn, "SELECT id FROM equipment WHERE company_id = {$this->companyId} AND active = 1 LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            $this->serverId = $row['id'];
        } else {
            // Seed a server if none exists
            mysqli_query($this->conn, "INSERT INTO equipment (company_id, equipment_type_id, name, hostname, status_id, active) VALUES ({$this->companyId}, 2, 'Test Server', 'srv-test-01', 1, 1)");
            $this->serverId = mysqli_insert_id($this->conn);
        }

        // Clean up any existing test logs for today/yesterday
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        mysqli_query($this->conn, "DELETE FROM backup_tape_log WHERE company_id = {$this->companyId} AND log_date IN ('$today', '$yesterday')");
    }

    /**
     * Helper to simulate a POST request to index.php by running it in a separate process
     */
    private function simulateAjaxRequest($postData, $sessionData = [])
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_sim_');
        $dir = str_replace('\\', '/', __DIR__);
        $sessionDataMerged = array_merge([
            'company_id' => $this->companyId,
            'employee_id' => 1,
            'role_name' => 'admin'
        ], $sessionData);
        $sessionDataExport = var_export($sessionDataMerged, true);
        $postDataExport = var_export($postData, true);

        $script = '<?php
define("ITM_CLI_SCRIPT", true);
error_reporting(0);
session_start();
require_once "' . $dir . '/../../../../../config/config.php";
$_SESSION = ' . $sessionDataExport . ';
$_SESSION["csrf_token"] = itm_get_csrf_token();
$_SERVER["REQUEST_METHOD"] = "POST";
$_POST = ' . $postDataExport . ';
$_POST["csrf_token"] = $_SESSION["csrf_token"];
chdir("' . $dir . '/../../../../../modules/backup_tape_log");
include "index.php";
';
        file_put_contents($tmpFile, $script);
        $output = shell_exec("php $tmpFile 2>&1");
        unlink($tmpFile);

        $decoded = json_decode($output, true);
        if ($decoded === null && !empty($output)) {
            if (preg_match('/\{.*\}/s', $output, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }
        return $decoded;
    }

    public function testRegularUserCanPunchTimeForToday()
    {
        $today = date('Y-m-d');
        $postData = [
            'action_timestamp' => '1',
            'type' => 'inserted',
            'server_id' => $this->serverId,
            'log_date' => $today
        ];

        $sessionData = [
            'role_name' => 'user',
            'employee_id' => 999
        ];

        $result = $this->simulateAjaxRequest($postData, $sessionData);

        $this->assertNotNull($result, "Result should not be null.");
        $this->assertTrue($result['success'], "Regular user should be able to punch time for today.");
    }

    public function testRegularUserCannotPunchTimeForPastDate()
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $postData = [
            'action_timestamp' => '1',
            'type' => 'inserted',
            'server_id' => $this->serverId,
            'log_date' => $yesterday
        ];

        $sessionData = [
            'role_name' => 'user',
            'employee_id' => 999
        ];

        $result = $this->simulateAjaxRequest($postData, $sessionData);

        $this->assertNotNull($result, "Result should not be null.");
        $this->assertFalse($result['success'], "Regular user should NOT be able to punch time for yesterday.");
    }
}
