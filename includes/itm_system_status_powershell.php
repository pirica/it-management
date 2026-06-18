<?php
/**
 * Why: Windows Laragon monitoring uses includes/*.ps1; centralise execution and permission checks.
 */

function itm_system_status_shell_exec_available(): bool
{
    if (!function_exists('shell_exec')) {
        return false;
    }
    $disabled = array_map('trim', explode(',', strtolower((string)ini_get('disable_functions'))));
    return !in_array('shell_exec', $disabled, true);
}

function itm_system_status_powershell_binary(): string
{
    $systemRoot = getenv('SystemRoot') ?: getenv('WINDIR');
    if ($systemRoot) {
        $candidate = rtrim(str_replace('/', '\\', $systemRoot), '\\') . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return 'powershell.exe';
}

/**
 * @return array{status:string,message?:string,data?:mixed,raw_output?:string}
 */
function itm_system_status_run_powershell_action(string $action): array
{
    $allowedActions = array_merge(
        itm_system_status_hardware_actions(),
        ['php_version', 'php_extensions', 'php_ini_values', 'mysql_status', 'mysql_version', 'mysql_databases', 'mysql_size']
    );
    if (!in_array($action, $allowedActions, true) || !preg_match('/^[a-z0-9_]+$/', $action)) {
        return ['status' => 'error', 'message' => 'Invalid PowerShell action requested.'];
    }

    $script_path = ROOT_PATH . 'includes/' . $action . '.ps1';
    if (!is_file($script_path)) {
        return ['status' => 'error', 'message' => 'PowerShell script not found: includes/' . $action . '.ps1'];
    }

    if (!is_readable($script_path)) {
        return ['status' => 'error', 'message' => 'PowerShell script is not readable by PHP (check includes/' . $action . '.ps1 permissions).'];
    }

    if (!itm_system_status_shell_exec_available()) {
        return [
            'status' => 'error',
            'message' => 'shell_exec is disabled in php.ini (disable_functions). Enable shell_exec for Windows hardware metrics.',
        ];
    }

    $powershell = itm_system_status_powershell_binary();
    $command = escapeshellarg($powershell) . ' -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($script_path);
    $output = shell_exec($command);

    if ($output === null || $output === '') {
        return [
            'status' => 'error',
            'message' => 'PowerShell returned no output. Verify ExecutionPolicy, Apache/PHP can run powershell.exe, and the IIS/Laragon user may read includes/*.ps1.',
        ];
    }

    $output = trim($output);
    if (substr($output, 0, 3) === "\xEF\xBB\xBF") {
        $output = substr($output, 3);
    }

    $json_data = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => 'error',
            'message' => 'Invalid JSON output from PowerShell.',
            'raw_output' => $output,
        ];
    }

    return $json_data;
}

function itm_system_status_prefers_native(string $action): bool
{
    static $nativeActions = [
        'php_version',
        'php_extensions',
        'php_ini_values',
        'mysql_status',
        'mysql_version',
        'mysql_databases',
        'mysql_size',
    ];

    return in_array($action, $nativeActions, true);
}

function itm_system_status_hardware_actions(): array
{
    return ['system_info', 'cpu_usage', 'ram_usage', 'disk_usage', 'uptime'];
}
