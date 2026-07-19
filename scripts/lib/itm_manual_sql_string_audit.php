<?php
/**
 * Static audit helpers for manually-constructed SQL strings in PHP.
 *
 * Why: Codacy and similar tools flag URL query builders (http_build_query) as SQL;
 * this audit targets real SQL built via concatenation/interpolation instead of
 * prepared statements with bound parameters.
 */

declare(strict_types=1);

if (!function_exists('itm_manual_sql_string_sql_keyword_pattern')) {
    function itm_manual_sql_string_sql_keyword_pattern(): string
    {
        return '/\b(SELECT|INSERT|UPDATE|DELETE|REPLACE|FROM|WHERE|JOIN|INTO|VALUES|SET|ORDER\s+BY|GROUP\s+BY|HAVING|LIMIT|DESCRIBE|SHOW|ALTER|CREATE|DROP|TRUNCATE|UNION)\b/i';
    }
}

if (!function_exists('itm_manual_sql_string_line_exempt')) {
    function itm_manual_sql_string_line_exempt(string $line): bool
    {
        if (strpos($line, 'itm-manual-sql-exempt:') !== false) {
            return true;
        }

        $trimmed = ltrim($line);
        if ($trimmed === '') {
            return true;
        }

        if ($trimmed[0] === '#' || $trimmed[0] === '*') {
            return true;
        }

        if (strpos($trimmed, '//') === 0) {
            return true;
        }

        // URL query builders — not SQL (Codacy false-positive family).
        if (preg_match('/http_build_query\s*\(/i', $line) === 1
            && preg_match(itm_manual_sql_string_sql_keyword_pattern(), $line) !== 1
        ) {
            return true;
        }

        if (preg_match('/\.php\?\s*[\'"]/i', $line) === 1
            || preg_match('/[\'"][^\'"]*\.php\?/i', $line) === 1
        ) {
            return true;
        }

        if (preg_match('/htmlspecialchars\s*\(\s*[\'"][^\'"]*\.php\?/i', $line) === 1) {
            return true;
        }

        // Prepared statements — placeholders are bound separately.
        if (preg_match('/\bmysqli_prepare\s*\(/i', $line) === 1) {
            return true;
        }

        if (preg_match('/\bmysqli_stmt_bind_param\s*\(/i', $line) === 1) {
            return true;
        }

        // Pass-through execution of a prepared/built statement variable.
        if (preg_match('/\bitm_run_query\s*\(\s*\$conn\s*,\s*\$(sql|statement)\s*\)/i', $line) === 1) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_manual_sql_string_line_has_safe_concat_context')) {
    function itm_manual_sql_string_line_has_safe_concat_context(string $line): bool
    {
        if (preg_match('/\bcr_escape_identifier\s*\(/i', $line) === 1
            && preg_match('/\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES)\b/i', $line) !== 1
        ) {
            return true;
        }

        if (preg_match('/\b(itm_is_safe_identifier|so_escape_identifier)\s*\(/i', $line) === 1
            && preg_match('/\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES)\b/i', $line) !== 1
        ) {
            return true;
        }

        // mysqli_prepare string with only ? placeholders (no inline variables).
        if (preg_match('/\bmysqli_prepare\s*\([^,]+,\s*["\'][^"\']*\?[^"\']*["\']\s*\)/i', $line) === 1
            && preg_match('/\$[a-zA-Z_]/', $line) !== 1
        ) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_manual_sql_string_line_has_user_input')) {
    function itm_manual_sql_string_line_has_user_input(string $line): bool
    {
        return preg_match('/\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES)\b|php:\/\/input/i', $line) === 1;
    }
}

if (!function_exists('itm_manual_sql_string_line_has_manual_sql')) {
    function itm_manual_sql_string_line_has_manual_sql(string $line): bool
    {
        if (preg_match(itm_manual_sql_string_sql_keyword_pattern(), $line) !== 1) {
            return false;
        }

        if (preg_match('/["\'][^"\']*\b(SELECT|INSERT|UPDATE|DELETE|REPLACE|DESCRIBE|SHOW|ALTER|CREATE|DROP|TRUNCATE)\b[^"\']*["\']\s*\./i', $line) === 1) {
            return true;
        }

        if (preg_match('/\.\s*["\'][^"\']*\b(FROM|WHERE|JOIN|INTO|VALUES|SET|ORDER\s+BY|GROUP\s+BY|HAVING|LIMIT)\b/i', $line) === 1) {
            return true;
        }

        if (preg_match('/["\'][^"\']*\$[a-zA-Z_][a-zA-Z0-9_]*/', $line) === 1
            && preg_match(itm_manual_sql_string_sql_keyword_pattern(), $line) === 1
        ) {
            return true;
        }

        if (preg_match('/\{(?:\$|\$[a-zA-Z_][a-zA-Z0-9_]*)\}/', $line) === 1
            && preg_match(itm_manual_sql_string_sql_keyword_pattern(), $line) === 1
        ) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_manual_sql_string_audit_line')) {
    /**
     * @return array<int, array{rule:string,message:string}>
     */
    function itm_manual_sql_string_audit_line(string $line): array
    {
        if (itm_manual_sql_string_line_exempt($line)) {
            return [];
        }

        if (itm_manual_sql_string_line_has_safe_concat_context($line)) {
            return [];
        }

        $violations = [];

        if (itm_manual_sql_string_line_has_user_input($line)
            && (
                preg_match('/\b(mysqli_query|itm_run_query)\s*\(/i', $line) === 1
                || itm_manual_sql_string_line_has_manual_sql($line)
            )
        ) {
            $violations[] = [
                'rule' => 'sql_user_input_concat',
                'message' => 'User input flows into manually-constructed SQL — use mysqli_prepare + bind_param',
            ];
        } elseif (itm_manual_sql_string_line_has_manual_sql($line)) {
            $violations[] = [
                'rule' => 'sql_string_concat',
                'message' => 'SQL built via string concatenation/interpolation — prefer prepared statements',
            ];
        } elseif (preg_match('/\b(mysqli_query|itm_run_query)\s*\(\s*\$conn\s*,\s*["\'][^"\']*\b(SELECT|INSERT|UPDATE|DELETE|REPLACE|DESCRIBE)\b/i', $line) === 1
            && preg_match('/\.\s*\$/', $line) === 1
        ) {
            $violations[] = [
                'rule' => 'mysqli_query_manual_concat',
                'message' => 'mysqli_query/itm_run_query with inline SQL concatenation — use mysqli_prepare',
            ];
        }

        return $violations;
    }
}

if (!function_exists('itm_manual_sql_string_collect_violations')) {
    /**
     * @param array<int, string> $scanRelativeDirs
     * @return array<int, string>
     */
    function itm_manual_sql_string_collect_violations(string $repoRoot, array $scanRelativeDirs = ['modules']): array
    {
        $violations = [];
        $root = rtrim($repoRoot, '/\\');

        foreach ($scanRelativeDirs as $relativeDir) {
            $base = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
            if (!is_dir($base)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
                    continue;
                }

                $path = $fileInfo->getPathname();
                $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
                $rel = str_replace('\\', '/', $rel);
                $content = file_get_contents($path);
                if ($content === false) {
                    continue;
                }

                $lines = preg_split('/\R/u', $content) ?: [];
                foreach ($lines as $lineNum => $line) {
                    foreach (itm_manual_sql_string_audit_line($line) as $hit) {
                        $violations[] = $rel . ':' . ($lineNum + 1) . ' [' . $hit['rule'] . '] ' . $hit['message'];
                    }
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
