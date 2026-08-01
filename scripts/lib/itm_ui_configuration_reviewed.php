<?php
/**
 * Reviewed gate-excluded UI configuration coverage exceptions.
 *
 * Why: check_ui_configuration_coverage.php still runs every check on gate-excluded
 * modules; this registry marks intentional [n/a][pass|fail|n/a] lines as reviewed.
 */

if (!function_exists('itm_ui_configuration_reviewed_registry_path')) {
    function itm_ui_configuration_reviewed_registry_path(): string
    {
        return dirname(__DIR__) . '/data/ui_configuration_reviewed.json';
    }
}

if (!function_exists('itm_ui_configuration_load_reviewed_registry')) {
    /**
     * @return array{version?:int,description?:string,modules?:array<string,array<string,mixed>>}
     */
    function itm_ui_configuration_load_reviewed_registry(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $path = itm_ui_configuration_reviewed_registry_path();
        if (!is_readable($path)) {
            $cached = ['version' => 1, 'description' => '', 'modules' => []];
            return $cached;
        }

        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            $cached = ['version' => 1, 'description' => '', 'modules' => []];
            return $cached;
        }

        if (!isset($decoded['modules']) || !is_array($decoded['modules'])) {
            $decoded['modules'] = [];
        }

        $cached = $decoded;
        return $cached;
    }
}

if (!function_exists('itm_ui_configuration_validate_reviewed_registry')) {
    /**
     * @param array<string,mixed> $registry
     * @return array{ok:bool,errors:list<string>}
     */
    function itm_ui_configuration_validate_reviewed_registry(array $registry): array
    {
        $errors = [];
        $modules = $registry['modules'] ?? null;
        if (!is_array($modules)) {
            $errors[] = 'modules must be an object map keyed by module slug';
            return ['ok' => false, 'errors' => $errors];
        }

        foreach ($modules as $moduleSlug => $moduleEntry) {
            $slug = trim((string) $moduleSlug);
            if ($slug === '') {
                $errors[] = 'modules contains an empty slug key';
                continue;
            }
            if (!is_array($moduleEntry)) {
                $errors[] = $slug . ': module entry must be an object';
                continue;
            }
            $checks = $moduleEntry['checks'] ?? null;
            if (!is_array($checks) || $checks === []) {
                $errors[] = $slug . ': checks must be a non-empty array';
                continue;
            }
            foreach ($checks as $idx => $checkEntry) {
                if (!is_array($checkEntry)) {
                    $errors[] = $slug . ': checks[' . $idx . '] must be an object';
                    continue;
                }
                $label = trim((string) ($checkEntry['check'] ?? ''));
                $code = trim((string) ($checkEntry['code'] ?? ''));
                if ($label === '' && $code === '') {
                    $errors[] = $slug . ': checks[' . $idx . '] requires check and/or code';
                }
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }
}

if (!function_exists('itm_ui_configuration_reviewed_registry_key_matches_module')) {
    /**
     * Registry keys may be an exact module slug or a prefix wildcard (e.g. is_*).
     */
    function itm_ui_configuration_reviewed_registry_key_matches_module(string $registryKey, string $moduleSlug): bool
    {
        $registryKey = trim($registryKey);
        $moduleSlug = trim($moduleSlug);
        if ($registryKey === '' || $moduleSlug === '') {
            return false;
        }

        if (substr($registryKey, -1) === '*') {
            $prefix = substr($registryKey, 0, -1);

            return $prefix !== '' && strpos($moduleSlug, $prefix) === 0;
        }

        return strcasecmp($registryKey, $moduleSlug) === 0;
    }
}

if (!function_exists('itm_ui_configuration_check_is_reviewed')) {
    /**
     * @param array<string,mixed>|null $registry
     */
    function itm_ui_configuration_check_is_reviewed(string $moduleSlug, string $checkName, ?array $registry = null): bool
    {
        $registry = $registry ?? itm_ui_configuration_load_reviewed_registry();
        $checkName = trim($checkName);
        if ($checkName === '') {
            return false;
        }

        foreach ($registry['modules'] ?? [] as $registryKey => $moduleEntry) {
            if (!is_string($registryKey) || !itm_ui_configuration_reviewed_registry_key_matches_module($registryKey, $moduleSlug)) {
                continue;
            }
            if (!is_array($moduleEntry)) {
                continue;
            }

            $checks = $moduleEntry['checks'] ?? [];
            if (!is_array($checks) || $checks === []) {
                continue;
            }

            foreach ($checks as $checkEntry) {
                if (!is_array($checkEntry)) {
                    continue;
                }
                $registryLabel = trim((string) ($checkEntry['check'] ?? ''));
                $registryCode = trim((string) ($checkEntry['code'] ?? ''));
                if ($registryLabel !== '' && strcasecmp($registryLabel, $checkName) === 0) {
                    return true;
                }
                if ($registryCode !== '' && strcasecmp($registryCode, $checkName) === 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
