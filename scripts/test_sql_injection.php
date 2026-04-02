<?php
/**
What the script expects
Path: scripts/test_sql_injection.php.

Allowed methods: GET and POST only.

POST requires a valid CSRF token (csrf_token body field or X-CSRF-Token header).

It requires a valid authenticated company context ($company_id > 0), otherwise it returns Unauthorized.

Quick ways to call it
1) Browser / GET (easiest)
Open:

http://127.0.0.1/scripts/test_sql_injection.php?payload=' OR '1'='1
If suspicious, response status will be 422.

If not suspicious, 200.

2) No payload (self-sample mode)
Open:

http://127.0.0.1/scripts/test_sql_injection.php
It returns sample payload checks and usage guidance (HTTP 200).

3) POST JSON (with CSRF)
curl -X POST "http://127.0.0.1/scripts/test_sql_injection.php" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: YOUR_TOKEN" \
  -d '{"payload":"admin'\'' UNION SELECT 1,2 --","csrf_token":"YOUR_TOKEN"}'
(For POST, CSRF must be valid.)

Response fields to look at
suspicious: true/false

matched_rules: which regex signatures matched

prepared_statement_demo: safe query metadata and rows returned

Why you may still see errors
401 Unauthorized: user/session not mapped to a valid company ($company_id <= 0).

403 Invalid CSRF token: POST without valid token.

405 Method not allowed: used method other than GET/POST.*/


require __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode((string)$rawInput, true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

$csrfToken = (string)($jsonInput['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if ($method === 'POST' && !itm_validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if ($company_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

function has_sql_injection_signature(string $value, array &$matchedRules = []): bool
{
    $patterns = [
        'comment-sequence' => '/(--|#|\/\*)/',
        'stacked-query' => '/;\s*(select|insert|update|delete|drop|union|alter|create)\b/i',
        'tautology' => '/\b(or|and)\b\s+[\'\"]?\w+[\'\"]?\s*=\s*[\'\"]?\w+[\'\"]?/i',
        'union-select' => '/\bunion\b\s+\bselect\b/i',
        'time-based' => '/\b(sleep|benchmark|pg_sleep|waitfor\s+delay)\b/i',
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

$payload = (string)($jsonInput['payload'] ?? ($_POST['payload'] ?? ($_GET['payload'] ?? '')));
if ($payload === '') {
    $samplePayloads = [
        "Core-Switch-01",
        "' OR '1'='1",
        "admin' UNION SELECT 1,2 --",
        "test; DROP TABLE users;",
    ];

    $sampleResults = [];
    foreach ($samplePayloads as $samplePayload) {
        $rules = [];
        $sampleResults[] = [
            'payload' => $samplePayload,
            'suspicious' => has_sql_injection_signature($samplePayload, $rules),
            'matched_rules' => array_values(array_unique($rules)),
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'test_mode' => 'sql_injection_detection',
        'message' => 'No payload provided. Pass payload via ?payload=... or POST/JSON payload to test a specific value.',
        'sample_results' => $sampleResults,
    ]);
    exit;
}

$matchedRules = [];
$isSuspicious = has_sql_injection_signature($payload, $matchedRules);

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

$statusCode = $isSuspicious ? 422 : 200;
http_response_code($statusCode);

echo json_encode([
    'success' => !$isSuspicious,
    'test_mode' => 'sql_injection_detection',
    'payload' => $payload,
    'suspicious' => $isSuspicious,
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
