<?php
/**
 * SQL Injection Testing Script
 * 
 * Provides a sandbox to test how the application's security filters handle 
 * various SQL injection payloads. It demonstrates both signature detection
 * and safe query execution using prepared statements.
 * 
 * Allowed methods: GET and POST.
 * POST requires a valid CSRF token.
 * Requires an authenticated session with a valid company context.
 */

require __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

// Method validation
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Input parsing
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode((string)$rawInput, true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

// CSRF check for POST requests
$csrfToken = (string)($jsonInput['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if ($method === 'POST' && !itm_validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Authentication check
if ($company_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

/**
 * Checks a string against known SQL injection signatures
 */
function has_sql_injection_signature(string $value, array &$matchedRules = []): bool
{
    $patterns = [
        'comment-sequence' => '/(--|#|\/\*)/',
        'stacked-query'    => '/;\s*(select|insert|update|delete|drop|union|alter|create)\b/i',
        'tautology'        => '/\b(or|and)\b\s+[\'\"]?\w+[\'\"]?\s*=\s*[\'\"]?\w+[\'\"]?/i',
        'union-select'     => '/\bunion\b\s+\bselect\b/i',
        'time-based'       => '/\b(sleep|benchmark|pg_sleep|waitfor\s+delay)\b/i',
    ];

    $found = false;
    foreach ($patterns as $rule => $pattern) {
        if (preg_match($pattern, $value) === 1) {
            $matchedRules[] = $rule;
            $found = true;
        }
    }

    return $found;
}

// Extract the payload from various possible input fields
$payload = (string)($jsonInput['payload']
    ?? $jsonInput['q']
    ?? ($_POST['payload'] ?? ($_POST['q'] ?? ($_GET['payload'] ?? ($_GET['q'] ?? ($_GET['input'] ?? ''))))));

// Fallback for raw query string parsing if standard methods fail
if ($payload === '') {
    $rawQueryString = (string)($_SERVER['QUERY_STRING'] ?? '');
    if ($rawQueryString !== '') {
        $queryParams = [];
        parse_str($rawQueryString, $queryParams);
        $payload = (string)($queryParams['payload'] ?? ($queryParams['q'] ?? ($queryParams['input'] ?? '')));

        if ($payload === '' && str_starts_with($rawQueryString, 'payload=')) {
            $payload = urldecode(substr($rawQueryString, 8));
        }
    }
}

// Ensure a payload was provided
if ($payload === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing payload',
        'hint' => 'Pass payload with ?payload=... (URL-encoded) or via POST/JSON as payload.',
        'example' => '/test_sql_injection.php?payload=%27%20OR%20%271%27=%271',
    ]);
    exit;
}

// Perform signature analysis
$matchedRules = [];
$isSuspicious = has_sql_injection_signature($payload, $matchedRules);

// Demonstrate safe query execution using the malicious payload
// Even if signatures are found, the query itself is safe because it uses prepared statements.
$exampleResultCount = 0;
$exampleSql = 'SELECT id, name FROM equipment WHERE company_id = ? AND name = ? LIMIT 5';
$exampleStmt = mysqli_prepare($conn, $exampleSql);
if ($exampleStmt) {
    $companyId = (int)$company_id;
    mysqli_stmt_bind_param($exampleStmt, 'is', $companyId, $payload);
    if (mysqli_stmt_execute($exampleStmt)) {
        $exampleRes = mysqli_stmt_get_result($exampleStmt);
        while ($exampleRes && mysqli_fetch_assoc($exampleRes)) {
            $exampleResultCount++;
        }
    }
    mysqli_stmt_close($exampleStmt);
}

// Return detailed analysis in JSON format
$statusCode = $isSuspicious ? 422 : 200;
http_response_code($statusCode);

echo json_encode([
    'success' => true,
    'test_mode' => 'sql_injection_detection',
    'payload' => $payload,
    'suspicious' => $isSuspicious,
    'decision' => $isSuspicious ? 'blocked' : 'allowed',
    'matched_rules' => array_values(array_unique($matchedRules)),
    'prepared_statement_demo' => [
        'query' => $exampleSql,
        'company_scope' => (int)$company_id,
        'rows_returned' => $exampleResultCount,
    ],
    'message' => $isSuspicious
        ? 'Potential SQL injection payload detected and blocked.'
        : 'No known SQL injection signature detected.',
]);
