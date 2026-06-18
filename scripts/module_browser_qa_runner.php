<?php
/**
 * HTTP session runner for full-module QA (mirrors browser checklist).
 *
 * Why: Exercising 101 modules × 5 companies via IDE browser alone is not practical;
 * this tool uses the same login, company scope, CSRF, and module URLs as manual QA.
 * Tier A order: mysql (database.sql INSERT count) → error_log → list → clear → … → error_log.
 * Tier A seeds FK parents, fills required NOT NULL columns, then:
 *   add — insert ~30 random tenant rows when count < records_per_page + 1 (mbqa_ensure_bulk_sample_rows);
 *   bulk_delete — when rows >= perPage and bulk UI visible on index; POST delete.php with up to 3 ids[].
 *   clear_table — after export_xlsx, before second clear (same row gate as bulk_delete).
 *   list (Tier A) — verifies bulk UI visibility matches rowCount >= perPage and pagination footer matches rowCount > perPage.
 *   pagination (after add) — when rows > perPage: page=1 must render Next→page=2; page=2 must render Previous→page=1 in HTML (sort=id).
 * Each module scopes error_log.txt (rename to error_log-N.txt when present, else byte offset); Tier A ends with sample restore + error_log check for new lines only.
 *
 * Usage (repository root, CLI):
 *   php scripts/module_browser_qa_runner.php
 *   php scripts/module_browser_qa_runner.php --module=expenses --company=1
 *   php scripts/module_browser_qa_runner.php --pilot-only
 *
 * Browser (Laragon): open scripts/module_browser_qa_runner.php, set options, Run QA.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/utf8_file.php';
require_once __DIR__ . '/lib/mbqa_import_helpers.php';
require_once __DIR__ . '/lib/mbqa_report_paths.php';
require_once __DIR__ . '/lib/mbqa_report_xlsx.php';
require_once __DIR__ . '/lib/mbqa_runner_tiers.php';
require_once __DIR__ . '/lib/mbqa_step_display.php';
require_once __DIR__ . '/lib/equipment_type_modules.php';

/**
 * @return array<int, string>
 */
function mbqa_company_name_map(): array
{
    return [
        1 => 'TechCorp Global',
        2 => 'DataCenter Plus',
        3 => 'Network Solutions',
        4 => 'CloudTech Services',
        5 => 'Enterprise IT',
    ];
}

/**
 * Module folders with index.php (for browser form before config.php loads).
 *
 * @return array<int, string>
 */
function mbqa_list_module_slugs(string $projectRoot): array
{
    $modulesDir = $projectRoot . DIRECTORY_SEPARATOR . 'modules';
    if (!is_dir($modulesDir)) {
        return [];
    }

    $slugs = [];
    foreach (scandir($modulesDir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $modulesDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && is_file($path . DIRECTORY_SEPARATOR . 'index.php')) {
            $slugs[] = $item;
        }
    }
    sort($slugs);

    return $slugs;
}

/**
 * @return array{run:bool, help:bool, pilot_only:bool, ui_click_smoke:bool, stream:bool, ajax:bool, run_id:string, base_url:string, module:?string, company:?int}
 */
function mbqa_parse_run_options(): array
{
    $options = [
        'run' => false,
        'help' => false,
        'pilot_only' => false,
        'ui_click_smoke' => false,
        'stream' => false,
        'ajax' => false,
        'autostart' => false,
        'run_id' => '',
        'base_url' => mbqa_is_cli_sapi() ? 'http://localhost/it-management/' : mbqa_detect_browser_base_url(),
        'module' => null,
        'company' => null,
    ];

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        $options['run'] = true;
        foreach (array_slice($GLOBALS['argv'] ?? [], 1) as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $options['help'] = true;
                $options['run'] = false;
                continue;
            }
            if ($arg === '--pilot-only') {
                $options['pilot_only'] = true;
                continue;
            }
            if ($arg === '--ui-click-smoke') {
                $options['ui_click_smoke'] = true;
                continue;
            }
            if (strpos($arg, '--base-url=') === 0) {
                $options['base_url'] = (string)substr($arg, 11);
                continue;
            }
            if (strpos($arg, '--module=') === 0) {
                $moduleRaw = substr($arg, 9);
                if ($moduleRaw !== '' && strtolower($moduleRaw) !== 'all') {
                    $options['module'] = $moduleRaw;
                }
                continue;
            }
            if (strpos($arg, '--company=') === 0) {
                $companyRaw = substr($arg, 10);
                if ($companyRaw !== '' && strtolower($companyRaw) !== 'all') {
                    $options['company'] = (int)$companyRaw;
                }
            }
        }
    } else {
        $options['run'] = isset($_GET['run']) || isset($_POST['run']);
        $options['help'] = isset($_GET['help']);
        $options['pilot_only'] = isset($_GET['pilot_only']) || isset($_POST['pilot_only']);
        $options['ui_click_smoke'] = isset($_GET['ui_click_smoke']) || isset($_POST['ui_click_smoke']);
        if (isset($_REQUEST['base_url'])) {
            $options['base_url'] = trim((string)$_REQUEST['base_url']);
        }
        $moduleManualRaw = trim((string)($_REQUEST['module_manual'] ?? ''));
        if ($moduleManualRaw !== '') {
            $options['module'] = $moduleManualRaw;
        } elseif (isset($_REQUEST['module'])) {
            $moduleRaw = trim((string)$_REQUEST['module']);
            if ($moduleRaw !== '' && strtolower($moduleRaw) !== 'all') {
                $options['module'] = $moduleRaw;
            }
        }
        if (isset($_REQUEST['company'])) {
            $companyRaw = trim((string)$_REQUEST['company']);
            if ($companyRaw !== '' && strtolower($companyRaw) !== 'all') {
                $options['company'] = (int)$companyRaw;
            }
        }
        $options['stream'] = isset($_GET['stream']) && (string)$_GET['stream'] === '1';
        $options['ajax'] = isset($_GET['ajax']) && (string)$_GET['ajax'] === '1';
        $options['autostart'] = isset($_GET['autostart']) && (string)$_GET['autostart'] === '1';
        $options['run_id'] = mbqa_sanitize_run_id((string)($_GET['run_id'] ?? ''));
    }

    if (substr($options['base_url'], -1) !== '/') {
        $options['base_url'] .= '/';
    }
    $options['base_url'] = mbqa_normalize_base_url($options['base_url']);

    return $options;
}

function mbqa_is_cli_sapi(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

/**
 * Why: UI click smoke runs in the browser and must fetch the app on the same origin as the runner page;
 * defaulting to localhost breaks when the user opens 127.0.0.1 or a remote host.
 */
function mbqa_is_local_loopback_host(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return true;
    }

    return strpos($host, '127.') === 0;
}

/**
 * Why: Laragon serves ITM on plain HTTP; HTTPS localhost often has no TLS listener (curl HTTP 0 on login).
 */
function mbqa_normalize_base_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return 'http://localhost/it-management/';
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return rtrim($url, '/') . '/';
    }

    $scheme = isset($parts['scheme']) ? strtolower((string)$parts['scheme']) : 'http';
    $host = (string)$parts['host'];
    if (mbqa_is_local_loopback_host($host) && $scheme === 'https') {
        $scheme = 'http';
    }

    $port = isset($parts['port']) ? (int)$parts['port'] : 0;
    $authority = $host;
    if ($port > 0 && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
        $authority .= ':' . $port;
    }

    $path = isset($parts['path']) ? (string)$parts['path'] : '/';
    if ($path === '') {
        $path = '/';
    }

    return rtrim($scheme . '://' . $authority . rtrim($path, '/'), '/') . '/';
}

function mbqa_detect_browser_base_url(): string
{
    if (mbqa_is_cli_sapi()) {
        return 'http://localhost/it-management/';
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if ($host === '') {
        $host = 'localhost';
    }
    if (strpos($host, ':') !== false) {
        $host = (string)(parse_url('http://' . $host, PHP_URL_HOST) ?? $host);
    }

    $https = !empty($_SERVER['HTTPS']) && (string)$_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    if (mbqa_is_local_loopback_host($host)) {
        $scheme = 'http';
    }

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptsPos = strrpos($scriptName, '/scripts/');
    if ($scriptsPos === false) {
        return mbqa_normalize_base_url(rtrim($scheme . '://' . $host, '/') . '/');
    }

    $appPath = substr($scriptName, 0, $scriptsPos);
    if ($appPath === '') {
        $appPath = '/';
    }

    return mbqa_normalize_base_url(rtrim($scheme . '://' . $host . rtrim($appPath, '/'), '/') . '/');
}

function mbqa_sanitize_run_id(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '' || strlen($raw) > 64) {
        return '';
    }
    if (preg_match('/^[a-zA-Z0-9_-]+$/', $raw) !== 1) {
        return '';
    }

    return $raw;
}

function mbqa_ajax_reports_dir(string $root): string
{
    return $root . DIRECTORY_SEPARATOR . 'qa-reports';
}

function mbqa_ensure_reports_dir_writable(string $root): bool
{
    $dir = mbqa_ajax_reports_dir($root);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (is_dir($dir)) {
        @chmod($dir, 0777);
    }

    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    return true;
}

function mbqa_ajax_progress_path(string $root, string $runId): string
{
    return mbqa_ajax_reports_dir($root) . DIRECTORY_SEPARATOR . '.mbqa-progress-' . $runId . '.json';
}

function mbqa_ajax_cancel_path(string $root, string $runId): string
{
    return mbqa_ajax_reports_dir($root) . DIRECTORY_SEPARATOR . '.mbqa-cancel-' . $runId;
}

function mbqa_ajax_cleanup_stale_files(string $root, string $keepRunId = '', int $ttlSeconds = 86400): void
{
    $dir = mbqa_ajax_reports_dir($root);
    if (!is_dir($dir)) {
        return;
    }

    $now = time();
    foreach (scandir($dir) ?: [] as $item) {
        if (strpos($item, '.mbqa-progress-') !== 0 && strpos($item, '.mbqa-cancel-') !== 0) {
            continue;
        }
        if ($keepRunId !== '' && strpos($item, $keepRunId) !== false) {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_file($path) && ($now - (int)filemtime($path)) > $ttlSeconds) {
            @unlink($path);
        }
    }
}

function mbqa_browser_ajax_active(): bool
{
    return !mbqa_is_cli_sapi() && !empty($GLOBALS['mbqa_ajax_run_id']);
}

function mbqa_browser_ajax_run_id(): string
{
    return (string)($GLOBALS['mbqa_ajax_run_id'] ?? '');
}

function mbqa_ajax_is_cancelled(string $root, string $runId): bool
{
    if ($runId === '') {
        return false;
    }

    return is_file(mbqa_ajax_cancel_path($root, $runId));
}

/**
 * @param array<string, mixed> $state
 */
function mbqa_browser_ajax_write_progress(string $root, string $runId, array $state): void
{
    if ($runId === '') {
        return;
    }
    $dir = mbqa_ajax_reports_dir($root);
    if (!mbqa_ensure_reports_dir_writable($root)) {
        return;
    }
    $state['updated_at'] = time();
    $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }
    itm_write_utf8_text_file(mbqa_ajax_progress_path($root, $runId), $json, false);
}

/**
 * @param array<int, array<string, mixed>> $steps
 * @return array{ok:bool,message:string,pass:int,fail:int,xlsx_href:string}
 */
function mbqa_append_ui_click_evidence(string $root, string $module, int $companyId, array $steps, string $expectedRunId): array
{
    if ($module === '' || $companyId <= 0 || empty($steps)) {
        return ['ok' => false, 'message' => 'Missing module, company, or click-smoke steps', 'pass' => 0, 'fail' => 0, 'xlsx_href' => ''];
    }
    if ($expectedRunId === '') {
        return ['ok' => false, 'message' => 'run_id required for click-smoke append', 'pass' => 0, 'fail' => 0, 'xlsx_href' => ''];
    }

    $jsonPath = mbqa_report_find_json_path_by_run_id($root, $expectedRunId);
    if ($jsonPath === '' || !is_file($jsonPath)) {
        return ['ok' => false, 'message' => 'QA JSON report not found', 'pass' => 0, 'fail' => 0, 'xlsx_href' => ''];
    }

    $payload = json_decode(itm_read_utf8_text_file($jsonPath), true);
    if (!is_array($payload) || !isset($payload['results']) || !is_array($payload['results'])) {
        return ['ok' => false, 'message' => 'QA JSON report is not readable', 'pass' => 0, 'fail' => 0, 'xlsx_href' => ''];
    }

    $reportRunId = (string)($payload['run_id'] ?? '');
    if ($reportRunId === '' || $reportRunId !== $expectedRunId) {
        return [
            'ok' => false,
            'message' => 'QA JSON report run_id mismatch (stale or overlapping run)',
            'pass' => 0,
            'fail' => 0,
            'xlsx_href' => '',
        ];
    }

    $normalisedSteps = [];
    foreach ($steps as $step) {
        $stepName = preg_replace('/[^a-z0-9_]/i', '', (string)($step['step'] ?? ''));
        if ($stepName === '') {
            continue;
        }
        $status = (string)($step['status'] ?? '');
        $normalisedSteps[] = [
            'step' => $stepName,
            'status' => strcasecmp($status, 'Pass') === 0 ? 'Pass' : 'Fail',
            'notes' => substr((string)($step['notes'] ?? ''), 0, 500),
            'evidence' => is_array($step['evidence'] ?? null) ? $step['evidence'] : [],
        ];
    }
    if (empty($normalisedSteps)) {
        return ['ok' => false, 'message' => 'No valid click-smoke steps supplied', 'pass' => 0, 'fail' => 0, 'xlsx_href' => ''];
    }

    $updated = false;
    foreach ($payload['results'] as &$result) {
        if (($result['module'] ?? '') === $module && (int)($result['company_id'] ?? 0) === $companyId) {
            if (!isset($result['steps']) || !is_array($result['steps'])) {
                $result['steps'] = [];
            }
            foreach ($normalisedSteps as $step) {
                $result['steps'][] = $step;
            }
            $updated = true;
            break;
        }
    }
    unset($result);

    if (!$updated) {
        return ['ok' => false, 'message' => 'Matching module/company result not found', 'pass' => 0, 'fail' => 0, 'xlsx_href' => ''];
    }

    $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    itm_write_utf8_text_file($jsonPath, json_encode($payload, $jsonFlags), false);

    $pass = 0;
    $fail = 0;
    foreach ($payload['results'] as $row) {
        foreach (($row['steps'] ?? []) as $step) {
            if (($step['status'] ?? '') === 'Pass') {
                $pass++;
            } else {
                $fail++;
            }
        }
    }

    $reportFiles = mbqa_report_files_from_json_path($jsonPath);
    $xlsxBuilt = mbqar_build_runner_xlsx(
        $root,
        $payload['results'],
        $pass,
        $fail,
        (string)($payload['generated_at'] ?? date('Y-m-d H:i:s')),
        $reportFiles['xlsx_path'] ?? null
    );

    return [
        'ok' => true,
        'message' => 'Click-smoke evidence appended',
        'pass' => $pass,
        'fail' => $fail,
        'xlsx_href' => $xlsxBuilt['ok'] && $reportFiles !== null
            ? ('../qa-reports/' . $reportFiles['xlsx_basename'])
            : '',
    ];
}

function mbqa_ajax_should_stop(string $root): bool
{
    if (!mbqa_browser_ajax_active()) {
        return false;
    }
    if (connection_aborted()) {
        return true;
    }

    return mbqa_ajax_is_cancelled($root, mbqa_browser_ajax_run_id());
}

/**
 * Lightweight poll/cancel handlers (no config.php — fast AJAX from the form page).
 */
function mbqa_ajax_handle_request(string $root): void
{
    if (mbqa_is_cli_sapi()) {
        return;
    }
    $action = trim((string)($_GET['ajax'] ?? ''));
    $runId = mbqa_sanitize_run_id((string)($_GET['run_id'] ?? ''));
    mbqa_ajax_cleanup_stale_files($root, $runId);
    if ($runId === '') {
        return;
    }

    if ($action === 'progress') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $path = mbqa_ajax_progress_path($root, $runId);
        if (!is_file($path)) {
            echo json_encode(['status' => 'pending', 'run_id' => $runId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        echo itm_read_utf8_text_file($path);
        exit;
    }

    if ($action === 'cancel') {
        header('Content-Type: application/json; charset=utf-8');
        $dir = mbqa_ajax_reports_dir($root);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        touch(mbqa_ajax_cancel_path($root, $runId));
        mbqa_browser_ajax_write_progress($root, $runId, [
            'status' => 'cancelling',
            'company_id' => 0,
            'module' => '',
            'step' => '',
            'message' => 'Stop requested',
        ]);
        echo json_encode(['ok' => true, 'run_id' => $runId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'ui_click_evidence') {
        header('Content-Type: application/json; charset=utf-8');
        $body = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($body)) {
            echo json_encode(['ok' => false, 'message' => 'Invalid JSON body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $result = mbqa_append_ui_click_evidence(
            $root,
            trim((string)($body['module'] ?? '')),
            (int)($body['company_id'] ?? 0),
            is_array($body['steps'] ?? null) ? $body['steps'] : [],
            $runId
        );
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

function mbqa_browser_stream_active(): bool
{
    return !mbqa_is_cli_sapi() && !empty($GLOBALS['mbqa_browser_stream']);
}

/**
 * @param array<string, mixed> $payload
 */
function mbqa_browser_stream_write_line(array $payload): void
{
    if (!mbqa_browser_stream_active()) {
        return;
    }
    $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return;
    }
    echo $line . "\n";
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    @flush();
}

/**
 * Why: Browser Run QA uses fetch + NDJSON so the form page can show co / module / step without a blocking pre log.
 */
function mbqa_browser_stream_begin(): void
{
    // Why: An open session holds the response until the script ends; release it before long HTTP/DB work.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    if (!headers_sent()) {
        header('Content-Type: application/x-ndjson; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('X-Accel-Buffering: no');
    }
    mbqa_browser_stream_write_line(['type' => 'ping', 'message' => 'connected']);
}

function mbqa_browser_progress_set_context(int $companyId, string $moduleSlug): void
{
    $GLOBALS['mbqa_progress_company_id'] = $companyId;
    $GLOBALS['mbqa_progress_module'] = $moduleSlug;
}

function mbqa_browser_progress_emit(string $step): void
{
    $companyId = (int)($GLOBALS['mbqa_progress_company_id'] ?? 0);
    $module = (string)($GLOBALS['mbqa_progress_module'] ?? '');
    if ($module === '') {
        return;
    }

    if (mbqa_browser_ajax_active()) {
        $root = (string)($GLOBALS['mbqa_project_root'] ?? '');
        $runId = mbqa_browser_ajax_run_id();
        if ($root !== '' && $runId !== '') {
            // Why: Do not overwrite ajax=cancel "cancelling" with "running" until the loop exits.
            $progressStatus = mbqa_ajax_is_cancelled($root, $runId) ? 'cancelling' : 'running';
            mbqa_browser_ajax_write_progress($root, $runId, [
                'status' => $progressStatus,
                'company_id' => $companyId,
                'module' => $module,
                'step' => $step,
                'message' => $progressStatus === 'cancelling' ? 'Stop requested' : '',
            ]);
        }
    }

    if (mbqa_browser_stream_active()) {
        mbqa_browser_stream_write_line([
            'type' => 'progress',
            'company_id' => $companyId,
            'module' => $module,
            'step' => $step,
        ]);
    }
}

/**
 * @param array{pass:int,fail:int,exit_code:int,json_href:string,report_href:string,rerun_href:string} $payload
 */
function mbqa_browser_stream_emit_done(array $payload): void
{
    if (!mbqa_browser_stream_active()) {
        return;
    }
    $payload['type'] = 'done';
    mbqa_browser_stream_write_line($payload);
}

function mbqa_echo_browser_url_actions_table(): void
{
    echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:0.95rem;">';
    echo '<thead><tr><th>URL / action</th><th>What happens</th></tr></thead><tbody>';
    echo '<tr><td>Form, no query</td><td>Form only</td></tr>';
    echo '<tr><td><code>?help=1</code></td><td>HTML help (readable)</td></tr>';
    echo '<tr><td><code>?run=1</code> only</td><td>Hint: use form + <strong>Run QA</strong> (this page)</td></tr>';
    echo '<tr><td><code>?run=1&amp;stream=1</code></td><td>Same hint — legacy NDJSON stream is disabled (was often not live on Laragon)</td></tr>';
    echo '<tr><td><strong>Run QA</strong> button</td><td><code>?run=1&amp;ajax=1&amp;run_id=…</code> + polling + <strong>Stop</strong></td></tr>';
    echo '<tr><td>CLI <code>php scripts/module_browser_qa_runner.php</code></td><td>No <code>run</code> / <code>stream</code> / <code>ajax</code> — always CLI output</td></tr>';
    echo '</tbody></table>';
}

function mbqa_render_browser_ajax_required(): void
{
    header('Content-Type: text/html; charset=utf-8');
    itm_script_browser_nav_echo();
    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;line-height:1.5;">';
    echo '<h1>Use the form — not <code>?run=1</code> alone</h1>';
    echo '<p>Open the runner form and click <strong>Run QA</strong>. Bare <code>?run=1</code> or <code>?run=1&amp;stream=1</code> does not start a run here.</p>';
    mbqa_echo_browser_url_actions_table();
    echo '<p style="margin-top:20px;"><a href="module_browser_qa_runner.php">← Runner form</a> · ';
    echo '<a href="module_browser_qa_runner.php?help=1">Full help</a></p>';
    echo '</main>';
}

function mbqa_out(string $message): void
{
    echo $message;
    if (!mbqa_is_cli_sapi()) {
        @flush();
        @ob_flush();
    }
}

function mbqa_err(string $message): void
{
    $plain = trim(str_replace(["\r", "\n"], ' ', $message));
    if (mbqa_browser_ajax_active()) {
        $root = (string)($GLOBALS['mbqa_project_root'] ?? '');
        $runId = mbqa_browser_ajax_run_id();
        if ($root !== '' && $runId !== '') {
            mbqa_browser_ajax_write_progress($root, $runId, [
                'status' => 'error',
                'company_id' => (int)($GLOBALS['mbqa_progress_company_id'] ?? 0),
                'module' => (string)($GLOBALS['mbqa_progress_module'] ?? ''),
                'step' => '',
                'message' => $plain,
            ]);
        }
        return;
    }
    if (mbqa_browser_stream_active()) {
        mbqa_browser_stream_write_line([
            'type' => 'error',
            'message' => $plain,
        ]);
        return;
    }
    if (mbqa_is_cli_sapi()) {
        fwrite(STDERR, $message);
    } else {
        mbqa_out($message);
    }
}

function mbqa_render_browser_help(): void
{
    header('Content-Type: text/html; charset=utf-8');
    itm_script_browser_nav_echo();
    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;line-height:1.5;">';
    echo '<h1>Module browser QA — help</h1>';
    echo '<p>Runs the full-module HTTP checklist (login, company switch, CRUD, export/import, delete) for one or all modules and tenants. ';
    echo 'Results are written to <code>qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json</code> and a matching <code>.xlsx</code> (new file each run).</p>';

    echo '<h2>URL and actions</h2>';
    mbqa_echo_browser_url_actions_table();
    echo '<p style="font-size:0.95rem;margin-top:12px;">Live status on the form: ';
    echo '<code>Running QA… co {id} — {module} - {step}</code> (poll every 400ms). ';
    echo 'Do not bookmark <code>?run=1</code> or <code>?run=1&amp;stream=1</code>.</p>';

    echo '<h2>Browser (recommended)</h2>';
    echo '<ol>';
    echo '<li>Open <a href="module_browser_qa_runner.php">module_browser_qa_runner.php</a> (the form, not this help URL alone).</li>';
    echo '<li>Choose <strong>Module</strong> (or type a slug under <strong>Or module slug (manual)</strong>).</li>';
    echo '<li>Choose <strong>Company</strong> (default <code>1</code> = TechCorp Global; empty = all five companies).</li>';
    echo '<li>Optional: enable <strong>UI click smoke</strong> only when one module and one company are selected.</li>';
    echo '<li>Click <strong>Run QA</strong> (not a bare run URL).</li>';
    echo '<li>Click <strong>Stop</strong> if the run is taking too long — the runner stops between companies/modules.</li>';
    echo '<li>When finished, use <strong>Download JSON</strong>, <strong>Download XLSX</strong>, or <a href="module_browser_qa_build_report.php">Build markdown report</a>.</li>';
    echo '</ol>';

    echo '<h2>Form fields</h2>';
    echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:0.95rem;">';
    echo '<thead><tr><th>Field</th><th>What it does</th></tr></thead><tbody>';
    echo '<tr><td>Base URL</td><td>App root (auto-detected from this runner page when left at default). On Laragon use <code>http://localhost/it-management/</code> — loopback hosts are normalised to HTTP because TLS is usually not configured (avoids login HTTP 0).</td></tr>';
    echo '<tr><td>Module</td><td><code>ALL</code> = every module with <code>index.php</code>; or pick one slug (e.g. <code>expenses</code>)</td></tr>';
    echo '<tr><td>Or module slug (manual)</td><td>Overrides the dropdown when filled (any folder name under <code>modules/</code>)</td></tr>';
    echo '<tr><td>Company</td><td><code>1</code>–<code>5</code> or <code>ALL</code> (all seeded tenants)</td></tr>';
    echo '<tr><td>Pilot only</td><td>Runs <code>expenses</code> only (all selected companies)</td></tr>';
    echo '<tr><td>UI click smoke</td><td>Browser-only click checks for one module + one company; appends <code>bulk_cancel_click</code>, <code>pagination_click</code>, <code>export_xlsx_click</code>, and <code>import_excel_click</code> evidence to JSON.</td></tr>';
    echo '</tbody></table>';

    echo '<h2>CLI (repository root)</h2>';
    echo '<pre style="background:#f6f8fa;border:1px solid #d0d7de;padding:12px;overflow:auto;font-size:0.9rem;">';
    echo htmlspecialchars(
        "php scripts/module_browser_qa_runner.php\n"
        . "php scripts/module_browser_qa_runner.php --module=expenses --company=1\n"
        . "php scripts/module_browser_qa_runner.php --pilot-only\n"
        . "php scripts/module_browser_qa_runner.php --ui-click-smoke --module=expenses --company=1 # browser-only guard\n"
        . "php scripts/module_browser_qa_runner.php --help\n",
        ENT_QUOTES,
        'UTF-8'
    );
    echo '</pre>';
    echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:0.95rem;margin-top:12px;">';
    echo '<thead><tr><th>Flag</th><th>Meaning</th></tr></thead><tbody>';
    echo '<tr><td><code>--base-url=URL</code></td><td>App root</td></tr>';
    echo '<tr><td><code>--module=SLUG</code></td><td>One module; omit for all</td></tr>';
    echo '<tr><td><code>--company=N</code></td><td>Company 1–5; omit for all five</td></tr>';
    echo '<tr><td><code>--pilot-only</code></td><td>Expenses only</td></tr>';
    echo '<tr><td><code>--ui-click-smoke</code></td><td>Accepted for parity, but click smoke is browser-only and requires the runner form.</td></tr>';
    echo '</tbody></table>';

    echo '<h2>Tier A step order (per module)</h2>';
    echo '<p style="font-size:0.95rem;">mysql → error_log → list → ui_check → clear → sample_data → add → pagination → bulk_cancel → bulk_delete → search → sort → create → view → edit → list_all → export_pdf → export_xlsx → clear_table → clear → import_db → single_delete → sample_data (restore) → error_log</p>';

    echo '<p style="margin-top:24px;"><a href="module_browser_qa_runner.php">← Back to runner form</a> · ';
    echo '<a href="module_browser_qa_build_report.php">Build markdown report</a> · ';
    echo '<a href="scripts.php">Scripts index</a></p>';
    echo '</main>';
}

function mbqa_print_help(): void
{
    if (!mbqa_is_cli_sapi()) {
        mbqa_render_browser_help();
        return;
    }

    itm_script_output_begin('Module browser QA runner');
    mbqa_out("Module browser QA runner\n\n");
    mbqa_out("Options:\n");
    mbqa_out("  --base-url=URL   App root (default http://localhost/it-management/)\n");
    mbqa_out("  --module=SLUG    Single module folder; omit or --module=all for every module with index.php\n");
    mbqa_out("  --company=N      Company id 1–5 only; omit or --company=all for all five tenants\n");
    mbqa_out("  --pilot-only     Expenses module only (all companies)\n");
    mbqa_out("  --ui-click-smoke Browser-only click smoke; use the runner form with one module + one company\n");
    mbqa_out("  --help           Show this help\n\n");
    mbqa_out("Output: qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json and matching .xlsx (new file each run)\n\n");
    mbqa_out("Tier A bulk steps (after add):\n");
    mbqa_out("  add         Insert ~30 random tenant rows if count < records_per_page + 1\n");
    mbqa_out("  pagination  After add: page=1 Next (HTML page=2) then page=2 Previous (HTML page=1); sort=id\n");
    mbqa_out("  bulk_cancel    bulk form + Select to Delete in index HTML; bulk-delete-selection.js in HTML; shared JS contract\n");
    mbqa_out("  bulk_delete If rows >= perPage and UI + delete.php: POST delete.php with up to 3 ids[]\n\n");
}

/**
 * Reuse module_clean_tests_qa_runner.php cleanup logic without rendering its UI.
 *
 * @return array{
 *   ok:bool,
 *   dirs_removed:int,
 *   companies_deleted:int,
 *   types_deleted:int,
 *   sidebar_deleted:int,
 *   canonical_ensured:int,
 *   runner_rows_deleted:int,
 *   runner_rows_detached:int,
 *   runner_tables_touched:int,
 *   runner_table_details:array<string,array{deleted:int,detached:int}>,
 *   errors:array<int,string>,
 *   warnings:array<int,string>
 * }
 */
function mbqa_run_clean_tests_silent(): array
{
    if (!function_exists('mbqa_clean_tests_run_cleanup')) {
        if (!defined('MBQA_CLEAN_TESTS_LIBRARY_MODE')) {
            define('MBQA_CLEAN_TESTS_LIBRARY_MODE', true);
        }
        require_once __DIR__ . '/module_clean_tests_qa_runner.php';
    }

    if (!function_exists('mbqa_clean_tests_run_cleanup')) {
        return [
            'ok' => false,
            'dirs_removed' => 0,
            'companies_deleted' => 0,
            'types_deleted' => 0,
            'sidebar_deleted' => 0,
            'canonical_ensured' => 0,
            'runner_rows_deleted' => 0,
            'runner_rows_detached' => 0,
            'runner_tables_touched' => 0,
            'runner_table_details' => [],
            'errors' => ['module_clean_tests_qa_runner.php could not be loaded in library mode.'],
            'warnings' => [],
        ];
    }

    $cleanup = mbqa_clean_tests_run_cleanup();
    if (!is_array($cleanup)) {
        return [
            'ok' => false,
            'dirs_removed' => 0,
            'companies_deleted' => 0,
            'types_deleted' => 0,
            'sidebar_deleted' => 0,
            'canonical_ensured' => 0,
            'runner_rows_deleted' => 0,
            'runner_rows_detached' => 0,
            'runner_tables_touched' => 0,
            'runner_table_details' => [],
            'errors' => ['module_clean_tests_qa_runner returned an invalid cleanup payload.'],
            'warnings' => [],
        ];
    }

    return $cleanup;
}

function mbqa_clean_tests_report_summary(array $cleanup, string $phaseLabel = 'QA cleanup'): string
{
    $ok = (bool)($cleanup['ok'] ?? false);
    if (!$ok) {
        $errors = array_values(array_filter((array)($cleanup['errors'] ?? []), static function ($line): bool {
            return trim((string)$line) !== '';
        }));
        if ($errors !== []) {
            return $phaseLabel . ' failed: ' . implode('; ', array_map('strval', $errors));
        }

        return $phaseLabel . ' failed.';
    }

    $parts = [];
    if ((int)($cleanup['dirs_removed'] ?? 0) > 0) {
        $parts[] = (int)$cleanup['dirs_removed'] . ' scaffold folder(s)';
    }
    if ((int)($cleanup['types_deleted'] ?? 0) > 0) {
        $parts[] = (int)$cleanup['types_deleted'] . ' equipment_types row(s)';
    }
    if ((int)($cleanup['sidebar_deleted'] ?? 0) > 0) {
        $parts[] = (int)$cleanup['sidebar_deleted'] . ' sidebar pref row(s)';
    }
    if ((int)($cleanup['companies_deleted'] ?? 0) > 0) {
        $parts[] = (int)$cleanup['companies_deleted'] . ' test compan' . ((int)$cleanup['companies_deleted'] === 1 ? 'y' : 'ies');
    }
    if ((int)($cleanup['runner_rows_deleted'] ?? 0) > 0 || (int)($cleanup['runner_rows_detached'] ?? 0) > 0) {
        $parts[] = (int)($cleanup['runner_rows_deleted'] ?? 0) . ' MBQA/QA-IMPORT row(s)'
            . ', ' . (int)($cleanup['runner_rows_detached'] ?? 0) . ' FK detach(es)';
    }

    if ($parts === []) {
        return '';
    }

    return $phaseLabel . ': removed ' . implode(', ', $parts) . '.';
}

/**
 * Selected company id for the browser form (&lt;select&gt;). Default **1** on first load; empty = ALL when user chose ALL.
 */
function mbqa_browser_form_company_selected(array $options): string
{
    if (array_key_exists('company', $_REQUEST)) {
        $raw = trim((string)$_REQUEST['company']);
        if ($raw === '' || strtolower($raw) === 'all') {
            return '';
        }
        $id = (int)$raw;

        return ($id >= 1 && $id <= 5) ? (string)$id : '1';
    }

    if ($options['company'] !== null && (int)$options['company'] > 0) {
        return (string)(int)$options['company'];
    }

    return '1';
}

/**
 * @param array{run:bool, help:bool, pilot_only:bool, ui_click_smoke:bool, stream:bool, base_url:string, module:?string, company:?int} $options
 */
function mbqa_render_browser_form(array $options): void
{
    header('Content-Type: text/html; charset=utf-8');
    itm_script_browser_nav_echo();

    $projectRoot = realpath(__DIR__ . '/..') ?: '';
    $baseUrl = htmlspecialchars($options['base_url'], ENT_QUOTES, 'UTF-8');
    $moduleRequested = ($options['module'] !== null && trim((string)$options['module']) !== '')
        ? trim((string)$options['module'])
        : '';
    $companySelected = mbqa_browser_form_company_selected($options);
    $pilotChecked = $options['pilot_only'] ? ' checked' : '';
    $uiClickSmokeChecked = $options['ui_click_smoke'] ? ' checked' : '';
    $moduleSlugs = $projectRoot !== '' ? mbqa_list_module_slugs($projectRoot) : [];
    $moduleSelectValue = '';
    $moduleManualValue = '';
    if ($moduleRequested !== '') {
        if (in_array($moduleRequested, $moduleSlugs, true)) {
            $moduleSelectValue = $moduleRequested;
        } else {
            $moduleManualValue = $moduleRequested;
        }
    }

    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
    echo '<h1>Module browser QA runner</h1>';
    echo '<p>Runs the full-module HTTP checklist (login, company switch, clear/seed/CRUD, export/import, delete). ';
    echo 'Writes timestamped <code>qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json</code> and matching <code>.xlsx</code> each run. Long runs may take several minutes.</p>';
    echo '<div id="mbqa-live-status" aria-live="polite" style="margin:12px 0;padding:10px 12px;background:#f6f8fa;border:1px solid #d0d7de;border-radius:6px;min-height:1.2em;font-size:0.95rem;display:none;"></div>';
    echo '<div id="mbqa-run-footer" hidden style="margin:12px 0;padding:10px 0;font-size:0.95rem;"></div>';
    echo '<form id="mbqa-run-form" method="get" action="module_browser_qa_runner.php" style="display:grid;gap:12px;max-width:520px;">';
    echo '<input type="hidden" name="run" value="1">';
    echo '<label>Base URL<br><input type="url" name="base_url" value="' . $baseUrl . '" style="width:100%;padding:8px;"></label>';
    echo '<label>Module<br><select name="module" id="mbqa-module-select" style="width:100%;padding:8px;">';
    echo '<option value=""' . ($moduleSelectValue === '' ? ' selected' : '') . '>ALL (all modules)</option>';
    foreach ($moduleSlugs as $slug) {
        $selected = $moduleSelectValue === $slug ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
            . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select></label>';
    echo '<label>Or module slug (manual)<br><input type="text" name="module_manual" id="mbqa-module-manual" value="'
        . htmlspecialchars($moduleManualValue, ENT_QUOTES, 'UTF-8')
        . '" placeholder="e.g. expenses" style="width:100%;padding:8px;" autocomplete="off"></label>';
    echo '<label>Company<br><select name="company" style="width:100%;padding:8px;">';
    echo '<option value=""' . ($companySelected === '' ? ' selected' : '') . '>ALL (companies 1–5)</option>';
    foreach (mbqa_company_name_map() as $companyId => $companyLabel) {
        $selected = $companySelected === (string)$companyId ? ' selected' : '';
        echo '<option value="' . (int)$companyId . '"' . $selected . '>'
            . (int)$companyId . ' — ' . htmlspecialchars($companyLabel, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select></label>';
    echo '<label><input type="checkbox" name="pilot_only" value="1"' . $pilotChecked . '> Pilot only (expenses)</label>';
    echo '<label><input type="checkbox" name="ui_click_smoke" value="1"' . $uiClickSmokeChecked . '> UI click smoke (browser-only; one module + one company)</label>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
    echo '<button type="submit" id="mbqa-run-btn" style="padding:10px 16px;font-weight:600;">Run QA</button>';
    echo '<button type="button" id="mbqa-stop-btn" disabled style="padding:10px 16px;">Stop</button>';
    echo '</div>';
    echo '</form>';
    echo '<p style="margin-top:20px;font-size:0.9rem;"><a href="module_browser_qa_runner.php?help=1">CLI options / help</a> · ';
    echo '<a href="module_browser_qa_build_report.php">Build markdown report</a></p>';
    mbqar_echo_xlsx_vendor_script();
    if (function_exists('mbqar_echo_xlsx_client_bootstrap')) {
        mbqar_echo_xlsx_client_bootstrap();
    }
    echo '<script>';
    echo <<<'MBQA_JS'
(function () {
  var form = document.getElementById('mbqa-run-form');
  var btn = document.getElementById('mbqa-run-btn');
  var stopBtn = document.getElementById('mbqa-stop-btn');
  var statusEl = document.getElementById('mbqa-live-status');
  var footerEl = document.getElementById('mbqa-run-footer');
  if (!form || !btn || !statusEl) {
    return;
  }

  var pollTimer = null;
  var runAbort = null;
  var activeRunId = '';
  var runActive = false;
  var terminalHandled = false;
  var cancellingSince = 0;
  var CANCELLING_STALE_MS = 8000;

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function newRunId() {
    return 'r' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
  }

  function progressLabel(ev) {
    if (ev.status === 'cancelling' || ev.status === 'cancelled') {
      return 'Stopping QA\u2026';
    }
    if (ev.module && ev.step) {
      return 'Running QA\u2026 co ' + ev.company_id + ' \u2014 ' + ev.module + ' - ' + ev.step;
    }
    return 'Running QA\u2026';
  }

  function showFooter(done) {
    var title = 'Completed';
    if (done.status === 'cancelled') {
      title = 'Stopped';
    } else if (done.exit_code !== 0 && done.exit_code !== undefined) {
      title = 'Completed with failures';
    } else if (done.status === 'error') {
      title = 'Failed';
    }
    var pass = done.pass !== undefined ? done.pass : 0;
    var fail = done.fail !== undefined ? done.fail : 0;
    var xlsxLink = done.xlsx_href
      ? ('<a href="' + esc(done.xlsx_href) + '">Download XLSX</a> \u00b7 ')
      : '';
    footerEl.innerHTML =
      '<p><strong>' + esc(title) + '</strong> \u2014 ' + esc(String(pass)) + ' pass, ' + esc(String(fail)) + ' fail</p>' +
      '<p><a href="' + esc(done.json_href || '#') + '">Download JSON</a> \u00b7 ' +
      xlsxLink +
      '<a href="' + esc(done.report_href || '#') + '">Build markdown report</a> \u00b7 ' +
      '<a href="#" id="mbqa-rerun-link">Re-Run Test</a> \u00b7 ' +
      '<a href="module_browser_qa_runner.php">Run QA runner</a></p>';
    footerEl.hidden = false;
    var rerun = document.getElementById('mbqa-rerun-link');
    if (rerun) {
      rerun.addEventListener('click', function (e) {
        e.preventDefault();
        startRun();
      });
    }
    var xlsxBtn = document.getElementById('mbqa-export-xlsx-btn');
    if (xlsxBtn && window.mbqaExportResultsFromJsonUrl && done.json_href) {
      xlsxBtn.addEventListener('click', function () {
        window.mbqaExportResultsFromJsonUrl(done.json_href);
      });
    }
  }

  function uiClickSmokeEnabled() {
    var el = form.querySelector('[name="ui_click_smoke"]');
    return Boolean(el && el.checked);
  }

  function selectedModule() {
    var manualEl = form.querySelector('[name="module_manual"]');
    var manualSlug = manualEl && manualEl.value ? manualEl.value.trim() : '';
    if (manualSlug !== '') {
      return manualSlug;
    }
    var select = form.querySelector('[name="module"]');
    return select && select.value ? select.value.trim() : '';
  }

  function selectedCompany() {
    var select = form.querySelector('[name="company"]');
    return select && select.value ? parseInt(select.value, 10) : 0;
  }

  function baseUrlValue() {
    var input = form.querySelector('[name="base_url"]');
    if (input && input.value) {
      return normalizeLoopbackBaseUrl(input.value);
    }
    return normalizeLoopbackBaseUrl(runnerAppRootFromLocation().toString());
  }

  function isLoopbackHost(hostname) {
    hostname = (hostname || '').toLowerCase();
    return hostname === 'localhost' || hostname === '127.0.0.1' || hostname.indexOf('127.') === 0;
  }

  function normalizeLoopbackBaseUrl(url) {
    try {
      var parsed = new URL(url, window.location.href);
      if (parsed.protocol === 'https:' && isLoopbackHost(parsed.hostname)) {
        parsed.protocol = 'http:';
        if (parsed.port === '443') {
          parsed.port = '';
        }
      }
      return parsed.toString();
    } catch (err) {
      return url;
    }
  }

  function runnerAppRootFromLocation() {
    var loc = window.location;
    var path = loc.pathname.replace(/\\/g, '/');
    var scriptsIdx = path.lastIndexOf('/scripts/');
    if (scriptsIdx === -1) {
      return new URL('./../', loc.href);
    }
    return new URL(path.slice(0, scriptsIdx + 1), loc.origin);
  }

  function syncBaseUrlToRunnerOrigin() {
    var input = form.querySelector('[name="base_url"]');
    if (!input) {
      return;
    }
    try {
      var derived = runnerAppRootFromLocation();
      if (!input.value) {
        input.value = normalizeLoopbackBaseUrl(derived.toString());
        return;
      }
      var configured = new URL(input.value, window.location.href);
      if (configured.origin !== window.location.origin) {
        input.value = normalizeLoopbackBaseUrl(derived.toString());
        return;
      }
      input.value = normalizeLoopbackBaseUrl(input.value);
    } catch (err) {
      /* keep user value */
    }
  }

  syncBaseUrlToRunnerOrigin();

  function makeClickStep(step, ok, notes, evidence) {
    return {
      step: step,
      status: ok ? 'Pass' : 'Fail',
      notes: notes || '',
      evidence: evidence || {}
    };
  }

  function queryByText(doc, selector, text) {
    var needle = text.toLowerCase();
    return Array.from(doc.querySelectorAll(selector)).find(function (el) {
      return (el.textContent || '').toLowerCase().indexOf(needle) !== -1;
    }) || null;
  }

  function isDisplayed(win, el) {
    if (!el) {
      return false;
    }
    var style = win.getComputedStyle(el);
    return style.display !== 'none' && style.visibility !== 'hidden';
  }

  function extractCsrf(htmlText) {
    var doc = new DOMParser().parseFromString(htmlText, 'text/html');
    var input = doc.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
  }

  function appFetch(appRoot, path, options) {
    return fetch(new URL(path, appRoot).toString(), Object.assign({
      credentials: 'same-origin',
      cache: 'no-store'
    }, options || {}));
  }

  function ensureSameOriginAppRoot() {
    var configured = new URL(baseUrlValue(), window.location.href);
    if (configured.origin === window.location.origin) {
      return configured;
    }
    var derived = runnerAppRootFromLocation();
    if (derived.origin === window.location.origin) {
      var input = form.querySelector('[name="base_url"]');
      if (input) {
        input.value = derived.toString();
      }
      return derived;
    }
    throw new Error(
      'UI click smoke requires Base URL on the same origin as this runner page '
      + '(open the runner and set Base URL to ' + derived.origin + ', not ' + configured.origin + ').'
    );
  }

  function loginAndSwitchCompany(appRoot, companyId) {
    return appFetch(appRoot, 'login.php')
      .then(function (res) { return res.text(); })
      .then(function (html) {
        var csrf = extractCsrf(html);
        var body = new URLSearchParams();
        body.set('email', 'Admin');
        body.set('password', 'Admin');
        body.set('csrf_token', csrf);
        return appFetch(appRoot, 'login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        });
      })
      .then(function () { return appFetch(appRoot, 'dashboard.php'); })
      .then(function (res) { return res.text(); })
      .then(function (html) {
        var csrf = extractCsrf(html);
        var body = new URLSearchParams();
        body.set('csrf_token', csrf);
        body.set('company_id', String(companyId));
        return appFetch(appRoot, 'dashboard.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        });
      });
  }

  function loadSmokeFrame(appRoot, moduleSlug) {
    return new Promise(function (resolve, reject) {
      var frame = document.getElementById('mbqa-ui-click-frame');
      if (!frame) {
        frame = document.createElement('iframe');
        frame.id = 'mbqa-ui-click-frame';
        frame.title = 'Module UI click smoke';
        frame.style.cssText = 'width:100%;height:420px;border:1px solid #d0d7de;border-radius:6px;margin-top:12px;';
        form.insertAdjacentElement('afterend', frame);
      }
      var done = false;
      frame.onload = function () {
        if (done) {
          return;
        }
        done = true;
        resolve(frame);
      };
      frame.onerror = function () {
        if (done) {
          return;
        }
        done = true;
        reject(new Error('Unable to load module frame'));
      };
      frame.src = new URL('modules/' + encodeURIComponent(moduleSlug) + '/index.php?search=&sort=id&dir=DESC&page=1', appRoot).toString();
      setTimeout(function () {
        if (!done) {
          done = true;
          resolve(frame);
        }
      }, 5000);
    });
  }

  function waitForFrameLoad(frame, trigger) {
    return new Promise(function (resolve) {
      var done = false;
      frame.onload = function () {
        if (!done) {
          done = true;
          resolve();
        }
      };
      trigger();
      setTimeout(function () {
        if (!done) {
          done = true;
          resolve();
        }
      }, 4000);
    });
  }

  function smokeBulkCancel(frame) {
    try {
      var doc = frame.contentDocument;
      var win = frame.contentWindow;
      var formEl = doc.querySelector('form#bulk-delete-form, form#department-bulk-form');
      var toggle = formEl ? formEl.querySelector('button[name="bulk_action"][value="bulk_delete"]') : null;
      if (!formEl || !toggle) {
        return makeClickStep('bulk_cancel_click', true, 'N/A (bulk form not visible)', { url: frame.src });
      }
      var beforeUrl = frame.contentWindow.location.href;
      toggle.click();
      var boxes = Array.from(doc.querySelectorAll('input[name="ids[]"]'));
      var firstCell = boxes[0] ? boxes[0].closest('td') : null;
      var visibleAfterSelect = firstCell ? isDisplayed(win, firstCell) : boxes.length > 0;
      var deleteLabel = (toggle.textContent || '').trim();
      var cancel = formEl.querySelector('[data-itm-bulk-cancel="1"]');
      if (boxes[0]) {
        boxes[0].checked = true;
      }
      if (cancel) {
        cancel.click();
      }
      var hiddenAfterCancel = firstCell ? !isDisplayed(win, firstCell) : true;
      var unchecked = boxes.every(function (box) { return !box.checked; });
      var restored = (toggle.textContent || '').trim() !== 'Delete Selected';
      var noPost = frame.contentWindow.location.href === beforeUrl;
      return makeClickStep(
        'bulk_cancel_click',
        visibleAfterSelect && deleteLabel === 'Delete Selected' && hiddenAfterCancel && unchecked && restored && noPost,
        'Select to Delete toggles rows; Cancel exits without POST',
        { checkboxes: boxes.length, visible_after_select: visibleAfterSelect, hidden_after_cancel: hiddenAfterCancel, no_post: noPost }
      );
    } catch (err) {
      return makeClickStep('bulk_cancel_click', false, err.message, { url: frame.src });
    }
  }

  function smokePagination(frame) {
    var doc = frame.contentDocument;
    var next = queryByText(doc, 'a', 'Next');
    if (!next) {
      return Promise.resolve(makeClickStep('pagination_click', true, 'N/A (Next link not visible)', { url: frame.src }));
    }
    return waitForFrameLoad(frame, function () { next.click(); })
      .then(function () {
        var page2 = frame.contentWindow.location.href.indexOf('page=2') !== -1;
        var prev = queryByText(frame.contentDocument, 'a', 'Previous');
        if (!prev) {
          return makeClickStep('pagination_click', false, 'Next clicked but Previous link missing', { page2: page2, url: frame.contentWindow.location.href });
        }
        return waitForFrameLoad(frame, function () { prev.click(); })
          .then(function () {
            var page1 = frame.contentWindow.location.href.indexOf('page=1') !== -1;
            return makeClickStep('pagination_click', page2 && page1, 'Next and Previous links clicked', { page2: page2, page1: page1 });
          });
      })
      .catch(function (err) {
        return makeClickStep('pagination_click', false, err.message, { url: frame.src });
      });
  }

  function smokeExportXlsx(frame) {
    try {
      var doc = frame.contentDocument;
      var win = frame.contentWindow;
      var btn = queryByText(doc, 'button', 'Export Excel');
      if (!btn) {
        return makeClickStep('export_xlsx_click', false, 'Export Excel button missing', { url: frame.src });
      }
      var clicked = false;
      var oldClick = win.HTMLAnchorElement.prototype.click;
      win.HTMLAnchorElement.prototype.click = function () { clicked = true; };
      btn.click();
      win.HTMLAnchorElement.prototype.click = oldClick;
      return makeClickStep('export_xlsx_click', clicked, clicked ? 'Export Excel generated a download link' : 'Export Excel click did not trigger download', { button_text: btn.textContent || '' });
    } catch (err) {
      return makeClickStep('export_xlsx_click', false, err.message, { url: frame.src });
    }
  }

  function smokeImportExcel(frame) {
    try {
      var doc = frame.contentDocument;
      var table = doc.querySelector('table[data-itm-db-import-endpoint]');
      var btn = queryByText(doc, 'button', 'Import Excel');
      var input = doc.querySelector('input[type="file"].table-tools-file');
      if (!table || !btn || !input) {
        return makeClickStep('import_excel_click', false, 'Import Excel control or data-itm-db-import-endpoint missing', {
          has_table_endpoint: Boolean(table),
          has_button: Boolean(btn),
          has_input: Boolean(input)
        });
      }
      var clicked = false;
      var oldClick = input.click;
      input.click = function () { clicked = true; };
      btn.click();
      input.click = oldClick;
      return makeClickStep('import_excel_click', clicked, 'Import Excel button opens the file chooser control', {
        endpoint: table.getAttribute('data-itm-db-import-endpoint'),
        file_input_clicked: clicked
      });
    } catch (err) {
      return makeClickStep('import_excel_click', false, err.message, { url: frame.src });
    }
  }

  function postUiClickEvidence(runId, moduleSlug, companyId, steps) {
    return fetch('module_browser_qa_runner.php?ajax=ui_click_evidence&run_id=' + encodeURIComponent(runId), {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ module: moduleSlug, company_id: companyId, steps: steps, run_id: runId })
    }).then(function (res) {
      if (!res.ok) {
        return Promise.reject(new Error('HTTP ' + res.status));
      }
      return res.json();
    }).then(function (data) {
      if (!data || typeof data !== 'object') {
        return Promise.reject(new Error('Invalid JSON response'));
      }
      return data;
    });
  }

  function runUiClickSmoke(done, runId) {
    var moduleSlug = selectedModule();
    var companyId = selectedCompany();
    if (!uiClickSmokeEnabled() || done.status === 'cancelled') {
      return Promise.resolve(done);
    }
    if (!moduleSlug || !companyId) {
      window.alert('UI click smoke requires one module and one company.');
      return Promise.resolve(done);
    }
    statusEl.textContent = 'Running UI click smoke\u2026';
    var appRoot;
    try {
      appRoot = ensureSameOriginAppRoot();
    } catch (err) {
      return postUiClickEvidence(runId, moduleSlug, companyId, [
        makeClickStep('bulk_cancel_click', false, err.message, {}),
        makeClickStep('pagination_click', false, err.message, {}),
        makeClickStep('export_xlsx_click', false, err.message, {}),
        makeClickStep('import_excel_click', false, err.message, {})
      ]).then(function (append) {
        done.pass = append.pass || done.pass;
        done.fail = append.fail || done.fail;
        done.xlsx_href = append.xlsx_href || done.xlsx_href;
        return done;
      });
    }
    return loginAndSwitchCompany(appRoot, companyId)
      .then(function () { return loadSmokeFrame(appRoot, moduleSlug); })
      .then(function (frame) {
        var steps = [smokeBulkCancel(frame)];
        return smokePagination(frame).then(function (paginationStep) {
          steps.push(paginationStep);
          steps.push(smokeExportXlsx(frame));
          steps.push(smokeImportExcel(frame));
          return postUiClickEvidence(runId, moduleSlug, companyId, steps);
        });
      })
      .then(function (append) {
        statusEl.textContent = append.ok ? 'UI click smoke saved' : ('UI click smoke failed: ' + append.message);
        done.pass = append.pass || done.pass;
        done.fail = append.fail || done.fail;
        done.xlsx_href = append.xlsx_href || done.xlsx_href;
        return done;
      })
      .catch(function (err) {
        return postUiClickEvidence(runId, moduleSlug, companyId, [
          makeClickStep('bulk_cancel_click', false, err.message, {}),
          makeClickStep('pagination_click', false, err.message, {}),
          makeClickStep('export_xlsx_click', false, err.message, {}),
          makeClickStep('import_excel_click', false, err.message, {})
        ]).then(function (append) {
          done.pass = append.pass || done.pass;
          done.fail = append.fail || done.fail;
          done.xlsx_href = append.xlsx_href || done.xlsx_href;
          return done;
        });
      });
  }

  function finishRun(ev) {
    if (terminalHandled) {
      return;
    }
    terminalHandled = true;
    runUiClickSmoke(ev, activeRunId).then(function (finalDone) {
      showFooter(finalDone);
      releaseRunUi();
    }).catch(function (err) {
      if (err && err.message) {
        ev.message = (ev.message ? ev.message + '; ' : '') + err.message;
      }
      showFooter(ev);
      releaseRunUi();
    });
  }

  function stopPolling() {
    if (pollTimer !== null) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function isTerminalProgressStatus(status) {
    return status === 'done' || status === 'error' || status === 'cancelled';
  }

  function releaseRunUi() {
    runActive = false;
    cancellingSince = 0;
    activeRunId = '';
    btn.disabled = false;
    if (stopBtn) {
      stopBtn.disabled = true;
    }
  }

  function applyProgress(ev) {
    statusEl.style.display = 'block';
    if (ev.status === 'cancelling') {
      if (!cancellingSince) {
        cancellingSince = Date.now();
      }
      statusEl.textContent = progressLabel(ev);
      btn.disabled = true;
      if (stopBtn) {
        stopBtn.disabled = true;
      }
      if (Date.now() - cancellingSince >= CANCELLING_STALE_MS) {
        stopPolling();
        showFooter({
          status: 'cancelled',
          pass: ev.pass !== undefined ? ev.pass : 0,
          fail: ev.fail !== undefined ? ev.fail : 0,
          exit_code: ev.exit_code !== undefined ? ev.exit_code : 130,
          json_href: ev.json_href,
          xlsx_href: ev.xlsx_href,
          report_href: ev.report_href,
          rerun_href: ev.rerun_href
        });
        statusEl.textContent = 'Stopped';
        releaseRunUi();
      }
      return;
    }
    cancellingSince = 0;
    statusEl.textContent = progressLabel(ev);
    if (!isTerminalProgressStatus(ev.status)) {
      return;
    }
    stopPolling();
    if (ev.status === 'done' || ev.status === 'cancelled') {
      finishRun(ev);
      return;
    } else if (ev.status === 'error') {
      statusEl.textContent = 'QA run failed: ' + (ev.message || 'unknown error');
    }
    releaseRunUi();
  }

  function pollProgress(runId) {
    var url = 'module_browser_qa_runner.php?ajax=progress&run_id=' + encodeURIComponent(runId);
    fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('Poll HTTP ' + res.status);
        }
        return res.json();
      })
      .then(function (ev) {
        if (!ev || ev.run_id && ev.run_id !== runId && ev.status === 'pending') {
          return;
        }
        if (ev.status && ev.status !== 'pending') {
          applyProgress(ev);
        }
      })
      .catch(function () { /* keep polling until run ends */ });
  }

  function requestCancel(runId) {
    var url = 'module_browser_qa_runner.php?ajax=cancel&run_id=' + encodeURIComponent(runId);
    fetch(url, { credentials: 'same-origin', cache: 'no-store' }).catch(function () {});
  }

  function buildRunUrl() {
    var params = new URLSearchParams(new FormData(form));
    var manualEl = form.querySelector('[name="module_manual"]');
    var manualSlug = manualEl && manualEl.value ? manualEl.value.trim() : '';
    if (manualSlug !== '') {
      params.set('module', manualSlug);
    }
    params.delete('module_manual');
    params.set('ajax', '1');
    params.set('run_id', activeRunId);
    return form.getAttribute('action') + '?' + params.toString();
  }

  function startRun(urlOverride) {
    if (runActive) {
      return;
    }
    if (runAbort) {
      runAbort.abort();
    }
    if (uiClickSmokeEnabled() && (!selectedModule() || !selectedCompany())) {
      window.alert('UI click smoke requires one module and one company.');
      return;
    }
    runActive = true;
    terminalHandled = false;
    cancellingSince = 0;
    activeRunId = newRunId();
    runAbort = new AbortController();
    stopPolling();
    btn.disabled = true;
    if (stopBtn) {
      stopBtn.disabled = false;
    }
    statusEl.style.display = 'block';
    statusEl.textContent = 'Running QA\u2026';
    footerEl.hidden = true;
    footerEl.innerHTML = '';

    pollTimer = setInterval(function () {
      pollProgress(activeRunId);
    }, 400);
    pollProgress(activeRunId);

    var runUrl = urlOverride || buildRunUrl();
    fetch(runUrl, { credentials: 'same-origin', cache: 'no-store', signal: runAbort.signal })
      .then(function (res) {
        return res.json().catch(function () {
          return null;
        });
      })
      .then(function (done) {
        if (done && isTerminalProgressStatus(done.status)) {
          applyProgress(done);
        }
      })
      .catch(function (err) {
        if (err.name === 'AbortError') {
          applyProgress({ status: 'cancelling', message: 'Stop requested' });
          return;
        }
        statusEl.textContent = 'QA run failed: ' + err.message;
        stopPolling();
        releaseRunUi();
      });
  }

  if (stopBtn) {
    stopBtn.addEventListener('click', function () {
      if (!activeRunId) {
        return;
      }
      statusEl.textContent = 'Stopping QA\u2026';
      stopBtn.disabled = true;
      requestCancel(activeRunId);
      if (runAbort) {
        runAbort.abort();
      }
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    startRun();
  });

  var qs = new URLSearchParams(window.location.search);
  if (qs.get('autostart') === '1' || (qs.get('run') === '1' && qs.get('ajax') !== '1')) {
    startRun();
  }
})();
MBQA_JS;
    echo '</script>';
    echo '</main>';
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    mbqa_err("Unable to resolve project root.\n");
    exit(2);
}

$mbqaOptions = mbqa_parse_run_options();

if ($mbqaOptions['help']) {
    mbqa_print_help();
    exit(0);
}

mbqa_ajax_handle_request($root);

if (!mbqa_is_cli_sapi() && !$mbqaOptions['run']) {
    mbqa_render_browser_form($mbqaOptions);
    exit(0);
}

if (!mbqa_is_cli_sapi() && $mbqaOptions['run'] && !$mbqaOptions['ajax']) {
    // Why: Re-Run Test / legacy ?run=1 links must open the form and start via AJAX, not a dead-end page.
    $mbqaOptions['run'] = false;
    $mbqaOptions['autostart'] = true;
    mbqa_render_browser_form($mbqaOptions);
    exit(0);
}

if (!mbqa_is_cli_sapi() && $mbqaOptions['ajax'] && $mbqaOptions['run_id'] === '') {
    mbqa_render_browser_ajax_required();
    exit(0);
}

// Why: Legacy ?stream=1 NDJSON was buffered on Laragon; browser runs use ajax=1 only.
if (!mbqa_is_cli_sapi() && $mbqaOptions['stream']) {
    mbqa_render_browser_ajax_required();
    exit(0);
}

$GLOBALS['mbqa_project_root'] = $root;
if (!mbqa_is_cli_sapi() && $mbqaOptions['ajax'] && $mbqaOptions['run_id'] !== '') {
    $GLOBALS['mbqa_ajax_run_id'] = $mbqaOptions['run_id'];
}
mbqa_validate_requested_scope(
    $mbqaOptions['module'],
    $mbqaOptions['company'],
    (bool)$mbqaOptions['ui_click_smoke'],
    mbqa_list_module_slugs($root),
    mbqa_company_name_map()
);
mbqa_validate_reports_writable_for_browser($root);

// Why: config.php starts the session and may set headers; browser HTML output must come after require.
define('ITM_CLI_SCRIPT', true);
require_once $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'itm_mbqa_test_user.php';
require_once $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'itm_maintenance_script_admin_gate.php';
if (!mbqa_is_cli_sapi()) {
    itm_enforce_maintenance_script_admin_browser($conn);
}

if (!mbqa_is_cli_sapi()) {
    @set_time_limit(0);
    @ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (!empty($GLOBALS['mbqa_ajax_run_id'])) {
        mbqa_browser_ajax_write_progress($root, mbqa_browser_ajax_run_id(), [
            'status' => 'running',
            'company_id' => 0,
            'module' => '_runner',
            'step' => 'starting',
        ]);
    }
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    mbqa_err("Database connection unavailable.\n");
    exit(mbqa_browser_stream_active() ? 1 : 2);
}

$baseUrl = $mbqaOptions['base_url'];
$pilotOnly = $mbqaOptions['pilot_only'];
$filterModule = $mbqaOptions['module'];
$filterCompany = $mbqaOptions['company'];
$browserRunViaQaButton = !mbqa_is_cli_sapi() && !empty($mbqaOptions['ajax']);

$bespokeSmoke = mbqa_runner_bespoke_smoke_modules();
$skipClear = mbqa_runner_skip_clear_modules();

/** Why: Some modules need lookup parents in database.sql before sample seed succeeds for a tenant. */
$sampleSeedPrerequisites = [
    // Why: cost_centers rows in database.sql reference departments; seed that chain before HTTP sample_data.
    'expenses' => ['departments', 'budget_categories', 'cost_centers', 'gl_accounts'],
    'employee_positions' => ['departments'],
    'employee_onboarding_requests' => ['departments', 'employee_positions'],
    'approvers' => ['departments', 'employee_positions'],
    'employee_assignment_history' => ['departments'],
    'inventory_items' => ['inventory_categories', 'suppliers'],
    'tickets' => ['ticket_categories', 'ticket_statuses', 'ticket_priorities', 'equipment'],
];

$companyNames = mbqa_company_name_map();

$lookupWave = [
    'departments', 'manufacturers', 'vlans', 'location_types', 'equipment_types',
    'budget_categories', 'cost_centers', 'gl_accounts', 'supplier_statuses',
    'ticket_categories', 'ticket_statuses', 'ticket_priorities', 'employee_statuses',
    'employee_positions', 'approver_type', 'approvers', 'equipment_statuses',
    'rj45_speed', 'warranty_types', 'workstation_modes', 'workstation_os_types',
];

$budgetWave = [
    'annual_budgets', 'monthly_budgets', 'forecast_revisions_status',
    'forecast_revisions', 'approvals_stage', 'approvals', 'expenses',
];

$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';
$allModules = [];
foreach (scandir($modulesDir) ?: [] as $item) {
    if ($item === '.' || $item === '..') {
        continue;
    }
    $path = $modulesDir . DIRECTORY_SEPARATOR . $item;
    if (is_dir($path) && is_file($path . DIRECTORY_SEPARATOR . 'index.php')) {
        $allModules[] = $item;
    }
}
sort($allModules);

function mbqa_fail_preflight(string $message, int $exitCode = 2): void
{
    if (mbqa_browser_ajax_active()) {
        $root = (string)($GLOBALS['mbqa_project_root'] ?? dirname(__DIR__));
        mbqa_browser_ajax_write_progress($root, mbqa_browser_ajax_run_id(), [
            'status' => 'error',
            'company_id' => 0,
            'module' => '_runner',
            'step' => 'preflight',
            'message' => $message,
            'exit_code' => $exitCode,
        ]);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'exit_code' => $exitCode,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        mbqa_err($message . "\n");
    }
    exit($exitCode);
}

function mbqa_validate_requested_scope(?string $module, ?int $company, bool $uiClickSmoke, array $allModules, array $companyNames): void
{
    if ($module !== null && !in_array($module, $allModules, true)) {
        mbqa_fail_preflight('Unknown module slug: ' . $module);
    }
    if ($company !== null && !array_key_exists((int)$company, $companyNames)) {
        mbqa_fail_preflight('Unknown company id: ' . (int)$company . ' (expected 1-5)');
    }
    if ($uiClickSmoke && mbqa_is_cli_sapi()) {
        mbqa_fail_preflight('--ui-click-smoke is browser-only; open scripts/module_browser_qa_runner.php and select one module + one company.');
    }
    if ($uiClickSmoke && ($module === null || $company === null)) {
        mbqa_fail_preflight('UI click smoke requires one module and one company.');
    }
}

function mbqa_validate_reports_writable_for_browser(string $root): void
{
    if (!mbqa_is_cli_sapi() && mbqa_browser_ajax_active() && !mbqa_ensure_reports_dir_writable($root)) {
        mbqa_fail_preflight('qa-reports is not writable by the web server. Make the directory writable before running browser QA.');
    }
}

function mbqa_http(string $url, string $method = 'GET', ?string $body = null, array $headers = [], string $cookieFile = ''): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($cookieFile !== '') {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    if (!empty($headers)) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $curlError = $raw === false ? (curl_error($ch) ?: 'curl failed') : '';
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        return ['status' => 0, 'body' => '', 'headers' => '', 'error' => $errno ? ('curl errno ' . $errno . ': ' . $curlError) : $curlError];
    }
    $headerSize = strpos($raw, "\r\n\r\n");
    $respHeaders = $headerSize !== false ? substr($raw, 0, $headerSize) : '';
    $respBody = $headerSize !== false ? substr($raw, $headerSize + 4) : $raw;
    return ['status' => $status, 'body' => $respBody, 'headers' => $respHeaders, 'error' => $errno ? 'curl errno ' . $errno : ''];
}

/**
 * @param array{status:int,body:string,headers:string,error:string} $response
 * @return array{ok:bool,inserted:int,note:string}
 */
function mbqa_parse_import_response(array $response): array
{
    if ((int)$response['status'] !== 200) {
        return ['ok' => false, 'inserted' => 0, 'note' => 'Import HTTP ' . (int)$response['status']];
    }

    $decoded = json_decode((string)$response['body'], true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'inserted' => 0, 'note' => 'Import response is not JSON: ' . substr((string)$response['body'], 0, 160)];
    }

    $inserted = (int)($decoded['inserted'] ?? 0);
    $ok = !empty($decoded['ok']) && $inserted > 0;
    if ($ok) {
        return ['ok' => true, 'inserted' => $inserted, 'note' => 'inserted=' . $inserted];
    }

    $errors = array_filter((array)($decoded['errors'] ?? []), static function ($value): bool {
        return trim((string)$value) !== '';
    });
    $message = (string)($decoded['message'] ?? $decoded['error'] ?? '');
    if ($message === '' && !empty($errors)) {
        $message = implode('; ', array_slice(array_map('strval', $errors), 0, 3));
    }
    if ($message === '') {
        $message = 'Import did not insert any rows';
    }

    return ['ok' => false, 'inserted' => $inserted, 'note' => $message . '; inserted=' . $inserted];
}

function mbqa_extract_csrf(string $html): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function mbqa_has_fatal(string $html): bool
{
    return stripos($html, 'Fatal error') !== false
        || stripos($html, 'Parse error') !== false
        || stripos($html, 'Uncaught Error') !== false;
}

function mbqa_row_ids(string $html): array
{
    $ids = [];
    if (preg_match_all('/name="ids\[\]"\s+value="(\d+)"/', $html, $m)) {
        foreach ($m[1] as $id) {
            $ids[(int)$id] = (int)$id;
        }
    }
    if (preg_match_all('/name="id"\s+value="(\d+)"/', $html, $m2)) {
        foreach ($m2[1] as $id) {
            $ids[(int)$id] = (int)$id;
        }
    }
    // Why: read-only modules (e.g. audit_logs) expose view/edit links without bulk ids[] or delete forms.
    if (preg_match_all('/(?:view|edit)\.php\?id=(\d+)/i', $html, $m3)) {
        foreach ($m3[1] as $id) {
            $ids[(int)$id] = (int)$id;
        }
    }
    return array_values($ids);
}

function mbqa_step_result(string $step, bool $ok, string $note = ''): array
{
    mbqa_browser_progress_emit($step);

    return [
        'step' => $step,
        'status' => $ok ? 'Pass' : 'Fail',
        'notes' => $note,
    ];
}

/**
 * Whether a Pass step note belongs in the markdown "Skip (quick index)" (build_report).
 * Matches runner N/A and Skip prefixes (e.g. "N/A (12 rows <= perPage 25)", "Skip (bespoke smoke)").
 */
function mbqa_step_note_is_skip_quick_index(string $note): bool
{
    $note = trim($note);

    return $note !== '' && preg_match('/^(?:Skip|N\/A)\b/i', $note) === 1;
}

/**
 * Record a step result; per-module exception map forces Pass + map note when the step is listed.
 */
function mbqa_step_result_for_module(string $moduleSlug, string $step, bool $ok, string $note = ''): array
{
    $exceptionNote = mbqa_runner_module_step_exception_note($moduleSlug, $step);
    if ($exceptionNote !== null) {
        return mbqa_step_result($step, true, $exceptionNote);
    }

    return mbqa_step_result($step, $ok, $note);
}

function mbqa_index_is_empty(string $html): bool
{
    return stripos($html, 'No records found') !== false;
}

/**
 * Mirrors modules/user_companies cr_is_admin_user_company_row(): Admin role, not username label.
 */
function mbqa_is_admin_user_company_assignment_row(mysqli $conn, array $row): bool
{
    $userId = (int)($row['user_id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    $sql = 'SELECT u.username, ur.name AS role_name FROM users u'
        . ' LEFT JOIN user_roles ur ON ur.id = u.role_id'
        . ' WHERE u.id=' . $userId . ' LIMIT 1';
    $res = mysqli_query($conn, $sql);
    $userRow = $res ? mysqli_fetch_assoc($res) : null;
    if (!is_array($userRow)) {
        return false;
    }

    $username = (string)($userRow['username'] ?? '');
    if ($username !== '' && itm_user_company_assignment_bypasses_admin_delete_guard($username)) {
        return false;
    }

    return isset($userRow['role_name']) && strcasecmp((string)$userRow['role_name'], 'Admin') === 0;
}

/**
 * Count tenant user_companies rows that clear_table should delete (non-Admin assignments).
 */
function mbqa_user_companies_non_admin_row_count(mysqli $conn, int $companyId): int
{
    if ($companyId <= 0) {
        return -1;
    }

    $count = 0;
    $res = mysqli_query($conn, 'SELECT id, user_id FROM user_companies WHERE company_id=' . (int)$companyId);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if (!mbqa_is_admin_user_company_assignment_row($conn, $row)) {
            $count++;
        }
    }

    return $count;
}

function mbqa_clear_table_index_ok(string $moduleSlug, string $html, ?mysqli $conn = null, int $companyId = 0): bool
{
    if ($moduleSlug === 'user_companies' && $conn instanceof mysqli && $companyId > 0) {
        return mbqa_user_companies_non_admin_row_count($conn, $companyId) === 0;
    }

    if (mbqa_index_is_empty($html)) {
        return true;
    }

    return false;
}

/**
 * Per-module Tier A steps that are not executed (slug => step => Pass/N/A note in report only).
 * All other steps run normally (e.g. user_companies still runs sample_data).
 *
 * @return array<string, string>
 */
function mbqa_runner_facade_routing_na_steps(): array
{
    return [
        'clear' => 'N/A routing',
        'sample_data' => 'N/A routing',
        'add' => 'N/A routing',
        'pagination' => 'N/A routing',
        'bulk_cancel' => 'N/A routing',
        'bulk_delete' => 'N/A routing',
        'search' => 'N/A routing',
        'sort' => 'N/A routing',
        'create' => 'N/A routing',
        'view' => 'N/A routing',
        'edit' => 'N/A routing',
        'list_all' => 'N/A routing',
        'export_pdf' => 'N/A routing',
        'export_xlsx' => 'N/A routing',
        'import_db' => 'N/A routing',
        'single_delete' => 'N/A routing',
        'clear_table' => 'N/A routing',
    ];
}

/**
 * Tier C equipment-type façades (modules/is_*) — routing smoke only, not full CRUD matrix.
 */
function mbqa_is_facade_routing_module(string $slug): bool
{
    return strpos($slug, 'is_') === 0;
}

/**
 * @return array<string, array<string, string>>
 */
function mbqa_runner_module_step_exceptions(): array
{
    $map = [
        'user_companies' => [
            'create' => 'N/A (module has no create screen)',
            'add' => 'N/A (no random bulk rows for junction assignments)',
            'import_db' => 'N/A (no Excel import round-trip)',
        ],
        // Why: database.sql has no INSERT rows for patches_updates; sample_data start + end restore are N/A in QA.
        'patches_updates' => [
            'sample_data' => 'No sample rows found in database.sql for this module.',
        ],
        // Why: Explorer not a standart module CRUD
        'explorer' => [
            'ui_check' => 'N/A (Not standart Module CRUD)',
            'clear' => 'N/A (Not standart Module CRUD)',
            'sample_data' => 'N/A (Not standart Module CRUD)',
            'add' => 'N/A (Not standart Module CRUD)',
            'pagination' => 'N/A (Not standart Module CRUD)',
            'bulk_cancel' => 'N/A (Not standart Module CRUD)',
            'bulk_delete' => 'N/A (Not standart Module CRUD)',
            'search' => 'N/A (Not standart Module CRUD)',
            'sort' => 'N/A (Not standart Module CRUD)',
            'create' => 'N/A (Not standart Module CRUD)',
            'view' => 'N/A (Not standart Module CRUD)',
            'edit' => 'N/A (Not standart Module CRUD)',
            'list_all' => 'N/A (Not standart Module CRUD)',
            'export_pdf' => 'N/A (Not standart Module CRUD)',
            'export_xlsx' => 'N/A (Not standart Module CRUD)',
            'clear_table' => 'N/A (Not standart Module CRUD)',
            'import_db' => 'N/A (Not standart Module CRUD)',
            'single_delete' => 'N/A (Not standart Module CRUD)',
        ],    
        // Why: Employee System Access shows the available systems for each Emplyoee, auto populated.
        'employee_system_access' => [
            'sample_data' => 'N/A (Auto populated)',
            'create' => 'N/A (Auto populated)',
            'bulk_cancel' => 'N/A (Auto populated)',
            'bulk_delete' => 'N/A (Auto populated)',
            'list_all' => 'N/A (Auto populated)',
            'clear_table' => 'N/A (Auto populated)',
            'single_delete' => 'N/A (Auto populated)',
        ],
        // Why: Employee Assignment History database.sql has no INSERT rows.
        'employee_assignment_history' => [
            'sample_data' => 'No sample rows found in database.sql for this module.',
        ],
        // Why: database.sql has no INSERT rows for approvers; sample_data start + end restore are N/A in QA.
        'approvers' => [
            'sample_data' => 'No sample rows found in database.sql for this module.',
        ],
        // Why: IP address rows are generated from live subnets, not stored as static database.sql samples.
        'ip_addresses' => [
            'sample_data' => 'N/A (IP addresses are generated from subnets, not database.sql samples)',
        ],
        // Why: bulk random rows on equipment_types scaffold modules/is_mbqa_* folders; avoid module creations in QA.
        'equipment_types' => [
            'add' => 'N/A (Bulk random rows — avoid module creations)',
        ],
        // Why: System Access shows the available systems, auto populated.
        'system_access' => [
            'clear_table' => 'N/A (Auto populated)',
            'sample_data' => 'N/A (Auto populated)',
        ],
        // Why: Idf Links dont have Sample data.
        'idf_links' => [
            'sample_data' => 'No sample rows found in database.sql for this module.',
        ],
        'idf_ports' => [
            'sample_data' => 'No sample rows found in database.sql for this module.',
        ],
        // Why: IDF positions need idf_id + device_type parents; HTTP sample seed from database.sql is unreliable in QA.
        'idf_positions' => [
            'sample_data' => 'No sample rows found in database.sql for this module.',
        ],
        // Why: Users manages application, protected; avoid users profiles creations in QA.
        'users' => [
            'clear' => 'N/A (users module is user creation)',
            'sample_data' => 'N/A (users module is user creation)',
            'add' => 'N/A (users module is user creation)',
            'bulk_cancel' => 'N/A (users module is user creation)',
            'bulk_delete' => 'N/A (users module is user creation)',
            'clear_table' => 'N/A (users module is user creation)',
            'single_delete' => 'N/A (users module is user creation)',
        ],
        // Why: User UI Configurarion, not standard tenant CRUD rows.
        'ui_configuration' => [
            'add' => 'N/A (User UI Configurarion capped by 1)',
            'create' => 'N/A (User UI Configurarion)',
            'pagination' => 'N/A (User UI Configurarion capped by 1)',
            'bulk_cancel' => 'N/A (User UI Configurarion)',
            'bulk_delete' => 'N/A (User UI Configurarion)',  
        ],
        // Why: settings manages application configuration and backup files, not standard tenant CRUD rows.
        'settings' => [
            'mysql' => 'N/A (settings module is configuration/backup UI)',
            'ui_check' => 'N/A (settings module is configuration/backup UI)',
            'clear' => 'N/A (settings module is configuration/backup UI)',
            'sample_data' => 'N/A (settings module is configuration/backup UI)',
            'add' => 'N/A (settings module is configuration/backup UI)',
            'pagination' => 'N/A (settings module is configuration/backup UI)',
            'bulk_cancel' => 'N/A (settings module is configuration/backup UI)',
            'bulk_delete' => 'N/A (settings module is configuration/backup UI)',
            'search' => 'N/A (settings module is configuration/backup UI)',
            'sort' => 'N/A (settings module is configuration/backup UI)',
            'create' => 'N/A (settings module is configuration/backup UI)',
            'view' => 'N/A (settings module is configuration/backup UI)',
            'edit' => 'N/A (settings module is configuration/backup UI)',
            'list_all' => 'N/A (settings module is configuration/backup UI)',
            'clear_table' => 'N/A (settings module is configuration/backup UI)',
            'import_db' => 'N/A (settings module is configuration/backup UI)',
            'single_delete' => 'N/A (settings module is configuration/backup UI)',
        ],
        // Why: audit_logs is a read-only evidence centre; QA documents N/A steps and seeds rows only via add DB fallback.
        'audit_logs' => [
            'list' => 'N/A (read-only audit centre)',
            'add' => 'N/A (read-only audit centre)',
            'create' => 'N/A (read-only audit centre)',
            'edit' => 'N/A (read-only audit centre)',
            'list_all' => 'N/A (read-only audit centre)',
            'bulk_cancel' => 'N/A (no bulk-delete form in HTML)',
            'bulk_delete' => 'N/A (read-only audit centre; delete disabled)',
            'clear_table' => 'N/A (read-only audit centre; delete disabled)',
            'import_db' => 'N/A (read-only audit centre)',
            'single_delete' => 'N/A (read-only audit centre; delete disabled)',
            'sample_data' => 'N/A (read-only audit centre)',
        ],
        // Why: attempts is a global security audit table (no company_id); list smoke only — no tenant clear/CRUD QA matrix.
        'attempts' => [
            'clear' => 'N/A (global audit table)',
            'sample_data' => 'N/A (global audit table)',
            'add' => 'N/A (global audit table)',
            'pagination' => 'N/A (global audit table)',
            'bulk_cancel' => 'N/A (global audit table)',
            'bulk_delete' => 'N/A (global audit table)',
            'search' => 'N/A (global audit table)',
            'sort' => 'N/A (global audit table)',
            'create' => 'N/A (global audit table)',
            'view' => 'N/A (global audit table)',
            'edit' => 'N/A (global audit table)',
            'list_all' => 'N/A (global audit table)',
            'export_pdf' => 'N/A (global audit table)',
            'export_xlsx' => 'N/A (global audit table)',
            'clear_table' => 'N/A (global audit table)',
            'import_db' => 'N/A (global audit table)',
            'single_delete' => 'N/A (global audit table)',
        ],
        // Why: sidebar layout rows are seeded from default ui_config layout, not standard CRUD/sample/add/import QA.
        'user_sidebar_preferences' => [
            'sample_data' => 'N/A (seeds default sidebar layout)',
            'add' => 'N/A (seeds default sidebar layout)',
            'pagination' => 'N/A (seeds default sidebar layout)',
            'bulk_cancel' => 'N/A (seeds default sidebar layout)',
            'bulk_delete' => 'N/A (seeds default sidebar layout)',
            'create' => 'N/A (seeds default sidebar layout)',
            'view' => 'N/A (seeds default sidebar layout)',
            'edit' => 'N/A (seeds default sidebar layout)',
            'export_pdf' => 'N/A (seeds default sidebar layout)',
            'export_xlsx' => 'N/A (seeds default sidebar layout)',
            'clear_table' => 'N/A (seeds default sidebar layout)',
            'import_db' => 'N/A (seeds default sidebar layout)',
            'single_delete' => 'N/A (seeds default sidebar layout)',
        ],
    ];

    $routingSteps = mbqa_runner_facade_routing_na_steps();
    foreach (itm_canonical_equipment_is_module_names() as $slug) {
        $map[$slug] = array_merge($map[$slug] ?? [], $routingSteps);
    }

    return $map;
}

function mbqa_runner_module_step_exception_note(string $slug, string $step): ?string
{
    $map = mbqa_runner_module_step_exceptions();
    if (isset($map[$slug][$step])) {
        return (string)$map[$slug][$step];
    }
    if (mbqa_is_facade_routing_module($slug) && isset(mbqa_runner_facade_routing_na_steps()[$step])) {
        return 'N/A routing';
    }

    return null;
}

/**
 * @deprecated Use mbqa_runner_module_step_exceptions() and check the create key.
 */
function mbqa_modules_without_create_screen(): array
{
    $slugs = [];
    foreach (mbqa_runner_module_step_exceptions() as $moduleSlug => $steps) {
        if (isset($steps['create'])) {
            $slugs[] = $moduleSlug;
        }
    }

    return $slugs;
}

/**
 * @deprecated Use mbqa_runner_module_step_exceptions().
 */
function mbqa_modules_without_direct_create_php(): array
{
    return mbqa_modules_without_create_screen();
}

/**
 * Tier A create step: GET create.php when the module supports create.
 *
 * @return array{ok:bool,note:string}
 */
function mbqa_run_create_screen_step(string $moduleUrl, string $modulesDir, string $slug, string $cookieFile): array
{
    $createNa = mbqa_runner_module_step_exception_note($slug, 'create');
    if ($createNa !== null) {
        return ['ok' => true, 'note' => $createNa];
    }

    $createPath = $modulesDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'create.php';

    if (!is_file($createPath)) {
        return ['ok' => false, 'note' => 'missing create.php'];
    }

    $create = mbqa_http($moduleUrl . 'create.php', 'GET', null, [], $cookieFile);
    $htmlCheck = mbqa_html_step_create($create['body'], $create['status']);

    return [
        'ok' => $htmlCheck['ok'],
        'note' => $htmlCheck['ok'] ? $htmlCheck['note'] : $htmlCheck['note'],
    ];
}

function mbqa_index_has_sample_seed_error(string $html): bool
{
    return stripos($html, 'No sample rows found in database.sql') !== false;
}

/** Pulls the flash error banner text after a failed sample-data POST. */
function mbqa_index_sample_seed_flash_note(string $html): string
{
    if (preg_match('/class="[^"]*alert[^"]*"[^>]*>(.*?)<\/div>/is', $html, $m)) {
        $text = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text !== '') {
            return $text;
        }
    }

    return '';
}

/**
 * Mirrors table-tools.js: read list table headers/rows for Excel export → import round-trip.
 *
 * @return array<int, array<int, string>>
 */
function mbqa_extract_table_export_rows(string $html): array
{
    if (!class_exists('DOMDocument')) {
        return [];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html) === false) {
        libxml_clear_errors();
        return [];
    }
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $tables = $xpath->query('//div[contains(@class,"card")]//table[thead and tbody]');
    if ($tables === false || $tables->length === 0) {
        $tables = $xpath->query('//table[thead and tbody]');
    }
    if ($tables === false || $tables->length === 0) {
        return [];
    }

    /** @var DOMElement $table */
    $table = $tables->item(0);
    $skipHeaders = ['actions', 'select to delete', ''];

    $headers = [];
    $columnIndices = [];
    $headerNodes = $xpath->query('.//thead/tr[1]/th', $table);
    if ($headerNodes !== false) {
        foreach ($headerNodes as $colIndex => $th) {
            $label = mbqa_normalize_cell_text($th->textContent ?? '');
            $key = strtolower(trim($label));
            if (in_array($key, $skipHeaders, true)) {
                continue;
            }
            if ($key === '' && $xpath->query('.//input[@type="checkbox"]', $th)->length > 0) {
                continue;
            }
            $columnIndices[] = (int)$colIndex;
            $headers[] = $label;
        }
    }

    if (empty($headers)) {
        return [];
    }

    $rows = [$headers];
    $bodyRows = $xpath->query('.//tbody/tr', $table);
    if ($bodyRows === false) {
        return $rows;
    }

    foreach ($bodyRows as $tr) {
        $cells = $xpath->query('./td', $tr);
        if ($cells === false || $cells->length === 0) {
            continue;
        }
        $row = [];
        foreach ($columnIndices as $colIndex) {
            $td = $cells->item($colIndex);
            $row[] = $td instanceof DOMElement
                ? mbqa_export_cell_text($td, $xpath)
                : '';
        }
        if (count($row) === count($headers) && implode('', $row) !== '') {
            $rows[] = $row;
        }
    }

    return count($rows) >= 2 ? $rows : [];
}

function mbqa_export_cell_text(DOMElement $cell, DOMXPath $xpath): string
{
    if ($cell->hasAttribute('data-itm-export-value')) {
        return mbqa_normalize_cell_text($cell->getAttribute('data-itm-export-value'));
    }

    $exportNodes = $xpath->query('.//*[@data-itm-export-value]', $cell);
    if ($exportNodes !== false && $exportNodes->length > 0) {
        $node = $exportNodes->item(0);
        if ($node instanceof DOMElement) {
            return mbqa_normalize_cell_text($node->getAttribute('data-itm-export-value'));
        }
    }

    return mbqa_normalize_cell_text($cell->textContent ?? '');
}

function mbqa_normalize_cell_text(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function mbqa_index_has_export_buttons(string $html): array
{
    // Why: table-tools.js injects Export PDF/Excel at runtime; CLI runner only sees the server HTML + shared script tag.
    $hasTableTools = stripos($html, 'table-tools.js') !== false && stripos($html, '<table') !== false;

    return [
        'pdf' => $hasTableTools
            || stripos($html, 'Export PDF') !== false
            || stripos($html, '📄 Export PDF') !== false,
        'xlsx' => $hasTableTools
            || stripos($html, 'Export Excel') !== false
            || stripos($html, '📗 Export Excel') !== false,
    ];
}

function mbqa_index_has_xlsx_library(string $html): bool
{
    return stripos($html, 'xlsx.full.min.js') !== false;
}

/**
 * HTTP sample seed, then database.sql seed (and FK parents) when the UI reports missing SQL samples.
 *
 * @return array{ok:bool, note:string, html:string, csrf:string, na:bool}
 */
function mbqa_ensure_sample_data(
    mysqli $conn,
    string $slug,
    int $companyId,
    string $moduleUrl,
    string $cookieFile
): array {
    global $sampleSeedPrerequisites;

    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    $csrf = mbqa_extract_csrf($index['body']);
    $hasSampleBtn = stripos($index['body'], 'name="add_sample_data"') !== false
        || stripos($index['body'], 'Add sample data') !== false;

    if (!$hasSampleBtn) {
        return ['ok' => true, 'note' => 'N/A (no handler)', 'html' => $index['body'], 'csrf' => $csrf, 'na' => true];
    }

    if (!mbqa_index_is_empty($index['body'])) {
        return ['ok' => true, 'note' => 'N/A (rows exist)', 'html' => $index['body'], 'csrf' => $csrf, 'na' => true];
    }

    $_SESSION['company_id'] = $companyId;
    mbqa_seed_lookup_parents_for_table($conn, $slug, $companyId);

    if ($csrf !== '') {
        mbqa_http(
            $moduleUrl . 'index.php',
            'POST',
            http_build_query(['add_sample_data' => '1', 'csrf_token' => $csrf]),
            ['Content-Type: application/x-www-form-urlencoded'],
            $cookieFile
        );
        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $csrf = mbqa_extract_csrf($index['body']);
        if (!mbqa_index_is_empty($index['body']) && !mbqa_index_has_sample_seed_error($index['body'])) {
            return ['ok' => true, 'note' => 'HTTP sample seed', 'html' => $index['body'], 'csrf' => $csrf, 'na' => false];
        }
    }

    if (!function_exists('itm_seed_table_from_database_sql')) {
        $flash = mbqa_index_sample_seed_flash_note($index['body']);
        $note = $flash !== '' ? $flash : 'Still empty; itm_seed_table_from_database_sql missing';

        return ['ok' => false, 'note' => $note, 'html' => $index['body'], 'csrf' => $csrf, 'na' => false];
    }

    $seedErr = '';
    $inserted = itm_seed_table_from_database_sql($conn, $slug, $companyId, $seedErr);
    if ($inserted <= 0) {
        $seedErr = '';
        $inserted = itm_seed_table_from_database_sql($conn, $slug, $companyId, $seedErr);
    }

    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    $csrf = mbqa_extract_csrf($index['body']);
    $ok = !mbqa_index_is_empty($index['body']);

    if ($ok) {
        $note = $inserted > 0
            ? ('DB sample seed (' . $inserted . ' row(s) from database.sql)')
            : 'DB sample seed (rows present after FK parent seed)';
        return ['ok' => true, 'note' => $note, 'html' => $index['body'], 'csrf' => $csrf, 'na' => false];
    }

    $flash = mbqa_index_sample_seed_flash_note($index['body']);
    $note = $seedErr !== '' ? $seedErr : ($flash !== '' ? $flash : 'Still empty or seed error');
    if (mbqa_index_has_sample_seed_error($index['body'])) {
        $note = 'No sample rows in database.sql (HTTP + DB seed)';
    }
    return ['ok' => false, 'note' => $note, 'html' => $index['body'], 'csrf' => $csrf, 'na' => false];
}

function mbqa_error_log_path(): string
{
    return (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__) . DIRECTORY_SEPARATOR) . 'error_log.txt';
}

function mbqa_error_log_byte_offset(): int
{
    $path = mbqa_error_log_path();

    return is_file($path) ? (int)filesize($path) : 0;
}

/**
 * HTTP sample seed at end of module QA (after single_delete); falls back to database.sql when HTTP seed leaves the list empty.
 *
 * @return array{ok:bool,note:string,na:bool,html?:string}
 */
function mbqa_http_sample_seed_end(string $moduleUrl, string $cookieFile, ?mysqli $conn = null, string $slug = '', int $companyId = 0): array
{
    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    $csrf = mbqa_extract_csrf($index['body']);
    $hasSampleBtn = stripos($index['body'], 'name="add_sample_data"') !== false
        || stripos($index['body'], 'Add sample data') !== false;

    if (!$hasSampleBtn) {
        return ['ok' => true, 'note' => 'N/A (no handler)', 'na' => true];
    }

    if (!mbqa_index_is_empty($index['body'])) {
        return ['ok' => true, 'note' => 'N/A (rows exist)', 'na' => true];
    }

    if ($csrf === '') {
        return ['ok' => false, 'note' => 'No CSRF for sample seed', 'na' => false];
    }

    mbqa_http(
        $moduleUrl . 'index.php',
        'POST',
        http_build_query(['add_sample_data' => '1', 'csrf_token' => $csrf]),
        ['Content-Type: application/x-www-form-urlencoded'],
        $cookieFile
    );
    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    $ok = !mbqa_index_is_empty($index['body']) && !mbqa_index_has_sample_seed_error($index['body']);

    if (!$ok && $conn instanceof mysqli && $slug !== '' && $companyId > 0 && function_exists('itm_seed_table_from_database_sql')) {
        $_SESSION['company_id'] = $companyId;
        if (function_exists('mbqa_seed_lookup_parents_for_table')) {
            mbqa_seed_lookup_parents_for_table($conn, $slug, $companyId);
        }
        $seedErr = '';
        $inserted = itm_seed_table_from_database_sql($conn, $slug, $companyId, $seedErr);
        if ($inserted <= 0) {
            $seedErr = '';
            $inserted = itm_seed_table_from_database_sql($conn, $slug, $companyId, $seedErr);
        }
        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $csrf = mbqa_extract_csrf($index['body']);
        $ok = !mbqa_index_is_empty($index['body']) && !mbqa_index_has_sample_seed_error($index['body']);
        if ($ok) {
            $note = $inserted > 0
                ? ('DB sample seed restore (' . $inserted . ' row(s) from database.sql)')
                : 'DB sample seed restore (rows present after FK parent seed)';
            return ['ok' => true, 'note' => $note, 'na' => false, 'html' => $index['body']];
        }
    }

    $htmlCheck = mbqa_html_step_sample_data($index['body'], $index['status'], true);
    $note = $ok ? 'HTTP sample seed' : 'HTTP sample seed failed or empty';
    if ($ok && $htmlCheck['note'] !== '') {
        $note .= '; ' . $htmlCheck['note'];
    }
    if ($ok && !$htmlCheck['ok']) {
        $ok = false;
        $note = $htmlCheck['note'];
    }

    return [
        'ok' => $ok,
        'note' => $note,
        'na' => false,
        'html' => $index['body'],
    ];
}

/**
 * @return array{ok:bool,note:string,count:int}
 */
function mbqa_read_error_log_since(int $byteOffset): array
{
    $path = mbqa_error_log_path();
    if (!is_file($path)) {
        return ['ok' => true, 'note' => '0 errors', 'count' => 0];
    }

    $size = (int)filesize($path);
    if ($byteOffset >= $size) {
        return ['ok' => true, 'note' => '0 errors', 'count' => 0];
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return ['ok' => false, 'note' => 'Unable to read error_log.txt', 'count' => 0];
    }

    fseek($handle, $byteOffset);
    $chunk = (string)stream_get_contents($handle);
    fclose($handle);

    $lines = [];
    foreach (preg_split('/\r\n|\r|\n/', $chunk) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    $count = count($lines);
    if ($count === 0) {
        return ['ok' => true, 'note' => '0 errors', 'count' => 0];
    }

    $note = $count . ' error(s)';
    if ($count <= 2) {
        $note .= ': ' . implode(' | ', array_map(static function ($l) {
            return substr($l, 0, 160);
        }, $lines));
    } else {
        $note .= ' (first: ' . substr($lines[0], 0, 160) . '…)';
    }

    return ['ok' => false, 'note' => $note, 'count' => $count];
}

/**
 * Next archive name under ROOT_PATH: error_log-1.txt, error_log-2.txt, …
 */
function mbqa_next_error_log_archive_path(): string
{
    $dir = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__) . DIRECTORY_SEPARATOR;
    $n = 1;
    while (is_file($dir . 'error_log-' . $n . '.txt')) {
        $n++;
    }

    return $dir . 'error_log-' . $n . '.txt';
}

/**
 * Move the live log aside so this module only sees new lines (keeps history on disk).
 */
function mbqa_rotate_error_log_file(): array
{
    $path = mbqa_error_log_path();
    if (!is_file($path)) {
        return ['ok' => true, 'archive' => '', 'note' => 'no error_log.txt'];
    }

    $archive = mbqa_next_error_log_archive_path();
    if (@rename($path, $archive)) {
        $base = basename($archive);

        return ['ok' => true, 'archive' => $archive, 'note' => 'renamed error_log.txt → ' . $base];
    }

    return ['ok' => false, 'archive' => '', 'note' => ''];
}

/**
 * Per-module error_log scope: rotate when possible; otherwise read only bytes appended after this point.
 *
 * @return array{offset:int,note:string}
 */
function mbqa_begin_module_error_log_scope(): array
{
    $rotate = mbqa_rotate_error_log_file();
    if ($rotate['ok']) {
        return [
            'offset' => 0,
            'note' => $rotate['note'] !== '' ? $rotate['note'] : 'no error_log.txt',
        ];
    }

    $offset = mbqa_error_log_byte_offset();

    return [
        'offset' => $offset,
        'note' => 'error_log.txt not rotated; checking new lines from offset ' . $offset,
    ];
}

/**
 * Build import rows from exported table data (adds one QA row for insert verification).
 *
 * @param array<int, array<int, string>> $exportRows
 * @return array<int, array<int, string>>
 */
function mbqa_build_import_rows_from_export(array $exportRows): array
{
    if (count($exportRows) < 2) {
        return $exportRows;
    }

    $template = $exportRows[1];
    $newRow = array_map(static function ($cell) {
        $text = trim((string)$cell);
        return strcasecmp($text, 'null') === 0 ? '' : $text;
    }, $template);
    $suffix = date('YmdHis');
    foreach ($exportRows[0] as $i => $header) {
        $headerKey = strtolower(trim(preg_replace('/\s+/', ' ', (string)$header)));
        if ($headerKey === 'id') {
            $newRow[$i] = '';
            continue;
        }
        if (strpos($headerKey, 'invoice') !== false) {
            $newRow[$i] = 'INV-QA-IMPORT-' . $suffix;
        } elseif (
            $headerKey === 'description'
            || $headerKey === 'name'
            || strpos($headerKey, 'code') !== false
            || strpos($headerKey, 'title') !== false
        ) {
            $base = trim((string)($template[$i] ?? ''));
            $newRow[$i] = ($base !== '' ? $base . ' ' : '') . 'QA-IMPORT-' . $suffix;
        }
    }

    // Why: Import only the derived row so FK labels/values from Export Excel match one insert attempt.
    return [$exportRows[0], $newRow];
}

function mbqa_humanize_field_label(string $field): string
{
    if (function_exists('itm_humanize_field_name')) {
        return itm_humanize_field_name($field);
    }

    $label = preg_replace('/_id$/', '', trim($field));
    if ($label === 'id') {
        return 'ID';
    }

    return ucwords(str_replace('_', ' ', $label));
}

/**
 * Aligns export-derived headers with flattened CRUD import fieldByLabel keys (itm_humanize_field_name / cr_humanize_field).
 *
 * @param array<int, array<int, string>> $importRows
 * @return array<int, array<int, string>>
 */
function mbqa_align_import_headers_for_crud_import(mysqli $conn, string $table, array $importRows): array
{
    if (count($importRows) < 1 || !function_exists('itm_humanize_field_name') || !itm_is_safe_identifier($table)) {
        return $importRows;
    }

    $columnNames = mbqa_table_column_names($conn, $table);
    $headers = $importRows[0];
    foreach ($headers as $i => $header) {
        $col = mbqa_match_list_header_to_column((string)$header, $columnNames);
        if ($col !== null) {
            $headers[$i] = itm_humanize_field_name($col);
        }
    }
    $importRows[0] = $headers;

    return $importRows;
}

/**
 * @return array<string, true> column name => true
 */
function mbqa_table_column_names(mysqli $conn, string $table): array
{
    if (!itm_is_safe_identifier($table)) {
        return [];
    }

    $cols = [];
    $tableEsc = '`' . str_replace('`', '``', $table) . '`';
    $res = mysqli_query($conn, 'SHOW COLUMNS FROM ' . $tableEsc);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $field = (string)($row['Field'] ?? '');
        if ($field !== '' && itm_is_safe_identifier($field)) {
            $cols[$field] = true;
        }
    }

    return $cols;
}

/**
 * Maps list-table header text (module-specific labels) back to a DB column for import payloads.
 */
function mbqa_match_list_header_to_column(string $header, array $columnNames): ?string
{
    $normHeader = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
    if ($normHeader === '' || $normHeader === 'id') {
        return null;
    }

    foreach (array_keys($columnNames) as $field) {
        if (in_array($field, ['id', 'company_id', 'created_at', 'updated_at'], true)) {
            continue;
        }
        if (strtolower(mbqa_humanize_field_label($field)) === $normHeader) {
            return $field;
        }
    }

    foreach (array_keys($columnNames) as $field) {
        if (!preg_match('/_id$/', (string)$field)) {
            continue;
        }
        $stem = strtolower(preg_replace('/_id$/', '', $field));
        $stemSpaced = str_replace('_', ' ', $stem);
        if ($stem !== '' && (strpos($normHeader, $stem) !== false || strpos($normHeader, $stemSpaced) !== false)) {
            return $field;
        }
    }

    return null;
}

/**
 * Count INSERT sample rows for one table in database.sql (Tier A module slug = table name).
 */
function mbqa_database_sql_insert_row_count(string $table): int
{
    if (!itm_is_safe_identifier($table)) {
        return 0;
    }

    $sqlPath = ROOT_PATH . 'database.sql';
    if (!is_file($sqlPath)) {
        return -1;
    }

    $sqlBody = @file_get_contents($sqlPath);
    if ($sqlBody === false) {
        return -1;
    }
    if (!function_exists('itm_parse_database_sql_inserts')) {
        return -1;
    }

    $parsed = itm_parse_database_sql_inserts($sqlBody, $table);

    return count($parsed[$table] ?? []);
}

/**
 * Tier A preflight: record how many INSERT rows database.sql defines for this module table.
 * Why: sample_data / end restore and import_db expectations depend on seed rows (0 vs N).
 *
 * @return array{ok:bool, note:string}
 */
function mbqa_mysql_database_sql_seed_check(string $table): array
{
    $count = mbqa_database_sql_insert_row_count($table);
    if ($count < 0) {
        return ['ok' => false, 'note' => 'database.sql missing or unreadable'];
    }
    if ($count === 0) {
        return ['ok' => true, 'note' => 'database.sql: 0 row(s) (empty)'];
    }

    return ['ok' => true, 'note' => 'database.sql: ' . $count . ' row(s)'];
}

/**
 * Raw insertable values keyed by column name from database.sql (tenant-resolved FK ids).
 *
 * @return array<string, string>
 */
function mbqa_database_sql_values_by_column(mysqli $conn, string $table, int $companyId): array
{
    if (!function_exists('itm_parse_database_sql_inserts') || !itm_is_safe_identifier($table) || $companyId <= 0) {
        return [];
    }

    $sqlPath = ROOT_PATH . 'database.sql';
    if (!is_file($sqlPath)) {
        return [];
    }

    $sqlBody = @file_get_contents($sqlPath);
    if ($sqlBody === false) {
        return [];
    }

    $parsed = itm_parse_database_sql_inserts($sqlBody, $table);
    $tableRows = $parsed[$table] ?? [];
    if (empty($tableRows)) {
        return [];
    }

    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $suffix = date('YmdHis');
    $chosen = null;

    foreach ($tableRows as $rowEntry) {
        $rawColumns = $rowEntry['columns'] ?? [];
        $rawValues = $rowEntry['values'] ?? [];
        $rowCompanyId = null;
        foreach ($rawColumns as $index => $columnToken) {
            $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
            if ($columnName !== 'company_id') {
                continue;
            }
            $rawCompanyToken = trim((string)($rawValues[$index] ?? ''), "'\"");
            $rowCompanyId = (int)$rawCompanyToken;
            break;
        }
        if ($rowCompanyId !== null && $rowCompanyId !== $companyId) {
            continue;
        }
        $chosen = $rowEntry;
        break;
    }

    if ($chosen === null) {
        $chosen = $tableRows[0];
    }

    $byColumn = [];
    foreach ($chosen['columns'] as $index => $columnToken) {
        $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
        if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
            continue;
        }
        if (in_array($columnName, ['id', 'company_id', 'created_at', 'updated_at'], true)) {
            continue;
        }

        $valueToken = trim((string)($chosen['values'][$index] ?? ''), "'\"");
        if (isset($fkMap[$columnName])) {
            $valueToken = mbqa_resolve_tenant_fk_import_value($conn, $fkMap[$columnName], $companyId, (string)$valueToken);
        }

        $key = strtolower(mbqa_humanize_field_label($columnName));
        if (strpos($key, 'invoice') !== false) {
            $valueToken = 'INV-QA-IMPORT-' . $suffix;
        } elseif ($key === 'description' || $key === 'name' || strpos($key, 'code') !== false) {
            $valueToken = ($valueToken !== '' ? $valueToken . ' ' : '') . 'QA-IMPORT-' . $suffix;
        }

        $byColumn[$columnName] = $valueToken;
    }

    if ($table === 'expenses' && isset($byColumn['cost_center_id'])) {
        $pickSql = 'SELECT cc.id AS cc_id, gl.id AS gl_id
            FROM cost_centers cc
            INNER JOIN gl_accounts gl ON gl.company_id = cc.company_id
            LEFT JOIN expenses e ON e.company_id = cc.company_id AND e.cost_center_id = cc.id
            WHERE cc.company_id = ' . (int)$companyId . ' AND e.id IS NULL
            ORDER BY cc.id ASC
            LIMIT 1';
        $pickRes = mysqli_query($conn, $pickSql);
        $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
        if ($pick) {
            $byColumn['cost_center_id'] = (string)(int)($pick['cc_id'] ?? 0);
            if (isset($byColumn['gl_account_id'])) {
                $byColumn['gl_account_id'] = (string)(int)($pick['gl_id'] ?? 0);
            }
        }
    }

    return $byColumn;
}

/**
 * Fallback import payload with raw DB values (FK ids) when label-based Excel import inserts 0 rows.
 *
 * @return array<int, array<int, string>>
 */
function mbqa_build_import_rows_from_db_template(mysqli $conn, string $table, int $companyId): array
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0 || !itm_table_has_column($conn, $table, 'company_id')) {
        return [];
    }

    $tableEsc = '`' . str_replace('`', '``', $table) . '`';
    $res = mysqli_query($conn, 'SELECT * FROM ' . $tableEsc . ' WHERE company_id=' . (int)$companyId . ' ORDER BY id ASC LIMIT 1');
    if (!$res || !($row = mysqli_fetch_assoc($res))) {
        return [];
    }

    $headers = [];
    $values = [];
    foreach ($row as $field => $value) {
        if ($field === 'id' || $field === 'company_id' || $field === 'created_at' || $field === 'updated_at') {
            continue;
        }
        $headers[] = mbqa_humanize_field_label((string)$field);
        $values[] = (string)$value;
    }

    if (empty($headers)) {
        return [];
    }

    $suffix = date('YmdHis');
    $importValues = $values;
    foreach ($headers as $i => $label) {
        $key = strtolower($label);
        if (strpos($key, 'invoice') !== false) {
            $importValues[$i] = 'INV-QA-IMPORT-' . $suffix;
        } elseif ($key === 'description' || $key === 'name' || strpos($key, 'code') !== false) {
            $importValues[$i] = trim($importValues[$i] . ' QA-IMPORT-' . $suffix);
        }
    }

    $rows = [$headers, $importValues];
    if ($table === 'ip_subnets') {
        $rows = mbqa_unique_ip_subnets_import_row($conn, $companyId, $rows);
    }

    return mbqa_align_import_headers_for_crud_import(
        $conn,
        $table,
        mbqa_apply_unique_scope_to_import_rows(
            $conn,
            $table,
            $companyId,
            mbqa_ensure_import_row_tenant_fk_values($conn, $table, $companyId, $rows)
        )
    );
}

/**
 * Import payload from database.sql INSERT samples (raw FK ids), when UI export labels fail to insert.
 *
 * @return array<int, array<int, string>>
 */
function mbqa_build_import_rows_from_database_sql_seed(mysqli $conn, string $table, int $companyId): array
{
    if (!function_exists('itm_parse_database_sql_inserts') || !itm_is_safe_identifier($table) || $companyId <= 0) {
        return [];
    }

    $sqlPath = ROOT_PATH . 'database.sql';
    if (!is_file($sqlPath)) {
        return [];
    }

    $sqlBody = @file_get_contents($sqlPath);
    if ($sqlBody === false) {
        return [];
    }

    $parsed = itm_parse_database_sql_inserts($sqlBody, $table);
    $tableRows = $parsed[$table] ?? [];
    if (empty($tableRows)) {
        return [];
    }

    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $suffix = date('YmdHis');
    $chosen = null;

    foreach ($tableRows as $rowEntry) {
        $rawColumns = $rowEntry['columns'] ?? [];
        $rawValues = $rowEntry['values'] ?? [];
        $rowCompanyId = null;
        foreach ($rawColumns as $index => $columnToken) {
            $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
            if ($columnName !== 'company_id') {
                continue;
            }
            $rawCompanyToken = trim((string)($rawValues[$index] ?? ''), "'\"");
            $rowCompanyId = (int)$rawCompanyToken;
            break;
        }
        if ($rowCompanyId !== null && $rowCompanyId !== $companyId) {
            continue;
        }
        $chosen = $rowEntry;
        break;
    }

    if ($chosen === null) {
        $chosen = $tableRows[0];
    }

    $headers = [];
    $values = [];
    foreach ($chosen['columns'] as $index => $columnToken) {
        $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
        if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
            continue;
        }
        if (in_array($columnName, ['id', 'company_id', 'created_at', 'updated_at'], true)) {
            continue;
        }

        $valueToken = trim((string)($chosen['values'][$index] ?? ''), "'\"");
        if (isset($fkMap[$columnName])) {
            $valueToken = mbqa_resolve_tenant_fk_import_value($conn, $fkMap[$columnName], $companyId, (string)$valueToken);
        }

        $key = strtolower(mbqa_humanize_field_label($columnName));
        if (strpos($key, 'invoice') !== false) {
            $valueToken = 'INV-QA-IMPORT-' . $suffix;
        } elseif ($key === 'description' || $key === 'name' || strpos($key, 'code') !== false) {
            $valueToken = ($valueToken !== '' ? $valueToken . ' ' : '') . 'QA-IMPORT-' . $suffix;
        }

        $headers[] = mbqa_humanize_field_label($columnName);
        $values[] = $valueToken;
    }

    if (empty($headers)) {
        return [];
    }

    $rows = [$headers, $values];
    if ($table === 'expenses') {
        $rows = mbqa_unique_expense_import_row($conn, $companyId, $rows);
    }
    if ($table === 'ip_subnets') {
        $rows = mbqa_unique_ip_subnets_import_row($conn, $companyId, $rows);
    }

    return mbqa_align_import_headers_for_crud_import(
        $conn,
        $table,
        mbqa_apply_unique_scope_to_import_rows(
            $conn,
            $table,
            $companyId,
            mbqa_ensure_import_row_tenant_fk_values($conn, $table, $companyId, $rows)
        )
    );
}

/**
 * ip_subnets.uq_ip_subnets_company_scope is (company_id, vlan_id, cidr); pick a free CIDR for import QA.
 *
 * @param array<int, array<int, string>> $sqlRows
 * @return array<int, array<int, string>>
 */
function mbqa_unique_ip_subnets_import_row(mysqli $conn, int $companyId, array $sqlRows): array
{
    if ($companyId <= 0 || count($sqlRows) < 2) {
        return $sqlRows;
    }

    $headers = $sqlRows[0];
    $values = $sqlRows[1];
    $cidrIndex = false;
    $networkIndex = false;
    $prefixIndex = false;
    $gatewayIndex = false;
    $dns1Index = false;
    $dns2Index = false;
    $vlanIndex = false;
    foreach ($headers as $i => $label) {
        $key = strtolower(trim(preg_replace('/\s+/', ' ', (string)$label)));
        if ($key === 'cidr') {
            $cidrIndex = $i;
        } elseif ($key === 'network ip') {
            $networkIndex = $i;
        } elseif ($key === 'prefix length') {
            $prefixIndex = $i;
        } elseif ($key === 'gateway ip') {
            $gatewayIndex = $i;
        } elseif ($key === 'dns 1') {
            $dns1Index = $i;
        } elseif ($key === 'dns 2') {
            $dns2Index = $i;
        } elseif ($key === 'vlan' || $key === 'vlan id') {
            $vlanIndex = $i;
        }
    }

    if ($cidrIndex === false) {
        return $sqlRows;
    }

    $vlanId = 0;
    if ($vlanIndex !== false) {
        $vlanRaw = trim((string)($values[$vlanIndex] ?? ''));
        if ($vlanRaw !== '' && ctype_digit($vlanRaw)) {
            $vlanId = (int)$vlanRaw;
        }
    }

    $seed = (int)substr((string)time(), -3);
    for ($offset = 0; $offset < 55; $offset++) {
        $octet = 128 + (($seed + $offset) % 55);
        $cidr = '10.' . $octet . '.0.0/24';
        $network = '10.' . $octet . '.0.0';
        $vlanClause = $vlanId > 0
            ? 'vlan_id=' . (int)$vlanId
            : 'vlan_id IS NULL';
        $cidrEsc = mysqli_real_escape_string($conn, $cidr);
        $checkSql = 'SELECT id FROM ip_subnets WHERE company_id=' . (int)$companyId
            . ' AND ' . $vlanClause . " AND cidr='{$cidrEsc}' LIMIT 1";
        $checkRes = mysqli_query($conn, $checkSql);
        if ($checkRes && mysqli_num_rows($checkRes) === 0) {
            $values[$cidrIndex] = $cidr;
            if ($networkIndex !== false) {
                $values[$networkIndex] = $network;
            }
            if ($prefixIndex !== false) {
                $values[$prefixIndex] = '24';
            }
            // Keep gateway/DNS inside the rewritten /24 (JSON import skips create/edit range checks).
            $gateway = '10.' . $octet . '.0.1';
            if ($gatewayIndex !== false) {
                $values[$gatewayIndex] = $gateway;
            }
            if ($dns1Index !== false) {
                $values[$dns1Index] = '';
            }
            if ($dns2Index !== false) {
                $values[$dns2Index] = '';
            }

            return [$headers, $values];
        }
    }

    return $sqlRows;
}

/**
 * expenses.uq_expenses_company_scope allows one row per company + cost_center; pick a free cost center for import QA.
 *
 * @param array<int, array<int, string>> $sqlRows
 * @return array<int, array<int, string>>
 */
function mbqa_unique_expense_import_row(mysqli $conn, int $companyId, array $sqlRows): array
{
    if ($companyId <= 0 || count($sqlRows) < 2) {
        return $sqlRows;
    }

    $headers = $sqlRows[0];
    $values = $sqlRows[1];
    $ccIndex = array_search('Cost Center', $headers, true);
    $glIndex = array_search('Gl Account', $headers, true);
    if ($ccIndex === false) {
        return $sqlRows;
    }

    $pickSql = 'SELECT cc.id AS cc_id, gl.id AS gl_id
        FROM cost_centers cc
        INNER JOIN gl_accounts gl ON gl.company_id = cc.company_id
        LEFT JOIN expenses e ON e.company_id = cc.company_id AND e.cost_center_id = cc.id
        WHERE cc.company_id = ' . (int)$companyId . ' AND e.id IS NULL
        ORDER BY cc.id ASC
        LIMIT 1';
    $pickRes = mysqli_query($conn, $pickSql);
    $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
    if ($pick) {
        $values[$ccIndex] = (string)(int)($pick['cc_id'] ?? 0);
        if ($glIndex !== false) {
            $values[$glIndex] = (string)(int)($pick['gl_id'] ?? 0);
        }
    }

    return [$headers, $values];
}

/**
 * Import rows for round-trip: Export Excel headers from the list table + insertable values from database.sql.
 *
 * @param array<int, array<int, string>> $exportRows
 * @return array<int, array<int, string>>
 */
function mbqa_import_rows_for_round_trip(mysqli $conn, string $table, int $companyId, array $exportRows): array
{
    if ($table === 'ip_addresses' && function_exists('mbqa_ip_addresses_import_rows')) {
        $ipAddressRows = mbqa_ip_addresses_import_rows($conn, $companyId);
        if (!empty($ipAddressRows)) {
            return $ipAddressRows;
        }
    }

    if (count($exportRows) >= 2) {
        $importRows = mbqa_build_import_rows_from_export($exportRows);
        $byColumn = mbqa_database_sql_values_by_column($conn, $table, $companyId);
        if (!empty($byColumn)) {
            $columnNames = mbqa_table_column_names($conn, $table);
            $headers = $importRows[0];
            $values = $importRows[1];
            foreach ($headers as $i => $header) {
                $col = mbqa_match_list_header_to_column((string)$header, $columnNames);
                if ($col !== null && isset($byColumn[$col])) {
                    $values[$i] = $byColumn[$col];
                }
            }
            $importRows[1] = $values;
        }

        if ($table === 'ip_subnets') {
            $importRows = mbqa_unique_ip_subnets_import_row($conn, $companyId, $importRows);
        }

        $importRows = mbqa_align_import_headers_for_crud_import($conn, $table, $importRows);
        $importRows = mbqa_ensure_import_row_tenant_fk_values($conn, $table, $companyId, $importRows);

        return mbqa_apply_unique_scope_to_import_rows($conn, $table, $companyId, $importRows);
    }

    $sqlRows = mbqa_build_import_rows_from_database_sql_seed($conn, $table, $companyId);
    if (!empty($sqlRows)) {
        return $sqlRows;
    }

    return mbqa_build_fallback_import_rows($conn, $table, $companyId);
}

/** Tables the runner must never wipe during FK prep or delete-retry clears (shared auth only). */
function mbqa_tables_never_clear(): array
{
    return ['companies', 'users'];
}

function mbqa_fk_restore_label_column(mysqli $conn, string $table): string
{
    foreach (['name', 'title', 'username', 'code', 'mode_name'] as $candidate) {
        if (itm_table_has_column($conn, $table, $candidate)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * Temporarily null protected child FKs so parent reference tables can be QA-cleared without deleting auth data.
 *
 * @param array<int, array{child_table:string, child_column:string, child_id:int, old_value:int, parent_table:string, parent_column:string, parent_company_id:int, parent_label_column:string, parent_label_value:string}> $detachedRefs
 */
function mbqa_temporarily_detach_never_clear_fk_refs(mysqli $conn, string $parentTable, int $companyId, array &$detachedRefs): int
{
    if (!itm_is_safe_identifier($parentTable) || $companyId <= 0 || !itm_table_has_column($conn, $parentTable, 'company_id')) {
        return 0;
    }

    $detached = 0;
    $parentEsc = '`' . str_replace('`', '``', $parentTable) . '`';
    $labelColumn = mbqa_fk_restore_label_column($conn, $parentTable);
    $labelSelect = $labelColumn !== ''
        ? ', p.`' . str_replace('`', '``', $labelColumn) . '` AS parent_label_value'
        : '';
    foreach (mbqa_inbound_fk_refs($conn, $parentTable) as $ref) {
        $child = $ref['child_table'];
        $childColumn = $ref['child_column'];
        $parentColumn = $ref['parent_column'];
        if (!in_array($child, mbqa_tables_never_clear(), true)
            || !itm_table_has_column($conn, $child, 'id')
            || !itm_table_column_is_nullable($conn, $child, $childColumn)) {
            continue;
        }

        $childEsc = '`' . str_replace('`', '``', $child) . '`';
        $childColumnEsc = '`' . str_replace('`', '``', $childColumn) . '`';
        $parentColumnEsc = '`' . str_replace('`', '``', $parentColumn) . '`';
        $childCompanyScope = itm_table_has_column($conn, $child, 'company_id')
            ? ' AND c.`company_id`=' . (int)$companyId
            : '';

        $selectSql = 'SELECT c.`id` AS child_id, c.' . $childColumnEsc . ' AS old_value' . $labelSelect
            . ' FROM ' . $childEsc . ' c INNER JOIN ' . $parentEsc . ' p ON c.' . $childColumnEsc . ' = p.' . $parentColumnEsc
            . ' WHERE p.`company_id`=' . (int)$companyId . $childCompanyScope;
        $res = mysqli_query($conn, $selectSql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $childId = (int)($row['child_id'] ?? 0);
            $oldValue = (int)($row['old_value'] ?? 0);
            if ($childId <= 0 || $oldValue <= 0) {
                continue;
            }
            $detachedRefs[] = [
                'child_table' => $child,
                'child_column' => $childColumn,
                'child_id' => $childId,
                'old_value' => $oldValue,
                'parent_table' => $parentTable,
                'parent_column' => $parentColumn,
                'parent_company_id' => $companyId,
                'parent_label_column' => $labelColumn,
                'parent_label_value' => (string)($row['parent_label_value'] ?? ''),
            ];
        }

        $updateSql = 'UPDATE ' . $childEsc . ' c INNER JOIN ' . $parentEsc . ' p ON c.' . $childColumnEsc . ' = p.' . $parentColumnEsc
            . ' SET c.' . $childColumnEsc . '=NULL'
            . ' WHERE p.`company_id`=' . (int)$companyId . $childCompanyScope;
        if (itm_run_query($conn, $updateSql)) {
            $detached += max(0, (int)mysqli_affected_rows($conn));
        }
    }

    return $detached;
}

/**
 * @param array<int, array{child_table:string, child_column:string, child_id:int, old_value:int, parent_table:string, parent_column:string, parent_company_id:int, parent_label_column:string, parent_label_value:string}> $detachedRefs
 */
function mbqa_detached_ref_is_disposable_users_seed(mysqli $conn, array $ref): bool
{
    $childTable = (string)($ref['child_table'] ?? '');
    $childId = (int)($ref['child_id'] ?? 0);
    if ($childTable !== 'users' || $childId <= 0) {
        return false;
    }

    if (!function_exists('itm_username_is_mbqa_runner_seeded')) {
        return false;
    }

    $res = mysqli_query($conn, 'SELECT `username` FROM `users` WHERE `id`=' . $childId . ' LIMIT 1');
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $username = is_array($row) ? (string)($row['username'] ?? '') : '';
    if ($username === '') {
        return false;
    }

    return itm_username_is_mbqa_runner_seeded($username);
}

/**
 * @param array<int, array{child_table:string, child_column:string, child_id:int, old_value:int, parent_table:string, parent_column:string, parent_company_id:int, parent_label_column:string, parent_label_value:string}> $detachedRefs
 */
function mbqa_restore_temporarily_detached_fk_refs(mysqli $conn, array &$detachedRefs, string &$note = ''): bool
{
    $note = '';
    if (empty($detachedRefs)) {
        return true;
    }

    $restored = 0;
    $skipped = 0;
    $skippedDisposable = 0;
    $ok = true;
    foreach ($detachedRefs as $ref) {
        $child = $ref['child_table'];
        $childColumn = $ref['child_column'];
        $parent = $ref['parent_table'];
        $parentColumn = $ref['parent_column'];
        $parentCompanyId = (int)($ref['parent_company_id'] ?? 0);
        $parentLabelColumn = (string)($ref['parent_label_column'] ?? '');
        $parentLabelValue = (string)($ref['parent_label_value'] ?? '');
        $childId = (int)$ref['child_id'];
        $oldValue = (int)$ref['old_value'];
        if (!itm_is_safe_identifier($child) || !itm_is_safe_identifier($childColumn)
            || !itm_is_safe_identifier($parent) || !itm_is_safe_identifier($parentColumn)
            || $childId <= 0 || $oldValue <= 0) {
            $skipped++;
            continue;
        }

        $parentEsc = '`' . str_replace('`', '``', $parent) . '`';
        $parentColumnEsc = '`' . str_replace('`', '``', $parentColumn) . '`';
        $parentExists = mysqli_query(
            $conn,
            'SELECT 1 FROM ' . $parentEsc . ' WHERE ' . $parentColumnEsc . '=' . $oldValue . ' LIMIT 1'
        );
        if (!$parentExists || mysqli_num_rows($parentExists) === 0) {
            if ($parentLabelColumn === '' || $parentLabelValue === '' || !itm_is_safe_identifier($parentLabelColumn)
                || $parentCompanyId <= 0
                || !itm_table_has_column($conn, $parent, 'company_id')) {
                $skipped++;
                continue;
            }

            $labelEsc = mysqli_real_escape_string($conn, $parentLabelValue);
            $parentLabelColumnEsc = '`' . str_replace('`', '``', $parentLabelColumn) . '`';
            $fallbackSql = 'SELECT ' . $parentColumnEsc . ' AS resolved_id FROM ' . $parentEsc
                . ' WHERE `company_id`=' . $parentCompanyId
                . ' AND ' . $parentLabelColumnEsc . "='" . $labelEsc . "' LIMIT 1";
            $fallbackRes = mysqli_query($conn, $fallbackSql);
            $fallbackRow = $fallbackRes ? mysqli_fetch_assoc($fallbackRes) : null;
            $resolvedValue = is_array($fallbackRow) ? (int)($fallbackRow['resolved_id'] ?? 0) : 0;
            if ($resolvedValue <= 0) {
                if (mbqa_detached_ref_is_disposable_users_seed($conn, $ref)) {
                    $skippedDisposable++;
                    continue;
                }
                $skipped++;
                continue;
            }
            $oldValue = $resolvedValue;
        }

        $childEsc = '`' . str_replace('`', '``', $child) . '`';
        $childColumnEsc = '`' . str_replace('`', '``', $childColumn) . '`';
        $sql = 'UPDATE ' . $childEsc . ' SET ' . $childColumnEsc . '=' . $oldValue
            . ' WHERE `id`=' . $childId . ' AND ' . $childColumnEsc . ' IS NULL LIMIT 1';
        if (itm_run_query($conn, $sql)) {
            $restored += max(0, (int)mysqli_affected_rows($conn));
        } else {
            $ok = false;
        }
    }

    $note = 'restored temporary FK detachments=' . $restored;
    if ($skippedDisposable > 0) {
        $note .= '; skipped_disposable=' . $skippedDisposable;
    }
    if ($skipped > 0) {
        $note .= '; skipped=' . $skipped;
        $ok = false;
    }
    $detachedRefs = [];

    return $ok;
}

/**
 * Tables that must not receive mbqa_insert_random_rows (creates is_mbqa_equipment_types_* scaffolds).
 *
 * @return string[]
 */
function mbqa_tables_skip_random_qa_inserts(): array
{
    return ['equipment_types'];
}

/**
 * Seed equipment_types from database.sql only — never MBQA-random names (sidebar scaffold pollution).
 */
function mbqa_seed_equipment_types_from_database_sql(mysqli $conn, int $companyId): void
{
    if ($companyId <= 0 || !function_exists('itm_seed_table_from_database_sql')) {
        return;
    }

    $seedErr = '';
    itm_seed_table_from_database_sql($conn, 'equipment_types', $companyId, $seedErr);
}

/** Ideal row count so bulk_delete / clear_table UI gates (default records_per_page 25) are exercisable when schema allows. */
function mbqa_bulk_row_target_ideal(mysqli $conn, ?int $companyId = null): int
{
    return max(30, mbqa_records_per_page($conn, $companyId) + 1);
}

/**
 * Max tenant rows allowed by unique indexes (e.g. expenses: one row per company_id + cost_center_id).
 */
function mbqa_unique_scope_capacity(mysqli $conn, string $table, int $companyId): int
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return PHP_INT_MAX;
    }

    $uniqueSets = mbqa_table_unique_column_sets($conn, $table);
    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $limiting = [];

    foreach ($uniqueSets as $set) {
        $nonId = array_values(array_filter($set, static function ($col) {
            return $col !== 'id';
        }));
        if (empty($nonId)) {
            continue;
        }

        $setCapacity = PHP_INT_MAX;
        foreach ($nonId as $col) {
            if ($col === 'company_id') {
                continue;
            }

            $refTable = mbqa_fk_reference_table($col, $fkMap);
            if ($refTable !== '' && itm_is_safe_identifier($refTable)) {
                if (itm_table_has_column($conn, $refTable, 'company_id')) {
                    $colCapacity = mbqa_tenant_row_count($conn, $refTable, $companyId);
                } else {
                    $colCapacity = count(mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId));
                }
                $setCapacity = min($setCapacity, max(0, $colCapacity));
            }
        }

        if ($setCapacity < PHP_INT_MAX) {
            if (in_array('month', $nonId, true)) {
                $setCapacity *= 12;
            }
            $limiting[] = max(1, $setCapacity);
        }
    }

    if (empty($limiting)) {
        return PHP_INT_MAX;
    }

    return max(1, min($limiting));
}

/**
 * Row target for add step: pursue bulk-action coverage but never exceed per-tenant unique scope.
 */
function mbqa_bulk_row_target_for_table(mysqli $conn, string $table, int $companyId): int
{
    $ideal = mbqa_bulk_row_target_ideal($conn, $companyId);
    $capacity = mbqa_unique_scope_capacity($conn, $table, $companyId);

    if ($capacity === PHP_INT_MAX) {
        return $ideal;
    }

    return max(1, min($ideal, $capacity));
}

/**
 * Parent tables referenced in unique indexes (e.g. cost_centers for expenses) — grow these so capacity can reach the ideal row target.
 *
 * @return string[]
 */
function mbqa_unique_scope_limiting_parent_tables(mysqli $conn, string $table, int $companyId): array
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return [];
    }

    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $parents = [];

    foreach (mbqa_table_unique_column_sets($conn, $table) as $set) {
        foreach ($set as $col) {
            if ($col === 'id' || $col === 'company_id') {
                continue;
            }
            $refTable = mbqa_fk_reference_table($col, $fkMap);
            if ($refTable !== '' && itm_is_safe_identifier($refTable) && itm_table_has_column($conn, $refTable, 'company_id')) {
                $parents[$refTable] = $refTable;
            }
        }
    }

    return array_values($parents);
}

/**
 * users is never cleared during QA, but junction tables (user_companies) need enough distinct user_id values.
 */
function mbqa_ensure_tenant_users_for_bulk(mysqli $conn, int $companyId, int $goalCount): int
{
    if ($companyId <= 0 || !itm_is_safe_identifier('users') || !itm_table_has_column($conn, 'users', 'company_id')) {
        return 0;
    }

    $goalCount = max(1, $goalCount);
    $current = mbqa_tenant_row_count($conn, 'users', $companyId);
    if ($current >= $goalCount) {
        return $current;
    }

    mbqa_insert_random_rows($conn, 'users', $companyId, $goalCount - $current, 0);

    return mbqa_tenant_row_count($conn, 'users', $companyId);
}

function mbqa_grow_unique_scope_parents(mysqli $conn, string $table, int $companyId, int $goalCount): void
{
    $goalCount = max(1, $goalCount);
    foreach (mbqa_unique_scope_limiting_parent_tables($conn, $table, $companyId) as $parentTable) {
        if ($parentTable === 'users') {
            mbqa_ensure_tenant_users_for_bulk($conn, $companyId, $goalCount);
            continue;
        }
        if (in_array($parentTable, mbqa_tables_never_clear(), true)) {
            continue;
        }
        if ($parentTable === 'equipment_types') {
            mbqa_seed_equipment_types_from_database_sql($conn, $companyId);
            continue;
        }
        if (in_array($parentTable, mbqa_tables_skip_random_qa_inserts(), true)) {
            continue;
        }
        $parentCount = mbqa_tenant_row_count($conn, $parentTable, $companyId);
        if ($parentCount < $goalCount) {
            mbqa_insert_random_rows($conn, $parentTable, $companyId, $goalCount - $parentCount, 0);
        }
    }
}

function mbqa_qa_login_identifier(): string
{
    return 'Admin';
}

/**
 * Resolve the users.id row the HTTP QA login uses (email or username, case-insensitive).
 */
function mbqa_qa_admin_user_id(mysqli $conn): int
{
    static $cachedId = null;
    if ($cachedId !== null) {
        return (int)$cachedId;
    }

    $loginId = mbqa_qa_login_identifier();
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id FROM users WHERE active = 1 AND (LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)) LIMIT 1'
    );
    if (!$stmt) {
        $cachedId = 0;
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $loginId, $loginId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    $cachedId = $row ? (int)($row['id'] ?? 0) : 0;

    return (int)$cachedId;
}

/**
 * Restore MySQL audit trigger session vars after cleanup helpers overwrite @app_* values.
 */
function mbqa_sync_mysql_audit_session(mysqli $conn, ?int $companyId = null, ?int $userId = null): void
{
    if ($companyId === null) {
        $companyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
    }
    if ($userId === null) {
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    }

    $auditCompanyId = $companyId > 0 ? (int)$companyId : null;
    $auditUserId = $userId > 0 ? (int)$userId : null;

    $username = mbqa_qa_login_identifier();
    $email = '';
    if ($auditUserId !== null) {
        $stmt = mysqli_prepare($conn, 'SELECT username, email FROM users WHERE id = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $auditUserId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            if ($row) {
                $username = trim((string)($row['username'] ?? $username));
                $email = trim((string)($row['email'] ?? ''));
            }
        }
    }

    $ip = function_exists('itm_get_client_ip_address') ? itm_get_client_ip_address() : (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'module_browser_qa_runner'), 0, 255);

    mysqli_query($conn, 'SET @app_user_id = ' . ($auditUserId === null ? 'NULL' : (string)$auditUserId));
    mysqli_query($conn, 'SET @app_company_id = ' . ($auditCompanyId === null ? 'NULL' : (string)$auditCompanyId));
    mysqli_query($conn, "SET @app_username = '" . mysqli_real_escape_string($conn, $username) . "'");
    mysqli_query($conn, "SET @app_email = '" . mysqli_real_escape_string($conn, $email) . "'");
    mysqli_query($conn, "SET @app_ip_address = '" . mysqli_real_escape_string($conn, $ip) . "'");
    mysqli_query($conn, "SET @app_user_agent = '" . mysqli_real_escape_string($conn, $userAgent) . "'");
}

function mbqa_records_per_page(mysqli $conn, ?int $companyId = null): int
{
    if (!function_exists('itm_get_ui_configuration') || !function_exists('itm_resolve_records_per_page')) {
        return 25;
    }

    if ($companyId === null || $companyId <= 0) {
        $companyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
    }

    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($userId <= 0) {
        $userId = mbqa_qa_admin_user_id($conn);
    }

    if ($companyId <= 0) {
        return itm_resolve_records_per_page(itm_ui_config_defaults());
    }

    $uiConfig = itm_get_ui_configuration($conn, $companyId, $userId);

    return itm_resolve_records_per_page($uiConfig);
}

function mbqa_tenant_row_count(mysqli $conn, string $table, int $companyId): int
{
    if (!itm_is_safe_identifier($table)) {
        return 0;
    }

    $tableEsc = '`' . str_replace('`', '``', $table) . '`';

    // Why: Global tables (e.g. attempts) have no company_id; list/pagination gates use full row count.
    if (!itm_table_has_column($conn, $table, 'company_id')) {
        $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM ' . $tableEsc);
        if (!$res || !($row = mysqli_fetch_assoc($res))) {
            return 0;
        }

        return (int)($row['c'] ?? 0);
    }

    if ($companyId <= 0) {
        return 0;
    }

    $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM ' . $tableEsc . ' WHERE company_id=' . (int)$companyId);
    if (!$res || !($row = mysqli_fetch_assoc($res))) {
        return 0;
    }

    return (int)($row['c'] ?? 0);
}

/**
 * @return array<int, array{name:string,type:string,null:string,default:?string,extra:string,key:string}>
 */
function mbqa_table_column_metas(mysqli $conn, string $table): array
{
    if (!itm_is_safe_identifier($table)) {
        return [];
    }

    $metas = [];
    $res = mysqli_query($conn, 'DESCRIBE `' . str_replace('`', '``', $table) . '`');
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $name = (string)($row['Field'] ?? '');
        if ($name === '' || !itm_is_safe_identifier($name)) {
            continue;
        }
        $metas[] = [
            'name' => $name,
            'type' => strtolower((string)($row['Type'] ?? '')),
            'null' => strtoupper((string)($row['Null'] ?? 'YES')),
            'default' => $row['Default'] ?? null,
            'extra' => strtolower((string)($row['Extra'] ?? '')),
            'key' => strtoupper((string)($row['Key'] ?? '')),
        ];
    }

    return $metas;
}

/**
 * @return array<int, array<int, string>>
 */
function mbqa_table_unique_column_sets(mysqli $conn, string $table): array
{
    if (!itm_is_safe_identifier($table)) {
        return [];
    }

    $sql = 'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME <> ?
            ORDER BY INDEX_NAME, SEQ_IN_INDEX';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    $primary = 'PRIMARY';
    mysqli_stmt_bind_param($stmt, 'ss', $table, $primary);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $byIndex = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $indexName = (string)($row['INDEX_NAME'] ?? '');
        $columnName = (string)($row['COLUMN_NAME'] ?? '');
        if ($indexName === '' || $columnName === '' || !itm_is_safe_identifier($columnName)) {
            continue;
        }
        $byIndex[$indexName][] = $columnName;
    }
    mysqli_stmt_close($stmt);

    return array_values($byIndex);
}

function mbqa_column_in_unique_set(string $column, array $uniqueSets): bool
{
    foreach ($uniqueSets as $set) {
        if (in_array($column, $set, true)) {
            return true;
        }
    }

    return false;
}

function mbqa_column_skipped_for_insert(string $name, array $meta): bool
{
    if ($name === 'id' || strpos((string)$meta['extra'], 'auto_increment') !== false) {
        return true;
    }

    return $name === 'created_at' || $name === 'updated_at';
}

function mbqa_column_has_db_default(array $meta): bool
{
    $default = $meta['default'];
    if ($default === null) {
        return false;
    }

    $defaultStr = strtoupper(trim((string)$default));
    if ($defaultStr === '' || $defaultStr === 'NULL') {
        return false;
    }

    return true;
}

/** NOT NULL columns without a DB default must receive an explicit insert value. */
function mbqa_column_is_required(array $meta): bool
{
    if (($meta['null'] ?? 'YES') === 'YES') {
        return false;
    }

    return !mbqa_column_has_db_default($meta);
}

function mbqa_fk_reference_table(string $column, array $fkMap): string
{
    if (isset($fkMap[$column])) {
        return (string)($fkMap[$column]['REFERENCED_TABLE_NAME'] ?? '');
    }

    if (preg_match('/_by$/', $column)) {
        return 'users';
    }

    if (preg_match('/_id$/', $column) && $column !== 'company_id' && preg_match('/^(.+)_id$/', $column, $idMatch)) {
        return (string)($idMatch[1] ?? '');
    }

    return '';
}

/**
 * Seeds database.sql rows and ensures each outbound FK parent has enough tenant rows.
 */
function mbqa_ensure_parent_rows_for_inserts(mysqli $conn, string $table, int $companyId, int $minimumRows): void
{
    mbqa_seed_lookup_parents_for_table($conn, $table, $companyId);

    if (!function_exists('itm_table_outbound_fk_map') || !itm_is_safe_identifier($table) || $companyId <= 0) {
        return;
    }

    $minimumRows = max(1, $minimumRows);
    foreach (itm_table_outbound_fk_map($conn, $table) as $fkMeta) {
        $parentTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
        if ($parentTable === '' || !itm_is_safe_identifier($parentTable) || in_array($parentTable, mbqa_tables_never_clear(), true)) {
            continue;
        }

        if (itm_table_has_column($conn, $parentTable, 'company_id')) {
            $parentCount = mbqa_tenant_row_count($conn, $parentTable, $companyId);
            if ($parentTable === 'equipment_types') {
                if ($parentCount < $minimumRows) {
                    mbqa_seed_equipment_types_from_database_sql($conn, $companyId);
                }
                continue;
            }
            if (in_array($parentTable, mbqa_tables_skip_random_qa_inserts(), true)) {
                continue;
            }
            if ($parentCount < $minimumRows) {
                mbqa_insert_random_rows($conn, $parentTable, $companyId, $minimumRows - $parentCount, 1);
            }
            continue;
        }

        if ($parentTable === 'users' && mbqa_query_first_id($conn, 'users', $companyId) <= 0 && function_exists('itm_seed_table_from_database_sql')) {
            $seedErr = '';
            itm_seed_table_from_database_sql($conn, 'users', $companyId, $seedErr);
        }
    }
}

/**
 * @param array<int, array{name:string,type:string,null:string,default:?string,extra:string,key:string}> $columnMetas
 * @return string[]
 */
function mbqa_required_column_names(array $columnMetas): array
{
    $required = [];
    foreach ($columnMetas as $meta) {
        $name = (string)($meta['name'] ?? '');
        if ($name === '' || mbqa_column_skipped_for_insert($name, $meta)) {
            continue;
        }
        if (mbqa_column_is_required($meta)) {
            $required[] = $name;
        }
    }

    return $required;
}

/**
 * Fills a required or optional scalar (non-FK) column with QA-safe random data.
 */
function mbqa_fill_scalar_value(
    string $name,
    string $type,
    int $sequence,
    string $tag,
    bool $inUnique,
    bool $forceRequired = false,
    ?int $maxLen = null
): ?string {
    // Calendar month (monthly_budgets.month, forecast_revisions.month): CHECK 1..12, not sequence+1.
    if ($name === 'month' && preg_match('/^tinyint/', $type)) {
        return (string)((($sequence - 1) % 12) + 1);
    }

    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit)/', $type) || $name === 'active') {
        return (string)($inUnique ? ($sequence + 1) : 1);
    }

    if (strpos($type, 'enum(') === 0) {
        if (preg_match("/enum\\((.+)\\)/", $type, $enumMatch)) {
            $opts = str_getcsv(str_replace("'", '', $enumMatch[1]));

            return (string)($opts[0] ?? '1');
        }

        return '1';
    }

    if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
        return date('Y-m-d H:i:s');
    }

    if (preg_match('/\bdate\b/', $type)) {
        return date('Y-m-d');
    }

    if (strpos($type, 'time') !== false && strpos($type, 'datetime') === false) {
        return '12:00:00';
    }

    if (strpos($type, 'year') !== false) {
        return date('Y');
    }

    if (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
        return number_format((float)($sequence % 999 + 1), 2, '.', '');
    }

    if (strpos($type, 'json') !== false) {
        return '{}';
    }

    if (strpos($type, 'char') !== false || strpos($type, 'text') !== false) {
        if ($maxLen === null) {
            $maxLen = mbqa_parse_char_max_length($type);
        }

        if ($inUnique || preg_match('/(name|title|code|label|hostname|email|username|slug|sku|number|invoice|description|subject|summary)/i', $name)) {
            return mbqa_fit_string_to_column_length($tag, $sequence, $maxLen);
        }

        $plain = 'QA ' . str_replace('_', ' ', $name) . ' ' . $sequence;

        return mbqa_fit_string_to_column_length($plain, $sequence, $maxLen);
    }

    if ($forceRequired) {
        if ($maxLen === null) {
            $maxLen = mbqa_parse_char_max_length($type);
        }

        return mbqa_fit_string_to_column_length($tag . '-' . $name, $sequence, $maxLen);
    }

    return null;
}

/**
 * Seeds database.sql parents for a module so random inserts can resolve NOT NULL FKs.
 */
function mbqa_seed_lookup_parents_for_table(mysqli $conn, string $table, int $companyId, array &$visited = []): void
{
    global $sampleSeedPrerequisites;

    if (!function_exists('itm_seed_table_from_database_sql') || !itm_is_safe_identifier($table) || $companyId <= 0) {
        return;
    }

    if (isset($visited[$table])) {
        return;
    }
    $visited[$table] = true;

    $parents = $sampleSeedPrerequisites[$table] ?? [];
    if (function_exists('itm_table_outbound_fk_map')) {
        foreach (itm_table_outbound_fk_map($conn, $table) as $fkMeta) {
            $parentTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
            if ($parentTable !== '' && itm_is_safe_identifier($parentTable) && !in_array($parentTable, mbqa_tables_never_clear(), true)) {
                $parents[] = $parentTable;
            }
        }
    }

    foreach (array_values(array_unique($parents)) as $parentTable) {
        mbqa_seed_lookup_parents_for_table($conn, $parentTable, $companyId, $visited);
        $seedErr = '';
        itm_seed_table_from_database_sql($conn, $parentTable, $companyId, $seedErr);
    }
}

/**
 * @return int[]
 */
function mbqa_query_fk_ids_for_tenant(mysqli $conn, string $refTable, int $companyId, int $limit = 200): array
{
    if (!itm_is_safe_identifier($refTable) || $limit <= 0) {
        return [];
    }

    $tableEsc = '`' . str_replace('`', '``', $refTable) . '`';
    if (itm_table_has_column($conn, $refTable, 'company_id')) {
        $sql = 'SELECT id FROM ' . $tableEsc . ' WHERE company_id=' . (int)$companyId . ' ORDER BY id ASC LIMIT ' . (int)$limit;
    } else {
        $sql = 'SELECT id FROM ' . $tableEsc . ' ORDER BY id ASC LIMIT ' . (int)$limit;
    }

    $ids = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return $ids;
}

function mbqa_query_first_id(mysqli $conn, string $refTable, int $companyId): int
{
    $ids = mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId, 1);

    return $ids[0] ?? 0;
}

function mbqa_pick_fk_value(mysqli $conn, string $refTable, int $companyId, bool $ensureParent = true, int $sequence = 1): int
{
    if (!itm_is_safe_identifier($refTable)) {
        return 0;
    }

    $ids = mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId);
    if (!empty($ids)) {
        return (int)$ids[($sequence - 1) % count($ids)];
    }

    if (!$ensureParent) {
        return 0;
    }

    if (in_array($refTable, mbqa_tables_never_clear(), true)) {
        if ($refTable === 'users') {
            mbqa_ensure_tenant_users_for_bulk($conn, $companyId, max($sequence, mbqa_bulk_row_target_ideal($conn, $companyId)));
            $ids = mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId);
            if (!empty($ids)) {
                return (int)$ids[($sequence - 1) % count($ids)];
            }
        }

        return 0;
    }

    static $ensuring = [];
    if (isset($ensuring[$refTable])) {
        return 0;
    }
    $ensuring[$refTable] = true;

    if ($refTable === 'equipment_types') {
        mbqa_seed_equipment_types_from_database_sql($conn, $companyId);
    } elseif (function_exists('itm_seed_table_from_database_sql')) {
        $seedErr = '';
        itm_seed_table_from_database_sql($conn, $refTable, $companyId, $seedErr);
    }

    $ids = mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId);
    if (empty($ids)) {
        if ($refTable === 'equipment_types' || in_array($refTable, mbqa_tables_skip_random_qa_inserts(), true)) {
            unset($ensuring[$refTable]);
            return 0;
        }
        mbqa_insert_random_rows($conn, $refTable, $companyId, 1, 1);
        $ids = mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId);
    }

    unset($ensuring[$refTable]);

    if (empty($ids)) {
        return 0;
    }

    return (int)$ids[($sequence - 1) % count($ids)];
}

/**
 * Builds one random insert payload (not from database.sql) for QA volume testing.
 *
 * @param array<int, array{name:string,type:string,null:string,default:?string,extra:string,key:string}> $columnMetas
 * @param array<string, array{REFERENCED_TABLE_NAME:string,REFERENCED_COLUMN_NAME:string}> $fkMap
 * @param array<int, array<int, string>> $uniqueSets
 * @return array{columns:array<int,string>,values:array<int,string>,types:array<int,string>}|null
 */
function mbqa_build_random_insert_row(
    mysqli $conn,
    string $table,
    int $companyId,
    int $sequence,
    array $columnMetas,
    array $fkMap,
    array $uniqueSets
): ?array {
    $columns = [];
    $values = [];
    $types = [];
    $tag = itm_mbqa_runner_row_tag($table, $companyId, $sequence);
    if ($tag === '') {
        return null;
    }

    foreach ($columnMetas as $meta) {
        $name = (string)$meta['name'];
        if (mbqa_column_skipped_for_insert($name, $meta)) {
            continue;
        }

        $type = (string)$meta['type'];
        $maxLen = mbqa_parse_char_max_length($type);
        $required = mbqa_column_is_required($meta);
        $nullable = ($meta['null'] === 'YES');
        $inUnique = mbqa_column_in_unique_set($name, $uniqueSets);

        if ($name === 'company_id') {
            $columns[] = '`company_id`';
            $values[] = (string)$companyId;
            $types[] = 'i';
            continue;
        }

        $value = null;
        $bindType = 's';
        $refTable = mbqa_fk_reference_table($name, $fkMap);
        $isFkColumn = ($refTable !== '' || isset($fkMap[$name]));

        if ($isFkColumn) {
            if ($refTable === '') {
                $refTable = mbqa_fk_reference_table($name, $fkMap);
            }
            $fkId = $refTable !== '' ? mbqa_pick_fk_value($conn, $refTable, $companyId, true, $sequence) : 0;
            if ($fkId > 0) {
                $value = (string)$fkId;
                $bindType = 'i';
            }
        } else {
            $scalar = mbqa_fill_scalar_value($name, $type, $sequence, $tag, $inUnique, $required, $maxLen);
            if ($scalar !== null) {
                $value = $scalar;
                if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit)/', $type) || $name === 'active') {
                    $bindType = 'i';
                }
            }
        }

        if ($value === null && mbqa_column_has_db_default($meta)) {
            $value = (string)$meta['default'];
        }

        if ($value === null && !$isFkColumn) {
            $value = mbqa_fill_scalar_value($name, $type, $sequence, $tag, $inUnique, true, $maxLen);
            if ($value !== null && preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit)/', $type)) {
                $bindType = 'i';
            }
        }

        if ($value === null) {
            if ($nullable && !$required) {
                continue;
            }

            return null;
        }

        $columns[] = '`' . str_replace('`', '``', $name) . '`';
        $values[] = $value;
        $types[] = $bindType;
    }

    if (empty($columns)) {
        return null;
    }

    $requiredNames = mbqa_required_column_names($columnMetas);
    $present = [];
    foreach ($columns as $colEsc) {
        $present[trim($colEsc, '`')] = true;
    }
    foreach ($requiredNames as $requiredName) {
        if (!isset($present[$requiredName])) {
            return null;
        }
    }

    return ['columns' => $columns, 'values' => $values, 'types' => $types];
}

/**
 * @return array{inserted:int,last_error:string}
 */
function mbqa_insert_random_rows(mysqli $conn, string $table, int $companyId, int $needed, int $parentDepth = 0): array
{
    if ($needed <= 0 || !itm_is_safe_identifier($table) || !itm_table_has_column($conn, $table, 'company_id')) {
        return ['inserted' => 0, 'last_error' => ''];
    }

    if (in_array($table, mbqa_tables_skip_random_qa_inserts(), true)) {
        return ['inserted' => 0, 'last_error' => 'Skipped random QA inserts for ' . $table];
    }

    if ($parentDepth > 4) {
        return ['inserted' => 0, 'last_error' => 'FK parent depth limit'];
    }

    if ($parentDepth === 0) {
        mbqa_ensure_parent_rows_for_inserts($conn, $table, $companyId, mbqa_bulk_row_target_for_table($conn, $table, $companyId));
    }

    $columnMetas = mbqa_table_column_metas($conn, $table);
    if (empty($columnMetas)) {
        return ['inserted' => 0, 'last_error' => 'No column metadata'];
    }

    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $uniqueSets = mbqa_table_unique_column_sets($conn, $table);
    $inserted = 0;
    $lastError = '';
    $sequence = (int)(microtime(true) * 1000) % 100000;
    $maxAttempts = max($needed * 6, $needed + 10);

    for ($attempt = 0; $attempt < $maxAttempts && $inserted < $needed; $attempt++) {
        $sequence++;
        $row = mbqa_build_random_insert_row($conn, $table, $companyId, $sequence, $columnMetas, $fkMap, $uniqueSets);
        if ($row === null) {
            $requiredCols = mbqa_required_column_names($columnMetas);
            $lastError = 'Could not build insert payload (missing FK parent or required column)';
            if (!empty($requiredCols)) {
                $lastError .= '; required: ' . implode(', ', array_slice($requiredCols, 0, 6));
            }
            continue;
        }

        $placeholders = implode(',', array_fill(0, count($row['columns']), '?'));
        $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $row['columns']) . ') VALUES (' . $placeholders . ')';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $lastError = mysqli_error($conn);
            continue;
        }

        $bindTypes = implode('', $row['types']);
        $bindValues = $row['values'];
        $bindParams = [$stmt, $bindTypes];
        for ($bi = 0, $bn = count($bindValues); $bi < $bn; $bi++) {
            $bindParams[] = &$bindValues[$bi];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bindParams);
        if (mysqli_stmt_execute($stmt)) {
            $inserted++;
            $lastError = '';
        } else {
            $lastError = mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    return ['inserted' => $inserted, 'last_error' => $lastError];
}

/**
 * @return array{ok:bool,note:string,na:bool,count:int}
 */
function mbqa_ensure_bulk_sample_rows(mysqli $conn, string $table, int $companyId): array
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return ['ok' => false, 'note' => 'Invalid table or company', 'na' => false, 'count' => 0];
    }

    if (in_array($table, mbqa_tables_never_clear(), true)) {
        return ['ok' => true, 'note' => 'N/A (shared auth table)', 'na' => true, 'count' => 0];
    }

    if (!itm_table_has_column($conn, $table, 'company_id')) {
        return ['ok' => true, 'note' => 'N/A (no company_id)', 'na' => true, 'count' => 0];
    }

    $ideal = mbqa_bulk_row_target_ideal($conn, $companyId);
    // Why: e.g. expenses needs enough cost_centers before uq_expenses_company_scope allows 30 expense rows.
    mbqa_grow_unique_scope_parents($conn, $table, $companyId, $ideal);

    $target = mbqa_bulk_row_target_for_table($conn, $table, $companyId);
    $capacity = mbqa_unique_scope_capacity($conn, $table, $companyId);
    $scopeParents = mbqa_unique_scope_limiting_parent_tables($conn, $table, $companyId);
    $targetNote = ' target=' . $target;
    if ($capacity < $ideal && $capacity < PHP_INT_MAX) {
        $targetNote .= ' capped by unique scope';
        if (!empty($scopeParents)) {
            $targetNote .= ' (' . implode(', ', $scopeParents) . ')';
        }
    }

    mbqa_ensure_parent_rows_for_inserts($conn, $table, $companyId, $ideal);

    $current = mbqa_tenant_row_count($conn, $table, $companyId);
    if ($current >= $target) {
        return ['ok' => true, 'note' => 'Already ' . $current . ' rows (>=' . $target . ')' . $targetNote, 'na' => false, 'count' => $current];
    }

    if ($current === 0 && function_exists('itm_seed_table_from_database_sql')) {
        $seedErr = '';
        itm_seed_table_from_database_sql($conn, $table, $companyId, $seedErr);
        $current = mbqa_tenant_row_count($conn, $table, $companyId);
    }

    $needed = $target - $current;
    $insertResult = mbqa_insert_random_rows($conn, $table, $companyId, $needed);
    $added = (int)$insertResult['inserted'];
    $final = mbqa_tenant_row_count($conn, $table, $companyId);
    $ok = $final >= $target;

    if ($added > 0) {
        $note = 'inserted ' . $added . ' random row(s); total=' . $final . $targetNote;
    } else {
        $note = 'Could not insert random rows; total=' . $final . $targetNote;
        if ($insertResult['last_error'] !== '') {
            $note .= '; ' . $insertResult['last_error'];
        }
    }

    return ['ok' => $ok, 'note' => $note, 'na' => false, 'count' => $final];
}

function mbqa_index_shows_bulk_actions(string $html): bool
{
    $hasBulkForm = stripos($html, 'bulk-delete-form') !== false
        || stripos($html, 'department-bulk-form') !== false;
    $hasBulkControl = stripos($html, 'name="bulk_action"') !== false
        || stripos($html, "name='bulk_action'") !== false
        || stripos($html, 'bulk-delete-toggle') !== false;

    // Toolbar-only modules (e.g. vlans): bulk form + bulk_action, checkboxes appear after Select to Delete.
    if ($hasBulkForm && $hasBulkControl) {
        return true;
    }

    // Row checkboxes already on index (e.g. user_companies when gated on).
    return stripos($html, 'name="ids[]"') !== false
        && (stripos($html, 'bulk_action') !== false || $hasBulkForm);
}

/**
 * Pagination footer (Previous / Page N of M) — standard modules show when totalRows > perPage.
 */
function mbqa_index_shows_pagination_footer(string $html): bool
{
    return stripos($html, 'Page ') !== false && stripos($html, ' of ') !== false;
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_index_bulk_ui_matches_row_gate(int $rowCount, int $perPage, string $html): array
{
    $shouldShow = $rowCount >= $perPage;
    $shows = mbqa_index_shows_bulk_actions($html);
    if ($shouldShow === $shows) {
        return [
            'ok' => true,
            'note' => $shouldShow
                ? 'bulk UI visible (' . $rowCount . ' rows >= perPage ' . $perPage . ')'
                : 'bulk UI hidden (' . $rowCount . ' rows < perPage ' . $perPage . ')',
        ];
    }

    return [
        'ok' => false,
        'note' => $shouldShow
            ? 'bulk UI expected visible (' . $rowCount . ' >= ' . $perPage . ') but hidden on index'
            : 'bulk UI expected hidden (' . $rowCount . ' < ' . $perPage . ') but visible on index',
    ];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_index_pagination_matches_row_gate(int $rowCount, int $perPage, string $html): array
{
    $shouldShow = $rowCount > $perPage;
    $shows = mbqa_index_shows_pagination_footer($html);
    if ($shouldShow === $shows) {
        return [
            'ok' => true,
            'note' => $shouldShow
                ? 'pagination visible (' . $rowCount . ' rows > perPage ' . $perPage . ')'
                : 'pagination hidden (' . $rowCount . ' rows <= perPage ' . $perPage . ')',
        ];
    }

    return [
        'ok' => false,
        'note' => $shouldShow
            ? 'pagination expected visible (' . $rowCount . ' > ' . $perPage . ') but missing on index'
            : 'pagination expected hidden (' . $rowCount . ' <= ' . $perPage . ') but present on index',
    ];
}

function mbqa_index_has_actions_column(string $html): bool
{
    if (preg_match('/<th\b[^>]*>\s*Actions\s*<\/th>/i', $html) === 1) {
        return true;
    }

    return stripos($html, 'data-itm-actions-origin') !== false;
}

/**
 * True when tbody renders at least one real data row (not a single-cell colspan empty state).
 */
function mbqa_index_tbody_has_data_rows(string $html): bool
{
    if (!empty(mbqa_row_ids($html))) {
        return true;
    }

    if (!preg_match('/<tbody\b[^>]*>(.*)<\/tbody>/is', $html, $tbodyMatch)) {
        return false;
    }

    if (!preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $tbodyMatch[1], $rowMatches)) {
        return false;
    }

    foreach ($rowMatches[1] as $rowHtml) {
        if (mbqa_html_tr_is_list_empty_state_row($rowHtml)) {
            continue;
        }
        if (preg_match('/<td\b/i', $rowHtml)) {
            return true;
        }
    }

    return false;
}

function mbqa_html_tr_is_list_empty_state_row(string $rowHtml): bool
{
    if (preg_match_all('/<td\b/i', $rowHtml) !== 1) {
        return false;
    }

    return preg_match('/<td\b[^>]*\bcolspan=/i', $rowHtml) === 1;
}

/**
 * Table Actions column must expose layout-engine markers on header and body cells.
 *
 * @return array{ok:bool,note:string}
 */
function mbqa_index_table_actions_matches_ui_contract(string $html): array
{
    if (stripos($html, '<table') === false) {
        return ['ok' => true, 'note' => 'N/A (no list table in HTML)'];
    }
    if (!mbqa_index_has_actions_column($html)) {
        return ['ok' => true, 'note' => 'N/A (no Actions column in HTML)'];
    }

    $headerOk = preg_match(
        '/<th\b(?=[^>]*\bitm-actions-cell\b)(?=[^>]*data-itm-actions-origin=["\']1["\'])[^>]*>/i',
        $html
    ) === 1;
    if (!$headerOk) {
        return [
            'ok' => false,
            'note' => 'Actions header missing class itm-actions-cell and data-itm-actions-origin="1"',
        ];
    }

    if (!mbqa_index_tbody_has_data_rows($html)) {
        return [
            'ok' => true,
            'note' => 'Actions header mapped (itm-actions-cell + data-itm-actions-origin); no data rows',
        ];
    }

    $bodyOk = preg_match(
        '/<td\b(?=[^>]*\bitm-actions-cell\b)(?=[^>]*data-itm-actions-origin=["\']1["\'])[^>]*>/i',
        $html
    ) === 1;
    if (!$bodyOk) {
        return [
            'ok' => false,
            'note' => 'Actions body cell missing class itm-actions-cell and data-itm-actions-origin="1"',
        ];
    }

    return [
        'ok' => true,
        'note' => 'Actions mapped (itm-actions-cell + data-itm-actions-origin on header and body)',
    ];
}

/**
 * Pagination Previous/Next anchor rendered in server HTML (not JS-only).
 *
 * @return array{ok:bool,note:string}
 */
function mbqa_index_pagination_button_in_html(string $html, string $label, int $targetPage): array
{
    $label = $label === 'Previous' ? 'Previous' : 'Next';
    $expectedTitle = $label === 'Previous' ? '◀️ Previous' : '▶️ Next';
    $pageToken = 'page=' . (int)$targetPage;

    if (!preg_match_all('/<a\b([^>]*)>\s*' . preg_quote($label, '/') . '\s*<\/a>/i', $html, $matches, PREG_SET_ORDER)) {
        return ['ok' => false, 'note' => $label . ' button missing in HTML'];
    }

    foreach ($matches as $match) {
        $attrs = $match[1];
        if (stripos($attrs, 'btn-sm') === false) {
            continue;
        }
        if (stripos($attrs, $pageToken) === false) {
            continue;
        }
        if (preg_match('/title="🔎\s*Search"/i', $attrs)) {
            return ['ok' => false, 'note' => $label . ' title=Search in HTML (expected ' . $expectedTitle . ')'];
        }
        if (preg_match('/title="/i', $attrs) && stripos($attrs, 'title="' . $expectedTitle . '"') === false) {
            return ['ok' => false, 'note' => $label . ' title not ' . $expectedTitle . ' in HTML'];
        }

        return ['ok' => true, 'note' => $label . ' rendered (page=' . $targetPage . ')'];
    }

    return ['ok' => false, 'note' => $label . ' btn-sm link to ' . $pageToken . ' missing in HTML'];
}

/**
 * After add: exercise page 2 then page 1 when tenant rows exceed records_per_page.
 *
 * @return array{ok:bool,note:string}
 */
function mbqa_run_pagination_nav_step(
    string $moduleUrl,
    string $cookieFile,
    int $rowCount,
    int $perPage
): array {
    if ($rowCount <= $perPage) {
        return ['ok' => true, 'note' => 'N/A (' . $rowCount . ' rows <= perPage ' . $perPage . ')'];
    }

    // Default list sort (id DESC) — do not scrape column header sort= links from index HTML.
    $sortField = 'id';
    $queryBase = 'search=&sort=' . rawurlencode($sortField) . '&dir=DESC';

    $page1Url = $moduleUrl . 'index.php?' . $queryBase . '&page=1';
    $page1 = mbqa_http($page1Url, 'GET', null, [], $cookieFile);
    if ($page1['status'] !== 200 || mbqa_has_fatal($page1['body'])) {
        return ['ok' => false, 'note' => 'page=1 HTTP ' . $page1['status']];
    }

    if (!preg_match('/Page\s+1\s+of/i', $page1['body'])) {
        return ['ok' => false, 'note' => 'page=1 missing Page 1 of N footer'];
    }

    $nextOnPage1 = mbqa_index_pagination_button_in_html($page1['body'], 'Next', 2);
    if (!$nextOnPage1['ok']) {
        return ['ok' => false, 'note' => 'page=1: ' . $nextOnPage1['note']];
    }

    $page2Url = $moduleUrl . 'index.php?' . $queryBase . '&page=2';
    $page2 = mbqa_http($page2Url, 'GET', null, [], $cookieFile);
    if ($page2['status'] !== 200 || mbqa_has_fatal($page2['body'])) {
        return ['ok' => false, 'note' => 'page=2 HTTP ' . $page2['status']];
    }

    if (!preg_match('/Page\s+2\s+of/i', $page2['body'])) {
        return ['ok' => false, 'note' => 'page=2 missing Page 2 of N footer'];
    }

    $prevOnPage2 = mbqa_index_pagination_button_in_html($page2['body'], 'Previous', 1);
    if (!$prevOnPage2['ok']) {
        return ['ok' => false, 'note' => 'page=2: ' . $prevOnPage2['note']];
    }

    return [
        'ok' => true,
        'note' => 'page=1 Next→2, page=2 Previous→1 in HTML (sort=' . $sortField . ', dir=DESC)',
    ];
}

/**
 * Count list rows rendered in index HTML (tbody tr or ids[] checkboxes).
 */
function mbqa_index_count_table_rows(string $html): int
{
    $ids = mbqa_row_ids($html);
    if (!empty($ids)) {
        return count($ids);
    }

    if (preg_match('/<tbody\b[^>]*>(.*)<\/tbody>/is', $html, $tbodyMatch)) {
        if (preg_match_all('/<tr\b/i', $tbodyMatch[1], $trMatches)) {
            return count($trMatches[0]);
        }
    }

    return 0;
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_check_http_page(string $html, int $status, string $context): array
{
    if ($status !== 200) {
        return ['ok' => false, 'note' => $context . ' HTTP ' . $status];
    }
    if (mbqa_has_fatal($html)) {
        return ['ok' => false, 'note' => $context . ' fatal in HTML'];
    }

    return ['ok' => true, 'note' => ''];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_sample_data(string $html, int $status, bool $seedAttempted): array
{
    if (!$seedAttempted) {
        return ['ok' => true, 'note' => ''];
    }
    $base = mbqa_html_check_http_page($html, $status, 'sample_data index');
    if (!$base['ok']) {
        return $base;
    }
    if (mbqa_index_has_sample_seed_error($html)) {
        return ['ok' => false, 'note' => 'sample seed error banner in HTML'];
    }
    if (mbqa_index_is_empty($html) || mbqa_index_count_table_rows($html) < 1) {
        return ['ok' => false, 'note' => 'sample_data: no table rows in HTML'];
    }
    if (stripos($html, '<table') === false) {
        return ['ok' => false, 'note' => 'sample_data: list table missing in HTML'];
    }

    return ['ok' => true, 'note' => 'table rows in HTML'];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_add_index(string $html, int $status, int $minRows): array
{
    $base = mbqa_html_check_http_page($html, $status, 'add index');
    if (!$base['ok']) {
        return $base;
    }
    $rowCount = mbqa_index_count_table_rows($html);
    if ($rowCount < $minRows) {
        return ['ok' => false, 'note' => 'add: expected >=' . $minRows . ' rows in HTML, saw ' . $rowCount];
    }
    if (stripos($html, '<table') === false) {
        return ['ok' => false, 'note' => 'add: list table missing in HTML'];
    }

    return ['ok' => true, 'note' => $rowCount . ' row(s) in HTML'];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_bulk_delete_ready(string $html): array
{
    if (!mbqa_index_shows_bulk_actions($html)) {
        return ['ok' => false, 'note' => 'bulk_delete: bulk UI missing in HTML'];
    }
    if (stripos($html, 'bulk_delete') === false && stripos($html, 'Select to Delete') === false) {
        return ['ok' => false, 'note' => 'bulk_delete: Select to Delete missing in HTML'];
    }
    if (mbqa_index_count_table_rows($html) < 1) {
        return ['ok' => false, 'note' => 'bulk_delete: no rows in HTML'];
    }

    return ['ok' => true, 'note' => 'bulk UI + rows in HTML'];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_search(string $html, int $status): array
{
    $base = mbqa_html_check_http_page($html, $status, 'search');
    if (!$base['ok']) {
        return $base;
    }
    if (stripos($html, 'name="search"') === false && stripos($html, 'id="moduleSearch"') === false) {
        return ['ok' => false, 'note' => 'search input missing in HTML'];
    }
    if (stripos($html, '<table') === false) {
        return ['ok' => false, 'note' => 'search: list table missing in HTML'];
    }

    return ['ok' => true, 'note' => 'HTTP 200; search input + table in HTML'];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_sort(string $html, int $status, string $sortField): array
{
    $base = mbqa_html_check_http_page($html, $status, 'sort');
    if (!$base['ok']) {
        return $base;
    }
    $hasSortLink = preg_match('/sort=' . preg_quote($sortField, '/') . '/i', $html) === 1;
    $hasDir = stripos($html, 'dir=DESC') !== false
        || stripos($html, '▼') !== false
        || stripos($html, '&#9660;') !== false;
    if (!$hasSortLink || !$hasDir) {
        return ['ok' => false, 'note' => 'sort=' . $sortField . ' link/dir missing in HTML'];
    }

    return ['ok' => true, 'note' => 'sort=' . $sortField . ' in HTML'];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_create(string $html, int $status): array
{
    $base = mbqa_html_check_http_page($html, $status, 'create');
    if (!$base['ok']) {
        return $base;
    }
    if (stripos($html, '<form') === false) {
        return ['ok' => false, 'note' => 'create form missing in HTML'];
    }
    if (stripos($html, 'csrf_token') === false && stripos($html, 'name="csrf_token"') === false) {
        return ['ok' => false, 'note' => 'create CSRF field missing in HTML'];
    }

    return ['ok' => true, 'note' => 'create form in HTML'];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_view(string $html, int $status, int $id): array
{
    $base = mbqa_html_check_http_page($html, $status, 'view');
    if (!$base['ok']) {
        return $base;
    }
    if (stripos($html, '<table') === false && stripos($html, '<form') === false) {
        return ['ok' => false, 'note' => 'view: table/form content missing in HTML'];
    }

    return ['ok' => true, 'note' => 'view screen in HTML' . ($id > 0 ? ' (id=' . $id . ')' : '')];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_edit(string $html, int $status, int $id): array
{
    $base = mbqa_html_check_http_page($html, $status, 'edit');
    if (!$base['ok']) {
        return $base;
    }
    if (stripos($html, '<form') === false) {
        return ['ok' => false, 'note' => 'edit form missing in HTML'];
    }
    if (stripos($html, 'csrf_token') === false && stripos($html, 'name="csrf_token"') === false) {
        return ['ok' => false, 'note' => 'edit CSRF field missing in HTML'];
    }

    return ['ok' => true, 'note' => 'edit form in HTML' . ($id > 0 ? ' (id=' . $id . ')' : '')];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_list_all(string $html, int $status): array
{
    $base = mbqa_html_check_http_page($html, $status, 'list_all');
    if (!$base['ok']) {
        return $base;
    }
    if (stripos($html, '<table') === false) {
        return ['ok' => false, 'note' => 'list_all table missing in HTML'];
    }

    return ['ok' => true, 'note' => 'list table in HTML'];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_export(string $html, string $kind, bool $hasTableExport): array
{
    $buttons = mbqa_index_has_export_buttons($html);
    if ($kind === 'pdf') {
        if (!$buttons['pdf']) {
            return ['ok' => false, 'note' => 'Export PDF not present in HTML (table-tools.js or label)'];
        }
        if (!$hasTableExport) {
            return ['ok' => false, 'note' => 'export: table rows missing in HTML'];
        }

        $rows = count(mbqa_extract_table_export_rows($html)) - 1;

        return ['ok' => true, 'note' => 'Export PDF in HTML, ' . $rows . ' row(s)'];
    }

    if (!$buttons['xlsx']) {
        return ['ok' => false, 'note' => 'Export Excel (.xlsx) not present in HTML (table-tools.js or label)'];
    }
    if (!mbqa_index_has_xlsx_library($html)) {
        return ['ok' => false, 'note' => 'xlsx.full.min.js missing (table-tools exports OOXML .xlsx via SheetJS)'];
    }
    if (!$hasTableExport) {
        return ['ok' => false, 'note' => 'export: table rows missing in HTML'];
    }

    $rows = count(mbqa_extract_table_export_rows($html)) - 1;

    return ['ok' => true, 'note' => 'Export Excel (.xlsx) in HTML, ' . $rows . ' row(s)'];
}

/**
 * Import Excel UI contract in server HTML (endpoint attr + table-tools.js like export steps).
 *
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_import_db(string $html, bool $hasTableExport): array
{
    if (stripos($html, 'data-itm-db-import-endpoint') === false) {
        return ['ok' => false, 'note' => 'data-itm-db-import-endpoint missing on table HTML'];
    }
    if (stripos($html, '<table') === false) {
        return ['ok' => false, 'note' => 'import_db: list table missing in HTML'];
    }
    $hasTableTools = stripos($html, 'table-tools.js') !== false;
    $hasImportUi = stripos($html, 'Import Excel') !== false
        || stripos($html, 'import_excel_rows') !== false;
    if (!$hasTableTools && !$hasImportUi) {
        return ['ok' => false, 'note' => 'Import Excel / table-tools.js missing in HTML'];
    }
    if (!$hasTableExport) {
        return ['ok' => false, 'note' => 'import_db: table rows missing in HTML'];
    }

    return ['ok' => true, 'note' => 'data-itm-db-import-endpoint + table-tools in HTML'];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_single_delete_action(string $html, int $id): array
{
    if ($id <= 0) {
        return ['ok' => true, 'note' => ''];
    }
    if (preg_match('/delete\.php\?[^"\']*id=' . $id . '\b/i', $html) === 1
        || preg_match('/name="delete_record"[^>]*value="' . $id . '"/i', $html) === 1
        || preg_match('/🗑️/u', $html) === 1) {
        return ['ok' => true, 'note' => 'delete control in HTML'];
    }

    return ['ok' => false, 'note' => 'single_delete: delete action missing in HTML for id=' . $id];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_html_step_clear_table_button(string $html): array
{
    if (stripos($html, 'value="clear_table"') !== false || stripos($html, "value='clear_table'") !== false) {
        return ['ok' => true, 'note' => 'Clear Table in HTML'];
    }
    if (stripos($html, 'Clear Table') !== false && stripos($html, 'bulk_action') !== false) {
        return ['ok' => true, 'note' => 'Clear Table in HTML'];
    }

    return ['ok' => false, 'note' => 'clear_table button missing in HTML'];
}

/**
 * Shared Cancel UX shipped in js/bulk-delete-selection.js (loaded from includes/header.php).
 *
 * @return array{ok:bool,note:string}
 */
function mbqa_shared_bulk_cancel_js_contract(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $repoRoot = dirname(__DIR__);
    $path = $repoRoot . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'bulk-delete-selection.js';
    if (!is_file($path)) {
        $cached = ['ok' => false, 'note' => 'missing js/bulk-delete-selection.js'];
        return $cached;
    }

    $js = (string)file_get_contents($path);
    $hasCancelLabel = stripos($js, "textContent = 'Cancel'") !== false
        || stripos($js, 'textContent = "Cancel"') !== false;
    $hasExit = stripos($js, 'exitSelectionMode') !== false;
    $hasCancelHook = stripos($js, 'data-itm-bulk-cancel') !== false;

    if ($hasCancelLabel && $hasExit && $hasCancelHook) {
        $cached = ['ok' => true, 'note' => 'shared JS: Cancel label + exitSelectionMode + data-itm-bulk-cancel'];
        return $cached;
    }

    $missing = [];
    if (!$hasCancelLabel) {
        $missing[] = 'Cancel label';
    }
    if (!$hasExit) {
        $missing[] = 'exitSelectionMode';
    }
    if (!$hasCancelHook) {
        $missing[] = 'data-itm-bulk-cancel';
    }
    $cached = ['ok' => false, 'note' => 'js/bulk-delete-selection.js missing ' . implode(', ', $missing)];

    return $cached;
}

/**
 * Bulk Cancel contract in index HTML (+ shared js/bulk-delete-selection.js on disk).
 *
 * @return array{ok:bool,note:string,na:bool}
 */
function mbqa_html_step_bulk_cancel(string $indexHtml): array
{
    $hasBulkForm = stripos($indexHtml, 'bulk-delete-form') !== false
        || stripos($indexHtml, 'department-bulk-form') !== false;
    if (!$hasBulkForm) {
        return ['ok' => true, 'note' => 'N/A (no bulk-delete form in HTML)', 'na' => true];
    }

    $issues = [];
    if (stripos($indexHtml, 'bulk-delete-selection.js') === false) {
        $issues[] = 'bulk-delete-selection.js missing in HTML';
    }
    if (stripos($indexHtml, 'name="bulk_action"') === false && stripos($indexHtml, "name='bulk_action'") === false) {
        $issues[] = 'bulk_action control missing in HTML';
    }
    if (stripos($indexHtml, 'bulk_delete') === false && stripos($indexHtml, 'Select to Delete') === false) {
        $issues[] = 'Select to Delete / bulk_delete missing in HTML';
    }
    if (preg_match('/let\s+selectionMode\s*=\s*false/i', $indexHtml)) {
        $issues[] = 'inline selectionMode script in HTML (use shared bulk-delete-selection.js)';
    }

    $hasStaticCancel = stripos($indexHtml, 'data-itm-bulk-cancel') !== false;
    if ($hasStaticCancel) {
        if (!preg_match('/<button[^>]*data-itm-bulk-cancel\s*=\s*["\']1["\'][^>]*type\s*=\s*["\']button["\']/i', $indexHtml)
            && !preg_match('/<button[^>]*type\s*=\s*["\']button["\'][^>]*data-itm-bulk-cancel\s*=\s*["\']1["\']/i', $indexHtml)) {
            $issues[] = 'data-itm-bulk-cancel button invalid in HTML (must be type="button")';
        }
    }

    if (!empty($issues)) {
        return ['ok' => false, 'note' => implode('; ', $issues), 'na' => false];
    }

    $htmlNote = 'bulk-delete-form in HTML; bulk-delete-selection.js in HTML; bulk_action + Select to Delete in HTML';
    if ($hasStaticCancel) {
        $htmlNote .= '; data-itm-bulk-cancel button in HTML';
    } else {
        $htmlNote .= '; Cancel injected by shared JS on first Select to Delete';
    }

    return ['ok' => true, 'note' => $htmlNote, 'na' => false];
}

/**
 * CLI/HTTP substitute for clicking Cancel: verify index markup + global script, not selectionMode duplicates.
 *
 * @return array{ok:bool,note:string,na:bool}
 */
function mbqa_verify_bulk_cancel_contract(string $indexHtml): array
{
    $jsCheck = mbqa_shared_bulk_cancel_js_contract();
    if (!$jsCheck['ok']) {
        return ['ok' => false, 'note' => $jsCheck['note'], 'na' => false];
    }

    $htmlCheck = mbqa_html_step_bulk_cancel($indexHtml);
    if ($htmlCheck['na']) {
        return $htmlCheck;
    }
    if (!$htmlCheck['ok']) {
        return ['ok' => false, 'note' => $htmlCheck['note'], 'na' => false];
    }

    return [
        'ok' => true,
        'note' => $jsCheck['note'] . '; ' . $htmlCheck['note'],
        'na' => false,
    ];
}

/**
 * Explains why bulk_delete/clear_table were skipped (row gate vs missing delete.php/CSRF/bulk UI).
 */
function mbqa_bulk_step_na_note(
    int $rowCount,
    int $perPage,
    string $indexHtml,
    string $deletePath,
    string $csrf,
    string $contextLabel = 'after add'
): string {
    if ($rowCount < $perPage) {
        return 'N/A (' . $rowCount . ' rows < perPage ' . $perPage . ' ' . $contextLabel . ')';
    }

    $reasons = [];
    if (!mbqa_index_shows_bulk_actions($indexHtml)) {
        $reasons[] = 'bulk UI hidden (no bulk-delete-form/bulk_action on index)';
    }
    if (!is_file($deletePath)) {
        $reasons[] = 'no delete.php';
    }
    if ($csrf === '') {
        $reasons[] = 'no CSRF on index';
    }

    if (empty($reasons)) {
        return 'N/A (bulk prerequisites unclear ' . $contextLabel . ')';
    }

    return 'N/A (' . $rowCount . ' rows >= perPage ' . $perPage . ' ' . $contextLabel . '; ' . implode('; ', $reasons) . ')';
}

/**
 * @param int[] $ids
 * @return array{ok:bool,note:string}
 */
function mbqa_run_bulk_delete(string $moduleUrl, string $cookieFile, string $csrf, array $ids): array
{
    if ($csrf === '' || empty($ids)) {
        return ['ok' => false, 'note' => 'N/A no csrf/ids'];
    }

    $parts = ['bulk_action=bulk_delete', 'csrf_token=' . rawurlencode($csrf)];
    foreach ($ids as $id) {
        $parts[] = 'ids[]=' . (int)$id;
    }
    $body = implode('&', $parts);

    $resp = mbqa_http(
        $moduleUrl . 'delete.php',
        'POST',
        $body,
        ['Content-Type: application/x-www-form-urlencoded'],
        $cookieFile
    );
    if ($resp['status'] < 200 || $resp['status'] >= 400) {
        return ['ok' => false, 'note' => 'bulk_delete HTTP ' . $resp['status']];
    }

    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    if ($index['status'] !== 200 || mbqa_has_fatal($index['body'])) {
        return ['ok' => false, 'note' => 'index after bulk_delete HTTP ' . $index['status']];
    }

    $remaining = 0;
    foreach ($ids as $id) {
        if (mbqa_index_still_has_row($index['body'], (int)$id)) {
            $remaining++;
        }
    }

    if ($remaining > 0) {
        return ['ok' => false, 'note' => $remaining . ' selected id(s) still listed'];
    }

    return ['ok' => true, 'note' => 'deleted ids=' . implode(',', array_map('intval', $ids))];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_run_clear_table(
    string $moduleUrl,
    string $cookieFile,
    string $csrf,
    string $moduleSlug = '',
    ?mysqli $conn = null,
    int $companyId = 0
): array {
    if ($csrf === '') {
        return ['ok' => false, 'note' => 'N/A no csrf'];
    }

    $resp = mbqa_http(
        $moduleUrl . 'delete.php',
        'POST',
        http_build_query(['bulk_action' => 'clear_table', 'csrf_token' => $csrf]),
        ['Content-Type: application/x-www-form-urlencoded'],
        $cookieFile
    );
    if ($resp['status'] < 200 || $resp['status'] >= 400) {
        return ['ok' => false, 'note' => 'clear_table HTTP ' . $resp['status']];
    }

    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    if ($index['status'] !== 200 || mbqa_has_fatal($index['body'])) {
        return ['ok' => false, 'note' => 'index after clear_table HTTP ' . $index['status']];
    }

    if (!mbqa_clear_table_index_ok($moduleSlug, $index['body'], $conn, $companyId)) {
        return ['ok' => false, 'note' => 'rows still present after clear_table'];
    }

    if ($moduleSlug === 'user_companies' && $conn instanceof mysqli && $companyId > 0
        && mbqa_user_companies_non_admin_row_count($conn, $companyId) === 0) {
        return ['ok' => true, 'note' => 'cleared; Admin assignment(s) retained by policy'];
    }

    return ['ok' => true, 'note' => 'table empty for tenant'];
}

/**
 * Extracts the blocking child table from a MySQL 1451 FK error (not the schema name).
 */
function mbqa_mysql_fk_blocker_table(string $errMsg): string
{
    if (preg_match('/foreign key constraint fails\s*\(\s*`[^`]+`\.`([^`]+)`/i', $errMsg, $m)) {
        $table = (string)($m[1] ?? '');
        return itm_is_safe_identifier($table) ? $table : '';
    }

    if (preg_match('/REFERENCES\s+`[^`]+`\.`([^`]+)`/i', $errMsg, $m)) {
        $table = (string)($m[1] ?? '');
        return itm_is_safe_identifier($table) ? $table : '';
    }

    if (preg_match_all('/`([^`]+)`/', $errMsg, $matches) && count($matches[1]) >= 2) {
        $table = (string)$matches[1][count($matches[1]) - 1];
        return itm_is_safe_identifier($table) ? $table : '';
    }

    return '';
}

/**
 * Inbound FK children of a table (MySQL information_schema).
 *
 * @return array<int, array{child_table:string, child_column:string, parent_column:string}>
 */
function mbqa_inbound_fk_refs(mysqli $conn, string $parentTable): array
{
    if (!itm_is_safe_identifier($parentTable)) {
        return [];
    }

    $sql = 'SELECT kcu.TABLE_NAME AS child_table,
                   kcu.COLUMN_NAME AS child_column,
                   kcu.REFERENCED_COLUMN_NAME AS parent_column
            FROM information_schema.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.REFERENCED_TABLE_NAME = ?
              AND kcu.REFERENCED_COLUMN_NAME IS NOT NULL';

    $refs = [];
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 's', $parentTable);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $child = (string)($row['child_table'] ?? '');
        $col = (string)($row['child_column'] ?? '');
        $parentCol = (string)($row['parent_column'] ?? 'id');
        if (!itm_is_safe_identifier($child) || !itm_is_safe_identifier($col) || !itm_is_safe_identifier($parentCol)) {
            continue;
        }
        $refs[] = [
            'child_table' => $child,
            'child_column' => $col,
            'parent_column' => $parentCol,
        ];
    }
    mysqli_stmt_close($stmt);

    return $refs;
}

/**
 * Deletes tenant rows from a table (company_id) or rows tied to a parent tenant via FK join.
 */
function mbqa_delete_table_company_scoped(
    mysqli $conn,
    string $table,
    int $companyId,
    ?string $parentTable = null,
    ?string $childFkColumn = null,
    ?string $parentPkColumn = 'id'
): bool {
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return false;
    }

    $tableEsc = '`' . str_replace('`', '``', $table) . '`';

    if (itm_table_has_column($conn, $table, 'company_id')) {
        $sql = 'DELETE FROM ' . $tableEsc . ' WHERE company_id=' . (int)$companyId;
        return itm_run_query($conn, $sql) !== false;
    }

    if (
        $parentTable !== null
        && $childFkColumn !== null
        && itm_is_safe_identifier($parentTable)
        && itm_is_safe_identifier($childFkColumn)
        && itm_is_safe_identifier($parentPkColumn)
        && itm_table_has_column($conn, $parentTable, 'company_id')
    ) {
        $parentEsc = '`' . str_replace('`', '``', $parentTable) . '`';
        $colEsc = '`' . str_replace('`', '``', $childFkColumn) . '`';
        $pkEsc = '`' . str_replace('`', '``', $parentPkColumn) . '`';
        $sql = 'DELETE c FROM ' . $tableEsc . ' c INNER JOIN ' . $parentEsc . ' p ON c.' . $colEsc . ' = p.' . $pkEsc
            . ' WHERE p.company_id=' . (int)$companyId;

        return itm_run_query($conn, $sql) !== false;
    }

    return false;
}

/**
 * Clears inbound FK dependents first, then the target module table for one company.
 */
function mbqa_clear_module_table_for_company(mysqli $conn, string $table, int $companyId, string &$note = ''): bool
{
    $note = '';
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        $note = 'Invalid table or company';
        return false;
    }
    if (in_array($table, mbqa_tables_never_clear(), true)) {
        $note = 'Skip never-clear table';
        return false;
    }
    if (!itm_table_has_column($conn, $table, 'company_id')) {
        $note = 'No company_id column';
        return false;
    }

    $clearedFirst = [];
    $visited = [];

    $clearRecursive = static function (mysqli $conn, string $target, int $companyId, array &$visited, array &$clearedFirst) use (&$clearRecursive): void {
        if (isset($visited[$target])) {
            return;
        }
        $visited[$target] = true;

        foreach (mbqa_inbound_fk_refs($conn, $target) as $ref) {
            $child = $ref['child_table'];
            if (in_array($child, mbqa_tables_never_clear(), true)) {
                continue;
            }
            $clearRecursive($conn, $child, $companyId, $visited, $clearedFirst);
            if (mbqa_delete_table_company_scoped($conn, $child, $companyId, $target, $ref['child_column'], $ref['parent_column'])) {
                $clearedFirst[$child] = $child;
            }
        }
    };

    $clearRecursive($conn, $table, $companyId, $visited, $clearedFirst);

    $tableEsc = '`' . str_replace('`', '``', $table) . '`';
    $sql = 'DELETE FROM ' . $tableEsc . ' WHERE company_id=' . (int)$companyId;
    $errCode = 0;
    $errMsg = '';
    $ok = itm_run_query($conn, $sql, $errCode, $errMsg) !== false;

    if (!$ok && (int)$errCode === 1451) {
        for ($attempt = 0; $attempt < 12 && !$ok; $attempt++) {
            $blocker = mbqa_mysql_fk_blocker_table((string)$errMsg);
            if ($blocker !== '' && !in_array($blocker, mbqa_tables_never_clear(), true)) {
                $subNote = '';
                mbqa_clear_module_table_for_company($conn, $blocker, $companyId, $subNote);
                $clearedFirst[$blocker] = $blocker;
            }
            $errCode = 0;
            $errMsg = '';
            $ok = itm_run_query($conn, $sql, $errCode, $errMsg) !== false;
        }
    }

    if ($ok) {
        if (!empty($clearedFirst)) {
            $note = 'SQL tenant clear (first cleared: ' . implode(', ', array_values($clearedFirst)) . ')';
        } else {
            $note = 'SQL tenant clear';
        }
    } else {
        $note = $errMsg !== '' ? ('Clear failed: ' . $errMsg) : 'Clear failed';
    }

    return $ok;
}

/**
 * Parses "in use by: employee_positions (1), …" from module error banners.
 *
 * @return string[]
 */
function mbqa_parse_in_use_tables(string $html): array
{
    $tables = [];
    if (!preg_match('/in use by:\s*(.+?)(?:\.|<\/)/is', $html, $m)) {
        return [];
    }
    $segment = (string)$m[1];
    if (preg_match_all('/\b([a-z][a-z0-9_]*)\s*\(\d+\)/i', $segment, $matches)) {
        foreach ($matches[1] as $name) {
            if (itm_is_safe_identifier($name)) {
                $tables[$name] = $name;
            }
        }
    }

    return array_values($tables);
}

function mbqa_index_still_has_row(string $html, int $id): bool
{
    return preg_match('/name="ids\[\]"\s+value="' . preg_quote((string)$id, '/') . '"/', $html) === 1
        || preg_match('/href=["\']view\.php\?id=' . preg_quote((string)$id, '/') . '\b/', $html) === 1;
}

/**
 * POST delete.php and, on "in use by" errors, clear blockers for the tenant then retry.
 */
function mbqa_delete_record_with_fk_retry(
    mysqli $conn,
    string $moduleUrl,
    string $table,
    int $recordId,
    int $companyId,
    string $csrf,
    string $cookieFile
): array {
    if ($recordId <= 0 || $csrf === '') {
        return ['ok' => false, 'note' => 'N/A no row/csrf'];
    }

    $cleared = [];
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $del = mbqa_http(
            $moduleUrl . 'delete.php',
            'POST',
            http_build_query(['id' => (string)$recordId, 'csrf_token' => $csrf]),
            ['Content-Type: application/x-www-form-urlencoded'],
            $cookieFile
        );
        if ($del['status'] < 200 || $del['status'] >= 400) {
            return ['ok' => false, 'note' => 'delete HTTP ' . $del['status']];
        }

        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        if ($index['status'] !== 200 || mbqa_has_fatal($index['body'])) {
            return ['ok' => false, 'note' => 'index after delete HTTP ' . $index['status']];
        }

        if (!mbqa_index_still_has_row($index['body'], $recordId)) {
            $note = 'deleted id=' . $recordId;
            if (!empty($cleared)) {
                $note .= '; first cleared: ' . implode(', ', $cleared);
            }
            return ['ok' => true, 'note' => $note];
        }

        $blockers = mbqa_parse_in_use_tables($index['body']);
        if (empty($blockers) && function_exists('itm_find_record_usage')) {
            $usage = itm_find_record_usage($conn, $table, 'id', $recordId, $companyId);
            foreach ($usage as $row) {
                $t = (string)($row['table'] ?? '');
                if ($t !== '' && itm_is_safe_identifier($t)) {
                    $blockers[] = $t;
                }
            }
            $blockers = array_values(array_unique($blockers));
        }

        if (empty($blockers)) {
            return ['ok' => false, 'note' => 'row still listed; no blockers parsed'];
        }

        $progress = false;
        foreach ($blockers as $blocker) {
            if (in_array($blocker, mbqa_tables_never_clear(), true)) {
                continue;
            }
            $subNote = '';
            if (mbqa_clear_module_table_for_company($conn, $blocker, $companyId, $subNote)) {
                $cleared[$blocker] = $blocker;
                $progress = true;
            }
        }

        if (!$progress) {
            return ['ok' => false, 'note' => 'blocked by ' . implode(', ', $blockers)];
        }
    }

    return ['ok' => false, 'note' => 'delete retries exhausted'];
}

mbqa_validate_requested_scope($filterModule, $filterCompany, (bool)$mbqaOptions['ui_click_smoke'], $allModules, $companyNames);
mbqa_ajax_cleanup_stale_files($root, mbqa_browser_ajax_active() ? mbqa_browser_ajax_run_id() : '');

$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itm_qa_cookies_' . getmypid() . '.txt';
@unlink($cookieFile);

mbqa_browser_progress_set_context(0, '_runner');
mbqa_browser_progress_emit('login');

// Login
$loginGet = mbqa_http($baseUrl . 'login.php', 'GET', null, [], $cookieFile);
$csrf = mbqa_extract_csrf($loginGet['body']);
$loginPost = mbqa_http(
    $baseUrl . 'login.php',
    'POST',
    http_build_query(['email' => mbqa_qa_login_identifier(), 'password' => 'Admin', 'csrf_token' => $csrf]),
    ['Content-Type: application/x-www-form-urlencoded'],
    $cookieFile
);
if ($loginPost['status'] < 200 || $loginPost['status'] >= 400 || mbqa_has_fatal($loginPost['body'])) {
    mbqa_err("Login failed (HTTP {$loginPost['status']}). Is Laragon running at {$baseUrl}? Use http:// (not https://) for localhost unless TLS is configured.\n");
    exit(1);
}

$mbqaAdminUserId = mbqa_qa_admin_user_id($conn);
if ($mbqaAdminUserId <= 0) {
    mbqa_err('QA login identifier ' . mbqa_qa_login_identifier() . ' not found in users table (active row required).' . "\n");
    exit(1);
}
$_SESSION['user_id'] = $mbqaAdminUserId;

// Why: browser Run QA should execute the same cleanup as "Run Clean Tests" silently before module steps.
$mbqaEquipmentPrecleanup = $browserRunViaQaButton
    ? mbqa_run_clean_tests_silent()
    : itm_run_equipment_test_module_artifacts_cleanup($conn, $modulesDir);
$mbqaEquipmentPrecleanupNote = $browserRunViaQaButton
    ? mbqa_clean_tests_report_summary($mbqaEquipmentPrecleanup, 'Pre-QA clean tests')
    : itm_equipment_cleanup_report_summary($mbqaEquipmentPrecleanup);
if (!$mbqaEquipmentPrecleanup['ok']) {
    $preCleanupError = $mbqaEquipmentPrecleanupNote !== '' ? $mbqaEquipmentPrecleanupNote : 'Pre-QA clean tests failed.';
    mbqa_err($preCleanupError . "\n");
}
mbqa_sync_mysql_audit_session($conn, null, $mbqaAdminUserId);

$orderedModules = array_unique(array_merge($lookupWave, $budgetWave, $allModules));
if ($pilotOnly) {
    $orderedModules = ['expenses'];
}
if ($filterModule !== null) {
    $orderedModules = in_array($filterModule, $allModules, true) ? [$filterModule] : [$filterModule];
}

$companiesToRun = array_keys($companyNames);
if ($filterCompany !== null && $filterCompany > 0) {
    $companiesToRun = [$filterCompany];
}

$results = [];
$summary = ['pass' => 0, 'fail' => 0, 'na' => 0, 'modules' => 0];

foreach ($companiesToRun as $companyId) {
    if (mbqa_ajax_should_stop($root)) {
        break;
    }
    $dashGet = mbqa_http($baseUrl . 'dashboard.php', 'GET', null, [], $cookieFile);
    $csrfDash = mbqa_extract_csrf($dashGet['body']);
    $switch = mbqa_http(
        $baseUrl . 'dashboard.php',
        'POST',
        http_build_query(['company_id' => (string)$companyId, 'csrf_token' => $csrfDash]),
        ['Content-Type: application/x-www-form-urlencoded'],
        $cookieFile
    );
    $companyOk = $switch['status'] >= 200 && $switch['status'] < 400
        && stripos($switch['body'], (string)$companyNames[$companyId]) !== false;
    mbqa_browser_progress_set_context($companyId, '_preflight');
    $results[] = [
        'module' => '_preflight',
        'company_id' => $companyId,
        'company_name' => $companyNames[$companyId],
        'tier' => 'preflight',
        'steps' => [mbqa_step_result('company_switch', $companyOk, $companyOk ? '' : 'Company name not found after switch')],
    ];

    // Why: A failed switch leaves the prior tenant active; skip destructive module steps for this company.
    if (!$companyOk) {
        continue;
    }

    // Why: list/pagination gates resolve records_per_page from ui_configuration for the active QA tenant, not a stale CLI session company.
    $_SESSION['company_id'] = $companyId;
    $_SESSION['user_id'] = $mbqaAdminUserId;
    mbqa_sync_mysql_audit_session($conn, $companyId, $mbqaAdminUserId);

    foreach ($orderedModules as $slug) {
        if (mbqa_ajax_should_stop($root)) {
            break 2;
        }
        if (!is_dir($modulesDir . DIRECTORY_SEPARATOR . $slug)) {
            continue;
        }

        mbqa_browser_progress_set_context($companyId, $slug);

        $tier = 'A';
        if (in_array($slug, $bespokeSmoke, true)) {
            $tier = 'D';
        } elseif (strpos($slug, 'is_') === 0) {
            $tier = 'C';
        }

        $moduleUrl = $baseUrl . 'modules/' . rawurlencode($slug) . '/';
        $steps = [];
        $detachedProtectedFkRefs = [];

        $mysqlNaNote = mbqa_runner_module_step_exception_note($slug, 'mysql');
        if ($mysqlNaNote !== null) {
            $steps[] = mbqa_step_result('mysql', true, $mysqlNaNote);
        } elseif ($tier === 'A' && itm_is_safe_identifier($slug)) {
            $mysqlCheck = mbqa_mysql_database_sql_seed_check($slug);
            $steps[] = mbqa_step_result('mysql', $mysqlCheck['ok'], $mysqlCheck['note']);
        } else {
            $steps[] = mbqa_step_result('mysql', true, 'N/A (Tier ' . $tier . ')');
        }

        $errorLogScope = mbqa_begin_module_error_log_scope();
        $errorLogOffset = $errorLogScope['offset'];
        $steps[] = mbqa_step_result('error_log', true, $errorLogScope['note']);

        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $listOk = $index['status'] === 200 && !mbqa_has_fatal($index['body']);
        $listNote = $listOk
            ? ('HTTP ' . $index['status'] . ', no fatal')
            : ('HTTP ' . $index['status'] . (mbqa_has_fatal($index['body']) ? ', fatal in body' : ''));
        if ($listOk && $tier === 'A' && itm_is_safe_identifier($slug)) {
            $perPageList = mbqa_records_per_page($conn, $companyId);
            $rowCountList = mbqa_tenant_row_count($conn, $slug, $companyId);
            $listNaNote = mbqa_runner_module_step_exception_note($slug, 'list');
            $bulkDeleteNa = mbqa_runner_module_step_exception_note($slug, 'bulk_delete');
            if ($listNaNote !== null) {
                $bulkGate = ['ok' => true, 'note' => $listNaNote];
            } elseif ($bulkDeleteNa !== null) {
                $bulkGate = ['ok' => true, 'note' => 'bulk UI intentionally hidden; ' . $bulkDeleteNa];
            } else {
                $bulkGate = mbqa_index_bulk_ui_matches_row_gate($rowCountList, $perPageList, $index['body']);
            }
            $pagGate = mbqa_index_pagination_matches_row_gate($rowCountList, $perPageList, $index['body']);
            if (!$bulkGate['ok']) {
                $listOk = false;
                $listNote .= '; ' . $bulkGate['note'];
            }
            if (!$pagGate['ok']) {
                $listOk = false;
                $listNote .= '; ' . $pagGate['note'];
            }
            if ($bulkGate['ok'] && $pagGate['ok']) {
                $listNote .= '; ' . $bulkGate['note'] . '; ' . $pagGate['note'];
            }
        }
        $steps[] = mbqa_step_result('list', $listOk, $listNote);

        $uiCheckOk = true;
        $uiCheckNote = 'N/A (Tier ' . $tier . ')';
        if ($tier === 'A' && itm_is_safe_identifier($slug)) {
            $uiCheckNa = mbqa_runner_module_step_exception_note($slug, 'ui_check');
            if ($uiCheckNa !== null) {
                $uiCheckOk = true;
                $uiCheckNote = $uiCheckNa;
            } elseif ($index['status'] !== 200 || mbqa_has_fatal($index['body'])) {
                $uiCheckOk = false;
                $uiCheckNote = 'Index not available for UI check (list failed)';
            } else {
                $actionsGate = mbqa_index_table_actions_matches_ui_contract($index['body']);
                $uiCheckOk = $actionsGate['ok'];
                $uiCheckNote = $actionsGate['note'];
            }
        } elseif ($tier === 'D') {
            $uiCheckNote = 'N/A smoke';
        } elseif ($tier === 'C') {
            $uiCheckNote = mbqa_runner_module_step_exception_note($slug, 'ui_check') ?? 'N/A routing';
        }
        $steps[] = mbqa_step_result('ui_check', $uiCheckOk, $uiCheckNote);

        if ($tier === 'D') {
            $steps[] = mbqa_step_result('clear', true, 'Skip (bespoke smoke)');
            $steps[] = mbqa_step_result('sample_data', true, 'N/A smoke');
            $steps[] = mbqa_step_result('add', true, 'N/A smoke');
            $steps[] = mbqa_step_result('pagination', true, 'N/A smoke');
            $steps[] = mbqa_step_result('create', true, 'N/A smoke');
            $steps[] = mbqa_step_result('view', true, 'N/A smoke');
            $steps[] = mbqa_step_result('edit', true, 'N/A smoke');
            $steps[] = mbqa_step_result('list_all', true, 'N/A');
            $steps[] = mbqa_step_result('search', $listOk, 'index only');
            $steps[] = mbqa_step_result('sort', $listOk, 'index only');
            $steps[] = mbqa_step_result('export_pdf', true, 'N/A smoke');
            $steps[] = mbqa_step_result('export_xlsx', true, 'N/A smoke');
            $steps[] = mbqa_step_result('import_db', true, 'N/A smoke');
            $steps[] = mbqa_step_result('single_delete', true, 'N/A smoke');
            $steps[] = mbqa_step_result('bulk_cancel', true, 'N/A smoke');
            $steps[] = mbqa_step_result('bulk_delete', true, 'N/A');
            $steps[] = mbqa_step_result('clear_table', true, 'N/A');
            $steps[] = mbqa_step_result('sample_data', true, 'N/A (end restore)');
            $errorLog = mbqa_read_error_log_since($errorLogOffset);
            $steps[] = mbqa_step_result('error_log', $errorLog['ok'], $errorLog['note']);
            $results[] = [
                'module' => $slug,
                'company_id' => $companyId,
                'company_name' => $companyNames[$companyId],
                'tier' => $tier,
                'steps' => $steps,
            ];
            continue;
        }

        if ($tier === 'C' && mbqa_is_facade_routing_module($slug)) {
            $routeOk = $listOk;
            foreach (['clear', 'sample_data', 'add'] as $s) {
                $naNote = mbqa_runner_module_step_exception_note($slug, $s) ?? 'N/A routing';
                $steps[] = mbqa_step_result($s, true, $naNote);
            }
            foreach (['create', 'view', 'edit', 'list_all', 'single_delete', 'search', 'sort', 'export_pdf', 'export_xlsx', 'import_db', 'pagination', 'bulk_cancel', 'bulk_delete', 'clear_table'] as $s) {
                $naNote = mbqa_runner_module_step_exception_note($slug, $s) ?? 'N/A routing';
                $steps[] = mbqa_step_result($s, $routeOk, $naNote);
            }
            $endSampleNote = mbqa_runner_module_step_exception_note($slug, 'sample_data') ?? 'N/A routing';
            $steps[] = mbqa_step_result('sample_data', true, $endSampleNote);
            $errorLog = mbqa_read_error_log_since($errorLogOffset);
            $steps[] = mbqa_step_result('error_log', $errorLog['ok'], $errorLog['note']);
            $results[] = [
                'module' => $slug,
                'company_id' => $companyId,
                'company_name' => $companyNames[$companyId],
                'tier' => $tier,
                'steps' => $steps,
            ];
            continue;
        }

        $csrfIndex = mbqa_extract_csrf($index['body']);
        $ids = mbqa_row_ids($index['body']);

        $clearNaNote = mbqa_runner_module_step_exception_note($slug, 'clear');
        if ($clearNaNote !== null) {
            $steps[] = mbqa_step_result('clear', true, $clearNaNote);
        } elseif (!in_array($slug, $skipClear, true) && itm_is_safe_identifier($slug)) {
            $detachedCount = mbqa_temporarily_detach_never_clear_fk_refs($conn, $slug, $companyId, $detachedProtectedFkRefs);
            $clearNote = '';
            $cleared = mbqa_clear_module_table_for_company($conn, $slug, $companyId, $clearNote);
            if ($detachedCount > 0) {
                $clearNote .= ($clearNote !== '' ? '; ' : '') . 'temporarily detached protected FK refs=' . $detachedCount;
            }
            $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
            $csrfIndex = mbqa_extract_csrf($index['body']);
            $steps[] = mbqa_step_result('clear', $cleared, $clearNote !== '' ? $clearNote : ($cleared ? 'SQL tenant clear' : 'Clear failed'));
        } else {
            $steps[] = mbqa_step_result('clear', true, 'Skip destructive clear');
        }

        $sampleDataNaNote = mbqa_runner_module_step_exception_note($slug, 'sample_data');
        if ($sampleDataNaNote !== null) {
            $steps[] = mbqa_step_result('sample_data', true, $sampleDataNaNote);
        } else {
            $seedResult = mbqa_ensure_sample_data($conn, $slug, $companyId, $moduleUrl, $cookieFile);
            $index['body'] = $seedResult['html'];
            $csrfIndex = $seedResult['csrf'];
            if ($seedResult['na']) {
                $steps[] = mbqa_step_result('sample_data', true, $seedResult['note']);
            } else {
                $seedHtml = mbqa_html_step_sample_data($seedResult['html'], 200, true);
                $seedOk = $seedResult['ok'] && $seedHtml['ok'];
                $seedNote = $seedResult['note'];
                if ($seedResult['ok'] && $seedHtml['note'] !== '') {
                    $seedNote .= '; ' . $seedHtml['note'];
                }
                if ($seedResult['ok'] && !$seedHtml['ok']) {
                    $seedNote = $seedHtml['note'];
                }
                $steps[] = mbqa_step_result('sample_data', $seedOk, $seedNote);
            }
        }

        $_SESSION['company_id'] = $companyId;
        $addNaNote = mbqa_runner_module_step_exception_note($slug, 'add');
        if ($addNaNote !== null) {
            // Why: audit_logs is read-only in the UI but QA still needs database.sql rows for list/pagination/view steps.
            if ($slug === 'audit_logs') {
                mbqa_ensure_bulk_sample_rows($conn, $slug, $companyId);
            }
            $steps[] = mbqa_step_result('add', true, $addNaNote);
        } else {
            $bulkResult = mbqa_ensure_bulk_sample_rows($conn, $slug, $companyId);
            if ($bulkResult['na']) {
                $steps[] = mbqa_step_result('add', true, $bulkResult['note']);
            } else {
                $indexAfterAdd = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
                $minHtmlRows = min((int)($bulkResult['count'] ?? 0), 1);
                $addHtml = mbqa_html_step_add_index($indexAfterAdd['body'], $indexAfterAdd['status'], max(1, $minHtmlRows));
                $addOk = $bulkResult['ok'] && $addHtml['ok'];
                $addNote = $bulkResult['note'];
                if ($bulkResult['ok'] && $addHtml['ok'] && $addHtml['note'] !== '') {
                    $addNote .= '; ' . $addHtml['note'];
                }
                if ($bulkResult['ok'] && !$addHtml['ok']) {
                    $addNote = $addHtml['note'];
                }
                $steps[] = mbqa_step_result('add', $addOk, $addNote);
            }
        }

        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $csrfIndex = mbqa_extract_csrf($index['body']);

        $perPage = mbqa_records_per_page($conn, $companyId);
        $rowCountAfterAdd = mbqa_tenant_row_count($conn, $slug, $companyId);
        $paginationNaNote = mbqa_runner_module_step_exception_note($slug, 'pagination');
        if ($paginationNaNote !== null) {
            $steps[] = mbqa_step_result('pagination', true, $paginationNaNote);
        } else {
            $paginationNav = mbqa_run_pagination_nav_step($moduleUrl, $cookieFile, $rowCountAfterAdd, $perPage);
            $steps[] = mbqa_step_result('pagination', $paginationNav['ok'], $paginationNav['note']);
        }

        $bulkCancelNaNote = mbqa_runner_module_step_exception_note($slug, 'bulk_cancel');
        if ($bulkCancelNaNote !== null) {
            $steps[] = mbqa_step_result('bulk_cancel', true, $bulkCancelNaNote);
        } else {
            $bulkCancelCheck = mbqa_verify_bulk_cancel_contract($index['body']);
            if ($bulkCancelCheck['na']) {
                $steps[] = mbqa_step_result('bulk_cancel', true, $bulkCancelCheck['note']);
            } else {
                $steps[] = mbqa_step_result('bulk_cancel', $bulkCancelCheck['ok'], $bulkCancelCheck['note']);
            }
        }

        $deletePath = $modulesDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'delete.php';
        $canBulkAfterAdd = $rowCountAfterAdd >= $perPage && mbqa_index_shows_bulk_actions($index['body']);

        $bulkDeleteNaNote = mbqa_runner_module_step_exception_note($slug, 'bulk_delete');
        if ($bulkDeleteNaNote !== null) {
            $steps[] = mbqa_step_result('bulk_delete', true, $bulkDeleteNaNote);
        } elseif ($canBulkAfterAdd && is_file($deletePath) && $csrfIndex !== '') {
            $bulkHtmlReady = mbqa_html_step_bulk_delete_ready($index['body']);
            $bulkIds = array_slice(mbqa_row_ids($index['body']), 0, 3);
            if (!empty($bulkIds) && $bulkHtmlReady['ok']) {
                $bulkDelEarly = mbqa_run_bulk_delete($moduleUrl, $cookieFile, $csrfIndex, $bulkIds);
                $bulkNote = $bulkDelEarly['note'] . '; ' . $bulkHtmlReady['note'];
                $steps[] = mbqa_step_result('bulk_delete', $bulkDelEarly['ok'], $bulkNote);
            } elseif (!empty($bulkIds) && !$bulkHtmlReady['ok']) {
                $steps[] = mbqa_step_result('bulk_delete', false, $bulkHtmlReady['note']);
                $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
                $csrfIndex = mbqa_extract_csrf($index['body']);
            } else {
                $steps[] = mbqa_step_result('bulk_delete', true, 'N/A (no ids[] on index after add)');
            }
        } else {
            $steps[] = mbqa_step_result(
                'bulk_delete',
                true,
                mbqa_bulk_step_na_note($rowCountAfterAdd, $perPage, $index['body'], $deletePath, $csrfIndex, 'after add')
            );
        }

        $searchNaNote = mbqa_runner_module_step_exception_note($slug, 'search');
        if ($searchNaNote !== null) {
            $steps[] = mbqa_step_result('search', true, $searchNaNote);
        } else {
            $search = mbqa_http($moduleUrl . 'index.php?search=sample&page=1', 'GET', null, [], $cookieFile);
            $searchHtml = mbqa_html_step_search($search['body'], $search['status']);
            $steps[] = mbqa_step_result('search', $searchHtml['ok'], $searchHtml['note']);
        }

        $sortNaNote = mbqa_runner_module_step_exception_note($slug, 'sort');
        if ($sortNaNote !== null) {
            $steps[] = mbqa_step_result('sort', true, $sortNaNote);
        } else {
            $sortField = 'id';
            if (preg_match('/\?[^"\']*sort=([a-zA-Z0-9_]+)/', $index['body'], $sortMatch)) {
                $sortField = $sortMatch[1];
            }
            $sort = mbqa_http($moduleUrl . 'index.php?sort=' . rawurlencode($sortField) . '&dir=DESC&page=1', 'GET', null, [], $cookieFile);
            $sortHtml = mbqa_html_step_sort($sort['body'], $sort['status'], $sortField);
            $steps[] = mbqa_step_result('sort', $sortHtml['ok'], $sortHtml['note']);
        }

        $createNaNote = mbqa_runner_module_step_exception_note($slug, 'create');
        if ($createNaNote !== null) {
            $steps[] = mbqa_step_result('create', true, $createNaNote);
        } else {
            $createResult = mbqa_run_create_screen_step($moduleUrl, $modulesDir, $slug, $cookieFile);
            $steps[] = mbqa_step_result('create', $createResult['ok'], $createResult['note']);
        }

        $ids = mbqa_row_ids($index['body']);
        $viewId = $ids[0] ?? 0;
        $viewNaNote = mbqa_runner_module_step_exception_note($slug, 'view');
        $editNaNote = mbqa_runner_module_step_exception_note($slug, 'edit');
        if ($viewNaNote !== null) {
            $steps[] = mbqa_step_result('view', true, $viewNaNote);
        } elseif ($viewId > 0) {
            $view = mbqa_http($moduleUrl . 'view.php?id=' . $viewId, 'GET', null, [], $cookieFile);
            $viewHtml = mbqa_html_step_view($view['body'], $view['status'], $viewId);
            $steps[] = mbqa_step_result('view', $viewHtml['ok'], $viewHtml['note']);
        } else {
            $steps[] = mbqa_step_result('view', true, 'N/A no rows');
        }
        if ($editNaNote !== null) {
            $steps[] = mbqa_step_result('edit', true, $editNaNote);
        } elseif ($viewNaNote === null && $viewId > 0) {
            $edit = mbqa_http($moduleUrl . 'edit.php?id=' . $viewId, 'GET', null, [], $cookieFile);
            $editHtml = mbqa_html_step_edit($edit['body'], $edit['status'], $viewId);
            $steps[] = mbqa_step_result('edit', $editHtml['ok'], $editHtml['note']);
        } else {
            $steps[] = mbqa_step_result('edit', true, 'N/A no rows');
        }

        $listAllPath = $modulesDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'list_all.php';
        $listAllNaNote = mbqa_runner_module_step_exception_note($slug, 'list_all');
        if ($listAllNaNote !== null) {
            $steps[] = mbqa_step_result('list_all', true, $listAllNaNote);
        } elseif (is_file($listAllPath)) {
            $la = mbqa_http($moduleUrl . 'list_all.php', 'GET', null, [], $cookieFile);
            $laHtml = mbqa_html_step_list_all($la['body'], $la['status']);
            $steps[] = mbqa_step_result('list_all', $laHtml['ok'], $laHtml['note']);
        } else {
            $steps[] = mbqa_step_result('list_all', true, 'N/A');
        }

        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $csrfIndex = mbqa_extract_csrf($index['body']);

        $exportButtons = mbqa_index_has_export_buttons($index['body']);
        $exportRows = mbqa_extract_table_export_rows($index['body']);
        $hasTableExport = count($exportRows) >= 2;

        $exportPdfNaNote = mbqa_runner_module_step_exception_note($slug, 'export_pdf');
        if ($exportPdfNaNote !== null) {
            $steps[] = mbqa_step_result('export_pdf', true, $exportPdfNaNote);
        } else {
            $exportPdfHtml = mbqa_html_step_export($index['body'], 'pdf', $hasTableExport);
            $steps[] = mbqa_step_result('export_pdf', $exportPdfHtml['ok'], $exportPdfHtml['note']);
        }

        $exportXlsxNaNote = mbqa_runner_module_step_exception_note($slug, 'export_xlsx');
        if ($exportXlsxNaNote !== null) {
            $steps[] = mbqa_step_result('export_xlsx', true, $exportXlsxNaNote);
        } else {
            $exportXlsxHtml = mbqa_html_step_export($index['body'], 'xlsx', $hasTableExport);
            $steps[] = mbqa_step_result('export_xlsx', $exportXlsxHtml['ok'], $exportXlsxHtml['note']);
        }

        $canRunClearTable = $rowCountAfterAdd >= $perPage
            && mbqa_index_shows_bulk_actions($index['body'])
            && is_file($deletePath)
            && $csrfIndex !== '';

        $clearTableNaNote = mbqa_runner_module_step_exception_note($slug, 'clear_table');
        if ($clearTableNaNote !== null) {
            $steps[] = mbqa_step_result('clear_table', true, $clearTableNaNote);
        } elseif ($canRunClearTable) {
            $clearTableHtml = mbqa_html_step_clear_table_button($index['body']);
            if (!$clearTableHtml['ok']) {
                $steps[] = mbqa_step_result('clear_table', false, $clearTableHtml['note']);
            } else {
                $detachedCount = mbqa_temporarily_detach_never_clear_fk_refs($conn, $slug, $companyId, $detachedProtectedFkRefs);
                $clearResult = mbqa_run_clear_table($moduleUrl, $cookieFile, $csrfIndex, $slug, $conn, $companyId);
                $clearTableNote = $clearResult['note'] . '; ' . $clearTableHtml['note'];
                if ($detachedCount > 0) {
                    $clearTableNote .= '; temporarily detached protected FK refs=' . $detachedCount;
                }
                $steps[] = mbqa_step_result('clear_table', $clearResult['ok'], $clearTableNote);
            }
        } else {
            $steps[] = mbqa_step_result(
                'clear_table',
                true,
                mbqa_bulk_step_na_note($rowCountAfterAdd, $perPage, $index['body'], $deletePath, $csrfIndex, 'before import clear')
            );
        }

        // Why: repeat tenant clear after export (and optional clear_table) so import_db runs on an empty table.
        if ($clearNaNote !== null) {
            $steps[] = mbqa_step_result('clear', true, $clearNaNote);
        } elseif (!in_array($slug, $skipClear, true) && itm_is_safe_identifier($slug)) {
            $detachedCount = mbqa_temporarily_detach_never_clear_fk_refs($conn, $slug, $companyId, $detachedProtectedFkRefs);
            $clearBeforeImportNote = '';
            $clearedBeforeImport = mbqa_clear_module_table_for_company($conn, $slug, $companyId, $clearBeforeImportNote);
            if ($detachedCount > 0) {
                $clearBeforeImportNote .= ($clearBeforeImportNote !== '' ? '; ' : '') . 'temporarily detached protected FK refs=' . $detachedCount;
            }
            $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
            $csrfIndex = mbqa_extract_csrf($index['body']);
            $steps[] = mbqa_step_result(
                'clear',
                $clearedBeforeImport,
                $clearBeforeImportNote !== '' ? $clearBeforeImportNote : ($clearedBeforeImport ? 'SQL tenant clear' : 'Clear failed')
            );
        } else {
            $steps[] = mbqa_step_result('clear', true, 'Skip destructive clear');
        }

        $importHtml = mbqa_html_step_import_db($index['body'], $hasTableExport);
        $importDbNa = mbqa_runner_module_step_exception_note($slug, 'import_db');
        if ($importDbNa !== null) {
            $steps[] = mbqa_step_result('import_db', true, $importDbNa);
        } elseif ($importHtml['ok'] && $csrfIndex !== '' && $hasTableExport) {
            // Why: import rows pick a free cost_center via mbqa_unique_expense_import_row; export rows were captured before the second clear.

            $indexImport = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
            $csrfIndex = mbqa_extract_csrf($indexImport['body']);
            $_SESSION['company_id'] = $companyId;
            $importRows = mbqa_import_rows_for_round_trip($conn, $slug, $companyId, $exportRows);
            if (empty($importRows)) {
                $importRows = mbqa_build_import_rows_from_export($exportRows);
            }
            $importNote = 'Export Excel headers with insertable row (database.sql FK ids when needed)';
            $importPayload = json_encode([
                'csrf_token' => $csrfIndex,
                'import_excel_rows' => $importRows,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $import = mbqa_http(
                $moduleUrl . 'index.php',
                'POST',
                $importPayload,
                ['Content-Type: application/json'],
                $cookieFile
            );
            $importParsed = mbqa_parse_import_response($import);
            $inserted = $importParsed['inserted'];
            $importOk = $importParsed['ok'];
            $importFailureNote = $importParsed['note'];

            if (!$importOk) {
                $dbImportRows = mbqa_build_import_rows_from_db_template($conn, $slug, $companyId);
                if (!empty($dbImportRows)) {
                    $importPayload = json_encode([
                        'csrf_token' => $csrfIndex,
                        'import_excel_rows' => $dbImportRows,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $import = mbqa_http(
                        $moduleUrl . 'index.php',
                        'POST',
                        $importPayload,
                        ['Content-Type: application/json'],
                        $cookieFile
                    );
                    $importParsed = mbqa_parse_import_response($import);
                    $inserted = $importParsed['inserted'];
                    $importOk = $importParsed['ok'];
                    $importFailureNote = $importParsed['note'];
                    $importNote = 'from live DB row after round-trip import';
                }
            }

            $indexAfterImport = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
            $rowsAfterImport = mbqa_index_count_table_rows($indexAfterImport['body']);
            $htmlAfterImport = $importOk
                && $indexAfterImport['status'] === 200
                && !mbqa_has_fatal($indexAfterImport['body'])
                && $rowsAfterImport > 0;

            if ($importOk) {
                $importStepNote = $importHtml['note'] . '; imported ' . $importNote . '; inserted=' . $inserted;
                if ($htmlAfterImport) {
                    $importStepNote .= '; ' . $rowsAfterImport . ' row(s) in HTML after import';
                } else {
                    $importOk = false;
                    $importStepNote = 'import POST ok but list rows missing in HTML after import';
                }
            } else {
                $importStepNote = $importFailureNote;
            }

            $steps[] = mbqa_step_result('import_db', $importOk, $importStepNote);
        } else {
            $steps[] = mbqa_step_result(
                'import_db',
                true,
                $importHtml['ok']
                    ? 'N/A (need table rows for export/import)'
                    : mbqa_step_na_note($importHtml['note'])
            );
        }

        // Why: pre-import clear drops rows chosen for view/edit; import may insert new ids — delete from the current list.
        $indexForDelete = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $deleteRefreshOk = $indexForDelete['status'] === 200 && !mbqa_has_fatal($indexForDelete['body']);
        $deleteRefreshNote = '';
        if (!$deleteRefreshOk) {
            if (($indexForDelete['error'] ?? '') !== '') {
                $deleteRefreshNote = (string)$indexForDelete['error'];
            } elseif ($indexForDelete['status'] !== 200) {
                $deleteRefreshNote = 'HTTP ' . $indexForDelete['status'];
            } else {
                $deleteRefreshNote = 'fatal in body';
            }
        }
        $csrfForDelete = $deleteRefreshOk ? mbqa_extract_csrf($indexForDelete['body']) : '';
        $deleteId = $deleteRefreshOk ? (mbqa_row_ids($indexForDelete['body'])[0] ?? 0) : 0;

        $singleDeleteNaNote = mbqa_runner_module_step_exception_note($slug, 'single_delete');
        if ($singleDeleteNaNote !== null) {
            $steps[] = mbqa_step_result('single_delete', true, $singleDeleteNaNote);
        } elseif (!$deleteRefreshOk) {
            $steps[] = mbqa_step_result('single_delete', false, 'single_delete: index refresh failed (' . $deleteRefreshNote . ')');
        } elseif ($deleteId > 0 && is_file($deletePath) && $csrfForDelete !== '') {
            $singleDelHtml = mbqa_html_step_single_delete_action($indexForDelete['body'], $deleteId);
            if (!$singleDelHtml['ok']) {
                $steps[] = mbqa_step_result('single_delete', false, $singleDelHtml['note']);
            } else {
            $delResult = mbqa_delete_record_with_fk_retry(
                $conn,
                $moduleUrl,
                $slug,
                $deleteId,
                $companyId,
                $csrfForDelete,
                $cookieFile
            );
            $delNote = $delResult['note'];
            if ($singleDelHtml['note'] !== '') {
                $delNote .= '; ' . $singleDelHtml['note'];
            }
            $steps[] = mbqa_step_result('single_delete', $delResult['ok'], $delNote);
            }
        } else {
            $steps[] = mbqa_step_result('single_delete', true, $deleteId > 0 ? 'N/A (no delete.php/csrf)' : 'N/A no rows');
        }

        $endSampleDataNaNote = mbqa_runner_module_step_exception_note($slug, 'sample_data');
        if ($endSampleDataNaNote !== null) {
            $steps[] = mbqa_step_result('sample_data', true, $endSampleDataNaNote);
        } else {
            $endSeed = mbqa_http_sample_seed_end($moduleUrl, $cookieFile, $conn, $slug, $companyId);
            if ($endSeed['na']) {
                $steps[] = mbqa_step_result('sample_data', true, $endSeed['note']);
            } else {
                $endHtml = mbqa_html_step_sample_data((string)($endSeed['html'] ?? ''), 200, true);
                $endOk = $endSeed['ok'] && ($endSeed['na'] || $endHtml['ok']);
                $endNote = $endSeed['note'];
                if ($endSeed['ok'] && isset($endSeed['html']) && $endHtml['note'] !== '') {
                    $endNote .= '; ' . $endHtml['note'];
                }
                if ($endSeed['ok'] && isset($endSeed['html']) && !$endHtml['ok']) {
                    $endOk = false;
                    $endNote = $endHtml['note'];
                }
                $steps[] = mbqa_step_result('sample_data', $endOk, $endNote);
            }
        }

        $restoreNote = '';
        $restoreOk = mbqa_restore_temporarily_detached_fk_refs($conn, $detachedProtectedFkRefs, $restoreNote);
        $errorLog = mbqa_read_error_log_since($errorLogOffset);
        if ($restoreNote !== '') {
            $errorLog['note'] .= '; ' . $restoreNote;
            $errorLog['ok'] = $errorLog['ok'] && $restoreOk;
        }
        $steps[] = mbqa_step_result('error_log', $errorLog['ok'], $errorLog['note']);

        $results[] = [
            'module' => $slug,
            'company_id' => $companyId,
            'company_name' => $companyNames[$companyId],
            'tier' => $tier,
            'steps' => $steps,
        ];
    }
}

@unlink($cookieFile);

$outDir = mbqa_report_dir($root);
if (!mbqa_ensure_reports_dir_writable($root)) {
    mbqa_fail_preflight('qa-reports is not writable; cannot write module browser QA output.');
}
$generatedAt = date('Y-m-d H:i:s');
$reportFilePaths = mbqa_report_paths_for_run($root, $generatedAt);
$jsonPath = $reportFilePaths['json_path'];
$reportPayload = [
    'generated_at' => $generatedAt,
    'report_files' => [
        'slug' => $reportFilePaths['slug'],
        'json' => $reportFilePaths['json_basename'],
        'xlsx' => $reportFilePaths['xlsx_basename'],
    ],
    'run_id' => mbqa_browser_ajax_active() ? mbqa_browser_ajax_run_id() : '',
    'module_step_exceptions' => mbqa_runner_module_step_exceptions(),
    'run_options' => [
        'module' => $filterModule,
        'company' => $filterCompany,
        'pilot_only' => $pilotOnly,
        'ui_click_smoke' => (bool)$mbqaOptions['ui_click_smoke'],
        'base_url' => $baseUrl,
    ],
    'results' => $results,
];
$jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
// JSON must not use BOM — json_decode() fails; UTF-8 bytes only.
itm_write_utf8_text_file($jsonPath, json_encode($reportPayload, $jsonFlags), false);

$failuresByCategory = [];
foreach ($results as $row) {
    foreach ($row['steps'] as $step) {
        if ($step['status'] === 'Pass') {
            $summary['pass']++;
        } else {
            $summary['fail']++;
            $cat = $row['module'] . ':' . $step['step'];
            $failuresByCategory[$cat] = ($failuresByCategory[$cat] ?? 0) + 1;
        }
    }
    if (($row['module'] ?? '') !== '_preflight') {
        $summary['modules']++;
    }
}

// Why: browser Run QA should execute "Run Clean Tests" silently again after all QA steps complete.
$mbqaEquipmentCleanup = $browserRunViaQaButton
    ? mbqa_run_clean_tests_silent()
    : itm_run_equipment_test_module_artifacts_cleanup($conn, $modulesDir);
$mbqaEquipmentCleanupNote = $browserRunViaQaButton
    ? mbqa_clean_tests_report_summary($mbqaEquipmentCleanup, 'Post-QA clean tests')
    : itm_equipment_cleanup_report_summary($mbqaEquipmentCleanup);

$xlsxBuilt = mbqar_build_runner_xlsx(
    $root,
    $results,
    (int)$summary['pass'],
    (int)$summary['fail'],
    (string)$reportPayload['generated_at'],
    $reportFilePaths['xlsx_path']
);

$exitCode = ($summary['fail'] > 0 || !$mbqaEquipmentCleanup['ok']) ? 1 : 0;
if (mbqa_is_cli_sapi()) {
    mbqa_out("Wrote {$jsonPath}\n");
    if ($xlsxBuilt['ok']) {
        mbqa_out("Wrote {$xlsxBuilt['path']}\n");
    } else {
        mbqa_err("XLSX: {$xlsxBuilt['error']}\n");
    }
    mbqa_out("Steps pass: {$summary['pass']}, fail: {$summary['fail']}\n");
    if ($mbqaEquipmentCleanupNote !== '') {
        mbqa_out($mbqaEquipmentCleanupNote . "\n");
    }
}

$jsonRel = '../qa-reports/' . $reportFilePaths['json_basename'];
$xlsxRel = $xlsxBuilt['ok'] ? ('../qa-reports/' . $reportFilePaths['xlsx_basename']) : '';
$reportHref = 'module_browser_qa_build_report.php?run=1';
$rerunParams = ['autostart' => '1'];
if ($filterModule !== null && trim((string)$filterModule) !== '') {
    $rerunParams['module'] = trim((string)$filterModule);
}
if ($filterCompany !== null && (int)$filterCompany > 0) {
    $rerunParams['company'] = (string)(int)$filterCompany;
}
if ($pilotOnly) {
    $rerunParams['pilot_only'] = '1';
    unset($rerunParams['module']);
}
if (!empty($mbqaOptions['ui_click_smoke'])) {
    $rerunParams['ui_click_smoke'] = '1';
}
if ($baseUrl !== 'http://localhost/it-management/') {
    $rerunParams['base_url'] = $baseUrl;
}
$rerunHref = 'module_browser_qa_runner.php?' . http_build_query($rerunParams);

$runStopped = mbqa_browser_ajax_active() && mbqa_ajax_should_stop($root);
$finalStatus = $runStopped ? 'cancelled' : 'done';
$finalMessage = $runStopped ? 'Run stopped by user' : ($exitCode === 0 ? '' : 'QA completed with failing steps');
if (!$runStopped && !$mbqaEquipmentCleanup['ok'] && $finalMessage === '') {
    $finalMessage = 'QA completed with failing steps';
}
if (!$runStopped && $mbqaEquipmentCleanupNote !== '') {
    $finalMessage = $finalMessage === ''
        ? $mbqaEquipmentCleanupNote
        : ($finalMessage . ' ' . $mbqaEquipmentCleanupNote);
}
mbqa_ajax_cleanup_stale_files($root, mbqa_browser_ajax_active() ? mbqa_browser_ajax_run_id() : '');

if (mbqa_browser_ajax_active()) {
    $ajaxDone = [
        'status' => $finalStatus,
        'company_id' => (int)($GLOBALS['mbqa_progress_company_id'] ?? 0),
        'module' => (string)($GLOBALS['mbqa_progress_module'] ?? ''),
        'step' => $runStopped ? 'stopped' : 'finished',
        'pass' => (int)$summary['pass'],
        'fail' => (int)$summary['fail'],
        'exit_code' => $runStopped ? 130 : $exitCode,
        'json_href' => $jsonRel,
        'xlsx_href' => $xlsxBuilt['ok'] ? $xlsxRel : '',
        'report_href' => $reportHref,
        'rerun_href' => $rerunHref,
        'message' => $finalMessage,
    ];
    mbqa_browser_ajax_write_progress($root, mbqa_browser_ajax_run_id(), $ajaxDone);
    @unlink(mbqa_ajax_cancel_path($root, mbqa_browser_ajax_run_id()));
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($ajaxDone, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit($runStopped ? 130 : $exitCode);
}

if (mbqa_browser_stream_active()) {
    mbqa_browser_stream_emit_done([
        'pass' => (int)$summary['pass'],
        'fail' => (int)$summary['fail'],
        'exit_code' => $exitCode,
        'json_href' => $jsonRel,
        'xlsx_href' => $xlsxBuilt['ok'] ? $xlsxRel : '',
        'report_href' => $reportHref,
        'rerun_href' => $rerunHref,
    ]);
}

exit($exitCode);
