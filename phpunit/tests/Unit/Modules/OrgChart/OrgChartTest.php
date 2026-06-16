<?php

namespace Tests\Unit\Modules\OrgChart;

use PHPUnit\Framework\TestCase;

class OrgChartTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('itm_is_circular_reporting')) {
            $indexPath = ROOT_PATH . 'modules/org_chart/index.php';
            $content = file_get_contents($indexPath);
            
            // Extract the function itm_is_circular_reporting
            if (preg_match('/function itm_is_circular_reporting.*?return false;\s*\}/s', $content, $matches)) {
                eval($matches[0]);
            }
        }
    }

    public function testCircularReportingDetection()
    {
        if (!function_exists('itm_is_circular_reporting')) {
            $this->markTestSkipped('itm_is_circular_reporting function could not be loaded.');
        }

        $map = [
            1 => 0, // Root
            2 => 1, // 2 reports to 1
            3 => 2, // 3 reports to 2
            4 => 3, // 4 reports to 3
        ];

        // Normal reporting
        $this->assertFalse(itm_is_circular_reporting($map, 1, 4), "Root reporting to leaf is not circular");
        $this->assertFalse(itm_is_circular_reporting($map, 2, 4), "Manager reporting to leaf is not circular");
        
        // Circular reporting
        $this->assertTrue(itm_is_circular_reporting($map, 4, 2), "Manager (2) reporting to subordinate (4) is circular");
        $this->assertTrue(itm_is_circular_reporting($map, 3, 2), "Manager (2) reporting to subordinate (3) is circular");
        $this->assertTrue(itm_is_circular_reporting($map, 2, 2), "Self reporting is circular");
    }
}
