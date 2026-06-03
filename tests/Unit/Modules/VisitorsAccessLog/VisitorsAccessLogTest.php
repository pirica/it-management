<?php
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Visitors Access Log Module functions
 */
class VisitorsAccessLogTest extends TestCase
{
    protected function setUp(): void
    {
        // Define ROOT_PATH if not already defined for the test environment
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', realpath(__DIR__ . '/../../../../') . '/');
        }

        // Mock itm_is_safe_identifier if not available
        if (!function_exists('itm_is_safe_identifier')) {
            function itm_is_safe_identifier($name) { return preg_match('/^[a-zA-Z0-9_]+$/', $name); }
        }

        require_once ROOT_PATH . 'modules/visitors_access_log/includes/functions.php';
    }

    public function testValIsToday()
    {
        $todayStr = date('Y-m-d H:i:s');
        $this->assertTrue(val_is_today($todayStr), "Should return true for today's datetime string");

        $todayTs = time();
        $this->assertTrue(val_is_today($todayTs), "Should return true for today's timestamp");

        $yesterdayStr = date('Y-m-d H:i:s', strtotime('-1 day'));
        $this->assertFalse(val_is_today($yesterdayStr), "Should return false for yesterday's datetime string");

        $tomorrowStr = date('Y-m-d H:i:s', strtotime('+1 day'));
        $this->assertFalse(val_is_today($tomorrowStr), "Should return false for tomorrow's datetime string");

        $this->assertFalse(val_is_today(null), "Should return false for null");
        $this->assertFalse(val_is_today(''), "Should return false for empty string");
        $this->assertFalse(val_is_today('invalid-date'), "Should return false for invalid date");
    }

    public function testValFormatDatetime()
    {
        $dt = '2026-06-03 15:30:00';
        $formatted = val_format_datetime($dt);
        $this->assertEquals('03-Jun-2026 15:30', $formatted);

        $this->assertEquals('—', val_format_datetime(null));
        $this->assertEquals('—', val_format_datetime(''));
    }

    public function testFunctionsExist()
    {
        $this->assertTrue(function_exists('val_count_logs'), "val_count_logs should exist");
        $this->assertTrue(function_exists('val_fetch_logs'), "val_fetch_logs should exist");
    }
}
