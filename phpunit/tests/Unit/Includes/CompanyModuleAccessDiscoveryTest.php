<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Tests\Unit\Support\ItmPhpunitTestSessionTrait;

/**
 * In-process sidebar discovery helpers for company module access.
 */
class CompanyModuleAccessDiscoveryTest extends TestCase
{
    use ItmPhpunitTestSessionTrait;

    private const PROBE_SLUG = 'mbqa_phpunit_sidebar_probe';

    /** @var array<string,mixed>|null */
    private $sessionActor;

    protected function setUp(): void
    {
        global $conn;

        if (!$conn instanceof mysqli) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        $this->sessionActor = $this->itmPhpunitBeginTestSession($conn, 1, true, 'cma-discovery');
        $employeeId = (int)$this->sessionActor['id'];

        mysqli_query(
            $conn,
            'INSERT INTO `ui_configuration` (company_id, employee_id, enable_auto_scaffolding) VALUES (1, '
            . $employeeId
            . ', 1) ON DUPLICATE KEY UPDATE enable_auto_scaffolding = 1'
        );
        itm_get_ui_configuration(null, 0, 0, true);
    }

    protected function tearDown(): void
    {
        global $conn;

        if ($conn instanceof mysqli) {
            itm_sidebar_discovery_probe_cleanup($conn, self::PROBE_SLUG);
        }

        $this->itmPhpunitEndTestSession();
    }

    public function testEnsureRegistryRowsForModuleSlugsInsertsMissingRow(): void
    {
        global $conn;

        $this->cleanupProbe($conn);

        $inserted = itm_ensure_registry_rows_for_module_slugs($conn, [self::PROBE_SLUG]);
        $this->assertSame(1, $inserted);

        $row = itm_module_access_registry_row($conn, self::PROBE_SLUG);
        $this->assertIsArray($row);
        $this->assertSame(self::PROBE_SLUG, (string)($row['module_slug'] ?? ''));

        $insertedAgain = itm_ensure_registry_rows_for_module_slugs($conn, [self::PROBE_SLUG]);
        $this->assertSame(0, $insertedAgain);
    }

    public function testNewMysqlTableAppearsInSidebarStructure(): void
    {
        global $conn;

        $this->cleanupProbe($conn);

        $sql = 'CREATE TABLE `' . self::PROBE_SLUG . '` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `company_id` INT NOT NULL,
            `active` TINYINT DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $this->assertTrue((bool)mysqli_query($conn, $sql), mysqli_error($conn));

        itm_sidebar_structure($conn, true);

        $this->assertFileExists(ROOT_PATH . 'modules/' . self::PROBE_SLUG . '/index.php');
        $this->assertTrue(itm_sidebar_structure_contains_slug($conn, self::PROBE_SLUG, true));
        $this->assertTrue(has_module_access($conn, 1, self::PROBE_SLUG));
    }

    /**
     * @param mysqli $conn
     */
    private function cleanupProbe($conn): void
    {
        itm_sidebar_discovery_probe_cleanup($conn, self::PROBE_SLUG);
    }
}
