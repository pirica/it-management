<?php

namespace Tests\Unit\Modules\IdfLinks;

use PHPUnit\Framework\TestCase;

class IdfLinksTest extends TestCase
{
    private $conn;
    private $companyId = 1;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        // Set session company_id for auditing
        mysqli_query($this->conn, "SET @app_company_id = {$this->companyId}");
    }

    private function getOrCreateIdfPort($portNo = 1) {
        $res = mysqli_query($this->conn, "SELECT id FROM `idf_ports` WHERE company_id = {$this->companyId} AND port_no = $portNo LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }

        // Need dependencies: idf_position, switch_port_type, switch_status
        $resPos = mysqli_query($this->conn, "SELECT id FROM idf_positions WHERE company_id = {$this->companyId} LIMIT 1");
        $posId = ($row = mysqli_fetch_assoc($resPos)) ? $row['id'] : 0;
        if (!$posId) {
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
            $posId = mysqli_insert_id($this->conn);
        }

        $resPortType = mysqli_query($this->conn, "SELECT id FROM switch_port_types WHERE company_id = {$this->companyId} LIMIT 1");
        $portTypeId = ($row = mysqli_fetch_assoc($resPortType)) ? $row['id'] : 0;
        if (!$portTypeId) {
            mysqli_query($this->conn, "INSERT INTO switch_port_types (company_id, name) VALUES ({$this->companyId}, 'RJ45')");
            $portTypeId = mysqli_insert_id($this->conn);
        }

        $resStat = mysqli_query($this->conn, "SELECT id FROM switch_status WHERE company_id = {$this->companyId} LIMIT 1");
        $statId = ($row = mysqli_fetch_assoc($resStat)) ? $row['id'] : 0;
        if (!$statId) {
            mysqli_query($this->conn, "INSERT INTO switch_status (company_id, status) VALUES ({$this->companyId}, 'Active')");
            $statId = mysqli_insert_id($this->conn);
        }

        mysqli_query($this->conn, "INSERT INTO `idf_ports` (company_id, position_id, port_no, port_type, status_id) VALUES ({$this->companyId}, $posId, $portNo, $portTypeId, $statId)");
        return mysqli_insert_id($this->conn);
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['port_id_a'] = $this->getOrCreateIdfPort(101);
        $data['port_id_b'] = $this->getOrCreateIdfPort(102);

        $sql = "INSERT INTO `idf_links` (company_id, `port_id_a`, `port_id_b`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['port_id_a'];
        $bindValues[] = $data['port_id_b'];
        $bindTypes = 'iii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `idf_links` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'EQ' . substr(uniqid(), -7); // Keep it under 9 chars
        $updateSql = "UPDATE `idf_links` SET `equipment_id` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `equipment_id` FROM `idf_links` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['equipment_id']);

        // 4. Delete
        $deleteSql = "DELETE FROM `idf_links` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `idf_links` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
