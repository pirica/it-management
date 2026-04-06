<?php

declare(strict_types=1);

/**
 * Compact, table-driven SQL injection test matrix.
 *
 * Run:
 *   php scripts/sql_injection_matrix_test.php
 */

function normalize_payload(string $payload): string
{
    $normalized = $payload;

    // Decode up to two times for common encoding bypass attempts.
    for ($i = 0; $i < 2; $i++) {
        $decoded = rawurldecode($normalized);
        if ($decoded === $normalized) {
            break;
        }
        $normalized = $decoded;
    }

    // Collapse whitespace.
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

    return trim($normalized);
}

function detect_sqli(string $payload): array
{
    $normalized = normalize_payload($payload);

    $patterns = [
        'union_select' => '/\bunion\b(?:\/\*.*?\*\/|\s)+\bselect\b/i',
        'comment_evasion' => '/(--|#|\/\*)/',
        'stacked_query' => '/;\s*(select|insert|update|delete|drop|union|alter|create|truncate)\b/i',
        'tautology' => '/\b(or|and)\b\s+[\'\"]?\w+[\'\"]?\s*=\s*[\'\"]?\w+[\'\"]?/i',
        'boolean_probe' => '/\b(and|or)\b\s+\d+\s*=\s*\d+/i',
        'time_based' => '/\b(sleep\s*\(|benchmark\s*\(|pg_sleep\s*\(|waitfor\s+delay)\b/i',
        'error_based' => '/\b(extractvalue\s*\(|updatexml\s*\(|xmltype\s*\()/i',
    ];

    $matched = [];
    foreach ($patterns as $rule => $pattern) {
        if (preg_match($pattern, $normalized) === 1) {
            $matched[] = $rule;
        }
    }

    // Obfuscation heuristic: mixed case SQL keywords with inline comments.
    if (preg_match('/u\s*n\s*i\s*o\s*n/i', str_replace(['/**/', '/*', '*/'], '', $normalized)) === 1
        && preg_match('/s\s*e\s*l\s*e\s*c\s*t/i', str_replace(['/**/', '/*', '*/'], '', $normalized)) === 1
        && preg_match('/\/\*.*?\*\//', $normalized) === 1) {
        $matched[] = 'obfuscated_union';
    }

    // Encoded attack indicator.
    if ($payload !== $normalized && preg_match('/\b(or|and)\b\s+\d+\s*=\s*\d+/i', $normalized) === 1) {
        $matched[] = 'normalized_tautology';
    }

    $matched = array_values(array_unique($matched));

    return [
        'suspicious' => count($matched) > 0,
        'matched_rules' => $matched,
        'decision' => count($matched) > 0 ? 'blocked' : 'allowed',
        'normalized_payload' => $normalized,
    ];
}

$tests = [
    [
        'id' => 'tautology_basic',
        'payload' => "' OR '1'='1",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['tautology'],
    ],
    [
        'id' => 'union_basic',
        'payload' => "' UNION SELECT 1,2--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['union_select'],
    ],
    [
        'id' => 'comment_truncation',
        'payload' => "' OR 1=1 --",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['comment_evasion', 'tautology'],
    ],
    [
        'id' => 'stacked_query',
        'payload' => "'; DROP TABLE equipment;--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['stacked_query'],
    ],
    [
        'id' => 'boolean_blind_true',
        'payload' => "' AND 1=1--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['boolean_probe'],
    ],
    [
        'id' => 'boolean_blind_false',
        'payload' => "' AND 1=2--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['boolean_probe'],
    ],
    [
        'id' => 'time_based_mysql',
        'payload' => "' OR SLEEP(3)--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['time_based'],
    ],
    [
        'id' => 'obfuscated_union',
        'payload' => "' UnIoN/**/SeLeCt 1,2--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['union_select', 'obfuscated_union'],
    ],
    [
        'id' => 'url_encoded_tautology',
        'payload' => '%27%20OR%201%3D1--',
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['normalized_tautology', 'tautology'],
    ],
    [
        'id' => 'error_based_extractvalue',
        'payload' => "' AND extractvalue(1,concat(0x7e,user(),0x7e))--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['error_based'],
    ],
    [
        'id' => 'benign_apostrophe',
        'payload' => "O'Reilly Laptop",
        'expect_suspicious' => false,
        'expect_decision' => 'allowed',
        'expect_rules_exact' => [],
    ],
    [
        'id' => 'benign_sql_like_phrase',
        'payload' => 'union jack cable',
        'expect_suspicious' => false,
        'expect_decision' => 'allowed',
        'expect_rules_exact' => [],
    ],
];

$failed = 0;
$passed = 0;

foreach ($tests as $test) {
    $result = detect_sqli($test['payload']);

    $ok = true;

    if ($result['suspicious'] !== $test['expect_suspicious']) {
        $ok = false;
        echo "[FAIL] {$test['id']} suspicious mismatch. expected="
            . ($test['expect_suspicious'] ? 'true' : 'false')
            . " actual=" . ($result['suspicious'] ? 'true' : 'false') . PHP_EOL;
    }

    if ($result['decision'] !== $test['expect_decision']) {
        $ok = false;
        echo "[FAIL] {$test['id']} decision mismatch. expected={$test['expect_decision']} actual={$result['decision']}" . PHP_EOL;
    }

    if (isset($test['expect_rules_any'])) {
        $intersection = array_values(array_intersect($result['matched_rules'], $test['expect_rules_any']));
        if (count($intersection) === 0) {
            $ok = false;
            echo "[FAIL] {$test['id']} expected any rules ["
                . implode(', ', $test['expect_rules_any'])
                . "] got [" . implode(', ', $result['matched_rules']) . "]" . PHP_EOL;
        }
    }

    if (array_key_exists('expect_rules_exact', $test)) {
        $expectedExact = $test['expect_rules_exact'];
        $actual = $result['matched_rules'];
        sort($expectedExact);
        sort($actual);
        if ($expectedExact !== $actual) {
            $ok = false;
            echo "[FAIL] {$test['id']} expected exact rules ["
                . implode(', ', $expectedExact)
                . "] got [" . implode(', ', $actual) . "]" . PHP_EOL;
        }
    }

    if ($ok) {
        $passed++;
        echo "[PASS] {$test['id']}" . PHP_EOL;
    } else {
        $failed++;
    }
}

echo PHP_EOL . "Summary: passed={$passed} failed={$failed} total=" . count($tests) . PHP_EOL;

exit($failed > 0 ? 1 : 0);
