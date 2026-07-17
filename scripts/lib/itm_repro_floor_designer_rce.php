<?php
/**
 * Floor Designer save_as_floor_plan repro helpers.
 * Why: Handler calls exit(); subprocess capture avoids parent abort. Sample bytes from switch_port_icons PNGs.
 */

if (!function_exists('itm_repro_floor_designer_sample_png_data_uri')) {
    /**
     * @return string data:image/png;base64,... or empty when no sample PNG exists
     */
    function itm_repro_floor_designer_sample_png_data_uri(): string
    {
        $preferred = ROOT_PATH . 'images/switch_port_icons/rj45_38x31.png';
        $path = is_file($preferred) ? $preferred : '';
        if ($path === '') {
            $matches = glob(ROOT_PATH . 'images/switch_port_icons/*.png') ?: [];
            $path = isset($matches[0]) ? (string)$matches[0] : '';
        }
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($raw);
    }
}

if (!function_exists('itm_repro_floor_designer_is_cli_php_binary')) {
  /**
   * @param string $path
   */
  function itm_repro_floor_designer_is_cli_php_binary($path): bool
  {
    $normalized = strtolower(str_replace('\\', '/', (string)$path));
    if ($normalized === '' || !is_file($path)) {
      return false;
    }
    // Why: Apache SAPI often exposes php-cgi.exe as PHP_BINARY; subprocess must be CLI for ITM_CLI_SCRIPT auth skip.
    if (strpos($normalized, 'php-cgi') !== false) {
      return false;
    }
    if (substr($normalized, -4) === '.dll') {
      return false;
    }

    return true;
  }
}

if (!function_exists('itm_repro_floor_designer_resolve_php_binary')) {
  function itm_repro_floor_designer_resolve_php_binary(): string
  {
    $laragonPhp = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
    if (is_file($laragonPhp)) {
      return $laragonPhp;
    }
    if (defined('PHP_BINARY') && PHP_BINARY !== '' && itm_repro_floor_designer_is_cli_php_binary(PHP_BINARY)) {
      return (string)PHP_BINARY;
    }

    return 'php';
  }
}

if (!function_exists('itm_repro_floor_designer_run_save_subprocess')) {
    /**
     * @param array<string,mixed> $sessionData
     * @param array<string,mixed> $postData csrf_token is stamped inside the subprocess
     */
    function itm_repro_floor_designer_run_save_subprocess(array $sessionData, array $postData): string
    {
        $moduleIndex = realpath(ROOT_PATH . 'modules/floor_designer/index.php');
        if ($moduleIndex === false) {
            return '';
        }

        $configPath = realpath(ROOT_PATH . 'config/config.php');
        if ($configPath === false) {
            return '';
        }

        $sessionInit = '';
        foreach ($sessionData as $key => $value) {
            $sessionInit .= "\$_SESSION['" . addslashes((string)$key) . "'] = " . var_export($value, true) . ";\n";
        }

        $postInit = '';
        foreach ($postData as $key => $value) {
            if ($key === 'csrf_token') {
                continue;
            }
            $postInit .= "\$_POST['" . addslashes((string)$key) . "'] = " . var_export($value, true) . ";\n";
        }

        $code = "<?php
define('ITM_CLI_SCRIPT', true);
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=itmanagement');
putenv('DB_NAME=itmanagement');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
" . $sessionInit . "require '" . addslashes($configPath) . "';
" . $postInit . "
\$_POST['csrf_token'] = itm_get_csrf_token();
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['PHP_SELF'] = '/it-management/modules/floor_designer/index.php';
chdir(dirname('" . addslashes($moduleIndex) . "'));
include basename('" . addslashes($moduleIndex) . "');
?>";

        $tmpFile = tempnam(sys_get_temp_dir(), 'repro_fd_rce');
        if ($tmpFile === false) {
            return '';
        }
        file_put_contents($tmpFile, $code);

        $phpBin = itm_repro_floor_designer_resolve_php_binary();
        if (!function_exists('itm_script_shell_stderr_discard')) {
            require_once __DIR__ . '/script_cli_output.php';
        }
        $output = shell_exec(
            escapeshellarg($phpBin)
            . ' -d error_reporting=0 '
            . escapeshellarg($tmpFile)
            . ' '
            . itm_script_shell_stderr_discard()
        );
        @unlink($tmpFile);

        return trim((string)$output);
    }
}

if (!function_exists('itm_repro_floor_designer_parse_json_response')) {
    /**
     * @return array<string,mixed>|null
     */
    function itm_repro_floor_designer_parse_json_response(string $output): ?array
    {
        if (stripos($output, 'Status: 302') !== false || stripos($output, 'Location:') !== false) {
            return null;
        }

        $decoded = json_decode($output, true);
        if (is_array($decoded) && array_key_exists('ok', $decoded)) {
            return $decoded;
        }
        if (preg_match('/\{.*\}/s', $output, $jsonMatch)) {
            $decoded = json_decode($jsonMatch[0], true);
            if (is_array($decoded) && array_key_exists('ok', $decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}

if (!function_exists('itm_repro_floor_designer_cleanup_plan')) {
    function itm_repro_floor_designer_cleanup_plan(
        mysqli $conn,
        int $companyId,
        int $planId,
        string $uploadDir,
        string $storedFilename,
        int $employeeId
    ): void {
        if ($planId <= 0) {
            return;
        }
        if ($storedFilename !== '' && is_file($uploadDir . $storedFilename)) {
            @unlink($uploadDir . $storedFilename);
        }
        $cleanup = mysqli_prepare(
            $conn,
            'UPDATE floor_plans SET deleted_at = NOW(), deleted_by = ?, active = 0 WHERE id = ? AND company_id = ?'
        );
        if ($cleanup) {
            mysqli_stmt_bind_param($cleanup, 'iii', $employeeId, $planId, $companyId);
            mysqli_stmt_execute($cleanup);
        }
    }
}
