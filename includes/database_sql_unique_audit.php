<?php
/**
 * Parse database.sql CREATE TABLE blocks and audit tenant unique-key policy.
 *
 * Why: Tenant tables should define exactly two uniques — PRIMARY KEY (`id`) plus one
 * business unique led by `company_id` and either `name` or the 3rd column (e.g. annual_budget_id).
 */

if (!function_exists('itm_database_sql_unique_audit_parse_column_defs')) {
    /**
     * @return array<int, string>
     */
    function itm_database_sql_unique_audit_parse_column_defs(string $body): array
    {
        $columns = [];
        foreach (preg_split('/\R/', $body) as $line) {
            if (!preg_match('/^\s*`([a-zA-Z0-9_]+)`\s+([a-z])/i', $line, $colMatch)) {
                continue;
            }
            $columns[] = (string) $colMatch[1];
        }

        return $columns;
    }
}

if (!function_exists('itm_database_sql_unique_audit_resolve_scope_column')) {
    /**
     * @param array<int, string> $columns
     */
    function itm_database_sql_unique_audit_resolve_scope_column(array $columns): string
    {
        if (in_array('name', $columns, true)) {
            return 'name';
        }

        if (count($columns) >= 3) {
            return $columns[2];
        }

        return '';
    }
}

if (!function_exists('itm_database_sql_unique_audit_parse')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_database_sql_unique_audit_parse(string $sqlPath): array
    {
        if (!is_readable($sqlPath)) {
            return [];
        }

        $sql = (string) file_get_contents($sqlPath);
        if (!preg_match_all(
            '/CREATE\s+TABLE\s+`([a-zA-Z0-9_]+)`\s*\((.*?)\)\s*ENGINE/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        $tables = [];
        foreach ($matches as $match) {
            $table = (string) $match[1];
            if (!itm_is_safe_identifier($table)) {
                continue;
            }

            $body = (string) $match[2];
            $columnDefs = itm_database_sql_unique_audit_parse_column_defs($body);
            $hasCompanyId = in_array('company_id', $columnDefs, true);
            $scopeColumn = $hasCompanyId ? itm_database_sql_unique_audit_resolve_scope_column($columnDefs) : '';

            $uniques = [];
            if (preg_match('/PRIMARY\s+KEY\s*\(([^)]+)\)/i', $body, $pkMatch)) {
                $uniques[] = [
                    'key' => 'PRIMARY',
                    'columns' => itm_database_sql_unique_audit_split_columns((string) $pkMatch[1]),
                ];
            }

            if (preg_match_all(
                '/UNIQUE\s+KEY\s+`([^`]+)`\s*\(([^)]+)\)/i',
                $body,
                $uniqueMatches,
                PREG_SET_ORDER
            )) {
                foreach ($uniqueMatches as $uniqueMatch) {
                    $uniques[] = [
                        'key' => (string) $uniqueMatch[1],
                        'columns' => itm_database_sql_unique_audit_split_columns((string) $uniqueMatch[2]),
                    ];
                }
            }

            $scopeUniqueKey = '';
            $scopeUniqueColumns = [];
            $hasScopeUnique = false;
            if ($scopeColumn !== '') {
                foreach ($uniques as $unique) {
                    if (itm_database_sql_unique_audit_unique_matches_scope($unique['columns'], $scopeColumn)) {
                        $hasScopeUnique = true;
                        $scopeUniqueKey = $unique['key'];
                        $scopeUniqueColumns = $unique['columns'];
                        break;
                    }
                }
            }

            $tables[] = [
                'table' => $table,
                'has_company_id' => $hasCompanyId,
                'scope_column' => $scopeColumn,
                'column_defs' => $columnDefs,
                'unique_count' => count($uniques),
                'has_scope_unique' => $hasScopeUnique,
                'scope_unique_key' => $scopeUniqueKey,
                'scope_unique_columns' => $scopeUniqueColumns,
                'uniques' => $uniques,
            ];
        }

        return $tables;
    }
}

if (!function_exists('itm_database_sql_unique_audit_split_columns')) {
    /**
     * @return array<int, string>
     */
    function itm_database_sql_unique_audit_split_columns(string $columnList): array
    {
        $parts = array_map('trim', explode(',', $columnList));
        $columns = [];
        foreach ($parts as $part) {
            $part = trim($part, " \t\n\r`");
            if ($part !== '') {
                $columns[] = $part;
            }
        }

        return $columns;
    }
}

if (!function_exists('itm_database_sql_unique_audit_unique_matches_scope')) {
    /**
     * UNIQUE must start with (`company_id`, scope_column); additional scope columns allowed.
     *
     * @param array<int, string> $columns
     */
    function itm_database_sql_unique_audit_unique_matches_scope(array $columns, string $scopeColumn): bool
    {
        if (count($columns) < 2) {
            return false;
        }

        return strtolower($columns[0]) === 'company_id'
            && strtolower($columns[1]) === strtolower($scopeColumn);
    }
}

if (!function_exists('itm_database_sql_unique_audit_run')) {
    /**
     * @return array{
     *     sql_path: string,
     *     required_unique_count: int,
     *     lines: array<int, array<string, mixed>>,
     *     summary: array{tables: int, pass: int, fail: int, skip: int}
     * }
     */
    function itm_database_sql_unique_audit_run(string $sqlPath, int $requiredUniqueCount = 2): array
    {
        $parsed = itm_database_sql_unique_audit_parse($sqlPath);
        $lines = [];
        $summary = [
            'tables' => count($parsed),
            'pass' => 0,
            'fail' => 0,
            'skip' => 0,
        ];

        foreach ($parsed as $tableRow) {
            $table = (string) $tableRow['table'];
            $scopeColumn = (string) $tableRow['scope_column'];

            if (!$tableRow['has_company_id']) {
                $summary['skip']++;
                $lines[] = [
                    'table' => $table,
                    'status' => 'skip',
                    'unique_count' => (int) $tableRow['unique_count'],
                    'scope_column' => '',
                    'scope_unique' => '',
                    'has_scope_unique' => false,
                    'message' => 'No company_id column (global / system table).',
                    'alter_sql' => '',
                ];
                continue;
            }

            if ($scopeColumn === '') {
                $summary['fail']++;
                $lines[] = [
                    'table' => $table,
                    'status' => 'fail',
                    'unique_count' => (int) $tableRow['unique_count'],
                    'scope_column' => '',
                    'scope_unique' => '',
                    'has_scope_unique' => false,
                    'message' => 'Has company_id but no name and fewer than 3 column definitions.',
                    'alter_sql' => '',
                ];
                continue;
            }

            $scopeUniqueCols = $tableRow['scope_unique_columns'];
            $scopeUniqueLabel = is_array($scopeUniqueCols) && $scopeUniqueCols
                ? '(`' . implode('`, `', $scopeUniqueCols) . '`)'
                : '(`company_id`, `' . $scopeColumn . '`)';

            $alterSql = 'ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uq_' . $table . '_company_scope` (`company_id`, `'
                . $scopeColumn . '`);';

            $ok = (int) $tableRow['unique_count'] === $requiredUniqueCount && !empty($tableRow['has_scope_unique']);

            if ($ok) {
                $summary['pass']++;
                $lines[] = [
                    'table' => $table,
                    'status' => 'pass',
                    'unique_count' => (int) $tableRow['unique_count'],
                    'scope_column' => $scopeColumn,
                    'scope_unique' => $scopeUniqueLabel,
                    'has_scope_unique' => true,
                    'message' => 'Has PRIMARY KEY + UNIQUE ' . $scopeUniqueLabel . '.',
                    'alter_sql' => '',
                ];
                continue;
            }

            $summary['fail']++;
            $message = 'Expected ' . $requiredUniqueCount . ' uniques (PRIMARY + UNIQUE starting with `company_id`, `'
                . $scopeColumn . '`); found ' . (int) $tableRow['unique_count'] . '.';
            if (empty($tableRow['has_scope_unique'])) {
                $message .= ' Missing matching UNIQUE.';
            } elseif ((int) $tableRow['unique_count'] > $requiredUniqueCount) {
                $message .= ' Extra UNIQUE keys present.';
            } elseif ((int) $tableRow['unique_count'] < $requiredUniqueCount) {
                $message .= ' Too few UNIQUE keys.';
            }

            $lines[] = [
                'table' => $table,
                'status' => 'fail',
                'unique_count' => (int) $tableRow['unique_count'],
                'scope_column' => $scopeColumn,
                'scope_unique' => $scopeUniqueLabel,
                'has_scope_unique' => !empty($tableRow['has_scope_unique']),
                'message' => $message,
                'alter_sql' => empty($tableRow['has_scope_unique']) ? $alterSql : '',
            ];
        }

        return [
            'sql_path' => $sqlPath,
            'required_unique_count' => $requiredUniqueCount,
            'lines' => $lines,
            'summary' => $summary,
        ];
    }
}
