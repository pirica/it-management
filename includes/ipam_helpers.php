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
    $stmt = mysqli_prepare(
        $conn,
        "SELECT ia.id, ia.ip_text, ia.status, ia.hostname,
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
        )";
        $types .= str_repeat('s', 7);
        for ($i = 0; $i < 7; $i++) {
            $params[] = $searchPattern;
        }
    }

    return ['sql' => $sql, 'types' => $types, 'params' => $params];
}

function itm_ipam_address_list_sort_sql(string $sort, string $dir): string
{
    $sortMap = [
        'ip_text' => 'INET_ATON(ia.ip_text)',
        'status' => 'ia.status',
        'subnet' => 's.cidr',
        'subnet_cidr' => 's.cidr',
        'equipment' => "COALESCE(NULLIF(TRIM(e_fk.hostname), ''), NULLIF(TRIM(e_ip.hostname), ''), NULLIF(TRIM(e_fk.name), ''), NULLIF(TRIM(e_ip.name), ''), '')",
        'hostname' => 'ia.hostname',
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

    $limit = max(1, min(500, $limit));
    $offset = max(0, $offset);
    $where = itm_ipam_address_list_where_clause($company_id, $subnet_id, $searchRaw);
    $orderSql = itm_ipam_address_list_sort_sql($sort, $dir);
    $equipmentJoins = itm_ipam_sql_equipment_joins();
    $equipmentSelect = itm_ipam_sql_equipment_select();
    $sql = "SELECT ia.id, ia.ip_text, ia.status, ia.hostname, ia.is_gateway,
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
