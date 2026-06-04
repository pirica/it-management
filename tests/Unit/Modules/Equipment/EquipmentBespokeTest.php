<?php

namespace Tests\Unit\Modules\Equipment;

use PHPUnit\Framework\TestCase;

class EquipmentBespokeTest extends TestCase
{
    private $conn;
    private $companyId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        require_once ROOT_PATH . 'modules/equipment/delete_functions.php';

        // Create a temporary company
        mysqli_query($this->conn, "INSERT INTO companies (company, active) VALUES ('Test Company Bespoke Equipment', 1)");
        $this->companyId = mysqli_insert_id($this->conn);
    }

    protected function tearDown(): void
    {
        if ($this->companyId) {
            // Cleanup equipment and dependencies for this company
            mysqli_query($this->conn, "DELETE FROM switch_ports WHERE company_id = {$this->companyId}");
            mysqli_query($this->conn, "DELETE FROM idf_positions WHERE company_id = {$this->companyId}");
            mysqli_query($this->conn, "DELETE FROM equipment WHERE company_id = {$this->companyId}");
            mysqli_query($this->conn, "DELETE FROM equipment_types WHERE company_id = {$this->companyId}");
            mysqli_query($this->conn, "DELETE FROM equipment_statuses WHERE company_id = {$this->companyId}");
            mysqli_query($this->conn, "DELETE FROM companies WHERE id = {$this->companyId}");
        }
    }

    public function testEquipmentDeleteTransactional()
    {
        // 1. Seed
        mysqli_query($this->conn, "INSERT INTO equipment_types (company_id, name) VALUES ({$this->companyId}, 'Switch')");
        $typeId = mysqli_insert_id($this->conn);
        mysqli_query($this->conn, "INSERT INTO equipment_statuses (company_id, name) VALUES ({$this->companyId}, 'Active')");
        $statusId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO equipment (company_id, equipment_type_id, status_id, name) VALUES ({$this->companyId}, $typeId, $statusId, 'Test Switch')");
        $equipmentId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO switch_ports (company_id, equipment_id, port_number, port_type, status_id) VALUES ({$this->companyId}, $equipmentId, 1, 'RJ45', 1)");
        $portId = mysqli_insert_id($this->conn);

        // 2. Delete Switch (should also delete ports)
        $error = equipment_delete_record($this->conn, $this->companyId, $equipmentId);
        $this->assertNull($error, "Delete should succeed: " . (string)$error);

        // 3. Verify
        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM equipment WHERE id = $equipmentId");
        $this->assertEquals(0, (int)mysqli_fetch_assoc($res)['count']);

        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM switch_ports WHERE equipment_id = $equipmentId");
        $this->assertEquals(0, (int)mysqli_fetch_assoc($res)['count']);
    }

    public function testEquipmentDeleteUsageBlock()
    {
        // 1. Seed
        mysqli_query($this->conn, "INSERT INTO equipment_types (company_id, name) VALUES ({$this->companyId}, 'Server')");
        $typeId = mysqli_insert_id($this->conn);
        mysqli_query($this->conn, "INSERT INTO equipment_statuses (company_id, name) VALUES ({$this->companyId}, 'Active')");
        $statusId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO equipment (company_id, equipment_type_id, status_id, name) VALUES ({$this->companyId}, $typeId, $statusId, 'Test Server')");
        $equipmentId = mysqli_insert_id($this->conn);

        // Create a blocker in tickets (supplemental relation)
        mysqli_query($this->conn, "INSERT INTO ticket_categories (company_id, name) VALUES ({$this->companyId}, 'Hardware')");
        $catId = mysqli_insert_id($this->conn);
        mysqli_query($this->conn, "INSERT INTO ticket_statuses (company_id, name) VALUES ({$this->companyId}, 'Open')");
        $statId = mysqli_insert_id($this->conn);
        mysqli_query($this->conn, "INSERT INTO ticket_priorities (company_id, name, level) VALUES ({$this->companyId}, 'High', 1)");
        $prioId = mysqli_insert_id($this->conn);

        mysqli_query($this->conn, "INSERT INTO tickets (company_id, title, category_id, status_id, priority_id, asset_id) VALUES ({$this->companyId}, 'Broken Server', $catId, $statId, $prioId, $equipmentId)");

        // 2. Delete (should be blocked)
        $error = equipment_delete_record($this->conn, $this->companyId, $equipmentId);
        $this->assertNotNull($error, "Delete should be blocked by ticket reference");
        $this->assertStringContainsString('ticket', $error);

        // 3. Verify record still exists
        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM equipment WHERE id = $equipmentId");
        $this->assertEquals(1, (int)mysqli_fetch_assoc($res)['count']);
        
        // Cleanup ticket blocker
        mysqli_query($this->conn, "DELETE FROM tickets WHERE company_id = {$this->companyId}");
        mysqli_query($this->conn, "DELETE FROM ticket_categories WHERE company_id = {$this->companyId}");
        mysqli_query($this->conn, "DELETE FROM ticket_statuses WHERE company_id = {$this->companyId}");
        mysqli_query($this->conn, "DELETE FROM ticket_priorities WHERE company_id = {$this->companyId}");
    }
}
