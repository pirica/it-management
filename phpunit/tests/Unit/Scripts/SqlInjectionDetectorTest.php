<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SqlInjectionDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
        require_once __DIR__ . '/../../../../scripts/lib/sql_injection_detector.php';
    }

    /**
     * @dataProvider injectionPayloadProvider
     */
    public function testDetectInjectionSignatures(string $payload, array $expectedRules): void
    {
        $matched = itm_sql_detect_injection_signatures($payload);
        foreach ($expectedRules as $rule) {
            $this->assertContains($rule, $matched, "Payload '$payload' should match rule '$rule'");
        }
    }

    public function injectionPayloadProvider(): array
    {
        return [
            ['SELECT * FROM employees --', ['comment-sequence']],
            ['1; DROP TABLE users', ['stacked-query']],
            ["' OR 1=1", ['tautology']],
            ["' UNION SELECT NULL", ['union-select']],
            ['1 AND 1=1', ['boolean-probe']],
            ['SLEEP(5)', ['time-based']],
            ['EXTRACTVALUE(1, "test")', ['error-based']],
            ['UNION/**/SELECT', ['obfuscated-union']],
        ];
    }

    public function testHasSqlInjectionSignature(): void
    {
        $matched = [];
        $this->assertTrue(itm_has_sql_injection_signature("' OR 1=1", $matched));
        $this->assertContains('tautology', $matched);

        $this->assertFalse(itm_has_sql_injection_signature("Safe search term"));
    }

    public function testNormalizePayload(): void
    {
        $this->assertEquals('1 OR 1=1', itm_sql_normalize_payload('1%20OR%201%3D1'));
        $this->assertEquals('1 OR 1=1', itm_sql_normalize_payload('1   OR   1=1'));
    }
}
