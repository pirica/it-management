<?php
/**
 * Split database.sql into schema / data / triggers files for ordered import.
 *
 * Why: Maintainers edit database.sql; split files are generated with clean boundaries
 * (DDL only in 01, DML in 03, triggers in 02) and import order 01 → 03 → 02.
 */

declare(strict_types=1);

if (!function_exists('itm_database_sql_split_parse_statements')) {
    /**
     * @return list<string>
     */
    function itm_database_sql_split_parse_statements(string $sql): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $sql) ?: [];
        $statements = [];
        $buffer = [];
        $pendingComments = [];
        $delimiter = ';';

        $flush = static function () use (&$statements, &$buffer, &$pendingComments): void {
            if ($buffer === [] && $pendingComments === []) {
                return;
            }
            $parts = $pendingComments;
            if ($buffer !== []) {
                $parts = array_merge($parts, $buffer);
            }
            $stmt = trim(implode("\n", $parts));
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = [];
            $pendingComments = [];
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($buffer !== []) {
                    $buffer[] = $line;
                }
                continue;
            }

            if (strpos($trimmed, '--') === 0) {
                if ($buffer === []) {
                    $pendingComments[] = $line;
                } else {
                    $buffer[] = $line;
                }
                continue;
            }

            $buffer[] = $line;

            if (preg_match('/^DELIMITER\s+(\S+)/i', $trimmed, $matches)) {
                $flush();
                $delimiter = $matches[1];
                continue;
            }

            $lineEnd = rtrim($line);
            $endsStatement = false;
            if ($delimiter === ';') {
                $endsStatement = substr($lineEnd, -1) === ';';
            } elseif ($delimiter !== '') {
                $endsStatement = (bool) preg_match('/' . preg_quote($delimiter, '/') . '\s*$/', $lineEnd);
            }

            if ($endsStatement) {
                $flush();
            }
        }

        $flush();

        return $statements;
    }
}

if (!function_exists('itm_database_sql_split_first_executable_line')) {
    function itm_database_sql_split_first_executable_line(string $statement): string
    {
        foreach (preg_split('/\r\n|\n|\r/', $statement) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '--') === 0) {
                continue;
            }

            return $trimmed;
        }

        return '';
    }
}

if (!function_exists('itm_database_sql_split_classify_statement')) {
    /**
     * @return 'schema'|'data'|'triggers'|'footer'|'skip'
     */
    function itm_database_sql_split_classify_statement(string $statement): string
    {
        if (preg_match('/\b(CREATE TRIGGER|DROP TRIGGER|DELIMITER\s+)/i', $statement)) {
            return 'triggers';
        }

        $first = itm_database_sql_split_first_executable_line($statement);
        if ($first === '') {
            return 'skip';
        }

        $normalized = preg_replace('/\s+/', ' ', $first);
        if ($normalized === null || $normalized === '') {
            return 'skip';
        }

        if (preg_match('/^DELIMITER\s+/i', $normalized)) {
            return 'triggers';
        }

        if (preg_match('/^SET FOREIGN_KEY_CHECKS\s*=\s*1\s*;?$/i', $normalized)) {
            return 'footer';
        }

        if (preg_match('/^(DROP TRIGGER|CREATE TRIGGER)\b/i', $normalized)) {
            return 'triggers';
        }

        if (preg_match('/^(DROP DATABASE|CREATE DATABASE|USE `|SET NAMES|SET CHARACTER SET|SET collation_connection|SET FOREIGN_KEY_CHECKS\s*=\s*0)/i', $normalized)) {
            return 'schema';
        }

        if (preg_match('/^(DROP TABLE|CREATE TABLE)\b/i', $normalized)) {
            return 'schema';
        }

        if (preg_match('/^(INSERT\b|INSERT IGNORE\b|DELETE\b|SET @|UPDATE\b)/i', $normalized)) {
            return 'data';
        }

        return 'skip';
    }
}

if (!function_exists('itm_database_sql_split_monolith')) {
    /**
     * @return array{schema: list<string>, data: list<string>, triggers: list<string>, footer: list<string>, skipped: list<string>}
     */
    function itm_database_sql_split_monolith(string $sql): array
    {
        $buckets = [
            'schema' => [],
            'data' => [],
            'triggers' => [],
            'footer' => [],
            'skipped' => [],
        ];

        foreach (itm_database_sql_split_parse_statements($sql) as $statement) {
            $bucket = itm_database_sql_split_classify_statement($statement);
            if ($bucket === 'skip') {
                $buckets['skipped'][] = $statement;
                continue;
            }
            $buckets[$bucket][] = $statement;
        }

        return $buckets;
    }
}

if (!function_exists('itm_database_sql_split_bootstrap_block')) {
    function itm_database_sql_split_bootstrap_block(bool $includeDropCreate = false): string
    {
        $lines = [];
        if ($includeDropCreate) {
            $lines[] = 'DROP DATABASE IF EXISTS `itmanagement`;';
            $lines[] = '';
            $lines[] = 'CREATE DATABASE `itmanagement` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
            $lines[] = '';
        }
        $lines[] = 'USE `itmanagement`;';
        $lines[] = '';
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = '';
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $lines[] = '';

        return implode("\n", $lines);
    }
}

if (!function_exists('itm_database_sql_split_render_file')) {
    /**
     * @param list<string> $statements
     */
    function itm_database_sql_split_render_file(array $statements, bool $includeDropCreate, bool $includeFooter): string
    {
        $parts = [itm_database_sql_split_bootstrap_block($includeDropCreate)];
        foreach ($statements as $statement) {
            $parts[] = $statement;
            $parts[] = '';
        }
        if ($includeFooter) {
            $parts[] = 'SET FOREIGN_KEY_CHECKS=1;';
            $parts[] = '';
        }

        return rtrim(implode("\n", $parts)) . "\n";
    }
}

if (!function_exists('itm_database_sql_split_write_files')) {
    /**
     * @return array{schema: string, triggers: string, data: string, skipped: list<string>}
     */
    function itm_database_sql_split_write_files(string $rootPath, string $monolithPath): array
    {
        $sql = file_get_contents($monolithPath);
        if ($sql === false) {
            throw new RuntimeException('Cannot read ' . $monolithPath);
        }

        $buckets = itm_database_sql_split_monolith($sql);
        if ($buckets['skipped'] !== []) {
            $preview = substr(preg_replace('/\s+/', ' ', $buckets['skipped'][0]) ?? '', 0, 120);
            throw new RuntimeException('Unclassified SQL statement: ' . $preview);
        }

        $databaseDir = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'db';
        if (!is_dir($databaseDir) && !mkdir($databaseDir, 0775, true) && !is_dir($databaseDir)) {
            throw new RuntimeException('Cannot create ' . $databaseDir);
        }

        $headerComment = '';
        if (preg_match('/^-- IT Management SQL Backup.*$/m', $sql, $headerMatch)) {
            $headerComment = $headerMatch[0] . "\n";
        }

        $schemaBody = itm_database_sql_split_render_file($buckets['schema'], true, false);
        $dataBody = itm_database_sql_split_render_file($buckets['data'], false, false);
        $triggerBody = itm_database_sql_split_render_file(
            array_merge($buckets['triggers'], $buckets['footer']),
            false,
            true
        );

        $schemaPath = $databaseDir . DIRECTORY_SEPARATOR . '01_schema.sql';
        $triggerPath = $databaseDir . DIRECTORY_SEPARATOR . '02_triggers.sql';
        $dataPath = $databaseDir . DIRECTORY_SEPARATOR . '03_data.sql';

        $schemaContent = $headerComment . "-- Schema (DDL only). Import before 03_data.sql and 02_triggers.sql.\n\n" . $schemaBody;
        $triggerContent = $headerComment . "-- Audit triggers. Import after 03_data.sql (single MySQL session with 01 + 03).\n\n" . $triggerBody;
        $dataContent = $headerComment . "-- Seed and replication data. Import after 01_schema.sql, before 02_triggers.sql.\n\n" . $dataBody;

        if (file_put_contents($schemaPath, $schemaContent) === false) {
            throw new RuntimeException('Cannot write ' . $schemaPath);
        }
        if (file_put_contents($triggerPath, $triggerContent) === false) {
            throw new RuntimeException('Cannot write ' . $triggerPath);
        }
        if (file_put_contents($dataPath, $dataContent) === false) {
            throw new RuntimeException('Cannot write ' . $dataPath);
        }

        return [
            'schema' => $schemaPath,
            'triggers' => $triggerPath,
            'data' => $dataPath,
            'skipped' => $buckets['skipped'],
        ];
    }
}

if (!function_exists('itm_database_sql_split_count_metrics')) {
    /**
     * @return array{tables: int, triggers: int, inserts: int}
     */
    function itm_database_sql_split_count_metrics(string $sql): array
    {
        preg_match_all('/^CREATE TABLE `([^`]+)`/m', $sql, $tables);
        preg_match_all('/^CREATE TRIGGER `([^`]+)`/m', $sql, $triggers);
        preg_match_all('/^INSERT\b/m', $sql, $inserts);

        return [
            'tables' => count($tables[1]),
            'triggers' => count($triggers[1]),
            'inserts' => count($inserts[0]),
        ];
    }
}

if (!function_exists('itm_database_sql_split_schema_violations')) {
    /**
     * @return list<string>
     */
    function itm_database_sql_split_schema_violations(string $schemaSql): array
    {
        $violations = [];
        if (preg_match('/^INSERT\b/im', $schemaSql)) {
            $violations[] = '01_schema.sql must not contain INSERT statements';
        }
        if (preg_match('/^DELETE\b/im', $schemaSql)) {
            $violations[] = '01_schema.sql must not contain DELETE statements';
        }
        if (preg_match('/^SET @/im', $schemaSql)) {
            $violations[] = '01_schema.sql must not contain SET @user variables (belong in 03_data.sql)';
        }
        if (preg_match('/^CREATE TRIGGER\b/im', $schemaSql)) {
            $violations[] = '01_schema.sql must not contain CREATE TRIGGER statements';
        }
        if (preg_match('/^DELIMITER\b/im', $schemaSql)) {
            $violations[] = '01_schema.sql must not contain DELIMITER statements';
        }

        return $violations;
    }
}
