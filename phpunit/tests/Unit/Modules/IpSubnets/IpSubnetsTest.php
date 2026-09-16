<?php

namespace Tests\Unit\Modules\IpSubnets;

use PHPUnit\Framework\TestCase;

class IpSubnetsTest extends TestCase
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
        $data['cidr'] = 'Test cidr';
        $data['network_ip'] = 'Test network_ip';
        $data['prefix_length'] = 1;
        $data['active'] = 1;
        // Find or fallback for vlan_id (vlans)
        $resvlan_id = mysqli_query($this->conn, "SELECT id FROM `vlans` WHERE " . (strpos('vlans', 'companies') === false && strpos('vlans', 'employees') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowvlan_id = mysqli_fetch_assoc($resvlan_id)) {
            $data['vlan_id'] = $rowvlan_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['vlan_id'] = null;
        }

        $sql = "INSERT INTO `ip_subnets` (company_id, `cidr`, `network_ip`, `prefix_length`, `active`) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['cidr'];
        $bindValues[] = $data['network_ip'];
        $bindValues[] = $data['prefix_length'];
        $bindValues[] = $data['active'];
        $bindTypes = 'issii';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `ip_subnets` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `ip_subnets` SET `cidr` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `cidr` FROM `ip_subnets` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['cidr']);

        // 4. Delete
        $deleteSql = "DELETE FROM `ip_subnets` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `ip_subnets` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
