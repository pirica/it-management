<?php

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class CrudAuditFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../config/config.php';
        require_once ROOT_PATH . 'includes/itm_crud_audit_fields.php';
    }

    public function testStampCreateAuditUsesNullSoftDeleteColumns()
    {
        $_SESSION['employee_id'] = 1;
        $data = [
            'title' => 'Audit bind test',
            'deleted_by' => '',
            'deleted_at' => '',
            'updated_at' => '',
        ];

        itm_crud_stamp_create_audit($data);

        $this->assertNull($data['deleted_by']);
        $this->assertNull($data['deleted_at']);
        $this->assertSame(1, (int)$data['created_by']);
        $this->assertSame(1, (int)$data['updated_by']);
        $this->assertNotSame('', (string)$data['created_at']);
        $this->assertNotSame('', (string)$data['updated_at']);
    }

    public function testNormalizeBindValuesConvertsEmptyDatetimeToNull()
    {
        $fieldColumns = [
            ['Field' => 'deleted_at', 'Type' => 'timestamp'],
            ['Field' => 'end_datetime', 'Type' => 'datetime'],
            ['Field' => 'title', 'Type' => 'varchar(255)'],
        ];
        $data = [
            'deleted_at' => '',
            'end_datetime' => '',
            'title' => '',
        ];

        itm_crud_normalize_bind_values_for_persist($data, $fieldColumns);

        $this->assertNull($data['deleted_at']);
        $this->assertNull($data['end_datetime']);
        $this->assertSame('', $data['title']);
    }

    public function testAlertsCreateInsertAcceptsStampedAuditColumns()
    {
        $conn = $GLOBALS['conn'] ?? null;
        if (!$conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        $companyId = 1;
        $employeeId = 1;
        $_SESSION['employee_id'] = $employeeId;
        $_SESSION['company_id'] = $companyId;

        $fieldColumns = [];
        $res = mysqli_query($conn, 'DESCRIBE alerts');
        if (!$res) {
            $this->fail(mysqli_error($conn));
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $fieldColumns[] = $row;
        }

        $title = 'Crud audit bind test ' . uniqid();
        $data = [
            'company_id' => $companyId,
            'title' => $title,
            'description' => 'PHPUnit audit bind',
            'start_datetime' => null,
            'end_datetime' => null,
            'location' => null,
            'category_id' => null,
            'assigned_to_employee_id' => null,
            'active' => 1,
            'deleted_by' => null,
            'deleted_at' => null,
            'created_by' => null,
            'created_at' => null,
            'updated_by' => null,
            'updated_at' => null,
        ];

        itm_crud_stamp_create_audit($data);
        itm_crud_normalize_bind_values_for_persist($data, $fieldColumns);

        $fields = [];
        $placeholders = [];
        $params = [];
        $types = '';
        foreach ($fieldColumns as $col) {
            $name = $col['Field'];
            if ($name === 'id') {
                continue;
            }
            $fields[] = '`' . $name . '`';
            $placeholders[] = '?';
            $params[] = $data[$name] ?? null;
            $colType = strtolower($col['Type']);
            if (strpos($colType, 'int') !== false || strpos($colType, 'decimal') !== false || strpos($colType, 'float') !== false || strpos($colType, 'double') !== false) {
                $types .= (($data[$name] ?? null) === null) ? 's' : (strpos($colType, 'int') !== false ? 'i' : 'd');
            } else {
                $types .= 's';
            }
        }

        $sql = 'INSERT INTO alerts (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $this->fail(mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $this->assertTrue(mysqli_stmt_execute($stmt), mysqli_stmt_error($stmt));
        $insertId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $this->assertGreaterThan(0, $insertId);
        mysqli_query($conn, 'DELETE FROM alerts WHERE id = ' . $insertId);
    }
}
