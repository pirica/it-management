<?php
use PHPUnit\Framework\TestCase;

class ApiRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('itm_api_allowed_tiers')) {
            require_once __DIR__ . '/../../../../includes/itm_api_rate_limit.php';
        }
    }

    public function testAllowedTiers(): void
    {
        $this->assertSame(['Free', 'Basic', 'Pro', 'Enterprise'], itm_api_allowed_tiers());
    }

    public function testTierHourlyLimits(): void
    {
        $this->assertSame(60, itm_api_tier_hourly_limit('Free'));
        $this->assertSame(300, itm_api_tier_hourly_limit('Basic'));
        $this->assertSame(1000, itm_api_tier_hourly_limit('Pro'));
        $this->assertSame(10000, itm_api_tier_hourly_limit('Enterprise'));
        $this->assertSame(60, itm_api_tier_hourly_limit('Unknown'));
    }

    public function testNormalizeTier(): void
    {
        $this->assertSame('Pro', itm_api_normalize_tier('Pro'));
        $this->assertSame('Free', itm_api_normalize_tier('invalid'));
    }

    public function testRateLimitStatusResetsExpiredWindow(): void
    {
        $row = [
            'tier' => 'Basic',
            'rate_limit_enabled' => 1,
            'rate_limit_window_start' => time() - 7200,
            'rate_limit_request_count' => 250,
        ];

        $status = itm_api_rate_limit_status_from_row($row);
        $this->assertSame('Basic', $status['tier']);
        $this->assertSame(300, $status['limit']);
        $this->assertSame(300, $status['remaining']);
        $this->assertSame(0, $status['request_count']);
    }

    public function testGenerateKeyLength(): void
    {
        $key = itm_api_generate_key();
        $this->assertNotSame('', $key);
        $this->assertLessThanOrEqual(191, strlen($key));
    }
}
