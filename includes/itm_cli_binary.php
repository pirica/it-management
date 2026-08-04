<?php
/**
 * Resolve PHP/MySQL CLI binaries for subprocess scripts (prefers .env PHP_EXE / MYSQL_EXE).
 */

if (!function_exists('itm_default_php_cli_binary')) {
    function itm_default_php_cli_binary(): string
    {
        return 'D:\\dunebox-v1.0.6\\system\\apps\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
    }
}

if (!function_exists('itm_default_mysql_cli_binary')) {
    function itm_default_mysql_cli_binary(): string
    {
        return 'D:\\dunebox-v1.0.6\\system\\apps\\mysql\\mysql-8.0.45-winx64\\bin\\mysql.exe';
    }
}

if (!function_exists('itm_is_cli_php_binary_path')) {
    function itm_is_cli_php_binary_path($path): bool
    {
        $normalized = strtolower(str_replace('\\', '/', (string) $path));
        if ($normalized === '' || !is_file($path)) {
            return false;
        }
        if (strpos($normalized, 'php-cgi') !== false) {
            return false;
        }
        // Why: Dunebox php74.cmd loads config/php/php-7.4.ini; subprocess must target php.exe (or a real binary).
        if (preg_match('~\.(cmd|bat)$~', $normalized)) {
            return false;
        }
        if (substr($normalized, -4) === '.dll') {
            return false;
        }

        return true;
    }
}

if (!function_exists('itm_resolve_cli_php_binary')) {
    function itm_resolve_cli_php_binary(): string
    {
        foreach (['PHP_EXE', 'PHP_BIN', 'ITM_PHP_BIN'] as $envKey) {
            $candidate = getenv($envKey);
            if (is_string($candidate) && $candidate !== '' && itm_is_cli_php_binary_path($candidate)) {
                return $candidate;
            }
        }

        $default = itm_default_php_cli_binary();
        if (is_file($default)) {
            return $default;
        }

        if (defined('PHP_BINARY') && PHP_BINARY !== '' && itm_is_cli_php_binary_path(PHP_BINARY)) {
            return (string) PHP_BINARY;
        }

        return 'php';
    }
}

if (!function_exists('itm_resolve_cli_mysql_binary')) {
    function itm_resolve_cli_mysql_binary(): string
    {
        foreach (['MYSQL_EXE'] as $envKey) {
            $candidate = getenv($envKey);
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        $default = itm_default_mysql_cli_binary();
        if (is_file($default)) {
            return $default;
        }

        return 'mysql';
    }
}

if (!function_exists('itm_phpunit_required_extensions')) {
    /**
     * @return list<string>
     */
    function itm_phpunit_required_extensions(): array
    {
        return ['dom', 'json', 'libxml', 'mbstring', 'tokenizer', 'xml', 'xmlwriter'];
    }
}

if (!function_exists('itm_cli_php_binary_missing_extensions')) {
    /**
     * @param list<string> $extensions
     * @return list<string>
     */
    function itm_cli_php_binary_missing_extensions(string $phpBin, array $extensions): array
    {
        if (!itm_is_cli_php_binary_path($phpBin)) {
            return $extensions;
        }

        $checks = [];
        foreach ($extensions as $ext) {
            $ext = (string) $ext;
            if ($ext === '') {
                continue;
            }
            $checks[] = "extension_loaded('" . str_replace("'", "\\'", $ext) . "')";
        }
        if ($checks === []) {
            return [];
        }

        $code = 'exit((' . implode('&&', $checks) . ')?0:1);';
        $cmd = escapeshellarg($phpBin) . ' -r ' . escapeshellarg($code);
        exec($cmd, $out, $exitCode);
        if ($exitCode === 0) {
            return [];
        }

        $missing = [];
        foreach ($extensions as $ext) {
            $ext = (string) $ext;
            if ($ext === '') {
                continue;
            }
            $probe = escapeshellarg($phpBin) . ' -r ' . escapeshellarg(
                'exit(extension_loaded(' . var_export($ext, true) . ')?0:1);'
            );
            exec($probe, $probeOut, $probeCode);
            if ($probeCode !== 0) {
                $missing[] = $ext;
            }
        }

        return $missing;
    }
}

if (!function_exists('itm_laragon_portable_php_cli_candidate')) {
    function itm_laragon_portable_php_cli_candidate(): string
    {
        if (!defined('ROOT_PATH')) {
            return '';
        }
        $portableRoot = realpath(dirname(dirname(rtrim(ROOT_PATH, '/\\'))));
        if ($portableRoot === false) {
            return '';
        }
        $candidate = $portableRoot . DIRECTORY_SEPARATOR . 'bin'
            . DIRECTORY_SEPARATOR . 'php'
            . DIRECTORY_SEPARATOR . 'php-7.4.33-nts-Win32-vc15-x64'
            . DIRECTORY_SEPARATOR . 'php.exe';

        return is_file($candidate) ? $candidate : '';
    }
}

if (!function_exists('itm_phpunit_cli_binary_candidates')) {
    /**
     * @return list<string>
     */
    function itm_phpunit_cli_binary_candidates(): array
    {
        $seen = [];
        $list = [];
        $push = static function ($path) use (&$seen, &$list) {
            $path = (string) $path;
            if ($path === '' || !itm_is_cli_php_binary_path($path)) {
                return;
            }
            $key = strtolower(str_replace('\\', '/', $path));
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $list[] = $path;
        };

        // Why: Prefer Dunebox/Laragon php.exe before .env shims (php74.cmd is not a subprocess binary).
        $push(itm_default_php_cli_binary());
        $push(itm_laragon_portable_php_cli_candidate());

        foreach (['PHP_EXE', 'PHP_BIN', 'ITM_PHP_BIN'] as $envKey) {
            $candidate = getenv($envKey);
            if (is_string($candidate)) {
                $push($candidate);
            }
        }

        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            $push((string) PHP_BINARY);
        }

        $push('php');

        return $list;
    }
}

if (!function_exists('itm_resolve_phpunit_cli_binary')) {
    function itm_resolve_phpunit_cli_binary(bool $requireCoverageDriver = false): string
    {
        $required = itm_phpunit_required_extensions();
        $fallback = '';
        foreach (itm_phpunit_cli_binary_candidates() as $candidate) {
            if (itm_cli_php_binary_missing_extensions($candidate, $required) !== []) {
                continue;
            }
            if (!$requireCoverageDriver) {
                return $candidate;
            }
            if (itm_cli_php_binary_has_coverage_driver($candidate)) {
                return $candidate;
            }
            if ($fallback === '') {
                $fallback = $candidate;
            }
        }

        if ($fallback !== '') {
            return $fallback;
        }

        return itm_resolve_cli_php_binary();
    }
}

if (!function_exists('itm_cli_php_binary_has_coverage_driver')) {
    function itm_cli_php_binary_has_coverage_driver(string $phpBin): bool
    {
        if (!itm_is_cli_php_binary_path($phpBin)) {
            return false;
        }
        foreach (['xdebug', 'pcov'] as $ext) {
            $cmd = escapeshellarg($phpBin) . ' -r ' . escapeshellarg(
                'exit(extension_loaded(' . var_export($ext, true) . ')?0:1);'
            );
            exec($cmd, $out, $code);
            if ($code === 0) {
                return true;
            }
        }

        return false;
    }
}
