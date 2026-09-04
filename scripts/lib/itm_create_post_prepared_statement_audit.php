<?php
/**
 * Static audit: modules create.php POST save SQL style.
 *
 * Why: escape_sql() on POST paths and flattened CRUD $sqlValues string INSERT/UPDATE
 * bypass the prepared-statement contract; this audit inventories them separately from
 * check_sql_injection_coverage.php (which treats mysqli_real_escape_string as safe).
 */

declare(strict_types=1);

if (!function_exists('itm_create_post_prepared_audit_has_escape_sql_call')) {
    function itm_create_post_prepared_audit_has_escape_sql_call(string $content): bool
    {
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '') {
                continue;
            }
            if ($trimmed[0] === '#' || strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0) {
                continue;
            }
            if (preg_match('/\bescape_sql\s*\(/i', $line) === 1) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_create_post_prepared_audit_is_wrapper')) {
    /**
     * Thin create.php that delegates to index.php (no local POST INSERT/UPDATE save).
     */
    function itm_create_post_prepared_audit_is_wrapper(string $content): bool
    {
        if (preg_match('/\brequire(?:_once)?\s+[\'"][^\'"]*index\.php[\'"]/i', $content) !== 1) {
            return false;
        }

        if (strpos($content, '$sqlValues') !== false) {
            return false;
        }

        if (preg_match('/\bmysqli_prepare\s*\(/i', $content) === 1) {
            return false;
        }

        if (itm_create_post_prepared_audit_has_post_insert_or_update($content)) {
            return false;
        }

        return true;
    }
}

if (!function_exists('itm_create_post_prepared_audit_has_post_insert_or_update')) {
    function itm_create_post_prepared_audit_has_post_insert_or_update(string $content): bool
    {
        if (preg_match('/\$_SERVER\s*\[\s*[\'"]REQUEST_METHOD[\'"]\s*\]\s*===\s*[\'"]POST[\'"]/i', $content) !== 1
            && strpos($content, '$_POST') === false
        ) {
            return false;
        }

        return preg_match('/\bINSERT\s+INTO\b/i', $content) === 1
            || preg_match('/\bUPDATE\s+[`a-zA-Z0-9_]+\s+SET\b/i', $content) === 1;
    }
}

if (!function_exists('itm_create_post_prepared_audit_classify')) {
    /**
     * @return array{status:string,reason:string}|null null when file should be omitted (wrapper)
     */
    function itm_create_post_prepared_audit_classify(string $relativePath, string $content): ?array
    {
        if (itm_create_post_prepared_audit_has_escape_sql_call($content)) {
            return [
                'status' => 'escape_sql',
                'reason' => 'escape_sql() still used on create save path — convert to mysqli_prepare + bind_param',
            ];
        }

        if (itm_create_post_prepared_audit_is_wrapper($content)) {
            return [
                'status' => 'wrapper',
                'reason' => 'delegates to index.php — audit index.php for POST save SQL separately',
            ];
        }

        $hasPrepare = preg_match('/\bmysqli_prepare\s*\(/i', $content) === 1;
        $hasScaffold = strpos($content, '$sqlValues') !== false
            || preg_match('/\$sqlValues\s*\[\s*\$name\s*\]/', $content) === 1;

        if ($hasScaffold) {
            return [
                'status' => 'scaffold_string_sql',
                'reason' => 'flattened CRUD $sqlValues + concatenated INSERT/UPDATE (mysqli_real_escape_string, not prepared)',
            ];
        }

        if ($hasPrepare && itm_create_post_prepared_audit_has_post_insert_or_update($content)) {
            return [
                'status' => 'prepared',
                'reason' => 'POST create/edit save uses mysqli_prepare',
            ];
        }

        if (itm_create_post_prepared_audit_has_post_insert_or_update($content)
            && preg_match('/\b(mysqli_query|itm_run_query)\s*\(/i', $content) === 1
        ) {
            return [
                'status' => 'bespoke_string_sql',
                'reason' => 'bespoke POST INSERT/UPDATE via mysqli_query/itm_run_query without mysqli_prepare',
            ];
        }

        if (!$hasPrepare && itm_create_post_prepared_audit_has_post_insert_or_update($content)) {
            return [
                'status' => 'bespoke_string_sql',
                'reason' => 'POST INSERT/UPDATE present without mysqli_prepare in this file',
            ];
        }

        return [
            'status' => 'no_local_post_save',
            'reason' => 'no local POST INSERT/UPDATE save detected in create.php',
        ];
    }
}

if (!function_exists('itm_create_post_prepared_audit_scan')) {
    /**
     * @return array{
     *   scanned:int,
     *   by_status:array<string,list<array{path:string,reason:string}>>,
     *   findings:list<array{path:string,status:string,reason:string}>
     * }
     */
    function itm_create_post_prepared_audit_scan(string $root, ?string $moduleSlug = null): array
    {
        $pattern = $root . '/modules/*/create.php';
        if ($moduleSlug !== null && $moduleSlug !== '') {
            $safeSlug = preg_replace('/[^a-z0-9_]/i', '', $moduleSlug);
            $pattern = $root . '/modules/' . $safeSlug . '/create.php';
        }

        $paths = glob($pattern) ?: [];
        sort($paths, SORT_STRING);

        $byStatus = [
            'escape_sql' => [],
            'scaffold_string_sql' => [],
            'bespoke_string_sql' => [],
            'prepared' => [],
            'wrapper' => [],
            'no_local_post_save' => [],
        ];
        $findings = [];

        foreach ($paths as $path) {
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }

            $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
            $classification = itm_create_post_prepared_audit_classify($relative, $content);
            if ($classification === null) {
                continue;
            }

            $row = [
                'path' => str_replace('\\', '/', $relative),
                'reason' => $classification['reason'],
            ];
            $status = $classification['status'];
            $byStatus[$status][] = $row;
            if (in_array($status, ['escape_sql', 'scaffold_string_sql', 'bespoke_string_sql'], true)) {
                $findings[] = [
                    'path' => $row['path'],
                    'status' => $status,
                    'reason' => $classification['reason'],
                ];
            }
        }

        return [
            'scanned' => count($paths),
            'by_status' => $byStatus,
            'findings' => $findings,
        ];
    }
}
