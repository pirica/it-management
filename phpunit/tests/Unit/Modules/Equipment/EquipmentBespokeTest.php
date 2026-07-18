<?php

namespace Tests\Unit\Modules\Equipment;

use PHPUnit\Framework\TestCase;

class EquipmentBespokeTest extends TestCase
{
    private $conn;
    private $companyId;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        require_once ROOT_PATH . 'modules/equipment/delete_functions.php';

        // Use company_id 1 which is guaranteed to exist by database.sql
        $this->companyId = 1;

        // Set session company_id for auditing to bypass the 0 FK constraint in audit_logs
        mysqli_query($this->conn, "SET @app_company_id = {$this->companyId}");
    }

    protected function tearDown(): void
    {
        // No cleanup of company 1
    }

    private function getOrCreateEquipmentType($name) {
        $res = mysqli_query($this->conn, "SELECT id FROM equipment_types WHERE company_id = {$this->companyId} AND name = '$name' LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO equipment_types (company_id, name) VALUES ({$this->companyId}, '$name')");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreateEquipmentStatus($name) {
        $res = mysqli_query($this->conn, "SELECT id FROM equipment_statuses WHERE company_id = {$this->companyId} AND name = '$name' LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO equipment_statuses (company_id, name) VALUES ({$this->companyId}, '$name')");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreateColor() {
        $res = mysqli_query($this->conn, "SELECT id FROM cable_colors LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        mysqli_query($this->conn, "INSERT INTO cable_colors (color_name, color_code) VALUES ('Blue', '#0000FF')");
        return mysqli_insert_id($this->conn);
    }

    private function getOrCreateUser() {
        $res = mysqli_query($this->conn, "SELECT id FROM employees WHERE company_id = {$this->companyId} LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            return $row['id'];
        }
        $res = mysqli_query($this->conn, "SELECT id FROM employee_roles WHERE company_id = {$this->companyId} LIMIT 1");
        $roleId = ($row = mysqli_fetch_assoc($res)) ? $row['id'] : 1;
        mysqli_query($this->conn, "INSERT INTO employees (company_id, username, password, email, role_id, active) VALUES ({$this->companyId}, 'testuser', 'pass', 'test@example.com', $roleId, 1)");
        return mysqli_insert_id($this->conn);
    }

    public function testEquipmentDeleteTransactional()
    {
        $uniqueSuffix = uniqid();
        $name = 'Test Switch Delete Trans ' . $uniqueSuffix;

        // 1. Seed
        $typeId = $this->getOrCreateEquipmentType('Switch');
        $statusId = $this->getOrCreateEquipmentStatus('Active');
        $colorId = $this->getOrCreateColor();

        $res = mysqli_query($this->conn, "INSERT INTO equipment (company_id, equipment_type_id, status_id, name) VALUES ({$this->companyId}, $typeId, $statusId, '$name')");
        $this->assertTrue($res, "Failed to insert equipment: " . mysqli_error($this->conn));
        $equipmentId = mysqli_insert_id($this->conn);

        $res = mysqli_query($this->conn, "INSERT INTO switch_ports (company_id, equipment_id, port_number, port_type, status_id, color_id) VALUES ({$this->companyId}, $equipmentId, 999, 'RJ45', 1, $colorId)");
        $this->assertTrue($res, "Failed to insert switch_port: " . mysqli_error($this->conn));
        $portId = mysqli_insert_id($this->conn);

        // 2. Delete Switch (should also delete ports)
        $error = equipment_delete_record($this->conn, $this->companyId, $equipmentId);
        $this->assertNull($error, "Delete should succeed: " . (string)$error);

        // 3. Verify
        // Equipment is soft-deleted
        $res = mysqli_query($this->conn, "SELECT deleted_at FROM equipment WHERE id = $equipmentId");
        $row = mysqli_fetch_assoc($res);
        $this->assertNotNull($row['deleted_at'] ?? null, "Equipment should be soft-deleted (deleted_at should not be null)");

        // Switch ports are hard-deleted
        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM switch_ports WHERE equipment_id = $equipmentId");
        $this->assertEquals(0, (int)mysqli_fetch_assoc($res)['count']);

        // Cleanup
        mysqli_query($this->conn, "DELETE FROM equipment WHERE id = $equipmentId");
    }

    public function testEquipmentDeleteUsageBlock()
    {
        $uniqueSuffix = uniqid();
        $name = 'Test Server Delete Block ' . $uniqueSuffix;

        // 1. Seed
        $typeId = $this->getOrCreateEquipmentType('Server');
        $statusId = $this->getOrCreateEquipmentStatus('Active');
        $employeeId = $this->getOrCreateUser();

        $res = mysqli_query($this->conn, "INSERT INTO equipment (company_id, equipment_type_id, status_id, name) VALUES ({$this->companyId}, $typeId, $statusId, '$name')");
        $this->assertTrue($res, "Failed to insert equipment: " . mysqli_error($this->conn));
        $equipmentId = mysqli_insert_id($this->conn);

        // Create a blocker in tickets (supplemental relation)
        $res = mysqli_query($this->conn, "SELECT id FROM ticket_categories WHERE company_id = {$this->companyId} AND name = 'Hardware' LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            $catId = $row['id'];
        } else {
            mysqli_query($this->conn, "INSERT INTO ticket_categories (company_id, name) VALUES ({$this->companyId}, 'Hardware')");
            $catId = mysqli_insert_id($this->conn);
        }

        $res = mysqli_query($this->conn, "SELECT id FROM ticket_statuses WHERE company_id = {$this->companyId} AND name = 'Open' LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            $statId = $row['id'];
        } else {
            mysqli_query($this->conn, "INSERT INTO ticket_statuses (company_id, name) VALUES ({$this->companyId}, 'Open')");
            $statId = mysqli_insert_id($this->conn);
        }

        $res = mysqli_query($this->conn, "SELECT id FROM ticket_priorities WHERE company_id = {$this->companyId} AND level = 1 LIMIT 1");
        if ($row = mysqli_fetch_assoc($res)) {
            $prioId = $row['id'];
        } else {
            mysqli_query($this->conn, "INSERT INTO ticket_priorities (company_id, name, level) VALUES ({$this->companyId}, 'High', 1)");
            $prioId = mysqli_insert_id($this->conn);
        }

        $res = mysqli_query($this->conn, "INSERT INTO tickets (company_id, title, category_id, status_id, priority_id, equipment_id, created_by_employee_id) VALUES ({$this->companyId}, 'Broken Server Test', $catId, $statId, $prioId, $equipmentId, $employeeId)");
        $this->assertTrue($res, "Failed to insert ticket: " . mysqli_error($this->conn));
        $ticketId = mysqli_insert_id($this->conn);

        // 2. Delete (should be blocked)
        $error = equipment_delete_record($this->conn, $this->companyId, $equipmentId);
        $this->assertNotNull($error, "Delete should be blocked by ticket reference");
        $this->assertStringContainsString('ticket', strtolower((string)$error));

        // 3. Verify record still exists
        $res = mysqli_query($this->conn, "SELECT COUNT(*) as count FROM equipment WHERE id = $equipmentId");
        $this->assertEquals(1, (int)mysqli_fetch_assoc($res)['count']);
        
        // Cleanup
        mysqli_query($this->conn, "DELETE FROM tickets WHERE id = $ticketId");
        mysqli_query($this->conn, "DELETE FROM equipment WHERE id = $equipmentId");
    }
}
