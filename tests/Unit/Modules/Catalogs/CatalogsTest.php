<?php

namespace Tests\Unit\Modules\Catalogs;

use PHPUnit\Framework\TestCase;

class CatalogsTest extends TestCase
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
    }

    public function testCRUD()
    {
        // 1. Create
        $data = [];
        $data['company_id'] = $this->companyId;
        $data['model'] = 'Test model';
        $data['active'] = 1;
        // Find or fallback for equipment_type_id (equipment_types)
        $resequipment_type_id = mysqli_query($this->conn, "SELECT id FROM `equipment_types` WHERE " . (strpos('equipment_types', 'companies') === false && strpos('equipment_types', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowequipment_type_id = mysqli_fetch_assoc($resequipment_type_id)) {
            $data['equipment_type_id'] = $rowequipment_type_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['equipment_type_id'] = null;
        }
        // Find or fallback for supplier_id (suppliers)
        $ressupplier_id = mysqli_query($this->conn, "SELECT id FROM `suppliers` WHERE " . (strpos('suppliers', 'companies') === false && strpos('suppliers', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowsupplier_id = mysqli_fetch_assoc($ressupplier_id)) {
            $data['supplier_id'] = $rowsupplier_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['supplier_id'] = null;
        }
        // Find or fallback for manufacturer_id (manufacturers)
        $resmanufacturer_id = mysqli_query($this->conn, "SELECT id FROM `manufacturers` WHERE " . (strpos('manufacturers', 'companies') === false && strpos('manufacturers', 'users') === false ? "company_id = {$this->companyId}" : "1=1") . " LIMIT 1");
        if ($rowmanufacturer_id = mysqli_fetch_assoc($resmanufacturer_id)) {
            $data['manufacturer_id'] = $rowmanufacturer_id['id'];
        } else {
            // If no existing record, we might need to seed it, but for now we skip this test if mandatory
            $data['manufacturer_id'] = null;
        }

        $sql = "INSERT INTO `catalogs` (company_id, `model`, `active`) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->assertNotFalse($stmt, mysqli_error($this->conn));
        
        $bindValues = [];
        $bindValues[] = $data['company_id'];
        $bindValues[] = $data['model'];
        $bindValues[] = $data['active'];
        $bindTypes = 'isi';
        mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
        
        $this->assertTrue(mysqli_stmt_execute($stmt));
        $id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        // 2. Read
        $res = mysqli_query($this->conn, "SELECT * FROM `catalogs` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row);
        $this->assertEquals($this->companyId, $row['company_id']);

        // 3. Update
        $updatedValue = 'Updated Value';
        $updateSql = "UPDATE `catalogs` SET `model` = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $updateSql);
        mysqli_stmt_bind_param($stmt, 'si', $updatedValue, $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT `model` FROM `catalogs` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals($updatedValue, $row['model']);

        // 4. Delete
        $deleteSql = "DELETE FROM `catalogs` WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $deleteSql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $this->assertTrue(mysqli_stmt_execute($stmt));
        mysqli_stmt_close($stmt);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM `catalogs` WHERE id = $id");
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(0, (int)$row['count']);
    }
}
