<?php
/**
 * Isolated GET probe for modules/explorer/file.php (profile ACL regressions).
 */

if (!function_exists('itm_verify_explorer_file_probe_run')) {
    /**
     * @return string stdout from isolated file.php include
     */
    function itm_verify_explorer_file_probe_run(string $scriptPath, array $sessionData, array $getData = []): string
    {
        if (!function_exists('shell_exec')) {
            return '';
        }

        $scriptPath = str_replace('\\', '/', $scriptPath);
        $configPath = str_replace('\\', '/', realpath(dirname(__DIR__, 2) . '/config/config.php') ?: '');
        if ($configPath === '' || !is_file($scriptPath)) {
            return '';
        }

        if (!function_exists('itm_resolve_cli_php_binary')) {
            require_once dirname(__DIR__) . '/../includes/itm_cli_binary.php';
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'explorer_file_probe');
        if ($tmpFile === false) {
            return '';
        }

        $repoRoot = str_replace('\\', '/', realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2));
        $documentRoot = str_replace('\\', '/', dirname($repoRoot));
        $scriptName = '/it-management/modules/explorer/file.php';

        $code = '<?php
define(\'ITM_CLI_SCRIPT\', true);
$_SERVER[\'REQUEST_METHOD\'] = \'GET\';
$_SERVER[\'REMOTE_ADDR\'] = \'127.0.0.1\';
$_SERVER[\'HTTP_HOST\'] = \'localhost\';
$_SERVER[\'SCRIPT_NAME\'] = ' . var_export($scriptName, true) . ';
$_SERVER[\'PHP_SELF\'] = ' . var_export($scriptName, true) . ';
$_SERVER[\'SCRIPT_FILENAME\'] = ' . var_export($scriptPath, true) . ';
$_SERVER[\'DOCUMENT_ROOT\'] = ' . var_export($documentRoot, true) . ';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = unserialize(' . var_export(serialize($sessionData), true) . ');
$_GET = ' . var_export($getData, true) . ';
require ' . var_export($configPath, true) . ';
chdir(dirname(' . var_export($scriptPath, true) . '));
ob_start();
include basename(' . var_export($scriptPath, true) . ');
echo ob_get_clean();
';

        file_put_contents($tmpFile, $code);
        $phpBin = itm_resolve_cli_php_binary();
        $phpIni = '';
        $mysqliSocket = ini_get('mysqli.default_socket');
        if (is_string($mysqliSocket) && $mysqliSocket !== '') {
            $phpIni = ' -d mysqli.default_socket=' . escapeshellarg($mysqliSocket);
        }
        $output = shell_exec(escapeshellarg($phpBin) . $phpIni . ' ' . escapeshellarg($tmpFile) . ' 2>&1');
        @unlink($tmpFile);

        return is_string($output) ? $output : '';
    }
}
