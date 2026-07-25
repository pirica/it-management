<?php
/**
 * IDFs API position_delete BAC repro helpers.
 * Why: idf_positions.device_type is NOT NULL; scripts must seed tenant device types before inserting positions.
 */

if (!function_exists('itm_repro_idfs_is_cli_php_binary')) {
    function itm_repro_idfs_is_cli_php_binary($path): bool
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

if (!function_exists('itm_repro_idfs_resolve_php_binary')) {
    function itm_repro_idfs_resolve_php_binary(): string
    {
        $laragonPhp = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
        if (is_file($laragonPhp)) {
            return $laragonPhp;
        }
        if (defined('PHP_BINARY') && PHP_BINARY !== '' && itm_repro_idfs_is_cli_php_binary(PHP_BINARY)) {
            return (string)PHP_BINARY;
        }

        return 'php';
    }
}

if (!function_exists('itm_repro_idfs_ensure_switch_device_type_id')) {
    function itm_repro_idfs_ensure_switch_device_type_id(mysqli $conn, int $companyId): int
    {
        if ($companyId <= 0) {
            return 0;
        }

        $lookupName = 'switch';
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM idf_device_type WHERE company_id = ? AND LOWER(idfdevicetype_name) = LOWER(?) ORDER BY id ASC LIMIT 1'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $lookupName);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                return (int)$row['id'];
            }
        }

        $emoji = '🔀';
        $insert = mysqli_prepare(
            $conn,
            'INSERT INTO idf_device_type (company_id, idfdevicetype_name, field_edit_emoji, active) VALUES (?, ?, ?, 1)'
        );
        if (!$insert) {
            return 0;
        }
        mysqli_stmt_bind_param($insert, 'iss', $companyId, $lookupName, $emoji);
        if (!mysqli_stmt_execute($insert)) {
            mysqli_stmt_close($insert);
            return 0;
        }
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($insert);

        return $newId;
    }
}

if (!function_exists('itm_repro_idfs_seed_test_position')) {
    /**
     * @return array{idf_id:int,position_id:int,device_type_id:int}|null
     */
    function itm_repro_idfs_seed_test_position(mysqli $conn, int $companyId, string $labelSuffix): ?array
    {
        if ($companyId <= 0) {
            return null;
        }

        $deviceTypeId = itm_repro_idfs_ensure_switch_device_type_id($conn, $companyId);
        if ($deviceTypeId <= 0) {
            return null;
        }

        $idfName = 'BAC Test IDF ' . $labelSuffix;
        $stmtIdf = mysqli_prepare($conn, 'INSERT INTO idfs (company_id, name, active) VALUES (?, ?, 1)');
        if (!$stmtIdf) {
            return null;
        }
        mysqli_stmt_bind_param($stmtIdf, 'is', $companyId, $idfName);
        if (!mysqli_stmt_execute($stmtIdf)) {
            mysqli_stmt_close($stmtIdf);
            return null;
        }
        $idfId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmtIdf);
        if ($idfId <= 0) {
            return null;
        }

        $positionNo = 1;
        $deviceName = 'BAC Test Device ' . $labelSuffix;
        $stmtPos = mysqli_prepare(
            $conn,
            'INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        if (!$stmtPos) {
            mysqli_query($conn, 'DELETE FROM idfs WHERE id = ' . (int)$idfId . ' AND company_id = ' . (int)$companyId);
            return null;
        }
        mysqli_stmt_bind_param($stmtPos, 'iiiis', $companyId, $idfId, $positionNo, $deviceTypeId, $deviceName);
        if (!mysqli_stmt_execute($stmtPos)) {
            mysqli_stmt_close($stmtPos);
            mysqli_query($conn, 'DELETE FROM idfs WHERE id = ' . (int)$idfId . ' AND company_id = ' . (int)$companyId);
            return null;
        }
        $positionId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmtPos);
        if ($positionId <= 0) {
            mysqli_query($conn, 'DELETE FROM idfs WHERE id = ' . (int)$idfId . ' AND company_id = ' . (int)$companyId);
            return null;
        }

        return [
            'idf_id' => $idfId,
            'position_id' => $positionId,
            'device_type_id' => $deviceTypeId,
        ];
    }
}

if (!function_exists('itm_repro_idfs_cleanup_idf')) {
    function itm_repro_idfs_cleanup_idf(mysqli $conn, int $companyId, int $idfId): void
    {
        if ($companyId <= 0 || $idfId <= 0) {
            return;
        }
        $stmt = mysqli_prepare($conn, 'DELETE FROM idfs WHERE id = ? AND company_id = ?');
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $idfId, $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('itm_repro_idfs_run_position_delete_subprocess')) {
    /**
     * @param array<string,mixed> $sessionData
     */
    function itm_repro_idfs_run_position_delete_subprocess(array $sessionData, int $positionId): string
    {
        $apiPath = realpath(ROOT_PATH . 'modules/idfs/api/position_delete.php');
        $configPath = realpath(ROOT_PATH . 'config/config.php');
        if ($apiPath === false || $configPath === false || $positionId <= 0) {
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
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
" . $sessionInit . "
function idf_read_json(): array {
    return [
        'csrf_token' => itm_get_csrf_token(),
        'position_id' => " . (int)$positionId . ",
    ];
}
require '" . addslashes($configPath) . "';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['SCRIPT_FILENAME'] = '" . addslashes($apiPath) . "';
chdir(dirname('" . addslashes($apiPath) . "'));
include basename('" . addslashes($apiPath) . "');
?>";

        $tmpFile = tempnam(sys_get_temp_dir(), 'repro_idfs_bac');
        if ($tmpFile === false) {
            return '';
        }
        file_put_contents($tmpFile, $code);

        if (!function_exists('itm_script_shell_stderr_discard')) {
            require_once __DIR__ . '/script_cli_output.php';
        }

        $phpBin = itm_repro_idfs_resolve_php_binary();
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

if (!function_exists('itm_repro_idfs_parse_api_json')) {
    /**
     * @return array<string,mixed>|null
     */
    function itm_repro_idfs_parse_api_json(string $output): ?array
    {
        if (stripos($output, 'Status: 302') !== false || stripos($output, 'Forbidden:') !== false) {
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

if (!function_exists('itm_repro_idfs_position_exists')) {
    function itm_repro_idfs_position_exists(mysqli $conn, int $companyId, int $positionId): bool
    {
        if ($companyId <= 0 || $positionId <= 0) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT p.id
             FROM idf_positions p
             JOIN idfs i ON i.id = p.idf_id
             WHERE p.id = ? AND i.company_id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $positionId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $exists = $res && mysqli_num_rows($res) > 0;
        mysqli_stmt_close($stmt);

        return $exists;
    }
}
