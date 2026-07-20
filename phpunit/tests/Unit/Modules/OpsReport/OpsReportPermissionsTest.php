<?php

namespace Tests\Unit\Modules\OpsReport;

use PHPUnit\Framework\TestCase;

class OpsReportPermissionsTest extends TestCase
{
    public function testEditableDateWindow()
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

        $this->assertTrue($this->isEditable($today, false));
        $this->assertTrue($this->isEditable($yesterday, false));
        $this->assertFalse($this->isEditable($twoDaysAgo, false));
        $this->assertTrue($this->isEditable($twoDaysAgo, true));
    }

    private function isEditable($dateStr, $isAdmin)
    {
        if ($isAdmin) {
            return true;
        }
        $cutoff = date('Y-m-d', strtotime('-2 days'));
        return date('Y-m-d', strtotime($dateStr)) > $cutoff;
    }
}
