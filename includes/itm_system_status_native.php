<?php
/**
 * Why: System Status PowerShell scripts target Windows Laragon; non-Windows hosts
 * need PHP-native metrics so admin tabs and API probes still return JSON.
 */

function itm_system_status_is_windows(): bool
{
    return DIRECTORY_SEPARATOR === '\\';
}

/**
 * @return array|null Standard {status, data} payload or null when action is Windows-only.
 */
function itm_system_status_native_payload(string $action, $conn): ?array
{
    switch ($action) {
        case 'php_version':
            return [
                'status' => 'success',
                'data' => [
                    'php_binary' => PHP_BINARY,
                    'version' => 'PHP ' . PHP_VERSION,
                    'ini_path' => php_ini_loaded_file() ?: '',
                ],
            ];

        case 'php_extensions':
            $extensions = get_loaded_extensions();
            sort($extensions);
            return ['status' => 'success', 'data' => $extensions];

        case 'php_ini_values':
            return [
                'status' => 'success',
                'data' => [
                    'memory_limit' => (string)ini_get('memory_limit'),
                    'upload_max_filesize' => (string)ini_get('upload_max_filesize'),
                    'post_max_size' => (string)ini_get('post_max_size'),
                    'max_execution_time' => (string)ini_get('max_execution_time'),
                ],
            ];

        case 'mysql_version':
            if (!$conn) {
                return ['status' => 'error', 'message' => 'Database connection unavailable.'];
            }
            $version = mysqli_get_server_info($conn);
            return [
                'status' => 'success',
                'data' => [
                    'binary' => 'mysqli',
                    'version' => $version,
                ],
            ];

        case 'mysql_status':
            if (!$conn) {
                return ['status' => 'error', 'message' => 'Database connection unavailable.'];
            }
            $ping = @mysqli_ping($conn);
            return [
                'status' => 'success',
                'data' => [
                    'service_name' => 'mysqld',
                    'status' => $ping ? 'Running' : 'Stopped',
                    'display_name' => 'MySQL Server',
                ],
            ];

        case 'mysql_databases':
            if (!$conn) {
                return ['status' => 'error', 'message' => 'Database connection unavailable.'];
            }
            $rows = [];
            $res = mysqli_query($conn, 'SHOW DATABASES');
            if ($res) {
                while ($row = mysqli_fetch_row($res)) {
                    $rows[] = $row[0];
                }
            }
            return ['status' => 'success', 'data' => $rows];

        case 'mysql_size':
            if (!$conn) {
                return ['status' => 'error', 'message' => 'Database connection unavailable.'];
            }
            $lines = [];
            $sql = "SELECT table_schema AS db_name, ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.TABLES
                    GROUP BY table_schema
                    ORDER BY size_mb DESC";
            $res = mysqli_query($conn, $sql);
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $lines[] = $row['db_name'] . ' ' . $row['size_mb'];
                }
            }
            return ['status' => 'success', 'data' => $lines];

        case 'system_info':
            return itm_system_status_native_system_info();

        case 'cpu_usage':
            return itm_system_status_native_cpu_usage();

        case 'ram_usage':
            return itm_system_status_native_ram_usage();

        case 'disk_usage':
            return itm_system_status_native_disk_usage();

        case 'uptime':
            return itm_system_status_native_uptime();
    }

    return null;
}

function itm_system_status_native_system_info(): array
{
    $ramTotal = 0;
    $ramFree = 0;
    if (is_readable('/proc/meminfo')) {
        $meminfo = file_get_contents('/proc/meminfo');
        if (preg_match('/MemTotal:\s+(\d+)\s+kB/i', $meminfo, $m)) {
            $ramTotal = (int)$m[1] * 1024;
        }
        if (preg_match('/MemAvailable:\s+(\d+)\s+kB/i', $meminfo, $m)) {
            $ramFree = (int)$m[1] * 1024;
        } elseif (preg_match('/MemFree:\s+(\d+)\s+kB/i', $meminfo, $m)) {
            $ramFree = (int)$m[1] * 1024;
        }
    }

    $cpuCores = 1;
    $cpuThreads = 1;
    $cpuModel = 'Unknown';
    if (is_readable('/proc/cpuinfo')) {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        if (preg_match('/model name\s*:\s*(.+)/i', $cpuinfo, $m)) {
            $cpuModel = trim($m[1]);
        }
        $cpuThreads = max(1, substr_count($cpuinfo, 'processor'));
        if (preg_match_all('/^cpu cores\s*:\s*(\d+)/mi', $cpuinfo, $matches) && !empty($matches[1])) {
            $cpuCores = (int)max($matches[1]);
        } else {
            $cpuCores = $cpuThreads;
        }
    }

    $disks = [];
    foreach (['/'] as $mount) {
        $total = @disk_total_space($mount);
        $free = @disk_free_space($mount);
        if ($total === false || $free === false) {
            continue;
        }
        $disks[] = [
            'DeviceID' => $mount,
            'Size' => (int)$total,
            'FreeSpace' => (int)$free,
        ];
    }

    return [
        'status' => 'success',
        'data' => [
            'os_version' => php_uname('s') . ' ' . php_uname('r'),
            'hostname' => gethostname() ?: php_uname('n'),
            'uptime' => itm_system_status_native_uptime_string(),
            'cpu_model' => $cpuModel,
            'cpu_cores' => $cpuCores,
            'cpu_threads' => $cpuThreads,
            'ram_total' => $ramTotal,
            'ram_used' => max(0, $ramTotal - $ramFree),
            'ram_free' => $ramFree,
            'disks' => $disks,
            'networks' => [],
        ],
    ];
}

function itm_system_status_native_cpu_usage(): array
{
    $load = 0.0;
    if (is_readable('/proc/loadavg')) {
        $parts = explode(' ', trim((string)file_get_contents('/proc/loadavg')));
        $threads = 1;
        if (is_readable('/proc/cpuinfo')) {
            $threads = max(1, substr_count(file_get_contents('/proc/cpuinfo'), 'processor'));
        }
        $load = min(100, round(((float)($parts[0] ?? 0) / $threads) * 100, 2));
    }

    return [
        'status' => 'success',
        'data' => [
            'cpu_load' => $load,
            'per_core' => [],
        ],
    ];
}

function itm_system_status_native_ram_usage(): array
{
    $info = itm_system_status_native_system_info();
    $data = $info['data'] ?? [];

    return [
        'status' => 'success',
        'data' => [
            'ram_total' => (int)($data['ram_total'] ?? 0),
            'ram_used' => (int)($data['ram_used'] ?? 0),
            'ram_free' => (int)($data['ram_free'] ?? 0),
        ],
    ];
}

function itm_system_status_native_disk_usage(): array
{
    $info = itm_system_status_native_system_info();
    return [
        'status' => 'success',
        'data' => $info['data']['disks'] ?? [],
    ];
}

function itm_system_status_native_uptime(): array
{
    return [
        'status' => 'success',
        'data' => [
            'uptime' => itm_system_status_native_uptime_string(),
        ],
    ];
}

function itm_system_status_native_uptime_string(): string
{
    if (!is_readable('/proc/uptime')) {
        return 'N/A';
    }

    $seconds = (int)floor((float)explode(' ', trim((string)file_get_contents('/proc/uptime')))[0]);
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    return sprintf('%d days, %d hours, %d minutes', $days, $hours, $minutes);
}
