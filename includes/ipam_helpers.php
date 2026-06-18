<?php
/**
 * IPAM helpers: CIDR parsing, IPv4 validation, and equipment sync.
 */

/**
 * Why: Subnet rows store normalized network/prefix derived from human-entered CIDR notation.
 */
function itm_ipam_parse_cidr(string $cidr): array
{
    $cidr = trim($cidr);
    if ($cidr === '') {
        return ['ok' => false, 'error' => 'CIDR is required.'];
    }

    if (!str_contains($cidr, '/') && itm_ipam_is_valid_ipv4($cidr)) {
        $cidr .= '/24';
    }

    if (!preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})\s*/\s*(\d{1,2})$#', $cidr, $matches)) {
        return ['ok' => false, 'error' => 'CIDR must look like 10.0.0.0/24.'];
    }

    $networkIp = $matches[1];
    $prefixLength = (int)$matches[2];
    if (!itm_ipam_is_valid_ipv4($networkIp)) {
        return ['ok' => false, 'error' => 'Network address is not a valid IPv4 address.'];
    }
    if ($prefixLength < 0 || $prefixLength > 32) {
        return ['ok' => false, 'error' => 'Prefix length must be between 0 and 32.'];
    }

    $networkLong = ip2long($networkIp);
    if ($networkLong === false) {
        return ['ok' => false, 'error' => 'Network address is not a valid IPv4 address.'];
    }

    $enteredIp = $networkIp;
    if ($prefixLength < 32) {
        $mask = (0xFFFFFFFF << (32 - $prefixLength)) & 0xFFFFFFFF;
        $networkLong = $networkLong & $mask;
        $networkIp = long2ip($networkLong);
        if ($networkIp === false) {
            return ['ok' => false, 'error' => 'Network address is not a valid IPv4 address.'];
        }
    }

    $normalizedFrom = ($enteredIp !== $networkIp) ? ($enteredIp . '/' . $prefixLength) : '';

    return [
        'ok' => true,
        'cidr' => $networkIp . '/' . $prefixLength,
        'network_ip' => $networkIp,
        'prefix_length' => $prefixLength,
        'normalized_from' => $normalizedFrom,
        'error' => '',
    ];
}

/**
 * Why: Users sometimes paste quoted values; strip wrappers before validation/display.
 */
function itm_ipam_trim_user_input($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (($value[0] === "'" && substr($value, -1) === "'") || ($value[0] === '"' && substr($value, -1) === '"')) {
        $value = substr($value, 1, -1);
    }
    return trim($value);
}

/**
 * Why: Gateway/DNS fields must belong to the subnet being saved, not just be valid IPv4.
 */
function itm_ipam_ipv4_in_cidr(string $ip, string $networkIp, int $prefixLength): bool
{
    if (!itm_ipam_is_valid_ipv4($ip) || !itm_ipam_is_valid_ipv4($networkIp)) {
        return false;
    }
    if ($prefixLength < 0 || $prefixLength > 32) {
        return false;
    }

    $ipLong = ip2long($ip);
    $netLong = ip2long($networkIp);
    if ($ipLong === false || $netLong === false) {
        return false;
    }
    if ($prefixLength === 0) {
        return true;
    }

    $mask = (0xFFFFFFFF << (32 - $prefixLength)) & 0xFFFFFFFF;

    return ($ipLong & $mask) === ($netLong & $mask);
}

function itm_ipam_is_valid_ipv4(string $ip): bool
{
    $ip = trim($ip);
    if ($ip === '') {
        return false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }
    $parts = explode('.', $ip);
    if (count($parts) !== 4) {
        return false;
    }
    foreach ($parts as $part) {
        if ($part === '' || !ctype_digit($part) || (int)$part > 255) {
            return false;
        }
    }
    return true;
}

/**
 * Why: Host checks must not shell out; TCP connect timing works on shared hosting without exec.
 *
 * @return array{ok: bool, reachable: bool, open: bool, port: int, message: string, response_ms: float|null, method: string}
 */
function itm_ipam_socket_ping(string $host, int $port, float $timeout = 10): array
{
    if (!itm_ipam_is_valid_ipv4($host)) {
        return [
            'ok' => false,
            'reachable' => false,
            'open' => false,
            'port' => $port,
            'message' => 'Ping IP must be a valid IPv4 address.',
            'response_ms' => null,
            'method' => 'tcp',
        ];
    }
    if ($port < 1 || $port > 65535) {
        return [
            'ok' => false,
            'reachable' => false,
            'open' => false,
            'port' => $port,
            'message' => 'Port must be between 1 and 65535.',
            'response_ms' => null,
            'method' => 'tcp',
        ];
    }

    $timeout = max(1.0, min(30.0, $timeout));
    $startTime = microtime(true);
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $responseMs = round((microtime(true) - $startTime) * 1000, 1);

    if (is_resource($socket)) {
        fclose($socket);

        return [
            'ok' => true,
            'reachable' => true,
            'open' => true,
            'port' => $port,
            'message' => 'Connected on port ' . $port . '. Response time: ' . $responseMs . ' ms.',
            'response_ms' => $responseMs,
            'method' => 'tcp',
        ];
    }

    $detail = trim($errstr);
    if ($detail === '' && $errno > 0) {
        $detail = 'error ' . $errno;
    }

    return [
        'ok' => true,
        'reachable' => false,
        'open' => false,
        'port' => $port,
        'message' => 'Failed to connect on port ' . $port . ($detail !== '' ? ': ' . $detail : '.'),
        'response_ms' => null,
        'method' => 'tcp',
    ];
}

/**
 * Why: When the requested port is closed, try common service ports before reporting unreachable.
 *
 * @return array{ok: bool, reachable: bool, message: string, method: string, port_used: int|null, response_ms: float|null, alternatives_tried: array<int, array<string, mixed>>}
 */
function itm_ipam_probe_host_reachability(string $ip, int $primaryPort = 80, float $timeout = 5): array
{
    if (!itm_ipam_is_valid_ipv4($ip)) {
        return [
            'ok' => false,
            'reachable' => false,
            'message' => 'Ping IP must be a valid IPv4 address.',
            'method' => 'tcp',
            'port_used' => null,
            'response_ms' => null,
            'alternatives_tried' => [],
        ];
    }

    $fallbackPorts = [80, 443, 22, 53, 3389];
    $portsToTry = [];
    if ($primaryPort > 0) {
        $portsToTry[] = $primaryPort;
    }
    foreach ($fallbackPorts as $fallbackPort) {
        if (!in_array($fallbackPort, $portsToTry, true)) {
            $portsToTry[] = $fallbackPort;
        }
    }

    $alternativesTried = [];
    foreach ($portsToTry as $port) {
        $attempt = itm_ipam_socket_ping($ip, $port, $timeout);
        $alternativesTried[] = [
            'port' => $port,
            'reachable' => !empty($attempt['reachable']),
            'message' => (string)($attempt['message'] ?? ''),
            'response_ms' => $attempt['response_ms'] ?? null,
        ];
        if (!empty($attempt['reachable'])) {
            return [
                'ok' => true,
                'reachable' => true,
                'message' => (string)$attempt['message'],
                'method' => 'tcp',
                'port_used' => $port,
                'response_ms' => $attempt['response_ms'] ?? null,
                'alternatives_tried' => $alternativesTried,
            ];
        }
    }

    $triedList = implode(', ', array_map(static function ($row) {
        return (string)($row['port'] ?? '');
    }, $alternativesTried));

    return [
        'ok' => true,
        'reachable' => false,
        'message' => 'No TCP response on tried ports: ' . $triedList . '.',
        'method' => 'tcp',
        'port_used' => null,
        'response_ms' => null,
        'alternatives_tried' => $alternativesTried,
    ];
}

/**
 * @return array{ok: bool, open: bool, message: string, response_ms?: float|null}
 */
function itm_ipam_check_tcp_port(string $ip, int $port, int $timeoutSec = 3): array
{
    $socketResult = itm_ipam_socket_ping($ip, $port, (float)$timeoutSec);
    if (!$socketResult['ok']) {
        return [
            'ok' => false,
            'open' => false,
            'message' => (string)$socketResult['message'],
        ];
    }

    return [
        'ok' => true,
        'open' => !empty($socketResult['open']),
        'message' => (string)$socketResult['message'],
        'response_ms' => $socketResult['response_ms'] ?? null,
    ];
}

/**
 * Why: Subnet index ping tool returns one JSON payload for TCP reachability and optional port checks.
 *
 * @return array{ok: bool, error?: string, ip?: string, ping?: array<string, mixed>, port?: array<string, mixed>|null}
 */
function itm_ipam_run_ping_port_check(string $ip, string $portRaw): array
{
    $ip = itm_ipam_trim_user_input($ip);
    if ($ip === '') {
        return ['ok' => false, 'error' => 'Ping IP is required.'];
    }

    $portRaw = itm_ipam_trim_user_input($portRaw);
    $userPort = 0;
    if ($portRaw !== '') {
        if (!ctype_digit($portRaw)) {
            return ['ok' => false, 'error' => 'Port must be a number between 1 and 65535.'];
        }
        $userPort = (int)$portRaw;
        if ($userPort < 1 || $userPort > 65535) {
            return ['ok' => false, 'error' => 'Port must be between 1 and 65535.'];
        }
    }

    $probe = itm_ipam_probe_host_reachability($ip, $userPort > 0 ? $userPort : 80);
    if (empty($probe['ok'])) {
        return ['ok' => false, 'error' => (string)($probe['message'] ?? 'Reachability check failed.')];
    }

    $result = [
        'ok' => true,
        'ip' => $ip,
        'ping' => [
            'ok' => true,
            'reachable' => !empty($probe['reachable']),
            'message' => (string)($probe['message'] ?? ''),
            'method' => (string)($probe['method'] ?? 'tcp'),
            'port_used' => $probe['port_used'] ?? null,
            'response_ms' => $probe['response_ms'] ?? null,
            'alternatives_tried' => $probe['alternatives_tried'] ?? [],
        ],
        'port' => null,
    ];

    if ($userPort > 0) {
        $portCheck = itm_ipam_check_tcp_port($ip, $userPort);
        if (!$portCheck['ok']) {
            return ['ok' => false, 'error' => (string)$portCheck['message']];
        }
        $result['port'] = $portCheck;
    }

    return $result;
}

/**
 * Why: Discovery UI accepts human start/end IPs and must cap scan size for PHP timeouts.
 *
 * @return array{ok: bool, ips: array<int, string>, error: string}
 */
function itm_ipam_ipv4_range_from_bounds(string $startIp, string $endIp, int $maxHosts = 255): array
{
    $startIp = itm_ipam_trim_user_input($startIp);
    $endIp = itm_ipam_trim_user_input($endIp);
    if (!itm_ipam_is_valid_ipv4($startIp) || !itm_ipam_is_valid_ipv4($endIp)) {
        return ['ok' => false, 'ips' => [], 'error' => 'Beginning and end of range must be valid IPv4 addresses.'];
    }

    $startLong = ip2long($startIp);
    $endLong = ip2long($endIp);
    if ($startLong === false || $endLong === false) {
        return ['ok' => false, 'ips' => [], 'error' => 'Range addresses could not be parsed.'];
    }
    if ($startLong > $endLong) {
        return ['ok' => false, 'ips' => [], 'error' => 'Beginning of range must be less than or equal to end of range.'];
    }

    $count = ($endLong - $startLong) + 1;
    if ($count > $maxHosts) {
        return ['ok' => false, 'ips' => [], 'error' => 'Select a range of up to ' . $maxHosts . ' addresses at a time.'];
    }

    $ips = [];
    for ($long = $startLong; $long <= $endLong; $long++) {
        $ip = long2ip($long);
        if ($ip !== false) {
            $ips[] = $ip;
        }
    }

    return ['ok' => true, 'ips' => $ips, 'error' => ''];
}

/**
 * @return array<int, array{id: int, network_ip: string, prefix_length: int, cidr: string}>
 */
function itm_ipam_load_company_subnets_for_discovery(mysqli $conn, int $company_id): array
{
    if ($company_id <= 0 || !itm_ipam_table_exists($conn, 'ip_subnets')) {
        return [];
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, network_ip, prefix_length, cidr
         FROM ip_subnets
         WHERE company_id = ? AND active = 1
         ORDER BY prefix_length DESC, cidr ASC'
    );
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'network_ip' => (string)($row['network_ip'] ?? ''),
            'prefix_length' => (int)($row['prefix_length'] ?? 0),
            'cidr' => (string)($row['cidr'] ?? ''),
        ];
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

function itm_ipam_find_subnet_id_for_ip(string $ip, array $subnets): int
{
    foreach ($subnets as $subnet) {
        $networkIp = (string)($subnet['network_ip'] ?? '');
        $prefixLength = (int)($subnet['prefix_length'] ?? 0);
        if ($networkIp === '' || $prefixLength < 0 || $prefixLength > 32) {
            continue;
        }
        if (itm_ipam_ipv4_in_cidr($ip, $networkIp, $prefixLength)) {
            return (int)($subnet['id'] ?? 0);
        }
    }

    return 0;
}

/**
 * @return string
 */
function itm_ipam_get_ip2whois_api_key(): string
{
    if (defined('IP2WHOIS_API_KEY')) {
        return trim((string)IP2WHOIS_API_KEY);
    }

    $fromEnv = trim((string)getenv('IP2WHOIS_API_KEY'));
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    return trim((string)getenv('ITM_IP2WHOIS_API_KEY'));
}

/**
 * Why: Prefer the first hosted domain as a hostname hint when equipment has no hostname.
 */
function itm_ipam_domain_to_hostname_hint(string $domain): string
{
    $domain = strtolower(trim($domain));
    if ($domain === '') {
        return '';
    }
    if (strpos($domain, 'www.') === 0) {
        $domain = substr($domain, 4);
    }
    if (strlen($domain) > 253) {
        $domain = substr($domain, 0, 253);
    }

    return $domain;
}

/**
 * Why: IP2WHOIS may return gzip/deflate bodies or stray bytes; curl CLI auto-decodes but PHP needs help.
 *
 * @return array<string, mixed>|null
 */
function itm_ipam_decode_json_response(string $raw)
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($raw, '{');
    $end = strrpos($raw, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $slice = substr($raw, $start, $end - $start + 1);
        $decoded = json_decode($slice, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

/**
 * Why: IP2WHOIS sits behind Cloudflare and rejects requests without a User-Agent (HTTP 520 plain-text body).
 *
 * @return array{ok: bool, http_code: int, body: string, error: string}
 */
function itm_ipam_ip2whois_http_get(string $url): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'http_code' => 0, 'body' => '', 'error' => 'cURL extension is not available.'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 18);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'User-Agent: IT-Management-IPAM/1.0 (Network Discovery; PHP cURL)',
    ]);

    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrNo = (int)curl_errno($ch);
    $curlErrText = trim((string)curl_error($ch));
    curl_close($ch);

    if ($curlErrNo !== 0) {
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'body' => is_string($body) ? $body : '',
            'error' => 'IP2WHOIS request failed: ' . $curlErrText,
        ];
    }

    return [
        'ok' => true,
        'http_code' => $httpCode,
        'body' => is_string($body) ? $body : '',
        'error' => '',
    ];
}

/**
 * @return array{ok: bool, http_code: int, body: string, error: string}
 */
function itm_ipam_ip2whois_http_get_with_retry(string $url, int $maxAttempts = 2): array
{
    $maxAttempts = max(1, min(3, $maxAttempts));
    $last = ['ok' => false, 'http_code' => 0, 'body' => '', 'error' => 'IP2WHOIS request failed.'];

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $last = itm_ipam_ip2whois_http_get($url);
        if (!$last['ok']) {
            return $last;
        }

        $httpCode = (int)$last['http_code'];
        if ($httpCode >= 200 && $httpCode < 300) {
            return $last;
        }

        if (!in_array($httpCode, [520, 522, 523, 524, 429], true) || $attempt >= $maxAttempts) {
            return $last;
        }

        usleep(350000);
    }

    return $last;
}

/**
 * Reverse hosted-domain lookup via IP2WHOIS (HTTPS + curl, no shell exec).
 *
 * @return array{
 *   ok: bool,
 *   domains: array<int, string>,
 *   total_domains?: int,
 *   skipped?: bool,
 *   error?: string
 * }
 */
function itm_ipam_ip2whois_lookup_domains(string $ip, int $page = 1): array
{
    if (!itm_ipam_is_valid_ipv4($ip)) {
        return ['ok' => false, 'domains' => [], 'error' => 'Invalid IP address.'];
    }

    $apiKey = itm_ipam_get_ip2whois_api_key();
    if ($apiKey === '' || $apiKey === 'YOUR_IP2WHOIS_API_KEY_HERE') {
        return ['ok' => true, 'domains' => [], 'skipped' => true, 'error' => ''];
    }

    $baseUrl = defined('IP2WHOIS_DOMAINS_URL') ? (string)IP2WHOIS_DOMAINS_URL : 'https://domains.ip2whois.com/domains';
    $query = http_build_query([
        'ip' => $ip,
        'key' => $apiKey,
        'format' => 'json',
        'page' => max(1, $page),
    ]);
    $url = $baseUrl . '?' . $query;

    $httpResult = itm_ipam_ip2whois_http_get_with_retry($url);
    if (empty($httpResult['ok'])) {
        return ['ok' => false, 'domains' => [], 'error' => (string)($httpResult['error'] ?? 'IP2WHOIS request failed.')];
    }

    $httpCode = (int)($httpResult['http_code'] ?? 0);
    $responseBody = (string)($httpResult['body'] ?? '');
    if ($responseBody === '') {
        return ['ok' => false, 'domains' => [], 'error' => 'IP2WHOIS returned an empty response.'];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $snippet = trim(preg_replace('/\s+/', ' ', $responseBody));
        if (strlen($snippet) > 140) {
            $snippet = substr($snippet, 0, 140) . '…';
        }
        $suffix = $snippet !== '' ? (': ' . $snippet) : '';
        return ['ok' => false, 'domains' => [], 'error' => 'IP2WHOIS HTTP ' . $httpCode . $suffix];
    }

    $decoded = itm_ipam_decode_json_response($responseBody);
    if (!is_array($decoded)) {
        $snippet = trim(preg_replace('/\s+/', ' ', $responseBody));
        if (strlen($snippet) > 140) {
            $snippet = substr($snippet, 0, 140) . '…';
        }
        return [
            'ok' => false,
            'domains' => [],
            'error' => 'IP2WHOIS returned a non-JSON response'
                . ($snippet !== '' ? (': ' . $snippet) : '.'),
        ];
    }

    if (isset($decoded['error'])) {
        if (is_array($decoded['error'])) {
            $errorMessage = trim((string)($decoded['error']['error_message'] ?? 'IP2WHOIS lookup failed.'));
            return ['ok' => false, 'domains' => [], 'error' => $errorMessage];
        }
        $errorMessage = trim((string)$decoded['error']);
        if ($errorMessage !== '') {
            return ['ok' => false, 'domains' => [], 'error' => $errorMessage];
        }
    }

    $domains = [];
    if (isset($decoded['domains']) && is_array($decoded['domains'])) {
        foreach ($decoded['domains'] as $domainName) {
            $domainName = trim((string)$domainName);
            if ($domainName !== '') {
                $domains[] = $domainName;
            }
        }
    }

    return [
        'ok' => true,
        'domains' => $domains,
        'total_domains' => (int)($decoded['total_domains'] ?? count($domains)),
        'page' => (int)($decoded['page'] ?? $page),
        'total_pages' => (int)($decoded['total_pages'] ?? 1),
    ];
}

/**
 * Why: Discovery scans use a short TCP probe only (no ICMP/exec).
 *
 * @return array{alive: bool, port_used: int|null, response_ms: float|null}
 */
function itm_ipam_quick_host_alive(string $ip, float $timeout = 0.35): array
{
    foreach ([80, 443, 22, 135, 3389] as $port) {
        $probe = itm_ipam_socket_ping($ip, $port, $timeout);
        if (!empty($probe['reachable'])) {
            return [
                'alive' => true,
                'port_used' => (int)$port,
                'response_ms' => $probe['response_ms'] ?? null,
            ];
        }
    }

    return ['alive' => false, 'port_used' => null, 'response_ms' => null];
}

/**
 * @param array<int, string> $ips
 * @return array{hosts: array<int, array<string, mixed>>, activities: array<int, array{level: string, message: string}>}
 */
function itm_ipam_network_discovery_scan_ips(
    mysqli $conn,
    int $company_id,
    array $ips,
    float $probeTimeout = 0.35
): array {
    $subnets = itm_ipam_load_company_subnets_for_discovery($conn, $company_id);
    $stmtExists = mysqli_prepare(
        $conn,
        'SELECT id, equipment_id FROM ip_addresses WHERE company_id = ? AND subnet_id = ? AND ip_text = ? LIMIT 1'
    );

    $hosts = [];
    $activities = [];

    foreach ($ips as $ip) {
        $activities[] = [
            'level' => 'info',
            'message' => 'Probing ' . $ip . ' (TCP ports 80, 443, 22, 135, 3389)…',
        ];

        $aliveProbe = itm_ipam_quick_host_alive($ip, $probeTimeout);
        if (empty($aliveProbe['alive'])) {
            $activities[] = ['level' => 'muted', 'message' => $ip . ': no TCP response'];
            continue;
        }

        $portUsed = (int)($aliveProbe['port_used'] ?? 0);
        $responseMs = $aliveProbe['response_ms'] ?? null;
        $responseLabel = $responseMs !== null ? round((float)$responseMs, 1) . ' ms' : 'n/a';
        $activities[] = [
            'level' => 'ok',
            'message' => $ip . ': host responded on port ' . $portUsed . ' (' . $responseLabel . ')',
        ];

        $subnetId = itm_ipam_find_subnet_id_for_ip($ip, $subnets);
        $subnetCidr = '';
        foreach ($subnets as $subnet) {
            if ((int)($subnet['id'] ?? 0) === $subnetId) {
                $subnetCidr = (string)($subnet['cidr'] ?? '');
                break;
            }
        }

        $inventoryId = 0;
        $inventoryEquipmentId = 0;
        if ($stmtExists && $subnetId > 0) {
            mysqli_stmt_bind_param($stmtExists, 'iis', $company_id, $subnetId, $ip);
            mysqli_stmt_execute($stmtExists);
            $existsRes = mysqli_stmt_get_result($stmtExists);
            $existsRow = $existsRes ? mysqli_fetch_assoc($existsRes) : null;
            if ($existsRow) {
                $inventoryId = (int)($existsRow['id'] ?? 0);
                $inventoryEquipmentId = (int)($existsRow['equipment_id'] ?? 0);
            }
        }

        $equipment = itm_ipam_find_equipment_by_ip_text($conn, $company_id, $ip);
        $equipmentId = (int)($equipment['id'] ?? 0);
        if ($inventoryEquipmentId > 0) {
            $equipmentId = $inventoryEquipmentId;
        }

        $equipmentLabel = '';
        if ($equipmentId > 0 && $equipment) {
            $equipmentLabel = trim((string)($equipment['hostname'] ?? ''));
            if ($equipmentLabel === '') {
                $equipmentLabel = trim((string)($equipment['name'] ?? ''));
            }
        }

        if ($subnetId > 0) {
            $activities[] = [
                'level' => 'info',
                'message' => $ip . ': matched subnet ' . ($subnetCidr !== '' ? $subnetCidr : ('#' . $subnetId)),
            ];
        } else {
            $activities[] = [
                'level' => 'warn',
                'message' => $ip . ': no company subnet covers this address',
            ];
        }

        if ($inventoryId > 0) {
            $activities[] = ['level' => 'muted', 'message' => $ip . ': already in IP inventory'];
        }

        $domains = [];
        $domainPrimary = '';
        $totalDomains = 0;
        $domainLookup = itm_ipam_ip2whois_lookup_domains($ip);
        if (!empty($domainLookup['skipped'])) {
            $activities[] = [
                'level' => 'muted',
                'message' => $ip . ': IP2WHOIS lookup skipped (set IP2WHOIS_API_KEY in .env)',
            ];
        } elseif (empty($domainLookup['ok'])) {
            $activities[] = [
                'level' => 'warn',
                'message' => $ip . ': IP2WHOIS — ' . (string)($domainLookup['error'] ?? 'lookup failed'),
            ];
        } else {
            $domains = $domainLookup['domains'] ?? [];
            $totalDomains = (int)($domainLookup['total_domains'] ?? count($domains));
            if ($domains !== []) {
                $domainPrimary = (string)$domains[0];
                $preview = implode(', ', array_slice($domains, 0, 3));
                if (count($domains) > 3) {
                    $preview .= '…';
                }
                $totalLabel = $totalDomains > count($domains)
                    ? (' (' . $totalDomains . ' total)')
                    : '';
                $activities[] = [
                    'level' => 'info',
                    'message' => $ip . ': IP2WHOIS hosted domains' . $totalLabel . ' — ' . $preview,
                ];
            } else {
                $activities[] = ['level' => 'muted', 'message' => $ip . ': IP2WHOIS returned no hosted domains'];
            }
        }

        $hosts[] = [
            'ip' => $ip,
            'port_used' => $aliveProbe['port_used'] ?? null,
            'response_ms' => $aliveProbe['response_ms'] ?? null,
            'subnet_id' => $subnetId,
            'subnet_cidr' => $subnetCidr,
            'equipment_id' => $equipmentId,
            'equipment_label' => $equipmentLabel,
            'domains' => $domains,
            'domain_primary' => $domainPrimary,
            'total_domains' => $totalDomains,
            'in_inventory' => $inventoryId > 0,
            'inventory_id' => $inventoryId,
        ];
    }

    if ($stmtExists) {
        mysqli_stmt_close($stmtExists);
    }

    return ['hosts' => $hosts, 'activities' => $activities];
}

/**
 * @return array{ok: bool, error?: string, scanned?: int, found?: int, hosts?: array<int, array<string, mixed>>, activities?: array<int, array{level: string, message: string}>}
 */
function itm_ipam_network_discovery_scan(
    mysqli $conn,
    int $company_id,
    string $rangeStart,
    string $rangeEnd,
    float $probeTimeout = 0.35
): array {
    if ($company_id <= 0) {
        return ['ok' => false, 'error' => 'Active company is required.'];
    }
    if (!itm_ipam_table_exists($conn, 'ip_addresses')) {
        return ['ok' => false, 'error' => 'IP address inventory is not available.'];
    }

    $range = itm_ipam_ipv4_range_from_bounds($rangeStart, $rangeEnd);
    if (empty($range['ok'])) {
        return ['ok' => false, 'error' => (string)($range['error'] ?? 'Invalid IP range.')];
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(180);
    }

    $ips = $range['ips'] ?? [];
    $scanResult = itm_ipam_network_discovery_scan_ips($conn, $company_id, $ips, $probeTimeout);
    $hosts = $scanResult['hosts'] ?? [];

    return [
        'ok' => true,
        'scanned' => count($ips),
        'found' => count($hosts),
        'hosts' => $hosts,
        'activities' => $scanResult['activities'] ?? [],
    ];
}

/**
 * Why: Batched AJAX requests keep the UI responsive and allow a real progress bar.
 *
 * @return array<string, mixed>
 */
function itm_ipam_network_discovery_scan_batch(
    mysqli $conn,
    int $company_id,
    string $rangeStart,
    string $rangeEnd,
    int $offset,
    int $batchSize = 5,
    float $probeTimeout = 0.35
): array {
    if ($company_id <= 0) {
        return ['ok' => false, 'error' => 'Active company is required.'];
    }
    if (!itm_ipam_table_exists($conn, 'ip_addresses')) {
        return ['ok' => false, 'error' => 'IP address inventory is not available.'];
    }

    $range = itm_ipam_ipv4_range_from_bounds($rangeStart, $rangeEnd);
    if (empty($range['ok'])) {
        return ['ok' => false, 'error' => (string)($range['error'] ?? 'Invalid IP range.')];
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(60);
    }

    $offset = max(0, $offset);
    $batchSize = max(1, min(25, $batchSize));
    $allIps = $range['ips'] ?? [];
    $total = count($allIps);
    $batchIps = array_slice($allIps, $offset, $batchSize);
    $batchCount = count($batchIps);
    $nextOffset = $offset + $batchCount;
    $complete = $nextOffset >= $total;

    $activities = [];
    if ($offset === 0) {
        $activities[] = [
            'level' => 'info',
            'message' => 'Validated range ' . $rangeStart . ' – ' . $rangeEnd . ' (' . $total . ' address' . ($total === 1 ? '' : 'es') . ')',
        ];
    }
    if ($batchCount === 0) {
        return [
            'ok' => true,
            'total' => $total,
            'offset' => $offset,
            'next_offset' => $nextOffset,
            'complete' => true,
            'scanned' => 0,
            'found' => 0,
            'hosts' => [],
            'activities' => $activities,
            'detail' => 'Scan complete.',
        ];
    }

    $activities[] = [
        'level' => 'info',
        'message' => 'Batch ' . (int)floor($offset / $batchSize + 1) . ': scanning ' . $batchIps[0] . ' – ' . $batchIps[$batchCount - 1],
    ];

    $scanResult = itm_ipam_network_discovery_scan_ips($conn, $company_id, $batchIps, $probeTimeout);
    $hosts = $scanResult['hosts'] ?? [];
    $activities = array_merge($activities, $scanResult['activities'] ?? []);

    $detail = $complete
        ? ('Finished scanning ' . $total . ' address' . ($total === 1 ? '' : 'es') . '.')
        : ('Scanned ' . $nextOffset . ' of ' . $total . ' addresses…');

    return [
        'ok' => true,
        'total' => $total,
        'offset' => $offset,
        'next_offset' => $nextOffset,
        'complete' => $complete,
        'scanned' => $batchCount,
        'found' => count($hosts),
        'hosts' => $hosts,
        'activities' => $activities,
        'detail' => $detail,
    ];
}

/**
 * @param array<int, string> $hostIps
 * @return array{ok: bool, error?: string, added?: int, skipped?: int, details?: array<int, array<string, mixed>>, activities?: array<int, array{level: string, message: string}>}
 */
function itm_ipam_network_discovery_import_hosts(mysqli $conn, int $company_id, array $hostIps): array
{
    $batch = itm_ipam_network_discovery_import_hosts_batch($conn, $company_id, $hostIps, 0, max(1, count($hostIps)));
    if (empty($batch['ok'])) {
        return ['ok' => false, 'error' => (string)($batch['error'] ?? 'Import failed.')];
    }

    return [
        'ok' => true,
        'added' => (int)($batch['added'] ?? 0),
        'skipped' => (int)($batch['skipped'] ?? 0),
        'details' => $batch['details'] ?? [],
        'activities' => $batch['activities'] ?? [],
    ];
}

/**
 * @param array<int, string> $hostIps
 * @return array<string, mixed>
 */
function itm_ipam_network_discovery_import_hosts_batch(
    mysqli $conn,
    int $company_id,
    array $hostIps,
    int $offset,
    int $batchSize = 5
): array {
    if ($company_id <= 0) {
        return ['ok' => false, 'error' => 'Active company is required.'];
    }
    if (!itm_ipam_table_exists($conn, 'ip_addresses')) {
        return ['ok' => false, 'error' => 'IP address inventory is not available.'];
    }

    $offset = max(0, $offset);
    $batchSize = max(1, min(25, $batchSize));
    $total = count($hostIps);
    $batchIps = array_slice($hostIps, $offset, $batchSize);
    $batchCount = count($batchIps);
    $nextOffset = $offset + $batchCount;
    $complete = $nextOffset >= $total;

    $subnets = itm_ipam_load_company_subnets_for_discovery($conn, $company_id);
    $stmtExists = mysqli_prepare(
        $conn,
        'SELECT id FROM ip_addresses WHERE company_id = ? AND subnet_id = ? AND ip_text = ? LIMIT 1'
    );
    $stmtInsertWithEquip = mysqli_prepare(
        $conn,
        'INSERT INTO ip_addresses (company_id, subnet_id, ip_text, status, equipment_id, hostname, notes, active)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );
    $stmtInsertNoEquip = mysqli_prepare(
        $conn,
        'INSERT INTO ip_addresses (company_id, subnet_id, ip_text, status, hostname, notes, active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );

    $added = 0;
    $skipped = 0;
    $details = [];
    $activities = [];

    if ($offset === 0 && $total > 0) {
        $activities[] = [
            'level' => 'info',
            'message' => 'Importing ' . $total . ' discovered host' . ($total === 1 ? '' : 's') . ' into IP inventory…',
        ];
    }

    foreach ($batchIps as $rawIp) {
        $ip = itm_ipam_trim_user_input((string)$rawIp);
        $activities[] = ['level' => 'info', 'message' => 'Importing ' . $ip . '…'];

        if (!itm_ipam_is_valid_ipv4($ip)) {
            $skipped++;
            $details[] = ['ip' => $ip, 'status' => 'invalid'];
            $activities[] = ['level' => 'warn', 'message' => $ip . ': skipped (invalid IP)'];
            continue;
        }

        $subnetId = itm_ipam_find_subnet_id_for_ip($ip, $subnets);
        if ($subnetId <= 0) {
            $skipped++;
            $details[] = ['ip' => $ip, 'status' => 'no_subnet'];
            $activities[] = ['level' => 'warn', 'message' => $ip . ': skipped (no matching subnet)'];
            continue;
        }

        if ($stmtExists) {
            mysqli_stmt_bind_param($stmtExists, 'iis', $company_id, $subnetId, $ip);
            mysqli_stmt_execute($stmtExists);
            $existsRes = mysqli_stmt_get_result($stmtExists);
            if ($existsRes && mysqli_num_rows($existsRes) > 0) {
                $skipped++;
                $details[] = ['ip' => $ip, 'status' => 'exists'];
                $activities[] = ['level' => 'muted', 'message' => $ip . ': already in inventory'];
                continue;
            }
        }

        $equipment = itm_ipam_find_equipment_by_ip_text($conn, $company_id, $ip);
        $equipmentId = (int)($equipment['id'] ?? 0);
        $hostname = $equipment ? trim((string)($equipment['hostname'] ?? '')) : '';
        if ($hostname === '') {
            $domainLookup = itm_ipam_ip2whois_lookup_domains($ip);
            if (!empty($domainLookup['ok']) && !empty($domainLookup['domains'][0])) {
                $hostname = itm_ipam_domain_to_hostname_hint((string)$domainLookup['domains'][0]);
                if ($hostname !== '') {
                    $activities[] = [
                        'level' => 'info',
                        'message' => $ip . ': hostname from IP2WHOIS — ' . $hostname,
                    ];
                }
            }
        }
        $status = 'used';
        $notes = 'Discovered via network scan';

        $insertOk = false;
        if ($equipmentId > 0 && $stmtInsertWithEquip) {
            mysqli_stmt_bind_param(
                $stmtInsertWithEquip,
                'iississ',
                $company_id,
                $subnetId,
                $ip,
                $status,
                $equipmentId,
                $hostname,
                $notes
            );
            $insertOk = mysqli_stmt_execute($stmtInsertWithEquip);
        } elseif ($stmtInsertNoEquip) {
            mysqli_stmt_bind_param(
                $stmtInsertNoEquip,
                'iissss',
                $company_id,
                $subnetId,
                $ip,
                $status,
                $hostname,
                $notes
            );
            $insertOk = mysqli_stmt_execute($stmtInsertNoEquip);
        }

        if (!$insertOk) {
            $skipped++;
            $details[] = ['ip' => $ip, 'status' => 'error'];
            $activities[] = ['level' => 'fail', 'message' => $ip . ': database insert failed'];
            continue;
        }

        $added++;
        $details[] = ['ip' => $ip, 'status' => 'added', 'subnet_id' => $subnetId];
        $activities[] = [
            'level' => 'ok',
            'message' => $ip . ': added to inventory' . ($equipmentId > 0 ? ' (linked equipment)' : ''),
        ];
    }

    if ($stmtExists) {
        mysqli_stmt_close($stmtExists);
    }
    if ($stmtInsertWithEquip) {
        mysqli_stmt_close($stmtInsertWithEquip);
    }
    if ($stmtInsertNoEquip) {
        mysqli_stmt_close($stmtInsertNoEquip);
    }

    $detail = $complete
        ? ('Import finished (' . $total . ' host' . ($total === 1 ? '' : 's') . ' processed).')
        : ('Processed ' . $nextOffset . ' of ' . $total . ' hosts…');

    return [
        'ok' => true,
        'total' => $total,
        'offset' => $offset,
        'next_offset' => $nextOffset,
        'complete' => $complete,
        'added' => $added,
        'skipped' => $skipped,
        'details' => $details,
        'activities' => $activities,
        'detail' => $detail,
    ];
}

/**
 * Why: Keep equipment.ip_address aligned when an IPAM row is assigned to an asset.
 */
function itm_ipam_sync_equipment_ip_address(
    mysqli $conn,
    int $company_id,
    int $equipment_id,
    string $ip_text,
    string $status
): void {
    if ($company_id <= 0 || $equipment_id <= 0) {
        return;
    }

    $ip_text = trim($ip_text);
    $status = strtolower(trim($status));
    $clearIp = ($ip_text === '' || !in_array($status, ['used', 'reserved', 'dhcp'], true));

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE equipment SET ip_address = ? WHERE id = ? AND company_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return;
    }

    $value = $clearIp ? null : $ip_text;
    mysqli_stmt_bind_param($stmt, 'sii', $value, $equipment_id, $company_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Why: Migration and feature gates must no-op when IPAM tables are not installed yet.
 */
function itm_ipam_table_exists(mysqli $conn, string $table): bool
{
    if (!itm_is_safe_identifier($table)) {
        return false;
    }
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$tableEsc}'");
    return $res && mysqli_num_rows($res) > 0;
}

/**
 * Why: Large prefixes (/16, etc.) cannot insert every host; cap keeps bulk-generate safe.
 */
function itm_ipam_subnet_bulk_generate_max_hosts(int $prefixLength): int
{
    if ($prefixLength < 0 || $prefixLength > 32) {
        return 0;
    }
    if ($prefixLength >= 31) {
        return 1;
    }

    $hostCount = (int)(2 ** (32 - $prefixLength)) - 2;
    if ($hostCount <= 0) {
        return 0;
    }

    return min(512, $hostCount);
}

function itm_ipam_can_bulk_generate_subnet(int $prefixLength): bool
{
    return itm_ipam_subnet_bulk_generate_max_hosts($prefixLength) > 0;
}

/**
 * Why: Subnet view and index list share the same bulk-generate labels and confirm copy.
 *
 * @return array{can_generate:bool,max_hosts:int,host_total:int,is_capped:bool,confirm_message:string,button_label:string}
 */
function itm_ipam_subnet_bulk_generate_ui(int $prefixLength): array
{
    $maxHosts = itm_ipam_subnet_bulk_generate_max_hosts($prefixLength);
    $canGenerate = $maxHosts > 0;
    $hostTotal = ($prefixLength >= 0 && $prefixLength <= 30)
        ? max(0, (int)(2 ** (32 - $prefixLength)) - 2)
        : 0;
    if ($hostTotal === 0 && $maxHosts > 0) {
        $hostTotal = $maxHosts;
    }
    $isCapped = $canGenerate && $hostTotal > $maxHosts;
    $confirmMessage = $isCapped
        ? 'Generate up to ' . $maxHosts . ' host IPs (first usable addresses in this subnet)? Existing IPs are kept.'
        : 'Generate all host IPs for this subnet? Existing IPs are kept.';
    $buttonLabel = $isCapped
        ? 'Generate host IPs (up to ' . $maxHosts . ')'
        : 'Generate host IPs';

    return [
        'can_generate' => $canGenerate,
        'max_hosts' => $maxHosts,
        'host_total' => $hostTotal,
        'is_capped' => $isCapped,
        'confirm_message' => $confirmMessage,
        'button_label' => $buttonLabel,
    ];
}

/**
 * Why: Subnet view should show the same totals/range users see in external CIDR calculators.
 *
 * @return array<string, mixed>|null
 */
function itm_ipam_subnet_statistics(string $networkIp, int $prefixLength): ?array
{
    if (!itm_ipam_is_valid_ipv4($networkIp) || $prefixLength < 0 || $prefixLength > 32) {
        return null;
    }

    $networkLong = ip2long($networkIp);
    if ($networkLong === false) {
        return null;
    }

    if ($prefixLength < 32) {
        $mask = (0xFFFFFFFF << (32 - $prefixLength)) & 0xFFFFFFFF;
        $networkLong = $networkLong & $mask;
    }

    $networkIpNormalized = long2ip($networkLong);
    if ($networkIpNormalized === false) {
        return null;
    }

    $totalAddresses = (int)(2 ** (32 - $prefixLength));
    $broadcastLong = $networkLong + $totalAddresses - 1;
    $broadcastIp = long2ip($broadcastLong);
    if ($broadcastIp === false) {
        return null;
    }

    if ($prefixLength >= 31) {
        $usableHosts = $totalAddresses;
        $firstUsable = $networkIpNormalized;
        $lastUsable = $broadcastIp;
    } else {
        $usableHosts = max(0, $totalAddresses - 2);
        $firstUsable = $usableHosts > 0 ? long2ip($networkLong + 1) : '';
        $lastUsable = $usableHosts > 0 ? long2ip($broadcastLong - 1) : '';
    }

    if ($firstUsable === false) {
        $firstUsable = '';
    }
    if ($lastUsable === false) {
        $lastUsable = '';
    }

    $usableRange = ($firstUsable !== '' && $lastUsable !== '')
        ? $firstUsable . ' → ' . $lastUsable
        : '—';

    return [
        'cidr' => $networkIpNormalized . '/' . $prefixLength,
        'total_ips' => $totalAddresses,
        'usable_ips' => $usableHosts,
        'network' => $networkIpNormalized,
        'broadcast' => $broadcastIp,
        'first_usable' => (string)$firstUsable,
        'last_usable' => (string)$lastUsable,
        'usable_range' => $usableRange,
        'bulk_generate_cap' => itm_ipam_subnet_bulk_generate_max_hosts($prefixLength),
    ];
}

/**
 * Why: Bulk generation skips network/broadcast and caps very large prefixes.
 */
function itm_ipam_host_addresses_for_subnet(string $networkIp, int $prefixLength, int $maxHosts = 512): array
{
    if (!itm_ipam_is_valid_ipv4($networkIp) || $prefixLength < 0 || $prefixLength > 32) {
        return [];
    }

    if ($prefixLength >= 31) {
        return [$networkIp];
    }

    $networkLong = ip2long($networkIp);
    if ($networkLong === false) {
        return [];
    }

    $totalAddresses = (int)(2 ** (32 - $prefixLength));
    $hostCount = max(0, $totalAddresses - 2);
    if ($hostCount <= 0) {
        return [];
    }
    if ($hostCount > $maxHosts) {
        $hostCount = $maxHosts;
    }

    $start = $networkLong + 1;
    $ips = [];
    for ($i = 0; $i < $hostCount; $i++) {
        $ips[] = long2ip($start + $i);
    }

    return $ips;
}

/**
 * Create missing host rows for a subnet (subnet view bulk-generate; large prefixes are capped).
 */
function itm_ipam_bulk_generate_subnet_ips(
    mysqli $conn,
    int $company_id,
    int $subnet_id,
    int &$createdCount,
    int &$skippedCount,
    string &$errorMessage
): bool {
    $createdCount = 0;
    $skippedCount = 0;
    $errorMessage = '';

    if ($company_id <= 0 || $subnet_id <= 0) {
        $errorMessage = 'Invalid company or subnet.';
        return false;
    }
    if (!itm_ipam_table_exists($conn, 'ip_subnets') || !itm_ipam_table_exists($conn, 'ip_addresses')) {
        $errorMessage = 'IPAM tables are not installed.';
        return false;
    }

    $stmtSubnet = mysqli_prepare(
        $conn,
        'SELECT id, network_ip, prefix_length, gateway_ip
         FROM ip_subnets
         WHERE id = ? AND company_id = ?
         LIMIT 1'
    );
    if (!$stmtSubnet) {
        $errorMessage = 'Unable to load subnet.';
        return false;
    }
    mysqli_stmt_bind_param($stmtSubnet, 'ii', $subnet_id, $company_id);
    mysqli_stmt_execute($stmtSubnet);
    $resSubnet = mysqli_stmt_get_result($stmtSubnet);
    $subnetRow = $resSubnet ? mysqli_fetch_assoc($resSubnet) : null;
    mysqli_stmt_close($stmtSubnet);

    if (!$subnetRow) {
        $errorMessage = 'Subnet not found.';
        return false;
    }

    $prefixLength = (int)($subnetRow['prefix_length'] ?? 0);
    $maxHosts = itm_ipam_subnet_bulk_generate_max_hosts($prefixLength);
    if ($maxHosts <= 0) {
        $errorMessage = 'This subnet has no host addresses to generate.';
        return false;
    }

    $networkIp = (string)($subnetRow['network_ip'] ?? '');
    $gatewayIp = trim((string)($subnetRow['gateway_ip'] ?? ''));
    $hostIps = itm_ipam_host_addresses_for_subnet($networkIp, $prefixLength, $maxHosts);
    if (!$hostIps) {
        $errorMessage = 'No host addresses could be calculated for this subnet.';
        return false;
    }

    $stmtExists = mysqli_prepare(
        $conn,
        'SELECT id FROM ip_addresses WHERE subnet_id = ? AND ip_text = ? LIMIT 1'
    );
    $stmtInsert = mysqli_prepare(
        $conn,
        'INSERT INTO ip_addresses (company_id, subnet_id, ip_text, status, is_gateway, active)
         VALUES (?, ?, ?, ?, ?, 1)'
    );
    if (!$stmtExists || !$stmtInsert) {
        $errorMessage = 'Unable to prepare bulk-generate statements.';
        if ($stmtExists) {
            mysqli_stmt_close($stmtExists);
        }
        if ($stmtInsert) {
            mysqli_stmt_close($stmtInsert);
        }
        return false;
    }

    foreach ($hostIps as $hostIp) {
        mysqli_stmt_bind_param($stmtExists, 'is', $subnet_id, $hostIp);
        mysqli_stmt_execute($stmtExists);
        $resExists = mysqli_stmt_get_result($stmtExists);
        $exists = $resExists && mysqli_num_rows($resExists) > 0;
        if ($exists) {
            $skippedCount++;
            continue;
        }

        $isGateway = ($gatewayIp !== '' && $hostIp === $gatewayIp) ? 1 : 0;
        $status = $isGateway ? 'gateway' : 'free';
        mysqli_stmt_bind_param($stmtInsert, 'iissi', $company_id, $subnet_id, $hostIp, $status, $isGateway);
        if (mysqli_stmt_execute($stmtInsert)) {
            $createdCount++;
        }
    }

    mysqli_stmt_close($stmtExists);
    mysqli_stmt_close($stmtInsert);

    return true;
}

/**
 * Why: Legacy equipment rows store IP in equipment.ip_address before ip_addresses.equipment_id is set.
 */
function itm_ipam_sql_equipment_joins(): string
{
    return 'LEFT JOIN equipment e_fk ON e_fk.id = ia.equipment_id AND e_fk.company_id = ia.company_id
            LEFT JOIN equipment e_ip ON e_ip.company_id = ia.company_id
                AND (ia.equipment_id IS NULL OR ia.equipment_id = 0)
                AND TRIM(COALESCE(e_ip.ip_address, \'\')) <> \'\'
                AND TRIM(e_ip.ip_address) = ia.ip_text';
}

function itm_ipam_sql_equipment_select(): string
{
    return 'COALESCE(ia.equipment_id, e_ip.id) AS equipment_id,
            COALESCE(e_fk.name, e_ip.name) AS equipment_name,
            COALESCE(e_fk.hostname, e_ip.hostname) AS equipment_hostname';
}

/**
 * Why: List queries must not show Free when an equipment link exists (FK or matching ip_address).
 */
function itm_ipam_sql_status_select(): string
{
    return "CASE
        WHEN ia.status = 'free'
             AND COALESCE(NULLIF(ia.equipment_id, 0), e_ip.id) IS NOT NULL
             AND COALESCE(NULLIF(ia.equipment_id, 0), e_ip.id) > 0
        THEN 'used'
        ELSE ia.status
    END AS status";
}

/**
 * Why: View/edit screens reuse the same effective status rules as the focused IP list.
 */
function itm_ipam_effective_status_from_row(array $row): string
{
    $status = strtolower(trim((string)($row['status'] ?? 'free')));
    if ($status !== 'free') {
        return $status;
    }

    return (int)($row['equipment_id'] ?? 0) > 0 ? 'used' : 'free';
}

/**
 * @return array{id: int, name: string, hostname: string}|null
 */
function itm_ipam_find_equipment_by_ip_text(mysqli $conn, int $company_id, string $ip_text): ?array
{
    $ip_text = trim($ip_text);
    if ($company_id <= 0 || $ip_text === '' || !itm_ipam_table_exists($conn, 'equipment')) {
        return null;
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, name, hostname
         FROM equipment
         WHERE company_id = ? AND TRIM(COALESCE(ip_address, \'\')) = ?
         ORDER BY id ASC
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'is', $company_id, $ip_text);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return null;
    }

    return [
        'id' => (int)($row['id'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
        'hostname' => (string)($row['hostname'] ?? ''),
    ];
}

/**
 * Why: One-time backfill links IPAM rows when equipment already has the same ip_address value.
 */
function itm_ipam_backfill_equipment_links_from_ip_address(mysqli $conn, int $company_id): int
{
    if ($company_id <= 0 || !itm_ipam_table_exists($conn, 'ip_addresses') || !itm_ipam_table_exists($conn, 'equipment')) {
        return 0;
    }

    $sql = 'UPDATE ip_addresses ia
            INNER JOIN (
                SELECT company_id, TRIM(ip_address) AS ip_text, MIN(id) AS equipment_id
                FROM equipment
                WHERE company_id = ? AND TRIM(COALESCE(ip_address, \'\')) <> \'\'
                GROUP BY company_id, TRIM(ip_address)
            ) em ON em.company_id = ia.company_id AND em.ip_text = ia.ip_text
            SET ia.equipment_id = em.equipment_id,
                ia.status = CASE WHEN ia.status = \'free\' THEN \'used\' ELSE ia.status END
            WHERE ia.company_id = ?
              AND (ia.equipment_id IS NULL OR ia.equipment_id = 0)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $company_id, $company_id);
    mysqli_stmt_execute($stmt);
    $updated = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    $stmtStatus = mysqli_prepare(
        $conn,
        "UPDATE ip_addresses
         SET status = 'used'
         WHERE company_id = ?
           AND status = 'free'
           AND equipment_id IS NOT NULL
           AND equipment_id > 0"
    );
    if ($stmtStatus) {
        mysqli_stmt_bind_param($stmtStatus, 'i', $company_id);
        mysqli_stmt_execute($stmtStatus);
        $updated += max(0, mysqli_stmt_affected_rows($stmtStatus));
        mysqli_stmt_close($stmtStatus);
    }

    return max(0, $updated);
}

/**
 * @return array<int, array<string, mixed>>
 */
function itm_ipam_fetch_subnet_addresses(mysqli $conn, int $company_id, int $subnet_id, int $limit = 300): array
{
    if ($company_id <= 0 || $subnet_id <= 0 || !itm_ipam_table_exists($conn, 'ip_addresses')) {
        return [];
    }

    $limit = max(1, min(1000, $limit));
    $equipmentJoins = itm_ipam_sql_equipment_joins();
    $equipmentSelect = itm_ipam_sql_equipment_select();
    $statusSelect = itm_ipam_sql_status_select();
    $stmt = mysqli_prepare(
        $conn,
        "SELECT ia.id, ia.ip_text, {$statusSelect}, ia.hostname,
                {$equipmentSelect}
         FROM ip_addresses ia
         {$equipmentJoins}
         WHERE ia.company_id = ? AND ia.subnet_id = ?
         ORDER BY INET_ATON(ia.ip_text) ASC
         LIMIT {$limit}"
    );
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $company_id, $subnet_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

function itm_ipam_count_subnet_addresses(mysqli $conn, int $company_id, int $subnet_id): int
{
    if ($company_id <= 0 || $subnet_id <= 0 || !itm_ipam_table_exists($conn, 'ip_addresses')) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM ip_addresses WHERE company_id = ? AND subnet_id = ?'
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $company_id, $subnet_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (int)($row['c'] ?? 0);
}

/**
 * Why: IP Addresses list should show hostname/name instead of raw equipment_id.
 */
function itm_ipam_equipment_label_from_row(array $row): string
{
    $label = trim((string)($row['equipment_hostname'] ?? ''));
    if ($label === '') {
        $label = trim((string)($row['equipment_name'] ?? ''));
    }

    return $label;
}

/**
 * Why: Equipment column should show asset name; hostname belongs in the Hostname column.
 */
function itm_ipam_equipment_name_label_from_row(array $row): string
{
    $label = trim((string)($row['equipment_name'] ?? ''));
    if ($label === '') {
        $label = trim((string)($row['equipment_hostname'] ?? ''));
    }

    return $label;
}

/**
 * Why: IP hostname may be blank while linked equipment still has a hostname to display.
 */
function itm_ipam_hostname_display_from_row(array $row): string
{
    $hostname = trim((string)($row['hostname'] ?? ''));
    if ($hostname !== '') {
        return $hostname;
    }

    return trim((string)($row['equipment_hostname'] ?? ''));
}

/**
 * @return array<int, array{id: int, label: string}>
 */
function itm_ipam_fetch_subnet_filter_options(mysqli $conn, int $company_id): array
{
    if ($company_id <= 0 || !itm_ipam_table_exists($conn, 'ip_subnets')) {
        return [];
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, cidr FROM ip_subnets WHERE company_id = ? AND active = 1 ORDER BY cidr ASC'
    );
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $options = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $options[] = [
            'id' => (int)($row['id'] ?? 0),
            'label' => (string)($row['cidr'] ?? ''),
        ];
    }
    mysqli_stmt_close($stmt);

    return $options;
}

/**
 * Why: Shared WHERE builder keeps subnet filter + search aligned for count and list queries.
 *
 * @return array{sql: string, types: string, params: array<int|string>}
 */
function itm_ipam_address_list_where_clause(int $company_id, int $subnet_id, string $searchRaw): array
{
    $sql = ' WHERE ia.company_id = ?';
    $types = 'i';
    $params = [$company_id];

    if ($subnet_id > 0) {
        $sql .= ' AND ia.subnet_id = ?';
        $types .= 'i';
        $params[] = $subnet_id;
    }

    $searchRaw = trim($searchRaw);
    if ($searchRaw !== '') {
        $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_'))
            ? $searchRaw
            : '%' . $searchRaw . '%';
        $sql .= " AND (
            CAST(ia.id AS CHAR) LIKE ?
            OR ia.ip_text LIKE ?
            OR ia.status LIKE ?
            OR ia.hostname LIKE ?
            OR s.cidr LIKE ?
            OR COALESCE(e_fk.name, e_ip.name, '') LIKE ?
            OR COALESCE(e_fk.hostname, e_ip.hostname, '') LIKE ?
            OR COALESCE(ia.notes, '') LIKE ?
        )";
        $types .= str_repeat('s', 8);
        for ($i = 0; $i < 8; $i++) {
            $params[] = $searchPattern;
        }
    }

    return ['sql' => $sql, 'types' => $types, 'params' => $params];
}

function itm_ipam_address_list_sort_sql(string $sort, string $dir): string
{
    $statusSort = "CASE
        WHEN ia.status = 'free'
             AND COALESCE(NULLIF(ia.equipment_id, 0), e_ip.id) IS NOT NULL
             AND COALESCE(NULLIF(ia.equipment_id, 0), e_ip.id) > 0
        THEN 'used'
        ELSE ia.status
    END";

    $sortMap = [
        'ip_text' => 'INET_ATON(ia.ip_text)',
        'status' => $statusSort,
        'subnet' => 's.cidr',
        'subnet_cidr' => 's.cidr',
        'equipment' => "COALESCE(NULLIF(TRIM(e_fk.hostname), ''), NULLIF(TRIM(e_ip.hostname), ''), NULLIF(TRIM(e_fk.name), ''), NULLIF(TRIM(e_ip.name), ''), '')",
        'hostname' => 'ia.hostname',
        'notes' => 'ia.notes',
        'id' => 'ia.id',
    ];
    $sortKey = array_key_exists($sort, $sortMap) ? $sort : 'ip_text';
    $dirSql = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

    return $sortMap[$sortKey] . ' ' . $dirSql . ', ia.id ASC';
}

/**
 * @param array<int|string> $params
 */
function itm_ipam_bind_address_list_params(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || !$params) {
        return;
    }

    $bind = [$types];
    foreach ($params as $index => $value) {
        $bind[] = &$params[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function itm_ipam_count_address_list(mysqli $conn, int $company_id, int $subnet_id, string $searchRaw): int
{
    if ($company_id <= 0 || !itm_ipam_table_exists($conn, 'ip_addresses') || !itm_ipam_table_exists($conn, 'ip_subnets')) {
        return 0;
    }

    $where = itm_ipam_address_list_where_clause($company_id, $subnet_id, $searchRaw);
    $equipmentJoins = itm_ipam_sql_equipment_joins();
    $sql = 'SELECT COUNT(*) AS c
            FROM ip_addresses ia
            INNER JOIN ip_subnets s ON s.id = ia.subnet_id AND s.company_id = ia.company_id
            ' . $equipmentJoins
        . $where['sql'];
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    itm_ipam_bind_address_list_params($stmt, $where['types'], $where['params']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (int)($row['c'] ?? 0);
}

/**
 * @return array<int, array<string, mixed>>
 */
function itm_ipam_fetch_address_list(
    mysqli $conn,
    int $company_id,
    int $subnet_id,
    string $searchRaw,
    string $sort,
    string $dir,
    int $limit,
    int $offset
): array {
    if ($company_id <= 0 || !itm_ipam_table_exists($conn, 'ip_addresses') || !itm_ipam_table_exists($conn, 'ip_subnets')) {
        return [];
    }

    itm_ipam_backfill_equipment_links_from_ip_address($conn, $company_id);

    $limit = max(1, min(500, $limit));
    $offset = max(0, $offset);
    $where = itm_ipam_address_list_where_clause($company_id, $subnet_id, $searchRaw);
    $orderSql = itm_ipam_address_list_sort_sql($sort, $dir);
    $equipmentJoins = itm_ipam_sql_equipment_joins();
    $equipmentSelect = itm_ipam_sql_equipment_select();
    $statusSelect = itm_ipam_sql_status_select();
    $sql = "SELECT ia.id, ia.ip_text, {$statusSelect}, ia.hostname, ia.notes, ia.is_gateway,
                   s.id AS subnet_id, s.cidr AS subnet_cidr,
                   {$equipmentSelect}
            FROM ip_addresses ia
            INNER JOIN ip_subnets s ON s.id = ia.subnet_id AND s.company_id = ia.company_id
            {$equipmentJoins}"
        . $where['sql']
        . ' ORDER BY ' . $orderSql
        . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    itm_ipam_bind_address_list_params($stmt, $where['types'], $where['params']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Why: IP list inline note edits update ip_addresses.notes only (not equipment.notes).
 */
function itm_ipam_save_address_notes(mysqli $conn, int $company_id, int $address_id, string $notes): bool
{
    if ($company_id <= 0 || $address_id <= 0 || !itm_ipam_table_exists($conn, 'ip_addresses')) {
        return false;
    }

    $notes = trim($notes);
    if (strlen($notes) > 255) {
        $notes = substr($notes, 0, 255);
    }

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE ip_addresses SET notes = ? WHERE id = ? AND company_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'sii', $notes, $address_id, $company_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return (bool)$ok;
}

/**
 * @return array<int, array<string, mixed>>
 */
function itm_ipam_fetch_equipment_ip_assignments(mysqli $conn, int $company_id, int $equipment_id): array
{
    if ($company_id <= 0 || $equipment_id <= 0 || !itm_ipam_table_exists($conn, 'ip_addresses')) {
        return [];
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT ia.id, ia.ip_text, ia.status, ia.subnet_id, s.cidr AS subnet_cidr
         FROM ip_addresses ia
         INNER JOIN ip_subnets s ON s.id = ia.subnet_id AND s.company_id = ia.company_id
         WHERE ia.company_id = ? AND ia.equipment_id = ?
         ORDER BY INET_ATON(ia.ip_text) ASC"
    );
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $company_id, $equipment_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Why: Legacy VLAN rows often store CIDR in vlans.subnet before ip_subnets existed.
 */
function itm_ipam_migrate_legacy_vlan_subnets(mysqli $conn, int $company_id): int
{
    if ($company_id <= 0) {
        return 0;
    }
    if (!itm_ipam_table_exists($conn, 'vlans') || !itm_ipam_table_exists($conn, 'ip_subnets')) {
        return 0;
    }

    $inserted = 0;
    $stmtVlan = mysqli_prepare(
        $conn,
        "SELECT id, subnet, gateway_ip, comments
         FROM vlans
         WHERE company_id = ?
           AND subnet IS NOT NULL
           AND TRIM(subnet) <> ''"
    );
    if (!$stmtVlan) {
        return 0;
    }
    mysqli_stmt_bind_param($stmtVlan, 'i', $company_id);
    mysqli_stmt_execute($stmtVlan);
    $resVlan = mysqli_stmt_get_result($stmtVlan);

    $stmtExistsVlan = mysqli_prepare(
        $conn,
        'SELECT id FROM ip_subnets WHERE company_id = ? AND vlan_id = ? LIMIT 1'
    );
    $stmtExistsCidr = mysqli_prepare(
        $conn,
        'SELECT id FROM ip_subnets WHERE company_id = ? AND cidr = ? LIMIT 1'
    );
    $stmtInsert = mysqli_prepare(
        $conn,
        "INSERT INTO ip_subnets
         (company_id, vlan_id, cidr, network_ip, prefix_length, gateway_ip, description, active)
         VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), ?, 1)"
    );
    if (!$stmtExistsVlan || !$stmtExistsCidr || !$stmtInsert) {
        mysqli_stmt_close($stmtVlan);
        if ($stmtExistsVlan) {
            mysqli_stmt_close($stmtExistsVlan);
        }
        if ($stmtExistsCidr) {
            mysqli_stmt_close($stmtExistsCidr);
        }
        if ($stmtInsert) {
            mysqli_stmt_close($stmtInsert);
        }
        return 0;
    }

    while ($resVlan && ($vlan = mysqli_fetch_assoc($resVlan))) {
        $vlanId = (int)($vlan['id'] ?? 0);
        $subnetRaw = trim((string)($vlan['subnet'] ?? ''));
        if ($vlanId <= 0 || $subnetRaw === '') {
            continue;
        }

        if (!str_contains($subnetRaw, '/')) {
            if (itm_ipam_is_valid_ipv4($subnetRaw)) {
                $subnetRaw .= '/32';
            } else {
                continue;
            }
        }

        $parsed = itm_ipam_parse_cidr($subnetRaw);
        if (!$parsed['ok']) {
            continue;
        }

        mysqli_stmt_bind_param($stmtExistsVlan, 'ii', $company_id, $vlanId);
        mysqli_stmt_execute($stmtExistsVlan);
        $resExistsVlan = mysqli_stmt_get_result($stmtExistsVlan);
        if ($resExistsVlan && mysqli_num_rows($resExistsVlan) > 0) {
            continue;
        }

        $cidr = (string)$parsed['cidr'];
        mysqli_stmt_bind_param($stmtExistsCidr, 'is', $company_id, $cidr);
        mysqli_stmt_execute($stmtExistsCidr);
        $resExistsCidr = mysqli_stmt_get_result($stmtExistsCidr);
        if ($resExistsCidr && mysqli_num_rows($resExistsCidr) > 0) {
            continue;
        }

        $gatewayIp = trim((string)($vlan['gateway_ip'] ?? ''));
        if ($gatewayIp !== '' && !itm_ipam_is_valid_ipv4($gatewayIp)) {
            $gatewayIp = '';
        }
        $description = trim((string)($vlan['comments'] ?? ''));
        if ($description === '') {
            $description = 'Migrated from VLAN subnet';
        }

        $networkIp = (string)$parsed['network_ip'];
        $prefixLength = (int)$parsed['prefix_length'];
        mysqli_stmt_bind_param(
            $stmtInsert,
            'iississ',
            $company_id,
            $vlanId,
            $cidr,
            $networkIp,
            $prefixLength,
            $gatewayIp,
            $description
        );
        if (mysqli_stmt_execute($stmtInsert)) {
            $inserted++;
        }
    }

    mysqli_stmt_close($stmtVlan);
    mysqli_stmt_close($stmtExistsVlan);
    mysqli_stmt_close($stmtExistsCidr);
    mysqli_stmt_close($stmtInsert);

    return $inserted;
}

/**
 * Run VLAN→subnet migration once per company per session.
 */
function itm_ipam_ensure_legacy_vlan_subnets_migrated(mysqli $conn, int $company_id): void
{
    if ($company_id <= 0) {
        return;
    }

    if (!isset($_SESSION) || !is_array($_SESSION)) {
        itm_ipam_migrate_legacy_vlan_subnets($conn, $company_id);
        return;
    }

    $sessionKey = 'itm_ipam_vlan_subnet_migrated_' . $company_id;
    if (!empty($_SESSION[$sessionKey])) {
        return;
    }

    itm_ipam_migrate_legacy_vlan_subnets($conn, $company_id);
    $_SESSION[$sessionKey] = 1;
}
