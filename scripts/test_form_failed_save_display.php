<?php
/**
 * Test CRUD create forms for SQL-quoted values after failed saves.
 *
 * Why: Legacy handlers stored escaped SQL literals in $data and re-displayed
 * them as value="'USA'" after validation/DB errors.
 *
 * Browser: open while logged in (uses your session cookie for runtime tests).
 * CLI static: php scripts/test_form_failed_save_display.php
 * CLI runtime: set ITM_TEST_BASE_URL and ITM_TEST_COOKIE, then add --runtime
 */

declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

/**
 * @param mixed $value
 */
function itm_form_failed_save_display_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($itmIsCli) {
    try {
        require_once dirname(__DIR__) . '/config/config.php';
        require_once dirname(__DIR__) . '/includes/form_failed_save_test.php';
    } catch (Throwable $e) {
        fwrite(STDERR, 'Bootstrap failed: ' . $e->getMessage() . "\n");
        exit(1);
    }

    $doRuntime = in_array('--runtime', $argv ?? [], true);
    $moduleFilter = '';
    $limit = 0;
    foreach ($argv ?? [] as $arg) {
        if (strpos($arg, '--module=') === 0) {
            $moduleFilter = substr($arg, 9);
        }
        if (strpos($arg, '--limit=') === 0) {
            $limit = (int) substr($arg, 8);
        }
    }

    $result = itm_form_failed_save_test_run(ROOT_PATH . 'modules', [
        'static' => true,
        'runtime' => $doRuntime,
        'module_filter' => $moduleFilter,
        'limit' => $limit,
    ]);

    fwrite(STDOUT, "Probe: {$result['probe']}\n");
    fwrite(STDOUT, "Modules scanned: {$result['summary']['modules']}\n\n");

    fwrite(STDOUT, "[STATIC]\n");
    foreach ($result['static_results'] as $row) {
        $flag = $row['status'] === 'fail' ? 'FAIL' : strtoupper((string) $row['status']);
        fwrite(STDOUT, "  [{$flag}] {$row['module']}\n");
        foreach ($row['files'] as $fileRow) {
            if ($fileRow['status'] === 'fail') {
                fwrite(STDOUT, "         {$fileRow['file']}: {$fileRow['notes']}\n");
            }
        }
    }

    if ($doRuntime) {
        fwrite(STDOUT, "\n[RUNTIME]\n");
        foreach ($result['runtime_results'] as $row) {
            $flag = strtoupper((string) $row['status']);
            fwrite(STDOUT, "  [{$flag}] {$row['module']}: {$row['notes']}\n");
        }
    }

    fwrite(
        STDOUT,
        "\nSummary: static_fail={$result['summary']['static_fail']}, runtime_fail={$result['summary']['runtime_fail']}\n"
    );

    $exitCode = ($result['summary']['static_fail'] > 0 || $result['summary']['runtime_fail'] > 0) ? 1 : 0;
    exit($exitCode);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/form_failed_save_test.php';

if ($company_id <= 0) {
    http_response_code(401);
    exit('Login required. Sign in to the app, then open this script again.');
}

$baseUrl = defined('BASE_URL') ? (string) BASE_URL : '../';
$scriptSelf = $baseUrl . 'scripts/test_form_failed_save_display.php';
$result = null;
$runRuntime = false;
$moduleFilter = '';
$limit = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $runRuntime = !empty($_POST['run_runtime']);
    $moduleFilter = trim((string) ($_POST['module_filter'] ?? ''));
    $limit = max(0, (int) ($_POST['limit'] ?? 0));
    $result = itm_form_failed_save_test_run(ROOT_PATH . 'modules', [
        'static' => true,
        'runtime' => $runRuntime,
        'module_filter' => $moduleFilter,
        'limit' => $limit,
    ]);
}

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/lib/script_browser_nav.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Form failed-save display test</title>
    <link rel="stylesheet" href="<?= itm_form_failed_save_display_escape($baseUrl . 'css/styles.css'); ?>">
    <style>
        .itm-fst-wrap { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .itm-fst-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; margin-bottom: 16px; padding: 16px; }
        .itm-fst-muted { color: var(--text-muted, #57606a); line-height: 1.5; }
        .itm-fst-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .itm-fst-table th, .itm-fst-table td { border: 1px solid var(--border-color, #d0d7de); padding: 8px; text-align: left; vertical-align: top; }
        .itm-fst-table th { background: var(--table-header-bg, #f6f8fa); }
        .itm-fst-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
        .itm-fst-badge-ok { background: #dafbe1; color: #116329; }
        .itm-fst-badge-fail { background: #ffebe9; color: #cf222e; }
        .itm-fst-badge-warn { background: #fff8c5; color: #9a6700; }
        .itm-fst-badge-skip { background: #f6f8fa; color: #57606a; }
        .itm-fst-badge-error { background: #ffebe9; color: #82071e; }
        .itm-fst-summary span { display: inline-block; margin: 0 8px 8px 0; padding: 6px 10px; border-radius: 6px; background: var(--table-header-bg, #f6f8fa); border: 1px solid var(--border-color, #d0d7de); }
        .itm-fst-form-grid { display: grid; gap: 12px; max-width: 520px; }
        .itm-fst-form-grid label { display: block; font-weight: 600; margin-bottom: 4px; }
        .itm-fst-form-grid input[type="text"], .itm-fst-form-grid input[type="number"] { width: 100%; padding: 8px; }
    </style>
</head>
<body>
<div class="itm-fst-wrap">
<?php itm_script_browser_nav_echo($baseUrl); ?>
    <div class="itm-fst-card">
        <h1>Form failed-save display test</h1>
        <p class="itm-fst-muted">
            Finds modules that may show SQL-quoted values (e.g. <code>'USA'</code>) after a failed save,
            and optionally POSTs to each <code>modules/*/create.php</code> to trigger a controlled error and
            inspect the re-rendered HTML. All modules are scanned, including AGENTS.md protection-zone folders.
        </p>
        <p class="itm-fst-muted">
            Probe string: <code><?= itm_form_failed_save_display_escape(itm_form_failed_save_test_probe()); ?></code>
        </p>
    </div>

    <div class="itm-fst-card">
        <h2>Run</h2>
        <form method="post" action="<?= itm_form_failed_save_display_escape($scriptSelf); ?>" class="itm-fst-form-grid">
            <input type="hidden" name="csrf_token" value="<?= itm_form_failed_save_display_escape(itm_get_csrf_token()); ?>">
            <div>
                <label><input type="checkbox" name="run_runtime" value="1" <?= $runRuntime ? 'checked' : ''; ?>> Runtime HTTP tests (slower; uses your login session)</label>
            </div>
            <div>
                <label for="module_filter">Module filter (optional)</label>
                <input type="text" id="module_filter" name="module_filter" value="<?= itm_form_failed_save_display_escape($moduleFilter); ?>" placeholder="e.g. it_locations">
            </div>
            <div>
                <label for="limit">Limit modules (0 = all)</label>
                <input type="number" id="limit" name="limit" min="0" value="<?= (int) $limit; ?>">
            </div>
            <div>
                <button type="submit" class="btn-primary">Run tests</button>
            </div>
        </form>
        <p class="itm-fst-muted" style="margin-top:12px;">
            CLI static: <code>php scripts/test_form_failed_save_display.php</code><br>
            CLI runtime: <code>set ITM_TEST_BASE_URL=http://yoursite/it-management/</code> and
            <code>set ITM_TEST_COOKIE=PHPSESSID=...</code> then
            <code>php scripts/test_form_failed_save_display.php --runtime</code>
        </p>
    </div>

    <?php if ($result !== null): ?>
    <div class="itm-fst-card">
        <h2>Summary</h2>
        <div class="itm-fst-summary">
            <span>Modules: <strong><?= (int) $result['summary']['modules']; ?></strong></span>
            <span>Static fail: <strong><?= (int) $result['summary']['static_fail']; ?></strong></span>
            <?php if ($runRuntime): ?>
            <span>Runtime fail: <strong><?= (int) $result['summary']['runtime_fail']; ?></strong></span>
            <span>Runtime ok: <strong><?= (int) $result['summary']['runtime_ok']; ?></strong></span>
            <span>Runtime skip: <strong><?= (int) $result['summary']['runtime_skip']; ?></strong></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="itm-fst-card">
        <h2>Static scan (source code)</h2>
        <p class="itm-fst-muted">Flags modules whose entry files still store SQL literals in <code>$data</code> without <code>$sqlValues</code> + <code>cr_form_display_value()</code>.</p>
        <table class="itm-fst-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Module</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['static_results'] as $row): ?>
                    <?php
                    $badge = 'itm-fst-badge-ok';
                    if ($row['status'] === 'fail') {
                        $badge = 'itm-fst-badge-fail';
                    } elseif ($row['status'] === 'skip') {
                        $badge = 'itm-fst-badge-skip';
                    }
                    $details = [];
                    foreach ($row['files'] as $fileRow) {
                        if ($fileRow['status'] === 'fail') {
                            $details[] = $fileRow['file'] . ': ' . $fileRow['notes'];
                        }
                    }
                    ?>
                <tr>
                    <td><span class="itm-fst-badge <?= $badge; ?>"><?= itm_form_failed_save_display_escape(strtoupper((string) $row['status'])); ?></span></td>
                    <td><?= itm_script_format_module_link((string)$row['module'], $baseUrl); ?></td>
                    <td><?= $details ? itm_form_failed_save_display_escape(implode(' · ', $details)) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($runRuntime): ?>
    <div class="itm-fst-card">
        <h2>Runtime HTTP (create forms)</h2>
        <p class="itm-fst-muted">POSTs invalid / incomplete data to force a failed save, then checks the HTML for <code>value="'…probe…'"</code>.</p>
        <table class="itm-fst-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Module</th>
                    <th>HTTP</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['runtime_results'] as $row): ?>
                    <?php
                    $badge = 'itm-fst-badge-skip';
                    if ($row['status'] === 'ok') {
                        $badge = 'itm-fst-badge-ok';
                    } elseif ($row['status'] === 'fail') {
                        $badge = 'itm-fst-badge-fail';
                    } elseif ($row['status'] === 'warn') {
                        $badge = 'itm-fst-badge-warn';
                    } elseif ($row['status'] === 'error') {
                        $badge = 'itm-fst-badge-error';
                    }
                    ?>
                <tr>
                    <td><span class="itm-fst-badge <?= $badge; ?>"><?= itm_form_failed_save_display_escape(strtoupper((string) $row['status'])); ?></span></td>
                    <td><?= itm_script_format_module_link((string)$row['module'], $baseUrl); ?></td>
                    <td><?= (int) $row['http_status']; ?></td>
                    <td><?= itm_form_failed_save_display_escape($row['notes']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
