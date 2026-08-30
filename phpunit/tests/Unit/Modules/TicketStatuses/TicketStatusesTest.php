<?php

namespace Tests\Unit\Modules\TicketStatuses;

use PHPUnit\Framework\TestCase;

class TicketStatusesTest extends TestCase
{
    private $conn;
    private $companyId = 1;
    private $createdId = null;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->conn && $this->createdId) {
            $id = (int) $this->createdId;
            mysqli_query($this->conn, "DELETE FROM `ticket_statuses` WHERE id = $id");
            $this->createdId = null;
        }
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        // Why: (company_id, name) is unique; a fixed label leaves orphan rows after failed runs.
        $data['name'] = 'Test name ' . uniqid('', true);
        $data['active'] = 1;

        $sql = "INSERT INTO `ticket_statuses` (company_id, `name`, `active`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['name'];
        $bindValues[] = $data['active'];
        $bindTypes = 'isi';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        $this->createdId = $id;
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `ticket_statuses` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value ' . uniqid('', true);
        $updateSql = "UPDATE `ticket_statuses` SET `name` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `name` FROM `ticket_statuses` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['name']);

        // 4. Delete
        $deleteSql = "DELETE FROM `ticket_statuses` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `ticket_statuses` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
