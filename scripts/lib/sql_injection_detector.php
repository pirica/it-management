<?php

declare(strict_types=1);

/**
 * Normalize input before signature checks.
 */
function itm_sql_normalize_payload(string $payload): string
{
    $normalized = $payload;

    for ($i = 0; $i < 2; $i++) {
        $decoded = rawurldecode($normalized);
        if ($decoded === $normalized) {
            break;
        }
        $normalized = $decoded;
    }

    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

    return trim($normalized);
}

/**
 * Returns matched rule IDs.
 *
 * @return string[]
 */
function itm_sql_detect_injection_signatures(string $payload): array
{
    $normalized = itm_sql_normalize_payload($payload);

    $patterns = [
        'comment-sequence' => '/(--|#|\/\*)/',
        'stacked-query' => '/;\s*(select|insert|update|delete|drop|union|alter|create|truncate)\b/i',
        'tautology' => '/\b(or|and)\b\s+[\'\"]?\w+[\'\"]?\s*=\s*[\'\"]?\w+[\'\"]?/i',
        'union-select' => '/\bunion\b(?:\/\*.*?\*\/|\s)+\bselect\b/i',
        'boolean-probe' => '/\b(and|or)\b\s+\d+\s*=\s*\d+/i',
        'time-based' => '/\b(sleep\s*\(|benchmark\s*\(|pg_sleep\s*\(|waitfor\s+delay)\b/i',
        'error-based' => '/\b(extractvalue\s*\(|updatexml\s*\(|xmltype\s*\()/i',
    ];

    $matched = [];
    foreach ($patterns as $rule => $pattern) {
        if (preg_match($pattern, $normalized) === 1) {
            $matched[] = $rule;
        }
    }

    $normalizedNoInlineComments = str_replace(['/**/', '/*', '*/'], '', $normalized);
    if (preg_match('/u\s*n\s*i\s*o\s*n/i', $normalizedNoInlineComments) === 1
        && preg_match('/s\s*e\s*l\s*e\s*c\s*t/i', $normalizedNoInlineComments) === 1
        && preg_match('/\/\*.*?\*\//', $normalized) === 1) {
        $matched[] = 'obfuscated-union';
    }

    if ($payload !== $normalized
        && preg_match('/\b(or|and)\b\s+\d+\s*=\s*\d+/i', $normalized) === 1) {
        $matched[] = 'normalized-tautology';
    }

    return array_values(array_unique($matched));
}

function itm_has_sql_injection_signature(string $payload, array &$matchedRules = []): bool
{
    $matchedRules = itm_sql_detect_injection_signatures($payload);

    return count($matchedRules) > 0;
}
