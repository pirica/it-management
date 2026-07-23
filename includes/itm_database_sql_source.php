<?php
/**
 * Canonical paths and readers for the db/ SQL split bundle (monolith db/ removed).
 */

declare(strict_types=1);

if (!function_exists('itm_database_sql_root')) {
    function itm_database_sql_root(): string
    {
        if (defined('ROOT_PATH')) {
            return ROOT_PATH;
        }

        return dirname(__DIR__) . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('itm_database_sql_schema_path')) {
    function itm_database_sql_schema_path(): string
    {
        return itm_database_sql_root() . 'db' . DIRECTORY_SEPARATOR . '01_schema.sql';
    }
}

if (!function_exists('itm_database_sql_data_path')) {
    function itm_database_sql_data_path(): string
    {
        return itm_database_sql_root() . 'db' . DIRECTORY_SEPARATOR . '02_data.sql';
    }
}

if (!function_exists('itm_database_sql_triggers_path')) {
    function itm_database_sql_triggers_path(): string
    {
        return itm_database_sql_root() . 'db' . DIRECTORY_SEPARATOR . '03_triggers.sql';
    }
}

if (!function_exists('itm_database_sql_sample_path')) {
    function itm_database_sql_sample_path(): string
    {
        return itm_database_sql_root() . 'db' . DIRECTORY_SEPARATOR . '02_data_sample.sql';
    }
}

/**
 * Which company_id values in db/02_data_sample.sql are template markers (file parse only — not a live DB tenant).
 */
if (!defined('ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID')) {
    define('ITM_SAMPLE_SQL_TEMPLATE_COMPANY_ID', 1);
}

if (!function_exists('itm_database_sql_read_file')) {
    function itm_database_sql_read_file(string $path): string
    {
        if (!is_readable($path)) {
            return '';
        }
        $body = file_get_contents($path);

        return $body === false ? '' : $body;
    }
}

if (!function_exists('itm_database_sql_read_schema')) {
    function itm_database_sql_read_schema(): string
    {
        return itm_database_sql_read_file(itm_database_sql_schema_path());
    }
}

if (!function_exists('itm_database_sql_read_data')) {
    function itm_database_sql_read_data(): string
    {
        return itm_database_sql_read_file(itm_database_sql_data_path());
    }
}

if (!function_exists('itm_database_sql_read_sample')) {
    function itm_database_sql_read_sample(): string
    {
        $sample = itm_database_sql_read_file(itm_database_sql_sample_path());
        if ($sample !== '') {
            return $sample;
        }

        // Why: transitional fallback when 02_data_sample.sql has not been generated yet.
        return itm_database_sql_read_data();
    }
}

if (!function_exists('itm_database_sql_read_triggers')) {
    function itm_database_sql_read_triggers(): string
    {
        return itm_database_sql_read_file(itm_database_sql_triggers_path());
    }
}

if (!function_exists('itm_database_sql_read_combined')) {
    function itm_database_sql_read_combined(): string
    {
        $parts = array_filter([
            itm_database_sql_read_schema(),
            itm_database_sql_read_data(),
            itm_database_sql_read_triggers(),
        ], static function (string $part): bool {
            return $part !== '';
        });

        return implode("\n\n", $parts);
    }
}

if (!function_exists('itm_database_sql_read_schema_and_triggers')) {
    function itm_database_sql_read_schema_and_triggers(): string
    {
        $parts = array_filter([
            itm_database_sql_read_schema(),
            itm_database_sql_read_triggers(),
        ], static function (string $part): bool {
            return $part !== '';
        });

        return implode("\n\n", $parts);
    }
}

if (!function_exists('itm_database_sql_bundle_label')) {
    function itm_database_sql_bundle_label(): string
    {
        return 'db/ split bundle (01_schema.sql, 02_data.sql, 03_triggers.sql)';
    }
}
