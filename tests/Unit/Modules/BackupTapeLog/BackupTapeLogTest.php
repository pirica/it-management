<?php

namespace Tests\Unit\Modules\BackupTapeLog;

use PHPUnit\Framework\TestCase;

class BackupTapeLogTest extends TestCase
{
    private $conn;
    private $companyId = 1;
    private $serverId = 1;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];

        // Ensure we have a server to test with
        $res = mysqli_query($this->conn, "SELECT id FROM equipment WHERE company_id = 1 LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            $this->serverId = $row['id'];
        }
    }

    public function testCreateLogEntry()
    {
        $date = date('Y-m-d');
        $tape = date('l');
        $status = 'Full';

        // Clean up any existing record for today
        mysqli_query($this->conn, "DELETE FROM backup_tape_log WHERE company_id = {$this->companyId} AND server_id = {$this->serverId} AND log_date = '{$date}'");

        $sql = "INSERT INTO backup_tape_log (company_id, server_id, log_date, tape_to_be_used, backup_status, print_name)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $printName = 'Test Agent';
        mysqli_stmt_bind_param($stmt, 'iissss', $this->companyId, $this->serverId, $date, $tape, $status, $printName);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // Verify
        $res = mysqli_query($this->conn, "SELECT * FROM backup_tape_log WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($status, $row['backup_status']);
        $this->assertEquals($tape, $row['tape_to_be_used']);

        return $id;
    }

    /**
     * @depends testCreateLogEntry
     */
    public function testUpdateLogEntry($id)
    {
        $newStatus = 'Part';
        $sql = "UPDATE backup_tape_log SET backup_status = ? WHERE id = ? AND company_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sii', $newStatus, $id, $this->companyId);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT backup_status FROM backup_tape_log WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($newStatus, $row['backup_status']);
    }

    /**
     * @depends testCreateLogEntry
     */
    public function testDeleteLogEntry($id)
    {
        $sql = "DELETE FROM backup_tape_log WHERE id = ? AND company_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $id, $this->companyId);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM backup_tape_log WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
