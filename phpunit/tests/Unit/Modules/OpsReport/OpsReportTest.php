<?php

namespace Tests\Unit\Modules\OpsReport;

use PHPUnit\Framework\TestCase;

class OpsReportTest extends TestCase
{
    private $conn;
    private $companyId = 1;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
    }

    public function testCRUD()
    {
        $reportDate = date('Y-m-d');
        mysqli_query($this->conn, "DELETE FROM ops_report WHERE company_id = {$this->companyId} AND report_date = '{$reportDate}'");

        $shift = 'PHPUnit today shift';
        $sql = 'INSERT INTO ops_report (company_id, report_date, today_shift, active) VALUES (?, ?, ?, 1)';
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        mysqli_stmt_bind_param($stmt, 'iss', $this->companyId, $reportDate, $shift);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = (int)mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT * FROM ops_report WHERE id = {$id}");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, (int)$row['company_id']);
        $this->assertEquals($shift, $row['today_shift']);

        $updated = 'Updated shift';
        $stmt = mysqli_prepare($this->conn, 'UPDATE ops_report SET today_shift = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $updated, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT today_shift FROM ops_report WHERE id = {$id}");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updated, $row['today_shift']);

        $stmt = mysqli_prepare($this->conn, 'DELETE FROM ops_report WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) AS c FROM ops_report WHERE id = {$id}");
        $this->assertEquals(0, (int)mysqli_fetch_assoc($res)['c']);
    }
}
