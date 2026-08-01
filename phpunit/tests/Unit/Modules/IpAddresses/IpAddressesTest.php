<?php

namespace Tests\Unit\Modules\IpAddresses;

use PHPUnit\Framework\TestCase;

class IpAddressesTest extends TestCase
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
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['ip_text'] = 'Test ip_text';
        $data['status'] = 'free';
        $data['active'] = 1;
        // Find or fallback for subnet_id (ip_subnets)
        $ressubnet_id = mysqli_query($this->conn, "SELECT id FROM `ip_subnets` WHERE " . (strpos('ip_subnets', 'companies') === false && strpos('ip_subnets', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowsubnet_id = mysqli_fetch_assoc($ressubnet_id)) {
            $data['subnet_id'] = $rowsubnet_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $this->markTestSkipped('Required dependency ip_subnets not found in database.');
        }
        // Find or fallback for equipment_id (equipment)
        $resequipment_id = mysqli_query($this->conn, "SELECT id FROM `equipment` WHERE " . (strpos('equipment', 'companies') === false && strpos('equipment', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_id = mysqli_fetch_assoc($resequipment_id)) {
            $data['equipment_id'] = $rowequipment_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_id'] = null;
        }

        $sql = "INSERT INTO `ip_addresses` (company_id, `subnet_id`, `ip_text`, `status`, `active`) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['subnet_id'];
        $bindValues[] = $data['ip_text'];
        $bindValues[] = $data['status'];
        $bindValues[] = $data['active'];
        $bindTypes = 'iissi';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `ip_addresses` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `ip_addresses` SET `ip_text` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `ip_text` FROM `ip_addresses` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['ip_text']);

        // 4. Delete
        $deleteSql = "DELETE FROM `ip_addresses` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `ip_addresses` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
