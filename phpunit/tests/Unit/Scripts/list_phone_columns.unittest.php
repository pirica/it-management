<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
class ListPhoneColumnsUnittest extends TestCase {
    public function testFileExists(): void {
        $this->assertFileExists(__DIR__ . '/../../../../scripts/list_phone_columns.php');
    }
}
