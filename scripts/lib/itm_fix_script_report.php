<?php
/**
 * Shared dry-run report contract for scripts/fix_*.php.
 *
 * Canonical prose: scripts/SCRIPTS.md → Fix scripts (fix_*.php).
 */

if (!function_exists('itm_fix_script_report_heading_live_db')) {
    /**
     * @return string
     */
    function itm_fix_script_report_heading_live_db()
    {
        return 'List Actual live DB';
    }
}

if (!function_exists('itm_fix_script_report_heading_sql_bundle')) {
    /**
     * @return string
     */
    function itm_fix_script_report_heading_sql_bundle()
    {
        return 'List db/*.sql data';
    }
}

if (!function_exists('itm_fix_script_report_heading_fix')) {
    /**
     * @return string
     */
    function itm_fix_script_report_heading_fix()
    {
        return 'List Fix';
    }
}

if (!function_exists('itm_fix_script_report_na_item')) {
    /**
     * Placeholder when a section does not apply to this fix script.
     *
     * @return string
     */
    function itm_fix_script_report_na_item()
    {
        return '(n/a — this fix script does not compare live MySQL)';
    }
}

if (!function_exists('itm_fix_script_report_sql_na_item')) {
    /**
     * @return string
     */
    function itm_fix_script_report_sql_na_item()
    {
        return '(n/a — this fix script does not read db/*.sql)';
    }
}

if (!function_exists('itm_fix_script_report_print_sections')) {
    /**
     * Print the three mandatory list sections (real newlines for browser <pre>).
     *
     * @param array<int,string> $liveDbItems Rows describing live MySQL findings
     * @param array<int,string> $sqlBundleItems Rows describing db/01_schema.sql (or other db/*.sql) findings
     * @param array<int,string> $fixItems Planned mutations (files, tables, or line summaries)
     * @return void
     */
    function itm_fix_script_report_print_sections(array $liveDbItems, array $sqlBundleItems, array $fixItems)
    {
        if (!function_exists('itm_apply_script_echo_list')) {
            require_once __DIR__ . '/itm_apply_script_bootstrap.php';
        }

        itm_apply_script_echo_list(itm_fix_script_report_heading_live_db(), $liveDbItems);
        itm_apply_script_echo_list(itm_fix_script_report_heading_sql_bundle(), $sqlBundleItems);
        itm_apply_script_echo_list(itm_fix_script_report_heading_fix(), $fixItems);
    }
}

if (!function_exists('itm_fix_script_report_print_dry_run_status')) {
    /**
     * Print the mandatory dry-run status line(s) before list sections.
     *
     * @param bool $stillNeedsFixes True when dry-run found work to do
     * @param string $nl Line ending from itm_script_output_nl()
     * @param array $options Keys:
     *   - broad_sql (bool): when true and nothing to fix, also prints
     *     "Dry-run: no broad SQL fixes needed."
     * @return void
     */
    function itm_fix_script_report_print_dry_run_status($stillNeedsFixes, $nl, array $options = [])
    {
        $stillNeedsFixes = (bool)$stillNeedsFixes;
        $broadSql = !empty($options['broad_sql']);

        if (!$stillNeedsFixes) {
            if ($broadSql) {
                echo 'Dry-run: no broad SQL fixes needed.' . $nl;
            }
            echo 'Dry-run complete — nothing to change.' . $nl;
            return;
        }

        echo 'Dry-run: Still need fixes.' . $nl;
    }
}

if (!function_exists('itm_fix_script_report_finish')) {
    /**
     * End-of-run helper: dry-run status + three lists + apply hint (when needed).
     *
     * @param bool $apply From itm_apply_script_bootstrap()
     * @param bool $isCli
     * @param bool $stillNeedsFixes
     * @param string $nl
     * @param string $scriptBasename e.g. fix_sql_broad.php
     * @param array<int,string> $liveDbItems
     * @param array<int,string> $sqlBundleItems
     * @param array<int,string> $fixItems
     * @param array $options Passed to itm_fix_script_report_print_dry_run_status()
     * @return void
     */
    function itm_fix_script_report_finish(
        $apply,
        $isCli,
        $stillNeedsFixes,
        $nl,
        $scriptBasename,
        array $liveDbItems,
        array $sqlBundleItems,
        array $fixItems,
        array $options = []
    ) {
        if (!function_exists('itm_apply_script_finish_hint')) {
            require_once __DIR__ . '/itm_apply_script_bootstrap.php';
        }

        if ($apply) {
            echo 'Apply complete.' . $nl;
            itm_fix_script_report_print_sections($liveDbItems, $sqlBundleItems, $fixItems);
            return;
        }

        itm_fix_script_report_print_dry_run_status($stillNeedsFixes, $nl, $options);
        itm_fix_script_report_print_sections($liveDbItems, $sqlBundleItems, $fixItems);

        if ($stillNeedsFixes) {
            itm_apply_script_finish_hint(false, $isCli, 1, $nl, $scriptBasename);
        }
    }
}

if (!function_exists('itm_fix_script_discover_slugs')) {
    /**
     * Basenames of scripts/fix_*.php (for fix_all.php and catalog maintenance).
     *
     * @param string $root Repository root with trailing slash
     * @return array<int,string>
     */
    function itm_fix_script_discover_slugs($root)
    {
        $root = rtrim(str_replace('\\', '/', (string)$root), '/') . '/';
        $glob = glob($root . 'scripts/fix_*.php');
        if ($glob === false) {
            return [];
        }

        $slugs = [];
        foreach ($glob as $path) {
            $slugs[] = basename($path);
        }
        sort($slugs, SORT_STRING);

        return $slugs;
    }
}
