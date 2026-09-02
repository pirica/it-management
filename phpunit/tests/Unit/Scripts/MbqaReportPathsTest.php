<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class MbqaReportPathsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once __DIR__ . '/../../../../scripts/lib/mbqa_report_paths.php';
    }

    public function testReportDir(): void
    {
        $this->assertEquals('/app/qa-reports', str_replace('\\', '/', mbqa_report_dir('/app')));
    }

    public function testTimestampSlug(): void
    {
        $slug = mbqa_report_timestamp_slug('2026-05-22 19:26:09');
        $this->assertEquals('2026-05-22-19-26-09', $slug);

        $slugNow = mbqa_report_timestamp_slug();
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/', $slugNow);
    }

    public function testReportPathsForRun(): void
    {
        $paths = mbqa_report_paths_for_run('/app', '2026-05-22 19:26:09');
        $this->assertEquals('2026-05-22-19-26-09', $paths['slug']);
        $this->assertEquals('module-browser-qa-2026-05-22-19-26-09.json', $paths['json_basename']);
        $this->assertStringContainsString('qa-reports', $paths['json_path']);
    }
}
