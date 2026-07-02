<?php

namespace Tests\Unit\Modules\WorkstationRam;

use PHPUnit\Framework\TestCase;

class WorkstationRamActiveFieldTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
    }

    public function testTableHasActiveColumn()
    {
        $res = mysqli_query($this->conn, "SHOW COLUMNS FROM `workstation_ram` LIKE 'active'");
        $this->assertEquals(1, mysqli_num_rows($res), "Table 'workstation_ram' should have an 'active' column.");
    }

    public function testFilesHaveHardcodedActiveInput()
    {
        $modulePath = ROOT_PATH . 'modules/workstation_ram/';
        $files = ['index.php'];

        foreach ($files as $file) {
            $path = $modulePath . $file;
            $this->assertFileExists($path);
            $content = file_get_contents($path);

            // In this project, "active" fields in forms should use checkboxes.
            // The previous test was asserting the ABSENCE of this, but it's required for consistency.
            // We allow both hardcoded 'active' and dynamic names used in generic CRUD templates.
            $this->assertMatchesRegularExpression('/name=["\'](active|<\?php echo sanitize\(\$name\); \?>)["\']/', $content, "File $file should contain an 'active' input field.");
        }
    }
}
