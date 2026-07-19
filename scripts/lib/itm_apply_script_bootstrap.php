<?php
/**
 * Shared bootstrap for scripts/apply*.php maintenance tools.
 *
 * Contract (SCRIPTS.md → apply* tools):
 * - Browser + CLI
 * - Default run is always dry-run (no writes)
 * - Writes only with CLI --apply or browser ?apply=1 (Admin session required in browser for apply)
 * - Browser dry-run: any signed-in session (config.php login gate); apply still requires Admin
 * - Callers should list scanned / skipped / changed targets
 */

if (!function_exists('itm_apply_script_is_cli')) {
    /**
     * @return bool
     */
    function itm_apply_script_is_cli()
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
}

if (!function_exists('itm_apply_script_bootstrap')) {
    /**
     * Load config, enforce Admin in browser when apply=1, start script output, resolve apply flag.
     *
     * @param string $title
     * @param array $options Keys: skip_db_tests (bool, default true)
     * @return array{is_cli:bool,apply:bool,nl:string,root:string,argv:array}
     */
    function itm_apply_script_bootstrap($title, array $options = [])
    {
        $isCli = itm_apply_script_is_cli();
        $skipDb = !array_key_exists('skip_db_tests', $options) || !empty($options['skip_db_tests']);
        if ($skipDb) {
            putenv('ITM_SKIP_DB_TESTS=1');
        }

        if ($isCli) {
            if (!defined('ITM_CLI_SCRIPT')) {
                define('ITM_CLI_SCRIPT', true);
            }
            require_once dirname(__DIR__) . '/../config/config.php';
        } else {
            require_once dirname(__DIR__) . '/../config/config.php';
        }

        require_once __DIR__ . '/script_cli_output.php';
        itm_script_output_begin((string)$title);

        $argv = $GLOBALS['argv'] ?? [];
        $apply = false;
        if ($isCli) {
            // Why: --apply opts into writes; bare run and --dry-run stay preview-only.
            $apply = in_array('--apply', $argv, true);
            if (in_array('--dry-run', $argv, true)) {
                $apply = false;
            }
        } else {
            $apply = isset($_GET['apply']) && (string)$_GET['apply'] === '1';
            if (isset($_GET['dry-run']) && (string)$_GET['dry-run'] === '1') {
                $apply = false;
            }
        }

        if (!$isCli && $apply) {
            // Why: Browser file writes require Admin; use script bootstrap gate so pre-swap authorization
            // employee is honored after itm_script_begin_browser_isolated_session() swaps $_SESSION.
            if (function_exists('itm_script_require_admin_script_or_exit')) {
                itm_script_require_admin_script_or_exit(
                    $GLOBALS['conn'] ?? null,
                    'Forbidden: administrator login required.'
                );
            } else {
                $employeeId = (int)($_SESSION['employee_id'] ?? 0);
                if (!function_exists('itm_is_admin') || !itm_is_admin($GLOBALS['conn'] ?? null, $employeeId)) {
                    http_response_code(403);
                    header('Content-Type: text/plain; charset=utf-8');
                    echo "Forbidden: administrator login required.\n";
                    itm_script_output_end();
                    exit(1);
                }
            }
        }

        $nl = itm_script_output_nl();
        $rootReal = realpath(__DIR__ . '/../..');
        $root = ($rootReal !== false)
            ? (rtrim(str_replace('\\', '/', $rootReal), '/') . '/')
            : (rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/') . '/');

        itm_apply_script_print_mode($apply, $nl);

        return [
            'is_cli' => $isCli,
            'apply' => $apply,
            'nl' => $nl,
            'root' => $root,
            'argv' => $argv,
        ];
    }
}

if (!function_exists('itm_apply_script_print_mode')) {
    /**
     * @param bool $apply
     * @param string $nl
     * @return void
     */
    function itm_apply_script_print_mode($apply, $nl)
    {
        if ($apply) {
            echo colorText('Mode: APPLY (writing files)', 'fail') . $nl;
        } else {
            echo colorText('Mode: DRY-RUN (default — no files written)', 'info') . $nl;
        }
    }
}

if (!function_exists('itm_apply_script_rel_path')) {
    /**
     * @param string $root Repository root with or without trailing slash
     * @param string $path Absolute file path
     * @return string
     */
    function itm_apply_script_rel_path($root, $path)
    {
        $rootNorm = rtrim(str_replace('\\', '/', (string)$root), '/');
        $pathNorm = str_replace('\\', '/', (string)$path);
        if (strpos($pathNorm, $rootNorm . '/') === 0) {
            return substr($pathNorm, strlen($rootNorm) + 1);
        }
        return $pathNorm;
    }
}

if (!function_exists('itm_apply_script_echo_list')) {
    /**
     * Print a headed list with real newlines (readable inside browser <pre>).
     *
     * @param string $heading
     * @param array $items
     * @return void
     */
    function itm_apply_script_echo_list($heading, array $items)
    {
        $items = array_values(array_unique(array_map('strval', $items)));
        sort($items, SORT_STRING);
        echo (string)$heading . ":\n";
        if ($items === []) {
            echo "  (none)\n\n";
            return;
        }
        foreach ($items as $item) {
            echo '  - ' . $item . "\n";
        }
        echo "\n";
    }
}

if (!function_exists('itm_apply_script_list_items')) {
    /**
     * @param string $label
     * @param array $items
     * @param string $nl Ignored — lists always use real newlines for browser <pre>.
     * @return void
     */
    function itm_apply_script_list_items($label, array $items, $nl = "\n")
    {
        itm_apply_script_echo_list($label . ' (' . count(array_unique(array_map('strval', $items))) . ')', $items);
    }
}

if (!function_exists('itm_apply_script_finish_hint')) {
    /**
     * @param bool $apply
     * @param bool $isCli
     * @param int $changed
     * @param string $nl
     * @param string $scriptBasename e.g. apply_foo.php
     * @return void
     */
    function itm_apply_script_finish_hint($apply, $isCli, $changed, $nl, $scriptBasename = '')
    {
        if ($apply) {
            echo 'Apply complete.' . $nl;
            return;
        }
        if ((int)$changed > 0) {
            if ($isCli) {
                $cmd = $scriptBasename !== ''
                    ? ('php scripts/' . $scriptBasename . ' --apply')
                    : 'php scripts/<apply-script>.php --apply';
                echo 'Re-run with --apply to write: ' . $cmd . $nl;
            } else {
                $href = $scriptBasename !== '' ? ($scriptBasename . '?apply=1') : '?apply=1';
                echo 'Open with ?apply=1 to write (Admin): ' . $href . $nl;
            }
        } else {
            echo 'Dry-run complete — nothing to change.' . $nl;
        }
    }
}

if (!function_exists('itm_apply_script_arg_value')) {
    /**
     * Read --key=value from argv or ?key= from browser.
     *
     * @param array $argv
     * @param bool $isCli
     * @param string $name
     * @param string $default
     * @return string
     */
    function itm_apply_script_arg_value(array $argv, $isCli, $name, $default = '')
    {
        $name = (string)$name;
        if ($isCli) {
            $prefix = '--' . $name . '=';
            foreach ($argv as $arg) {
                $arg = (string)$arg;
                if (strpos($arg, $prefix) === 0) {
                    return substr($arg, strlen($prefix));
                }
            }
            return (string)$default;
        }
        return isset($_GET[$name]) ? trim((string)$_GET[$name]) : (string)$default;
    }
}
