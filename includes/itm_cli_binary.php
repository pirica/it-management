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
