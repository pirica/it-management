<?php
/**
 * Fast account creator for demo employees with module-scoped RBAC.
 *
 * Browser: scripts/fast_create_acc.php (Admin session)
 * CLI: php scripts/fast_create_acc.php --seed-demo-bundle [--company=1]
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_demo_module_users_seed.php';

$conn = $GLOBALS['conn'] ?? null;
$nl = itm_script_output_nl();
$isCli = PHP_SAPI === 'cli';
$messages = [];
$errors = [];

if (!$conn instanceof mysqli) {
    if ($isCli) {
        echo '[FAIL] Database connection required.' . $nl;
        exit(1);
    }
    http_response_code(500);
    exit('Database connection required.');
}

if ($isCli) {
    itm_script_output_begin('Fast Create Account');
    $options = getopt('', ['seed-demo-bundle', 'company:']);
    if (isset($options['seed-demo-bundle'])) {
        $companyId = isset($options['company']) ? (int)$options['company'] : 1;
        $summary = itm_demo_module_users_seed_bundle($conn, $companyId, 0);
        foreach ($summary['messages'] as $line) {
            echo colorText('[INFO] ' . $line, 'pass') . $nl;
        }
        foreach ($summary['errors'] as $line) {
            echo colorText('[FAIL] ' . $line, 'fail') . $nl;
        }
        exit($summary['ok'] ? 0 : 1);
    }

    echo 'Usage:' . $nl;
    echo '  php scripts/fast_create_acc.php --seed-demo-bundle [--company=1]' . $nl;
    echo 'Browser UI: scripts/fast_create_acc.php' . $nl;
    exit(0);
}

itm_script_require_admin_script_or_exit($conn, 'Administrator access required.');

$selectedCompanyId = (int)($_GET['company_id'] ?? $_POST['company_id'] ?? ($_SESSION['company_id'] ?? 1));
if ($selectedCompanyId <= 0) {
    $selectedCompanyId = 1;
}

$demoTemplates = itm_demo_module_restrictions_demo_users();
$fkOptions = itm_demo_module_users_fetch_fk_options($conn, $selectedCompanyId);
$grantedByEmployeeId = (int)($_SESSION['employee_id'] ?? 0);

$form = [
    'company_id' => $selectedCompanyId,
    'demo_template' => '',
    'username' => '',
    'password' => '',
    'first_name' => '',
    'last_name' => 'Demo',
    'work_email' => '',
    'role_name' => '',
    'module_slugs' => [],
    'access_level_id' => 0,
    'employment_status_id' => 0,
    'department_id' => 0,
    'employee_position_id' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = trim((string)($_POST['action'] ?? 'create'));

        if ($action === 'seed_demo_bundle') {
            $companyId = (int)($_POST['company_id'] ?? 1);
            $summary = itm_demo_module_users_seed_bundle($conn, $companyId, $grantedByEmployeeId);
            $messages = array_merge($messages, $summary['messages']);
            $errors = array_merge($errors, $summary['errors']);
            $selectedCompanyId = $companyId;
            $fkOptions = itm_demo_module_users_fetch_fk_options($conn, $selectedCompanyId);
        } else {
            $postedModuleSlugs = $_POST['module_slugs'] ?? [];
            if (!is_array($postedModuleSlugs)) {
                $postedModuleSlugs = [];
            }
            $form['module_slugs'] = array_values(array_filter(array_map('trim', $postedModuleSlugs)));

            foreach (array_keys($form) as $key) {
                if ($key === 'module_slugs') {
                    continue;
                }
                if ($key === 'company_id' || $key === 'access_level_id' || $key === 'employment_status_id'
                    || $key === 'department_id' || $key === 'employee_position_id') {
                    $form[$key] = (int)($_POST[$key] ?? 0);
                    continue;
                }
                $form[$key] = trim((string)($_POST[$key] ?? ''));
            }

            $templateKey = $form['demo_template'];
            if ($templateKey !== '') {
                foreach ($demoTemplates as $template) {
                    if ((string)$template['username'] === $templateKey) {
                        $form['username'] = (string)$template['username'];
                        $form['password'] = (string)$template['password'];
                        $form['role_name'] = (string)$template['role_name'];
                        $form['module_slugs'] = itm_demo_module_restrictions_module_slugs_for_user($template);
                        $form['first_name'] = ucfirst((string)$template['username']);
                        $form['work_email'] = strtolower((string)$template['username']) . '@demo.example.com';
                        $form['company_id'] = (int)$template['company_id'];
                        break;
                    }
                }
            }

            $payload = [
                'company_id' => (int)$form['company_id'],
                'username' => $form['username'],
                'password' => $form['password'],
                'first_name' => $form['first_name'],
                'last_name' => $form['last_name'] !== '' ? $form['last_name'] : 'Demo',
                'work_email' => $form['work_email'],
                'role_name' => $form['role_name'],
                'module_slugs' => $form['module_slugs'],
                'access_level_id' => (int)$form['access_level_id'],
                'employment_status_id' => (int)$form['employment_status_id'],
                'department_id' => (int)$form['department_id'],
                'employee_position_id' => (int)$form['employee_position_id'],
                'granted_by_employee_id' => $grantedByEmployeeId,
            ];

            $upsert = itm_demo_module_users_upsert_employee($conn, $payload);
            $messages = array_merge($messages, $upsert['messages']);
            $errors = array_merge($errors, $upsert['errors']);
            $selectedCompanyId = (int)$form['company_id'];
            $fkOptions = itm_demo_module_users_fetch_fk_options($conn, $selectedCompanyId);
        }
    }
}

$csrfToken = itm_get_csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fast Create Account</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body { padding: 32px 20px; background: var(--bg-secondary, #f6f8fa); }
        .fca-wrap { max-width: 960px; margin: 0 auto; background: var(--bg-primary, #fff); border: 1px solid var(--border, #d0d7de); border-radius: 8px; padding: 28px; }
        .fca-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .fca-grid .full { grid-column: 1 / -1; }
        label { display: block; font-weight: 600; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px; border: 1px solid var(--border, #d0d7de); border-radius: 6px; background: var(--bg-primary); color: var(--text-primary); }
        select[multiple] { min-height: 220px; }
        .alert { padding: 12px 14px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #dafbe1; color: #1a7f37; border: 1px solid #aff5b4; }
        .alert-error { background: #ffebe9; color: #cf222e; border: 1px solid #ffc1c0; }
        .toolbar { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 20px; }
        .hint { color: var(--text-secondary, #57606a); font-size: 0.92rem; margin-top: 8px; }
        h1 { margin-top: 0; }
        @media (max-width: 760px) { .fca-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="fca-wrap">
    <?php require_once __DIR__ . '/lib/script_browser_nav.php'; itm_script_browser_nav_echo(); ?>
    <h1 title="Fast create account">➕</h1>
    <p class="hint">Create or update a demo employee with required FK fields, dedicated role, RBAC rows per selected module, sidebar prefs, and <code>ui_configuration</code>. Hold Ctrl (Windows) or Cmd (macOS) to select multiple modules.</p>

    <?php foreach ($messages as $message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endforeach; ?>

    <form method="POST" class="toolbar" style="margin-bottom: 24px;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="seed_demo_bundle">
        <label>
            Company
            <select name="company_id">
                <?php foreach ($fkOptions['companies'] as $company): ?>
                    <option value="<?php echo (int)$company['id']; ?>"<?php echo (int)$company['id'] === $selectedCompanyId ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$company['company'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn btn-primary" title="Seed demo bundle">💾</button>
    </form>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="create">
        <div class="fca-grid">
            <div class="full">
                <label for="demo_template">Demo template</label>
                <select name="demo_template" id="demo_template">
                    <option value="">-- Custom account --</option>
                    <?php foreach ($demoTemplates as $template): ?>
                        <?php
                        $templateModules = implode(', ', itm_demo_module_restrictions_module_slugs_for_user($template));
                        ?>
                        <option value="<?php echo htmlspecialchars((string)$template['username'], ENT_QUOTES, 'UTF-8'); ?>"
                            <?php echo $form['demo_template'] === (string)$template['username'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$template['username'] . ' → ' . $templateModules, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="company_id">Company</label>
                <input type="hidden" name="company_id" value="<?php echo (int)$form['company_id']; ?>">
                <select id="company_id" onchange="window.location='?company_id=' + encodeURIComponent(this.value);">
                    <?php foreach ($fkOptions['companies'] as $company): ?>
                        <option value="<?php echo (int)$company['id']; ?>"<?php echo (int)$company['id'] === (int)$form['company_id'] ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$company['company'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="full">
                <label for="module_slugs">Modules</label>
                <select name="module_slugs[]" id="module_slugs" multiple required>
                    <?php foreach ($fkOptions['modules'] as $module): ?>
                        <?php
                        $slug = (string)$module['module_slug'];
                        $isSelected = in_array($slug, $form['module_slugs'], true);
                        ?>
                        <option value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isSelected ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$module['module_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="username">Username</label>
                <input type="text" name="username" id="username" required value="<?php echo htmlspecialchars($form['username'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="password">Password</label>
                <input type="text" name="password" id="password" required value="<?php echo htmlspecialchars($form['password'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="first_name">First name</label>
                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($form['first_name'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="last_name">Last name</label>
                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($form['last_name'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="full">
                <label for="work_email">Work email</label>
                <input type="email" name="work_email" id="work_email" value="<?php echo htmlspecialchars($form['work_email'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="role_name">Role name</label>
                <input type="text" name="role_name" id="role_name" required value="<?php echo htmlspecialchars($form['role_name'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div>
                <label for="employment_status_id">Employment status</label>
                <select name="employment_status_id" id="employment_status_id">
                    <option value="0">-- Auto (Active) --</option>
                    <?php foreach ($fkOptions['employee_statuses'] as $row): ?>
                        <option value="<?php echo (int)$row['id']; ?>"<?php echo (int)$row['id'] === (int)$form['employment_status_id'] ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="access_level_id">Access level</label>
                <select name="access_level_id" id="access_level_id">
                    <option value="0">-- Auto (Limited) --</option>
                    <?php foreach ($fkOptions['access_levels'] as $row): ?>
                        <option value="<?php echo (int)$row['id']; ?>"<?php echo (int)$row['id'] === (int)$form['access_level_id'] ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="department_id">Department (optional)</label>
                <select name="department_id" id="department_id">
                    <option value="0">-- None --</option>
                    <?php foreach ($fkOptions['departments'] as $row): ?>
                        <option value="<?php echo (int)$row['id']; ?>"<?php echo (int)$row['id'] === (int)$form['department_id'] ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="employee_position_id">Position (optional)</label>
                <select name="employee_position_id" id="employee_position_id">
                    <option value="0">-- None --</option>
                    <?php foreach ($fkOptions['employee_positions'] as $row): ?>
                        <option value="<?php echo (int)$row['id']; ?>"<?php echo (int)$row['id'] === (int)$form['employee_position_id'] ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="toolbar">
            <button type="submit" class="btn btn-primary" title="Create account">💾</button>
            <a class="btn" href="verify_demo_module_restrictions.php" title="Verify demo restrictions">🔎</a>
        </div>
    </form>
</div>
</body>
</html>
