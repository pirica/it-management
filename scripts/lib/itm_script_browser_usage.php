<?php
/**
 * Browser landing: How to use (dodgerblue info) then Dry-run / Continue before run=1.
 *
 * Why: Catalog scripts.php keeps What it does only; usage text lives in each PHP entry script.
 */

require_once __DIR__ . '/script_browser_nav.php';

if (!function_exists('itm_script_browser_usage_exempt_basenames')) {
    /**
     * Scripts that must not show the usage landing (catalog, full HTML apps).
     *
     * @return string[]
     */
    function itm_script_browser_usage_exempt_basenames()
    {
        return [
            'scripts.php',
            'api.php',
            'run_tests.php',
            'system_status_api.php',
            'module_browser_qa_runner.php',
            'module_clean_tests_qa_runner.php',
            'detect_fk_dropdown_ui_risk_ui.php',
            'pitfalls.php',
            'force_delete_company.php',
            'fast_create_acc.php',
            'fast_create_acc_browser.php',
            'DBdesign.php',
            'schema_report.php',
            // Why: No-auth plain-text monitors; external probes expect immediate output without run=1 landing.
            'count_db_tables.php',
            // Why: Full HTML form with built-in dry-run; POST must not require run=1 on the action URL.
            'update_all_created_at.php',
        ];
    }
}

if (!function_exists('itm_script_browser_usage_register')) {
    /**
     * @param string $text Plain text or safe HTML for How to use body
     * @param array{supports_apply?:bool,title?:string} $options
     */
    function itm_script_browser_usage_register($text, array $options = [])
    {
        $GLOBALS['itm_script_browser_usage'] = [
            'text' => (string)$text,
            'supports_apply' => !empty($options['supports_apply']),
            'title' => isset($options['title']) ? (string)$options['title'] : '',
        ];
    }
}

if (!function_exists('itm_script_browser_usage_resolve_text')) {
    function itm_script_browser_usage_resolve_text()
    {
        if (isset($GLOBALS['itm_script_browser_usage']['text'])) {
            $text = trim((string)$GLOBALS['itm_script_browser_usage']['text']);
            if ($text !== '') {
                return $text;
            }
        }
        if (function_exists('itm_script_browser_how_to_use')) {
            $text = trim((string)itm_script_browser_how_to_use());
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }
}

if (!function_exists('itm_script_browser_usage_resolve_options')) {
    /**
     * @param array{supports_apply?:bool,title?:string,exempt?:bool} $options
     * @return array{supports_apply:bool,title:string,exempt:bool}
     */
    function itm_script_browser_usage_resolve_options(array $options = [])
    {
        $registered = isset($GLOBALS['itm_script_browser_usage']) && is_array($GLOBALS['itm_script_browser_usage'])
            ? $GLOBALS['itm_script_browser_usage']
            : [];

        $supportsApply = array_key_exists('supports_apply', $options)
            ? !empty($options['supports_apply'])
            : !empty($registered['supports_apply']);

        $title = isset($options['title']) && (string)$options['title'] !== ''
            ? (string)$options['title']
            : (isset($registered['title']) ? (string)$registered['title'] : '');

        $exempt = !empty($options['exempt']);

        return [
            'supports_apply' => $supportsApply,
            'title' => $title,
            'exempt' => $exempt,
        ];
    }
}

if (!function_exists('itm_script_browser_usage_run_confirmed')) {
    function itm_script_browser_usage_run_confirmed()
    {
        return isset($_GET['run']) && (string)$_GET['run'] === '1';
    }
}

if (!function_exists('itm_script_browser_usage_build_action_href')) {
    /**
     * @param bool $withApply
     * @return string
     */
    function itm_script_browser_usage_build_action_href($withApply)
    {
        $params = $_GET;
        unset($params['apply'], $params['dry-run']);
        $params['run'] = '1';
        if ($withApply) {
            $params['apply'] = '1';
        } else {
            unset($params['apply']);
        }

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'script.php'));

        return $query === '' ? $script : ($script . '?' . $query);
    }
}

if (!function_exists('itm_script_browser_usage_render_landing')) {
    /**
     * @param string $usageText
     * @param array{supports_apply:bool,title:string} $options
     */
    function itm_script_browser_usage_render_landing($usageText, array $options)
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $pageTitle = $options['title'] !== ''
            ? $options['title']
            : basename((string)($_SERVER['SCRIPT_NAME'] ?? 'Script'), '.php');
        $titleEsc = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . $titleEsc . '</title>';
        echo '<style>';
        echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;margin:16px;max-width:900px;color:#24292f;line-height:1.5;}';
        echo 'h1{font-size:1.35rem;margin:0 0 12px;}';
        echo '.itm-script-usage-info{color:dodgerblue;margin:0 0 18px;padding:12px 14px;background:#f6f8fa;border:1px solid #d0d7de;border-radius:8px;font-size:0.92rem;}';
        echo '.itm-script-usage-info pre{margin:8px 0 0;white-space:pre-wrap;word-break:break-word;font-family:Consolas,"Courier New",monospace;font-size:0.88rem;color:dodgerblue;}';
        echo '.itm-script-usage-actions{display:flex;flex-wrap:wrap;gap:10px;margin:16px 0;}';
        echo '.itm-script-usage-actions a{display:inline-block;padding:8px 14px;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.9rem;border:1px solid #d0d7de;background:#fff;color:#0969da;}';
        echo '.itm-script-usage-actions a.itm-script-usage-continue{border-color:#cf222e;color:#cf222e;}';
        echo '.itm-script-usage-actions a:hover{background:#f6f8fa;}';
        echo '</style></head><body>';

        itm_script_browser_nav_echo();

        echo '<h1 title="Script usage">' . $titleEsc . '</h1>';
        echo '<p style="margin:0 0 8px;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#57606a;">How to use</p>';
        echo '<div class="itm-script-usage-info">';

        $trimmed = trim($usageText);
        if (strpos($trimmed, '<') !== false) {
            echo $trimmed;
        } else {
            echo '<pre>' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '</pre>';
        }

        echo '</div>';

        echo '<div class="itm-script-usage-actions">';
        if (!empty($options['supports_apply'])) {
            $dryHref = htmlspecialchars(itm_script_browser_usage_build_action_href(false), ENT_QUOTES, 'UTF-8');
            echo '<a href="' . $dryHref . '" title="Dry-run">🔍</a>';
            $contHref = htmlspecialchars(itm_script_browser_usage_build_action_href(true), ENT_QUOTES, 'UTF-8');
            echo '<a class="itm-script-usage-continue" href="' . $contHref . '" title="Continue with apply">▶️</a>';
        } else {
            $runHref = htmlspecialchars(itm_script_browser_usage_build_action_href(false), ENT_QUOTES, 'UTF-8');
            echo '<a href="' . $runHref . '" title="Continue">▶️</a>';
        }

        echo '</div>';
        echo '</body></html>';
    }
}

if (!function_exists('itm_script_browser_usage_maybe_gate')) {
    /**
     * Browser-only: show usage landing unless run=1. No-op on CLI or exempt scripts.
     *
     * @param array{supports_apply?:bool,title?:string,exempt?:bool} $options
     */
    function itm_script_browser_usage_maybe_gate(array $options = [])
    {
        if (itm_script_is_cli_sapi()) {
            return;
        }

        static $passed = false;
        if ($passed) {
            return;
        }

        $basename = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $resolved = itm_script_browser_usage_resolve_options($options);
        if ($resolved['exempt'] || in_array($basename, itm_script_browser_usage_exempt_basenames(), true)) {
            return;
        }

        $usageText = itm_script_browser_usage_resolve_text();
        if ($usageText === '') {
            return;
        }

        if (!itm_script_browser_usage_run_confirmed()) {
            if ($resolved['title'] === '') {
                $resolved['title'] = basename($basename, '.php');
            }
            itm_script_browser_usage_render_landing($usageText, $resolved);
            exit;
        }

        $passed = true;
    }
}
