<?php

declare(strict_types=1);

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class SwitchPortApiHelpersTest extends TestCase
{
    /** @var \mysqli|null */
    private $conn;

    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once ROOT_PATH . 'includes/switch_port_api_helpers.php';
        $this->conn = $GLOBALS['conn'] ?? null;
    }

    public function testFindLookupIdByNumericId(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Active'],
            ['id' => 2, 'name' => 'Inactive'],
        ];
        $this->assertSame(1, find_lookup_id($rows, 1));
        $this->assertSame(2, find_lookup_id($rows, '2'));
        $this->assertSame(0, find_lookup_id($rows, 99));
        $this->assertSame(0, find_lookup_id($rows, ''));
        $this->assertSame(0, find_lookup_id($rows, null));
    }

    public function testFindLookupIdByName(): void
    {
        $rows = [
            ['id' => 10, 'name' => 'Unknown'],
            ['id' => 11, 'name' => 'Active Port'],
        ];
        $this->assertSame(10, find_lookup_id($rows, 'unknown'));
        $this->assertSame(11, find_lookup_id($rows, ' Active Port '));
        $this->assertSame(0, find_lookup_id($rows, 'Missing'));
    }

    public function testFetchLookupMapRejectsUnsafeIdentifiers(): void
    {
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        $this->assertSame([], fetch_lookup_map($this->conn, 'bad-table', 'name'));
        $this->assertSame([], fetch_lookup_map($this->conn, 'vlans', 'bad column'));
    }

    public function testFetchCompanyVlansReturnsRowsForSeededCompany(): void
    {
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        $GLOBALS['company_id'] = 1;
        $rows = fetch_company_vlans($this->conn, 1);
        $this->assertIsArray($rows);
        if ($rows !== []) {
            $this->assertArrayHasKey('id', $rows[0]);
            $this->assertArrayHasKey('name', $rows[0]);
            $this->assertArrayHasKey('color', $rows[0]);
        }
    }

    public function testFetchLookupMapForVlansWhenCompanyScoped(): void
    {
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }

        $GLOBALS['company_id'] = 1;
        $rows = fetch_lookup_map($this->conn, 'switch_status', 'status_name');
        $this->assertIsArray($rows);
        foreach ($rows as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('name', $row);
        }
    }
}
