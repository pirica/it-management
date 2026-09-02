<?php
/**
 * Public OpenAPI 3.0 spec for API v2 (no employee login from loopback, allowlisted IP, or maintenance token).
 *
 * CLI: php scripts/openapi.php
 * Browser: scripts/openapi.php?format=json
 */
declare(strict_types=1);

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

if (!defined('ITM_SCRIPT_NO_AUTH')) {
    define('ITM_SCRIPT_NO_AUTH', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/itm_api_v2_openapi.php';

$format = strtolower(trim((string)($_GET['format'] ?? 'json')));
$document = itm_api_v2_openapi_build_document();

if ($format === 'yaml') {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "# OpenAPI YAML export is not implemented; use ?format=json\n";
    echo "# Document title: " . ($document['info']['title'] ?? 'API v2') . "\n";
    exit(0);
}

$json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($json)) {
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8', true, 500);
    }
    echo "Unable to encode OpenAPI document.\n";
    exit(1);
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

echo $json;
if (PHP_SAPI === 'cli') {
    echo "\n";
}

exit(0);
