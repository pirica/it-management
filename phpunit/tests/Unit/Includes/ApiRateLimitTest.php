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
        $this->assertSame(0, itm_api_tier_hourly_limit('Free'));
        $this->assertTrue(itm_api_tier_is_unlimited('Free'));
        $this->assertSame(300, itm_api_tier_hourly_limit('Basic'));
        $this->assertSame(1000, itm_api_tier_hourly_limit('Pro'));
        $this->assertSame(10000, itm_api_tier_hourly_limit('Enterprise'));
        $this->assertSame(0, itm_api_tier_hourly_limit('Unknown'));
    }

    public function testFreeTierStatusIsUnlimited(): void
    {
        $row = [
            'tier' => 'Free',
            'rate_limit_enabled' => 1,
            'rate_limit_window_start' => time() - 120,
            'rate_limit_request_count' => 9999,
        ];

        $status = itm_api_rate_limit_status_from_row($row);
        $this->assertTrue($status['unlimited']);
        $this->assertSame(0, $status['limit']);
        $this->assertNull($status['remaining']);
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

    public function testFreeTierDoesNotRequireApiKey(): void
    {
        $this->assertFalse(itm_api_tier_requires_api_key('Free'));
        $this->assertTrue(itm_api_tier_requires_api_key('Basic'));
        $this->assertTrue(itm_api_tier_requires_api_key('Pro'));
        $this->assertTrue(itm_api_tier_requires_api_key('Enterprise'));
    }

    public function testFreeProbePayloadMarksKeyOptional(): void
    {
        $row = [
            'id' => 1,
            'company_id' => 1,
            'employee_id' => 2,
            'api_key' => '',
            'api_key_is_active' => 1,
            'tier' => 'Free',
            'rate_limit_enabled' => 0,
            'rate_limit_window_start' => 0,
            'rate_limit_request_count' => 0,
        ];

        $payload = itm_api_build_rate_limit_probe_payload($row);
        $this->assertTrue($payload['ok']);
        $this->assertSame(2, $payload['employee_id']);
        $this->assertArrayNotHasKey('user_id', $payload);
        $this->assertFalse($payload['api_key_required']);
        $this->assertTrue($payload['unlimited']);
    }

    public function testApiKeyLastUsedDisplayLabelNeverWhenEmpty(): void
    {
        $this->assertSame('Never', itm_api_format_key_last_used_display_label(null));
        $this->assertSame('Never', itm_api_format_key_last_used_display_label(''));
        $this->assertSame('Never', itm_api_format_key_last_used_display_label('   '));
    }

    public function testApiKeyLastUsedDisplayLabelPreservesTimestamp(): void
    {
        if (!function_exists('itm_format_datetime_display')) {
            require_once __DIR__ . '/../../../../includes/itm_date_format.php';
        }

        $stamp = '2026-06-18 14:30:00';
        $this->assertSame('18/Jun/2026 14:30', itm_api_format_key_last_used_display_label($stamp));
        $this->assertSame('18/Jun/2026 14:30', itm_api_format_key_last_used_display_label('  ' . $stamp . '  '));
    }

    public function testApiKeyHashPrefixAndVerify(): void
    {
        $key = 'integration-test-key-' . bin2hex(random_bytes(4));
        $prefix = itm_api_key_prefix($key);
        $hash = itm_api_hash_api_key($key);

        $this->assertSame(16, strlen($prefix));
        $this->assertSame(64, strlen($hash));
        $this->assertTrue(itm_api_verify_api_key($key, $hash));
        $this->assertFalse(itm_api_verify_api_key($key . 'x', $hash));
    }

    public function testApiKeyExtractIgnoresQueryString(): void
    {
        $_GET['api_key'] = 'should-not-read';
        $_POST = [];
        unset($_SERVER['HTTP_X_API_KEY']);

        $this->assertSame('', itm_api_extract_request_key());

        unset($_GET['api_key']);
    }

    public function testConfigurationRowHasApiKeyDetectsHash(): void
    {
        $this->assertTrue(itm_api_configuration_row_has_api_key(['api_key_hash' => hash('sha256', 'x')]));
        $this->assertTrue(itm_api_configuration_row_has_api_key(['api_key' => 'legacy-plain']));
        $this->assertFalse(itm_api_configuration_row_has_api_key(['api_key' => '', 'api_key_hash' => '']));
    }
}
