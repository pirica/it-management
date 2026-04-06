<?php

declare(strict_types=1);

require __DIR__ . '/lib/sql_injection_detector.php';

/**
 * Compact, table-driven SQL injection test matrix.
 *
 * Run:
 *   php scripts/sql_injection_matrix_test.php
 */

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
        'expect_rules_any' => ['union-select'],
    ],
    [
        'id' => 'comment_truncation',
        'payload' => "' OR 1=1 --",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['comment-sequence', 'tautology'],
    ],
    [
        'id' => 'stacked_query',
        'payload' => "'; DROP TABLE equipment;--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['stacked-query'],
    ],
    [
        'id' => 'boolean_blind_true',
        'payload' => "' AND 1=1--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['boolean-probe'],
    ],
    [
        'id' => 'boolean_blind_false',
        'payload' => "' AND 1=2--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['boolean-probe'],
    ],
    [
        'id' => 'time_based_mysql',
        'payload' => "' OR SLEEP(3)--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['time-based'],
    ],
    [
        'id' => 'obfuscated_union',
        'payload' => "' UnIoN/**/SeLeCt 1,2--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['union-select', 'obfuscated-union'],
    ],
    [
        'id' => 'url_encoded_tautology',
        'payload' => '%27%20OR%201%3D1--',
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['normalized-tautology', 'tautology'],
    ],
    [
        'id' => 'error_based_extractvalue',
        'payload' => "' AND extractvalue(1,concat(0x7e,user(),0x7e))--",
        'expect_suspicious' => true,
        'expect_decision' => 'blocked',
        'expect_rules_any' => ['error-based'],
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
    $matchedRules = [];
    $isSuspicious = itm_has_sql_injection_signature($test['payload'], $matchedRules);

    $result = [
        'suspicious' => $isSuspicious,
        'decision' => $isSuspicious ? 'blocked' : 'allowed',
        'matched_rules' => $matchedRules,
    ];

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
