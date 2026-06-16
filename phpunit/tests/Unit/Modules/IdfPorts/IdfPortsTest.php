<?php

namespace Tests\Unit\Modules\IdfPorts;

use PHPUnit\Framework\TestCase;

class IdfPortsTest extends TestCase
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

        // Set session company_id for auditing
        mysqli_query($this->conn, "SET @app_company_id = {$this->companyId}");
    }

    private function getOrCreateIdfPosition() {
        $res = mysqli_query($this->conn, "SELECT id FROM `idf_positions` WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }

        // Need dependencies: idf, idf_device_type
        $resIdf = mysqli_query($this->conn, "SELECT id FROM idfs WHERE company_id = {$this->companyId} LIMIT 1");
        $idfId = ($row = mysqli_fetch_assoc($resIdf)) ? $row['id'] : 0;
        if (!$idfId) {
            mysqli_query($this->conn, "INSERT INTO idfs (company_id, idf_name) VALUES ({$this->companyId}, 'Test IDF')");
            $idfId = mysqli_insert_id($this->conn);
        }

        $resDev = mysqli_query($this->conn, "SELECT id FROM idf_device_type WHERE company_id = {$this->companyId} LIMIT 1");
        $devId = ($row = mysqli_fetch_assoc($resDev)) ? $row['id'] : 0;
        if (!$devId) {
            mysqli_query($this->conn, "INSERT INTO idf_device_type (company_id, name) VALUES ({$this->companyId}, 'Patch Panel')");
            $devId = mysqli_insert_id($this->conn);
        }

        mysqli_query($this->conn, "INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name) VALUES ({$this->companyId}, $idfId, 1, $devId, 'Test Panel')");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreatePortType() {
        $res = mysqli_query($this->conn, "SELECT id FROM switch_port_types WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO switch_port_types (company_id, name) VALUES ({$this->companyId}, 'RJ45')");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreateStatus() {
        $res = mysqli_query($this->conn, "SELECT id FROM switch_status WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO switch_status (company_id, status) VALUES ({$this->companyId}, 'Active')");
        return mysqli_insert_id($this->conn);
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['port_no'] = 999;
        $data['position_id'] = $this->getOrCreateIdfPosition();
        $data['port_type'] = $this->getOrCreatePortType();
        $data['status_id'] = $this->getOrCreateStatus();

        $sql = "INSERT INTO `idf_ports` (company_id, `position_id`, `port_no`, `port_type`, `status_id`) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['position_id'];
        $bindValues[] = $data['port_no'];
        $bindValues[] = $data['port_type'];
        $bindValues[] = $data['status_id'];
        $bindTypes = 'iiiii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `idf_ports` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Label ' . uniqid();
        $updateSql = "UPDATE `idf_ports` SET `label` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `label` FROM `idf_ports` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['label']);

        // 4. Delete
        $deleteSql = "DELETE FROM `idf_ports` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `idf_ports` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
