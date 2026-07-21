<?php
use PHPUnit\Framework\TestCase;

/**
 * Tests for database-level helpers and constraint parsing.
 */
class DatabaseUnittest extends TestCase
{
    /**
     * Test itm_mysql_error_extract_column() for parsing MySQL error messages.
     */
    public function testMysqlErrorExtractColumn()
    {
        $this->assertEquals('name', itm_mysql_error_extract_column("Column 'name' cannot be null"));
        $this->assertEquals('email', itm_mysql_error_extract_column("Field 'email' doesn't have a default value"));
        $this->assertEquals('description', itm_mysql_error_extract_column("Data too long for column 'description' at row 1"));
    }

    /**
     * Test itm_format_db_constraint_error() for user-friendly error messages.
     */
    public function testFormatDbConstraintError()
    {
        // Test Duplicate Entry
        $this->assertStringContainsString('already exists', itm_format_db_constraint_error(1062));
        
        // Test Foreign Key Constraint Fail (Delete)
        $msg = "Cannot delete or update a parent row: a foreign key constraint fails (`itmanagement`.`employees`, CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`))";
        $this->assertStringContainsString('cannot be deleted because other records still reference it', itm_format_db_constraint_error(1451, $msg));
        $this->assertStringContainsString('Referenced by table "employees"', itm_format_db_constraint_error(1451, $msg));

        $fkMsg = "Cannot add or update a child row: a foreign key constraint fails (`itmanagement`.`equipment`, CONSTRAINT `equipment_ibfk_4` FOREIGN KEY (`status_id`) REFERENCES `equipment_statuses` (`id`))";
        $this->assertStringContainsString('Status', itm_format_db_constraint_error(1452, $fkMsg));
    }
}
