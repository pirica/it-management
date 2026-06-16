<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class CheckDatabaseSqlCompanyNameUniquesUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/check_database_sql_company_name_uniques.php');
    }
}
