<?php
/**
 * Shared isolated subprocess runner for repro_vulnerabilities.php.
 */

if (!function_exists('itm_repro_vulnerabilities_is_cli_php_binary')) {
    function itm_repro_vulnerabilities_is_cli_php_binary($path): bool
    {
        $normalized = strtolower(str_replace('\\', '/', (string)$path));
        if ($normalized === '' || !is_file($path)) {
            return false;
        }
        if (strpos($normalized, 'php-cgi') !== false) {
            return false;
        }
        if (substr($normalized, -4) === '.dll') {
            return false;
        }

        return true;
    }
}

if (!function_exists('itm_repro_vulnerabilities_resolve_php_binary')) {
    function itm_repro_vulnerabilities_resolve_php_binary(): string
    {
        $laragonPhp = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
        if (is_file($laragonPhp)) {
            return $laragonPhp;
        }
        if (defined('PHP_BINARY') && PHP_BINARY !== '' && itm_repro_vulnerabilities_is_cli_php_binary(PHP_BINARY)) {
            return (string)PHP_BINARY;
        }

        return 'php';
    }
}

if (!function_exists('itm_repro_vulnerabilities_run_isolated')) {
    /**
     * @param array<string,mixed> $sessionData
     * @param array<string,mixed> $postData
     * @param array<string,mixed> $getData
     * @param array<string,mixed> $extraGlobals
     */
    function itm_repro_vulnerabilities_run_isolated(
        string $scriptPath,
        array $sessionData = [],
        array $postData = [],
        array $getData = [],
        array $extraGlobals = []
    ): string {
        $scriptPath = realpath($scriptPath);
        if ($scriptPath === false) {
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
            $postInit .= "\$_POST['" . addslashes((string)$key) . "'] = " . var_export($value, true) . ";\n";
        }

        $getInit = '';
        foreach ($getData as $key => $value) {
            $getInit .= "\$_GET['" . addslashes((string)$key) . "'] = " . var_export($value, true) . ";\n";
        }

        $globalsInit = '';
        foreach ($extraGlobals as $key => $value) {
            $globalsInit .= '$' . $key . ' = ' . var_export($value, true) . ";\n";
        }

        $code = "<?php
define('ITM_CLI_SCRIPT', true);
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=itmanagement');
putenv('DB_NAME=itmanagement');
function itm_validate_csrf_token(\$token) { return true; }
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
" . $sessionInit . "require '" . addslashes($configPath) . "';
" . $postInit . $getInit . $globalsInit . "
\$_SERVER['REQUEST_METHOD'] = !empty(\$_POST) ? 'POST' : 'GET';
\$_SERVER['SCRIPT_FILENAME'] = '" . addslashes($scriptPath) . "';
chdir(dirname('" . addslashes($scriptPath) . "'));
include basename('" . addslashes($scriptPath) . "');
?>";

        $tmpFile = tempnam(sys_get_temp_dir(), 'repro_vuln');
        if ($tmpFile === false) {
            return '';
        }
        file_put_contents($tmpFile, $code);

        if (!function_exists('itm_script_shell_stderr_discard')) {
            require_once __DIR__ . '/script_cli_output.php';
        }

        $phpBin = itm_repro_vulnerabilities_resolve_php_binary();
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

if (!function_exists('itm_repro_vulnerabilities_run_explorer_upload')) {
    /**
     * @param array<string,mixed> $sessionData
     */
    function itm_repro_vulnerabilities_run_explorer_upload(array $sessionData, string $tmpUploadPath, string $uploadName): string
    {
        $apiPath = realpath(ROOT_PATH . 'modules/explorer/api.php');
        $configPath = realpath(ROOT_PATH . 'config/config.php');
        if ($apiPath === false || $configPath === false || !is_file($tmpUploadPath)) {
            return '';
        }

        $sessionInit = '';
        foreach ($sessionData as $key => $value) {
            $sessionInit .= "\$_SESSION['" . addslashes((string)$key) . "'] = " . var_export($value, true) . ";\n";
        }

        $code = "<?php
define('ITM_CLI_SCRIPT', true);
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=root');
putenv('DB_PASS=itmanagement');
putenv('DB_NAME=itmanagement');
function itm_validate_csrf_token(\$token) { return true; }
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
" . $sessionInit . "require '" . addslashes($configPath) . "';
\$_POST['action'] = 'upload';
\$_POST['path'] = 'Common';
\$_POST['csrf_token'] = 'test-token';
\$_FILES['files'] = [
    'name' => ['" . addslashes($uploadName) . "'],
    'type' => ['application/x-php'],
    'tmp_name' => ['" . addslashes(str_replace('\\', '/', $tmpUploadPath)) . "'],
    'error' => [0],
    'size' => [" . (int)filesize($tmpUploadPath) . "],
];
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['SCRIPT_FILENAME'] = '" . addslashes($apiPath) . "';
chdir(dirname('" . addslashes($apiPath) . "'));
include basename('" . addslashes($apiPath) . "');
?>";

        $tmpFile = tempnam(sys_get_temp_dir(), 'repro_vuln_exp');
        if ($tmpFile === false) {
            return '';
        }
        file_put_contents($tmpFile, $code);

        if (!function_exists('itm_script_shell_stderr_discard')) {
            require_once __DIR__ . '/script_cli_output.php';
        }

        $phpBin = itm_repro_vulnerabilities_resolve_php_binary();
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
