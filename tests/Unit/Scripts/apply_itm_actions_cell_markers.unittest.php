<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ApplyItmActionsCellMarkersUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../scripts/apply_itm_actions_cell_markers.php');
    }
}
