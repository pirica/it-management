<?php
/**
 * First-run setup wizard (cPanel-style step installer).
 *
 * Open setup/index.php in the browser while installation is incomplete.
 * Step 8 removes this file and writes setup/.installed.
 */

declare(strict_types=1);

define('ITM_SETUP_WIZARD', true);

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/itm_setup_wizard.php';

if (itm_setup_wizard_is_complete() && empty($_GET['force'])) {
    header('Location: ' . BASE_URL . 'login.php?setup=complete');
    exit;
}

$steps = itm_setup_wizard_steps();
$state = itm_setup_wizard_state();
$currentStep = itm_setup_wizard_current_step();
$flash = isset($state['flash']) && is_array($state['flash']) ? $state['flash'] : ['type' => '', 'message' => ''];
$envFile = itm_setup_wizard_read_env_file();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_try_post_csrf()) {
        itm_setup_wizard_state_set(['flash' => ['type' => 'error', 'message' => 'Invalid CSRF token. Refresh and try again.']]);
        header('Location: ' . BASE_URL . 'setup/index.php?step=' . $currentStep);
        exit;
    }

    $action = (string)($_POST['wizard_action'] ?? '');
    $postedStep = (int)($_POST['step'] ?? $currentStep);

    if ($action === 'goto_step') {
        $target = (int)($_POST['target_step'] ?? 1);
        itm_setup_wizard_set_step($target);
        header('Location: ' . BASE_URL . 'setup/index.php?step=' . $target);
        exit;
    }

    if ($action === 'step1_preview') {
        header('Content-Type: application/json; charset=utf-8');
        $payload = itm_setup_wizard_step1_preview_payload((string)($_POST['project_root'] ?? ''), true);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'step1_save') {
        $projectRootRaw = (string)($_POST['project_root'] ?? '');
        $confirmReplaceFolder = !empty($_POST['confirm_replace_folder']);
        $rootCheck = itm_setup_wizard_provision_project_root($projectRootRaw, $confirmReplaceFolder);
        if (!$rootCheck['ok']) {
            itm_setup_wizard_state_set([
                'project_root' => itm_setup_wizard_format_path_for_input($projectRootRaw),
                'folder_probe' => [
                    'needs_replace_confirm' => !empty($rootCheck['needs_replace_confirm']),
                ],
                'flash' => ['type' => !empty($rootCheck['needs_replace_confirm']) ? 'info' : 'error', 'message' => $rootCheck['message']],
            ]);
            header('Location: ' . BASE_URL . 'setup/index.php?step=1');
            exit;
        }

        $appUrl = rtrim(trim((string)($_POST['itm_app_url'] ?? '')), '/') . '/';
        if ($appUrl === '/') {
            $appUrl = itm_setup_wizard_detect_paths()['base_url'];
        }
        $successMessage = $rootCheck['message'] !== ''
            ? $rootCheck['message']
            : 'Install folder confirmed.';
        itm_setup_wizard_state_set([
            'project_root' => itm_setup_wizard_format_path_for_input($rootCheck['path']),
            'itm_app_url' => $appUrl,
            'install_notes' => trim((string)($_POST['install_notes'] ?? '')),
            'file_checks' => null,
            'folder_probe' => ['needs_replace_confirm' => false],
            'flash' => ['type' => 'success', 'message' => $successMessage],
        ]);
        itm_setup_wizard_mark_step_done(1);
        itm_setup_wizard_set_step(2);
        header('Location: ' . BASE_URL . 'setup/index.php?step=2');
        exit;
    }

    if ($action === 'step2_run') {
        $checks = itm_setup_wizard_verify_files();
        $ensure = itm_setup_wizard_ensure_directories();
        $failed = false;
        foreach (array_merge($checks, $ensure) as $row) {
            if ($row['level'] === 'fail') {
                $failed = true;
                break;
            }
        }
        itm_setup_wizard_state_set([
            'file_checks' => $checks,
            'ensure_results' => $ensure,
            'flash' => [
                'type' => $failed ? 'error' : 'success',
                'message' => $failed ? 'File verification found blocking issues.' : 'File verification passed.',
            ],
        ]);
        if (!$failed) {
            itm_setup_wizard_mark_step_done(2);
            itm_setup_wizard_set_step(3);
        }
        header('Location: ' . BASE_URL . 'setup/index.php?step=' . ($failed ? 2 : 3));
        exit;
    }

    if ($action === 'step3_test') {
        $host = trim((string)($_POST['db_host'] ?? '127.0.0.1'));
        $port = (int)($_POST['db_port'] ?? 3306);
        $user = trim((string)($_POST['db_user'] ?? 'root'));
        $pass = (string)($_POST['db_pass'] ?? '');
        $name = trim((string)($_POST['db_name'] ?? 'itmanagement'));
        $test = itm_setup_wizard_test_database($host, $port, $user, $pass, $name);
        $flashType = 'error';
        if ($test['ok']) {
            $flashType = 'success';
        } elseif (!empty($test['needs_create'])) {
            $flashType = 'info';
        }
        itm_setup_wizard_state_set([
            'db' => compact('host', 'port', 'user', 'pass', 'name'),
            'db_probe' => [
                'needs_create' => !empty($test['needs_create']),
                'needs_replace_confirm' => !empty($test['needs_replace_confirm']),
                'table_count' => (int)($test['table_count'] ?? 0),
                'database_exists' => !empty($test['database_exists']),
            ],
            'flash' => ['type' => $flashType, 'message' => $test['message']],
        ]);
        header('Location: ' . BASE_URL . 'setup/index.php?step=3');
        exit;
    }

    if ($action === 'step3_create_db') {
        $host = trim((string)($_POST['db_host'] ?? '127.0.0.1'));
        $port = (int)($_POST['db_port'] ?? 3306);
        $user = trim((string)($_POST['db_user'] ?? 'root'));
        $pass = (string)($_POST['db_pass'] ?? '');
        $name = trim((string)($_POST['db_name'] ?? 'itmanagement'));
        $create = itm_setup_wizard_create_database($host, $port, $user, $pass, $name);
        itm_setup_wizard_state_set([
            'db' => compact('host', 'port', 'user', 'pass', 'name'),
            'db_probe' => [
                'needs_create' => !$create['ok'],
                'needs_replace_confirm' => false,
                'table_count' => 0,
                'database_exists' => $create['ok'],
            ],
            'flash' => ['type' => $create['ok'] ? 'success' : 'error', 'message' => $create['message']],
        ]);
        header('Location: ' . BASE_URL . 'setup/index.php?step=3');
        exit;
    }

    if ($action === 'step3_import') {
        $db = $state['db'] ?? [];
        $host = (string)($db['host'] ?? trim((string)($_POST['db_host'] ?? '127.0.0.1')));
        $port = (int)($db['port'] ?? (int)($_POST['db_port'] ?? 3306));
        $user = (string)($db['user'] ?? trim((string)($_POST['db_user'] ?? 'root')));
        $pass = (string)($db['pass'] ?? (string)($_POST['db_pass'] ?? ''));
        $name = (string)($db['name'] ?? trim((string)($_POST['db_name'] ?? 'itmanagement')));
        $confirmReplace = !empty($_POST['confirm_replace']);
        $probe = itm_setup_wizard_probe_database($host, $port, $user, $pass, $name);

        if (!$probe['database_exists']) {
            itm_setup_wizard_state_set([
                'db' => compact('host', 'port', 'user', 'pass', 'name'),
                'db_probe' => [
                    'needs_create' => true,
                    'needs_replace_confirm' => false,
                    'table_count' => 0,
                    'database_exists' => false,
                ],
                'flash' => [
                    'type' => 'error',
                    'message' => 'Database "' . $name . '" does not exist. Create it before importing.',
                ],
            ]);
            header('Location: ' . BASE_URL . 'setup/index.php?step=3');
            exit;
        }

        if ($probe['needs_replace_confirm'] && !$confirmReplace) {
            itm_setup_wizard_state_set([
                'db' => compact('host', 'port', 'user', 'pass', 'name'),
                'db_probe' => [
                    'needs_create' => false,
                    'needs_replace_confirm' => true,
                    'table_count' => (int)$probe['table_count'],
                    'database_exists' => true,
                ],
                'flash' => [
                    'type' => 'error',
                    'message' => 'Database "' . $name . '" already contains ' . (int)$probe['table_count']
                        . ' table(s). Confirm replacement to destroy all existing structure and data.',
                ],
            ]);
            header('Location: ' . BASE_URL . 'setup/index.php?step=3');
            exit;
        }

        if ($probe['needs_replace_confirm'] && $confirmReplace) {
            $reset = itm_setup_wizard_reset_database($host, $port, $user, $pass, $name);
            if (!$reset['ok']) {
                itm_setup_wizard_state_set([
                    'flash' => ['type' => 'error', 'message' => $reset['message']],
                ]);
                header('Location: ' . BASE_URL . 'setup/index.php?step=3');
                exit;
            }
        }

        $import = itm_setup_wizard_import_database($host, $port, $user, $pass, $name);
        $connAfter = itm_setup_wizard_connect_database(compact('host', 'port', 'user', 'pass', 'name'));
        $tableCount = $connAfter instanceof mysqli ? itm_setup_wizard_count_tables($connAfter, $name) : 0;
        $triggerCount = $connAfter instanceof mysqli ? itm_setup_wizard_count_triggers($connAfter, $name) : 0;
        $expected = itm_setup_wizard_expected_table_count();
        $expectedTriggers = itm_setup_wizard_expected_trigger_count();
        $importOk = $import['ok'] && $tableCount >= $expected && $triggerCount >= $expectedTriggers;
        itm_setup_wizard_state_set([
            'db_import' => $import,
            'table_count' => $tableCount,
            'trigger_count' => $triggerCount,
            'flash' => [
                'type' => $importOk ? 'success' : 'error',
                'message' => $import['message']
                    . ' — tables: ' . $tableCount . '/' . $expected
                    . '; triggers: ' . $triggerCount . '/' . $expectedTriggers,
            ],
        ]);
        if ($importOk) {
            itm_setup_wizard_mark_step_done(3);
            itm_setup_wizard_set_step(4);
        }
        header('Location: ' . BASE_URL . 'setup/index.php?step=' . ($importOk ? 4 : 3));
        exit;
    }

    if ($action === 'step4_continue') {
        itm_setup_wizard_state_set([
            'extensions' => itm_setup_wizard_extension_matrix(),
            'flash' => ['type' => 'success', 'message' => 'Extension scan recorded.'],
        ]);
        itm_setup_wizard_mark_step_done(4);
        itm_setup_wizard_set_step(5);
        header('Location: ' . BASE_URL . 'setup/index.php?step=5');
        exit;
    }

    if ($action === 'step5_save') {
        $appUrl = rtrim(trim((string)($_POST['itm_app_url'] ?? ($state['itm_app_url'] ?? BASE_URL))), '/') . '/';
        $appEnv = trim((string)($_POST['app_env'] ?? 'development'));
        if (!in_array($appEnv, ['development', 'production'], true)) {
            $appEnv = 'production';
        }
        $itmDev = !empty($_POST['itm_dev']) ? '1' : '0';
        $skipForce = !empty($_POST['itm_skip_force_password_change']) ? '1' : '0';
        $enableErrors = !empty($_POST['enable_error_reporting']) ? 1 : 0;

        if ($appEnv === 'production') {
            $itmDev = '0';
            $skipForce = '0';
            $enableErrors = 0;
        }

        $connSettings = itm_setup_wizard_reload_connection();
        if ($connSettings instanceof mysqli && $enableErrors === 0) {
            itm_setup_wizard_apply_ui_error_reporting($connSettings, 0);
        } elseif ($connSettings instanceof mysqli) {
            itm_setup_wizard_apply_ui_error_reporting($connSettings, 1);
        }

        itm_setup_wizard_state_set([
            'itm_app_url' => $appUrl,
            'app_env' => $appEnv,
            'itm_dev' => $itmDev === '1',
            'itm_skip_force_password_change' => $skipForce === '1',
            'enable_error_reporting' => $enableErrors,
            'flash' => [
                'type' => 'success',
                'message' => 'Environment settings saved (written to .env on step 7).',
            ],
        ]);
        itm_setup_wizard_mark_step_done(5);
        itm_setup_wizard_set_step(6);
        header('Location: ' . BASE_URL . 'setup/index.php?step=6');
        exit;
    }

    if ($action === 'step6_save') {
        $connAdmin = itm_setup_wizard_reload_connection();
        if (!($connAdmin instanceof mysqli)) {
            itm_setup_wizard_state_set(['flash' => ['type' => 'error', 'message' => 'Database connection required — complete step 3 first.']]);
            header('Location: ' . BASE_URL . 'setup/index.php?step=3');
            exit;
        }
        $username = trim((string)($_POST['admin_username'] ?? 'Admin'));
        $password = (string)($_POST['admin_password'] ?? '');
        $confirm = (string)($_POST['admin_password_confirm'] ?? '');
        $firstName = trim((string)($_POST['admin_first_name'] ?? 'System'));
        $lastName = trim((string)($_POST['admin_last_name'] ?? 'Administrator'));
        $workEmail = trim((string)($_POST['admin_work_email'] ?? 'admin@example.com'));
        if ($password === '' || $password !== $confirm) {
            itm_setup_wizard_state_set(['flash' => ['type' => 'error', 'message' => 'Password and confirmation must match.']]);
            header('Location: ' . BASE_URL . 'setup/index.php?step=6');
            exit;
        }
        $save = itm_setup_wizard_save_admin($connAdmin, $username, $password, $firstName, $lastName, $workEmail);
        itm_setup_wizard_state_set(['flash' => ['type' => $save['ok'] ? 'success' : 'error', 'message' => $save['message']]]);
        if ($save['ok']) {
            itm_setup_wizard_mark_step_done(6);
            itm_setup_wizard_set_step(7);
        }
        header('Location: ' . BASE_URL . 'setup/index.php?step=' . ($save['ok'] ? 7 : 6));
        exit;
    }

    if ($action === 'step7_skip') {
        $envWrite = itm_setup_wizard_persist_env_from_state();
        if (!$envWrite['ok']) {
            itm_setup_wizard_state_set(['flash' => ['type' => 'error', 'message' => $envWrite['message']]]);
            header('Location: ' . BASE_URL . 'setup/index.php?step=7');
            exit;
        }
        itm_setup_wizard_mark_step_done(7);
        itm_setup_wizard_set_step(8);
        itm_setup_wizard_state_set(['flash' => ['type' => 'info', 'message' => 'Skipped sample data. ' . $envWrite['message']]]);
        header('Location: ' . BASE_URL . 'setup/index.php?step=8');
        exit;
    }

    if ($action === 'step7_install') {
        $connSample = itm_setup_wizard_reload_connection();
        if (!($connSample instanceof mysqli)) {
            itm_setup_wizard_state_set(['flash' => ['type' => 'error', 'message' => 'Database connection required.']]);
            header('Location: ' . BASE_URL . 'setup/index.php?step=3');
            exit;
        }
        $companyId = max(1, (int)($_POST['sample_company_id'] ?? 1));
        $seed = itm_setup_wizard_install_sample_data($connSample, $companyId);
        if ($seed['ok']) {
            $envWrite = itm_setup_wizard_persist_env_from_state();
            if (!$envWrite['ok']) {
                itm_setup_wizard_state_set(['flash' => ['type' => 'error', 'message' => $seed['message'] . ' — ' . $envWrite['message']]]);
                header('Location: ' . BASE_URL . 'setup/index.php?step=7');
                exit;
            }
            itm_setup_wizard_mark_step_done(7);
            itm_setup_wizard_set_step(8);
            itm_setup_wizard_state_set([
                'flash' => [
                    'type' => 'success',
                    'message' => $seed['message'] . ($seed['detail'] !== '' ? ' — ' . $seed['detail'] : '') . ' — ' . $envWrite['message'],
                ],
            ]);
            header('Location: ' . BASE_URL . 'setup/index.php?step=8');
            exit;
        }
        itm_setup_wizard_state_set(['flash' => ['type' => 'error', 'message' => $seed['message'] . ($seed['detail'] !== '' ? ' — ' . $seed['detail'] : '')]]);
        header('Location: ' . BASE_URL . 'setup/index.php?step=7');
        exit;
    }

    if ($action === 'step8_finish') {
        $envWrite = itm_setup_wizard_persist_env_from_state();
        if (!$envWrite['ok']) {
            itm_setup_wizard_state_set(['flash' => ['type' => 'error', 'message' => $envWrite['message']]]);
            header('Location: ' . BASE_URL . 'setup/index.php?step=7');
            exit;
        }
        $cleanup = itm_setup_wizard_remove_entrypoint();
        if ($cleanup['ok']) {
            header('Location: ' . BASE_URL . 'login.php?setup=done');
            exit;
        }
        itm_setup_wizard_state_set(['flash' => ['type' => 'error', 'message' => $cleanup['message']]]);
        header('Location: ' . BASE_URL . 'setup/index.php?step=8');
        exit;
    }
}

if (isset($_GET['step'])) {
    itm_setup_wizard_set_step((int)$_GET['step']);
    $currentStep = itm_setup_wizard_current_step();
}

$paths = itm_setup_wizard_detect_paths();
$state = itm_setup_wizard_state();
$flash = $state['flash'] ?? ['type' => '', 'message' => ''];
itm_setup_wizard_state_set(['flash' => ['type' => '', 'message' => '']]);

$dbDefaults = $state['db'] ?? [];
// Why: During install, DB creds live in wizard session only — do not pre-fill from a stale .env.
$dbHost = (string)($dbDefaults['host'] ?? '127.0.0.1');
$dbPort = (int)($dbDefaults['port'] ?? 3306);
$dbUser = (string)($dbDefaults['user'] ?? 'root');
$dbPass = (string)($dbDefaults['pass'] ?? '');
$dbName = (string)($dbDefaults['name'] ?? 'itmanagement');
$appUrl = (string)($state['itm_app_url'] ?? $envFile['ITM_APP_URL'] ?? $paths['base_url']);
$projectRootInput = itm_setup_wizard_project_root_input_value();
$projectRootPreview = itm_setup_wizard_preview_project_root_path($projectRootInput);
$step1DocumentRoot = itm_setup_wizard_resolve_step1_document_root($projectRootPreview);
$step1DocrootAligned = itm_setup_wizard_docroot_aligned($projectRootPreview, $step1DocumentRoot);
$step1PreviewConfig = $currentStep === 1 ? itm_setup_wizard_step1_preview_config() : [];
$appEnv = (string)($state['app_env'] ?? $envFile['APP_ENV'] ?? 'development');
$extensions = itm_setup_wizard_extension_matrix();
$fileChecks = $currentStep === 2
    ? itm_setup_wizard_verify_files()
    : ($state['file_checks'] ?? itm_setup_wizard_verify_files());
$dbProbe = isset($state['db_probe']) && is_array($state['db_probe']) ? $state['db_probe'] : [];
$dbNeedsCreate = !empty($dbProbe['needs_create']);
$dbNeedsReplaceConfirm = !empty($dbProbe['needs_replace_confirm']);
$dbExistingTableCount = (int)($dbProbe['table_count'] ?? 0);
$folderProbe = isset($state['folder_probe']) && is_array($state['folder_probe']) ? $state['folder_probe'] : [];
$folderNeedsReplaceConfirm = !empty($folderProbe['needs_replace_confirm']);
$csrfToken = itm_get_csrf_token();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT Management — Setup Wizard</title>
    <style>
        :root { --bg:#0d1117; --panel:#161b22; --border:#30363d; --text:#e6edf3; --muted:#8b949e; --accent:#2f81f7; --ok:#3fb950; --warn:#d29922; --err:#f85149; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Segoe UI, system-ui, sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width:1100px; margin:0 auto; padding:24px; }
        h1 { margin:0 0 8px; font-size:1.6rem; }
        .sub { color:var(--muted); margin-bottom:24px; }
        .grid { display:grid; grid-template-columns:280px 1fr; gap:20px; }
        .steps { background:var(--panel); border:1px solid var(--border); border-radius:10px; padding:12px; }
        .step-link { display:block; padding:10px 12px; border-radius:8px; color:var(--text); text-decoration:none; margin-bottom:6px; border:1px solid transparent; }
        .step-link small { display:block; color:var(--muted); font-size:.8rem; margin-top:2px; }
        .step-link.active { border-color:var(--accent); background:rgba(47,129,247,.12); }
        .step-link.done { color:var(--ok); }
        .panel { background:var(--panel); border:1px solid var(--border); border-radius:10px; padding:20px; }
        .flash { padding:12px 14px; border-radius:8px; margin-bottom:16px; }
        .flash.success { background:rgba(63,185,80,.15); border:1px solid var(--ok); }
        .flash.error { background:rgba(248,81,73,.15); border:1px solid var(--err); }
        .flash.info { background:rgba(47,129,247,.12); border:1px solid var(--accent); }
        label { display:block; margin:12px 0 6px; font-weight:600; }
        input[type=text], input[type=password], input[type=email], input[type=number], select, textarea {
            width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--border); background:#0d1117; color:var(--text);
        }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .btn { display:inline-block; padding:10px 16px; border-radius:8px; border:1px solid var(--border); background:#21262d; color:var(--text); cursor:pointer; text-decoration:none; font-size:.95rem; }
        .btn-primary { background:var(--accent); border-color:var(--accent); color:#fff; }
        .btn-danger { background:var(--err); border-color:var(--err); color:#fff; }
        .actions { margin-top:20px; display:flex; gap:10px; flex-wrap:wrap; }
        table { width:100%; border-collapse:collapse; margin-top:12px; }
        th, td { text-align:left; padding:8px 10px; border-bottom:1px solid var(--border); }
        .ok { color:var(--ok); } .warn { color:var(--warn); } .bad { color:var(--err); }
        code { background:#0d1117; padding:2px 6px; border-radius:4px; }
        .setup-path, .setup-verify-table td.setup-path-cell {
            word-break: break-all;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }
        .setup-verify-table td:first-child { width: 2.5rem; vertical-align: top; }
        .setup-verify-table td.setup-path-cell { vertical-align: top; }
        .setup-project-root-row { display:flex; gap:8px; align-items:stretch; }
        .setup-project-root-row input[type=text] { flex:1; margin:0; }
        .setup-project-root-row .btn { flex:0 0 auto; min-width:48px; }
        #setup-step1-preview-status.ok { color:var(--ok); }
        #setup-step1-preview-status.bad { color:var(--err); }
        @media (max-width:900px) { .grid { grid-template-columns:1fr; } .row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <h1>IT Management — Setup Wizard</h1>
    <p class="sub">Step-by-step installer with automatic checks. Complete all steps, then remove this wizard from the web root.</p>

    <?php if (!empty($flash['message'])): ?>
        <div class="flash <?php echo sanitize($flash['type']); ?>"><?php echo itm_setup_wizard_h((string)$flash['message']); ?></div>
    <?php endif; ?>

    <div class="grid">
        <nav class="steps" aria-label="Setup steps">
            <?php foreach ($steps as $num => $meta): ?>
                <?php
                $classes = [];
                if ($num === $currentStep) {
                    $classes[] = 'active';
                }
                if (itm_setup_wizard_step_done($num)) {
                    $classes[] = 'done';
                }
                ?>
                <a class="step-link <?php echo sanitize(implode(' ', $classes)); ?>" href="?step=<?php echo (int)$num; ?>">
                    <strong><?php echo (int)$num; ?>. <?php echo sanitize($meta['title']); ?></strong>
                    <small><?php echo sanitize($meta['subtitle']); ?></small>
                </a>
            <?php endforeach; ?>
        </nav>

        <main class="panel">
            <?php if ($currentStep === 1): ?>
                <h2>1. Select install folder</h2>
                <p>Confirm where the application is deployed and the public base URL used for links and cookies.</p>
                <?php if ($folderNeedsReplaceConfirm): ?>
                    <div class="flash info">Folder already exists — Download will ask you to confirm deleting <strong>all files inside</strong> before downloading a fresh copy.</div>
                <?php endif; ?>
                <form method="post" id="setup-step1-form">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step1_save">
                    <input type="hidden" name="step" value="1">
                    <input type="hidden" name="confirm_replace_folder" id="setup-step1-confirm-replace" value="0">
                    <label for="project_root">Project root</label>
                    <div class="setup-project-root-row">
                        <input type="text" id="project_root" name="project_root" value="<?php echo itm_setup_wizard_h($projectRootInput); ?>" required>
                        <button type="button" class="btn btn-primary" id="setup-step1-save-preview" title="Save">💾</button>
                    </div>
                    <p id="setup-step1-preview-status" class="sub" style="margin-top:8px;" aria-live="polite"></p>
                    <p class="sub" style="margin-top:0;">Absolute path for a <strong>new</strong> install folder. Use Windows backslashes after the drive letter (example: <code>C:\laragon\www\it-management5</code>). Click <strong>💾</strong> to validate the folder and refresh the preview rows below. When the path does not exist, the wizard creates it and downloads <code>pirica/it-management</code> from GitHub on Download. If the folder already exists, confirm replacement to delete all files inside and download again, or keep the auto-detected current install path for in-place setup.</p>
                    <table>
                        <tr><th>Auto-detect</th><td><code class="setup-path" id="setup-auto-detect-path"><?php echo itm_setup_wizard_h($projectRootPreview); ?></code></td></tr>
                        <tr><th>Document root</th><td><code class="setup-path" id="setup-document-root-path"><?php echo itm_setup_wizard_h($step1DocumentRoot !== '' ? $step1DocumentRoot : '(not detected)'); ?></code></td></tr>
                        <tr><th>Detected BASE_URL</th><td><code id="setup-detected-base-url"><?php echo sanitize($paths['base_url']); ?></code></td></tr>
                        <tr><th>Docroot aligned</th><td id="setup-docroot-aligned"><?php echo $step1DocrootAligned ? '<span class="ok">Yes</span>' : '<span class="warn">Check Apache alias / virtual host</span>'; ?></td></tr>
                    </table>
                    <label for="itm_app_url">Public application URL (ITM_APP_URL)</label>
                    <input type="text" id="itm_app_url" name="itm_app_url" value="<?php echo sanitize($appUrl); ?>" required>
                    <label for="install_notes">Install notes (optional)</label>
                    <textarea id="install_notes" name="install_notes" rows="3" placeholder="e.g. Laragon alias /it-management/"><?php echo sanitize((string)($state['install_notes'] ?? '')); ?></textarea>
                    <div class="actions">
                        <button class="btn btn-primary" type="submit" id="setup-step1-download" title="Download">Download</button>
                    </div>
                    <p id="setup-step1-wait-status" class="sub" style="margin-top:8px;" aria-live="polite"></p>
                </form>

            <?php elseif ($currentStep === 2): ?>
                <h2>2. Verify files</h2>
                <p>Checks the canonical <code>db/</code> bundle, writable upload paths, and PHP version before database work.</p>
                <p class="sub">Confirmed project root from step 1: <code class="setup-path"><?php echo itm_setup_wizard_h(itm_setup_wizard_format_path_display(itm_setup_wizard_project_root())); ?></code></p>
                <table class="setup-verify-table">
                    <?php foreach ($fileChecks as $row): ?>
                        <tr>
                            <td class="<?php echo sanitize($row['level'] === 'pass' ? 'ok' : ($row['level'] === 'warn' ? 'warn' : 'bad')); ?>">
                                <?php echo $row['level'] === 'pass' ? '✅' : ($row['level'] === 'warn' ? '⚠️' : '❌'); ?>
                            </td>
                            <td class="setup-path-cell"><?php echo itm_setup_wizard_h($row['message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step2_run">
                    <input type="hidden" name="step" value="2">
                    <div class="actions">
                        <a class="btn" href="?step=1" title="Back">◀️</a>
                        <button class="btn btn-primary" type="submit" title="Run verification">Run verification</button>
                    </div>
                </form>

            <?php elseif ($currentStep === 3): ?>
                <h2>3. Verify database connection</h2>
                <p>Test MySQL credentials, then import <code>01_schema → 02_data → 03_triggers</code>. Database settings are kept in the wizard session until step 7 writes <code>.env</code>.</p>
                <?php if ($dbNeedsCreate): ?>
                    <div class="flash info">Database not found — use <strong>Create database</strong> below (server credentials are OK).</div>
                <?php elseif ($dbNeedsReplaceConfirm): ?>
                    <div class="flash error">Database <code><?php echo sanitize($dbName); ?></code> already has <?php echo (int)$dbExistingTableCount; ?> table(s). Import will <strong>destroy all existing tables and data</strong> after you confirm.</div>
                <?php endif; ?>
                <form method="post" id="setup-step3-test-form">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step3_test">
                    <input type="hidden" name="step" value="3">
                    <div class="row">
                        <div>
                            <label for="db_host">DB host</label>
                            <input type="text" id="db_host" name="db_host" value="<?php echo sanitize($dbHost); ?>">
                        </div>
                        <div>
                            <label for="db_port">DB port</label>
                            <input type="number" id="db_port" name="db_port" value="<?php echo (int)$dbPort; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div>
                            <label for="db_user">DB user</label>
                            <input type="text" id="db_user" name="db_user" value="<?php echo sanitize($dbUser); ?>">
                        </div>
                        <div>
                            <label for="db_name">DB name</label>
                            <input type="text" id="db_name" name="db_name" value="<?php echo sanitize($dbName); ?>">
                        </div>
                    </div>
                    <label for="db_pass">DB password</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?php echo sanitize($dbPass); ?>">
                    <div class="actions">
                        <a class="btn" href="?step=2" title="Back">◀️</a>
                        <button class="btn" type="submit" title="Test connection">Test connection</button>
                    </div>
                </form>
                <?php if ($dbNeedsCreate): ?>
                <form method="post" style="margin-top:12px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step3_create_db">
                    <input type="hidden" name="step" value="3">
                    <input type="hidden" name="db_host" value="<?php echo sanitize($dbHost); ?>">
                    <input type="hidden" name="db_port" value="<?php echo (int)$dbPort; ?>">
                    <input type="hidden" name="db_user" value="<?php echo sanitize($dbUser); ?>">
                    <input type="hidden" name="db_name" value="<?php echo sanitize($dbName); ?>">
                    <input type="hidden" name="db_pass" value="<?php echo sanitize($dbPass); ?>">
                    <button class="btn btn-primary" type="submit" title="Create database">Create database</button>
                </form>
                <?php endif; ?>
                <form method="post" id="setup-step3-import-form" style="margin-top:12px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step3_import">
                    <input type="hidden" name="step" value="3">
                    <input type="hidden" name="db_host" value="<?php echo sanitize($dbHost); ?>">
                    <input type="hidden" name="db_port" value="<?php echo (int)$dbPort; ?>">
                    <input type="hidden" name="db_user" value="<?php echo sanitize($dbUser); ?>">
                    <input type="hidden" name="db_name" value="<?php echo sanitize($dbName); ?>">
                    <input type="hidden" name="db_pass" value="<?php echo sanitize($dbPass); ?>">
                    <input type="hidden" name="confirm_replace" id="setup-step3-confirm-replace" value="0">
                    <button class="btn btn-primary" type="submit" title="Import database">Import database bundle</button>
                    <?php if (!empty($state['table_count'])): ?>
                        <span class="ok">Tables: <?php echo (int)$state['table_count']; ?> / <?php echo itm_setup_wizard_expected_table_count(); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($state['trigger_count'])): ?>
                        <span class="ok">Triggers: <?php echo (int)$state['trigger_count']; ?> / <?php echo itm_setup_wizard_expected_trigger_count(); ?></span>
                    <?php endif; ?>
                </form>

            <?php elseif ($currentStep === 4): ?>
                <h2>4. PHP extensions</h2>
                <p>Required extensions for the portal; PHPUnit and optional integrations listed separately.</p>
                <table>
                    <tr><th>Extension</th><th>Group</th><th>Status</th></tr>
                    <?php foreach ($extensions as $ext): ?>
                        <tr>
                            <td><?php echo sanitize($ext['label']); ?><?php echo $ext['required'] ? ' *' : ''; ?></td>
                            <td><?php echo sanitize($ext['group']); ?></td>
                            <td class="<?php echo $ext['loaded'] ? 'ok' : ($ext['required'] ? 'bad' : 'warn'); ?>">
                                <?php echo $ext['loaded'] ? 'Loaded' : 'Missing'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step4_continue">
                    <input type="hidden" name="step" value="4">
                    <div class="actions">
                        <a class="btn" href="?step=3" title="Back">◀️</a>
                        <button class="btn btn-primary" type="submit" title="Continue">Continue</button>
                    </div>
                </form>

            <?php elseif ($currentStep === 5): ?>
                <h2>5. Environment settings</h2>
                <p>Choose development vs production profile. Production disables dev bypass flags and browser error display. Values are stored in the wizard session and written to <code>.env</code> on step 7.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step5_save">
                    <input type="hidden" name="step" value="5">
                    <label for="itm_app_url5">ITM_APP_URL</label>
                    <input type="text" id="itm_app_url5" name="itm_app_url" value="<?php echo sanitize($appUrl); ?>">
                    <label for="app_env">APP_ENV</label>
                    <select id="app_env" name="app_env">
                        <option value="development"<?php echo $appEnv === 'development' ? ' selected' : ''; ?>>development</option>
                        <option value="production"<?php echo $appEnv === 'production' ? ' selected' : ''; ?>>production</option>
                    </select>
                    <label><input type="checkbox" name="itm_dev" value="1"<?php echo (!empty($state['itm_dev']) || (!isset($state['itm_dev']) && ($envFile['ITM_DEV'] ?? '1') === '1')) ? ' checked' : ''; ?>> ITM_DEV (local dev shortcut)</label>
                    <label><input type="checkbox" name="itm_skip_force_password_change" value="1"<?php echo (!empty($state['itm_skip_force_password_change']) || (!isset($state['itm_skip_force_password_change']) && ($envFile['ITM_SKIP_FORCE_PASSWORD_CHANGE'] ?? '') === '1')) ? ' checked' : ''; ?>> ITM_SKIP_FORCE_PASSWORD_CHANGE</label>
                    <label><input type="checkbox" name="enable_error_reporting" value="1"<?php echo !empty($state['enable_error_reporting']) ? ' checked' : ''; ?>> Enable browser error reporting (ui_configuration)</label>
                    <div class="actions">
                        <a class="btn" href="?step=4" title="Back">◀️</a>
                        <button class="btn btn-primary" type="submit" title="Save settings">Save settings</button>
                    </div>
                </form>

            <?php elseif ($currentStep === 6): ?>
                <h2>6. Administrator account</h2>
                <p>After import, rotate the seed <code>Admin</code> password (default import password is <code>Admin</code>).</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step6_save">
                    <input type="hidden" name="step" value="6">
                    <label for="admin_username">Username</label>
                    <input type="text" id="admin_username" name="admin_username" value="Admin">
                    <div class="row">
                        <div>
                            <label for="admin_first_name">First name</label>
                            <input type="text" id="admin_first_name" name="admin_first_name" value="System">
                        </div>
                        <div>
                            <label for="admin_last_name">Last name</label>
                            <input type="text" id="admin_last_name" name="admin_last_name" value="Administrator">
                        </div>
                    </div>
                    <label for="admin_work_email">Work email</label>
                    <input type="email" id="admin_work_email" name="admin_work_email" value="admin@techcorp.example.com">
                    <div class="row">
                        <div>
                            <label for="admin_password">New password</label>
                            <input type="password" id="admin_password" name="admin_password" required>
                        </div>
                        <div>
                            <label for="admin_password_confirm">Confirm password</label>
                            <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                        </div>
                    </div>
                    <div class="actions">
                        <a class="btn" href="?step=5" title="Back">◀️</a>
                        <button class="btn btn-primary" type="submit" title="Save administrator">Save administrator</button>
                    </div>
                </form>

            <?php elseif ($currentStep === 7): ?>
                <h2>7. Sample data (optional)</h2>
                <p>Install demo rows from <code>db/02_data_sample.sql</code> for company 1 (TechCorp). Safe to skip for production. Continuing writes <code>.env</code> with database and environment settings from earlier steps.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step7_install">
                    <input type="hidden" name="step" value="7">
                    <label for="sample_company_id">Company ID</label>
                    <input type="number" id="sample_company_id" name="sample_company_id" value="1" min="1">
                    <div class="actions">
                        <a class="btn" href="?step=6" title="Back">◀️</a>
                        <button class="btn btn-primary" type="submit" title="Install sample data">Install sample data</button>
                    </div>
                </form>
                <form method="post" style="margin-top:8px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step7_skip">
                    <input type="hidden" name="step" value="7">
                    <button class="btn" type="submit" title="Skip sample data">Skip</button>
                </form>

            <?php elseif ($currentStep === 8): ?>
                <h2>8. Finish — remove setup entry point</h2>
                <p>Writes <code>setup/.installed</code> and deletes <code>setup/index.php</code> so the installer cannot be reached from the web.</p>
                <ul>
                    <li>Run <code>php scripts/check_prod_hardening.php --enforce</code> before production go-live.</li>
                    <li>Sign in at <a href="<?php echo sanitize(BASE_URL); ?>login.php"><?php echo sanitize(BASE_URL); ?>login.php</a></li>
                </ul>
                <form method="post" onsubmit="return confirm('Delete setup/index.php and lock the installer?');">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="wizard_action" value="step8_finish">
                    <input type="hidden" name="step" value="8">
                    <div class="actions">
                        <a class="btn" href="?step=7" title="Back">◀️</a>
                        <button class="btn btn-danger" type="submit" title="Finish setup">Finish setup</button>
                    </div>
                </form>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php if ($currentStep === 1): ?>
<script>
(function () {
    var form = document.querySelector('form input[name="wizard_action"][value="step1_save"]');
    form = form ? form.closest('form') : null;
    var input = document.getElementById('project_root');
    var preview = document.getElementById('setup-auto-detect-path');
    var aligned = document.getElementById('setup-docroot-aligned');
    var appUrlInput = document.getElementById('itm_app_url');
    var detectedBaseUrl = document.getElementById('setup-detected-base-url');
    var documentRootEl = document.getElementById('setup-document-root-path');
    var saveBtn = document.getElementById('setup-step1-save-preview');
    var statusEl = document.getElementById('setup-step1-preview-status');
    var downloadBtn = document.getElementById('setup-step1-download');
    var waitEl = document.getElementById('setup-step1-wait-status');
    var confirmField = document.getElementById('setup-step1-confirm-replace');
    var needsReplace = <?php echo $folderNeedsReplaceConfirm ? 'true' : 'false'; ?>;
    if (!form || !input || !preview || !saveBtn) {
        return;
    }

    function applyPreview(data) {
        if (data.projectRoot) {
            input.value = data.projectRoot;
        }
        preview.textContent = data.autoDetect || data.projectRoot || '—';
        if (documentRootEl) {
            documentRootEl.textContent = data.documentRoot || '—';
        }
        if (detectedBaseUrl) {
            detectedBaseUrl.textContent = data.baseUrl || '';
        }
        if (appUrlInput) {
            appUrlInput.value = data.appUrl || '';
        }
        if (aligned) {
            aligned.innerHTML = data.docrootAlignedHtml || '';
        }
        if (statusEl) {
            statusEl.textContent = data.message || '';
            if (data.ok) {
                statusEl.className = 'sub ok';
            } else if (data.needsReplaceConfirm) {
                statusEl.className = 'sub warn';
            } else {
                statusEl.className = 'sub bad';
            }
        }
        if (typeof data.needsReplaceConfirm !== 'undefined') {
            needsReplace = !!data.needsReplaceConfirm;
        }
    }

    function savePreview() {
        var csrfInput = form.querySelector('input[name="csrf_token"]');
        if (!csrfInput || !csrfInput.value) {
            if (statusEl) {
                statusEl.textContent = 'Missing CSRF token — refresh the page.';
                statusEl.className = 'sub bad';
            }
            return;
        }
        saveBtn.disabled = true;
        if (statusEl) {
            statusEl.textContent = 'Validating folder…';
            statusEl.className = 'sub';
        }
        var body = new FormData();
        body.append('csrf_token', csrfInput.value);
        body.append('wizard_action', 'step1_preview');
        body.append('project_root', input.value);
        fetch(form.getAttribute('action') || window.location.href, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) {
                        throw new Error((data && data.message) ? data.message : 'Validation request failed.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                applyPreview(data);
            })
            .catch(function (error) {
                if (statusEl) {
                    statusEl.textContent = error && error.message ? error.message : 'Validation request failed.';
                    statusEl.className = 'sub bad';
                }
            })
            .finally(function () {
                saveBtn.disabled = false;
            });
    }

    form.addEventListener('submit', function (event) {
        if (needsReplace) {
            var folderPath = input ? input.value : 'this folder';
            var message = 'Folder "' + folderPath + '" already exists.\n\n'
                + 'Download will DELETE ALL FILES inside the folder and download a fresh copy from GitHub.\n\n'
                + 'Continue?';
            if (!window.confirm(message)) {
                event.preventDefault();
                return;
            }
            if (confirmField) {
                confirmField.value = '1';
            }
        }
        if (downloadBtn) {
            downloadBtn.disabled = true;
        }
        if (saveBtn) {
            saveBtn.disabled = true;
        }
        if (waitEl) {
            waitEl.textContent = 'Please wait...';
            waitEl.className = 'sub';
        }
    });

    saveBtn.addEventListener('click', savePreview);
})();
</script>
<?php elseif ($currentStep === 3): ?>
<script>
(function () {
    var importForm = document.getElementById('setup-step3-import-form');
    var confirmField = document.getElementById('setup-step3-confirm-replace');
    var needsReplace = <?php echo $dbNeedsReplaceConfirm ? 'true' : 'false'; ?>;
    var dbName = <?php echo json_encode($dbName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var tableCount = <?php echo (int)$dbExistingTableCount; ?>;
    if (!importForm) {
        return;
    }
    importForm.addEventListener('submit', function (event) {
        if (!needsReplace) {
            return;
        }
        var message = 'Database "' + dbName + '" already contains ' + tableCount + ' table(s).\n\n'
            + 'Import will DROP and recreate the database, destroying ALL existing tables and data.\n\n'
            + 'Continue?';
        if (!window.confirm(message)) {
            event.preventDefault();
            return;
        }
        if (confirmField) {
            confirmField.value = '1';
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
