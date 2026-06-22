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

    public function testTableDoesNotHaveActiveColumn()
    {
        $res = mysqli_query($this->conn, "SHOW COLUMNS FROM `workstation_ram` LIKE 'active'");
        $this->assertEquals(0, mysqli_num_rows($res), "Table 'workstation_ram' should not have an 'active' column.");
    }

    public function testFilesDoNotHaveHardcodedActiveInput()
    {
        $modulePath = ROOT_PATH . 'modules/workstation_ram/';
        $files = ['create.php', 'edit.php', 'index.php'];

        foreach ($files as $file) {
            $path = $modulePath . $file;
            $this->assertFileExists($path);
            $content = file_get_contents($path);

            // Check for hardcoded input fields named "active"
            // We allow mentions of "active" in generic logic/comments, but not as a hardcoded input name
            $this->assertDoesNotMatchRegularExpression('/<input[^>]+name=["\']active["\']/', $content, "File $file should not contain a hardcoded 'active' input field.");
        }
    }
}
