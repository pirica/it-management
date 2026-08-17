<?php
/**
 * LDAP SSO login entry point (public — mirrors login.php session contract).
 */

include 'config/config.php';

$csrfToken = itm_get_csrf_token();
$error = '';
$companyHint = trim((string)($_GET['company_id'] ?? $_GET['company'] ?? ''));
$ssoCompanies = itm_sso_list_enabled_companies($conn);
$selectedCompany = itm_sso_resolve_company_for_login($conn, $companyHint);
$selectedCompanyId = is_array($selectedCompany) ? (int)($selectedCompany['id'] ?? 0) : 0;

if ($selectedCompanyId <= 0 && $ssoCompanies !== []) {
    $selectedCompany = itm_sso_fetch_company_row($conn, (int)($ssoCompanies[0]['id'] ?? 0));
    $selectedCompanyId = is_array($selectedCompany) ? (int)($selectedCompany['id'] ?? 0) : 0;
}

/**
 * Why: SSO login shares the same brute-force throttling contract as login.php.
 */
function itm_sso_record_login_attempt(mysqli $conn, string $attemptType, string $ipAddress, ?string $identifier = null, ?int $employeeId = null): void
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO attempts (attempt_source, attempt_type, ip_address, email, employee_id, active)
         VALUES ('login', ?, ?, ?, ?, IF(
            EXISTS(
                SELECT 1 FROM employees
                WHERE LOWER(TRIM(COALESCE(work_email, personal_email, ''))) = LOWER(TRIM(COALESCE(?, '')))
                LIMIT 1
            ),
            1,
            0
         ))"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sssis', $attemptType, $ipAddress, $identifier, $employeeId, $identifier);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function itm_sso_is_login_rate_limited(mysqli $conn, string $ipAddress, ?string $identifier = null): bool
{
    $maxIpFailures = 12;
    $maxIdentifierFailures = 6;

    $stmtIp = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) FROM attempts WHERE attempt_source = 'login' AND attempt_type = 'failure' AND ip_address = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
    );
    if ($stmtIp) {
        mysqli_stmt_bind_param($stmtIp, 's', $ipAddress);
        mysqli_stmt_execute($stmtIp);
        mysqli_stmt_bind_result($stmtIp, $ipAttempts);
        mysqli_stmt_fetch($stmtIp);
        mysqli_stmt_close($stmtIp);
        if ((int)$ipAttempts >= $maxIpFailures) {
            return true;
        }
    }

    if ($identifier !== null && $identifier !== '') {
        $stmtIdentifier = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) FROM attempts WHERE attempt_source = 'login' AND attempt_type = 'failure' AND email = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
        );
        if ($stmtIdentifier) {
            mysqli_stmt_bind_param($stmtIdentifier, 's', $identifier);
            mysqli_stmt_execute($stmtIdentifier);
            mysqli_stmt_bind_result($stmtIdentifier, $identifierAttempts);
            mysqli_stmt_fetch($stmtIdentifier);
            mysqli_stmt_close($stmtIdentifier);
            if ((int)$identifierAttempts >= $maxIdentifierFailures) {
                return true;
            }
        }
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_try_post_csrf()) {
        $error = 'Invalid CSRF token.';
        $csrfToken = itm_get_csrf_token();
    } else {
        $postedCompanyId = (int)($_POST['company_id'] ?? 0);
        if ($postedCompanyId > 0) {
            $selectedCompany = itm_sso_fetch_company_row($conn, $postedCompanyId);
            $selectedCompanyId = is_array($selectedCompany) ? (int)($selectedCompany['id'] ?? 0) : 0;
        }

        $loginIdentifier = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $requestIp = substr(itm_get_login_request_ip(), 0, 45);
        $storedAttemptIdentifier = itm_normalize_login_attempt_identifier($loginIdentifier);

        if ($selectedCompanyId <= 0) {
            $error = 'SSO is not available. Contact your administrator.';
        } elseif (itm_sso_is_login_rate_limited($conn, $requestIp, $storedAttemptIdentifier)) {
            itm_sso_record_login_attempt($conn, 'failure', $requestIp, $storedAttemptIdentifier, null);
            $error = 'Too many login attempts. Please wait a few minutes and try again.';
        } else {
            $authResult = itm_ldap_auth_attempt($conn, $selectedCompanyId, $loginIdentifier, $password);
            if (!empty($authResult['ok']) && is_array($authResult['employee'] ?? null)) {
                $employee = $authResult['employee'];
                $employeeId = (int)($employee['id'] ?? 0);
                $resolvedEmail = trim((string)($employee['work_email'] ?? ''));
                if ($resolvedEmail === '') {
                    $resolvedEmail = trim((string)($employee['personal_email'] ?? ''));
                }
                $successIdentifier = $resolvedEmail !== ''
                    ? itm_normalize_login_attempt_identifier($resolvedEmail)
                    : $storedAttemptIdentifier;
                itm_sso_record_login_attempt($conn, 'success', $requestIp, $successIdentifier, $employeeId);

                $redirectTarget = itm_sso_finalize_employee_login_session($conn, $employee);
                if ($redirectTarget === 'dashboard') {
                    header('Location: dashboard.php');
                    exit();
                }
                if ($redirectTarget === 'user-config') {
                    header('Location: user-config.php');
                    exit();
                }
                header('Location: index.php');
                exit();
            }

            itm_sso_record_login_attempt(
                $conn,
                'failure',
                $requestIp,
                $storedAttemptIdentifier,
                null
            );
            $error = (string)($authResult['error'] ?? 'Invalid credentials.');
        }
    }
}

$companyLabel = is_array($selectedCompany) ? (string)($selectedCompany['company'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSO Login - <?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></title>
    <?php if (!empty($favicon_url)): ?>
        <link rel="icon" type="image/x-icon" href="<?php echo sanitize($favicon_url); ?>">
    <?php endif; ?>
    <style>
        :root { --accent: #0969da; --bg: #ffffff; --text: #24292f; --muted: #666; }
        [data-theme="dark"] { --accent: #58a6ff; --bg: #0d1117; --text: #c9d1d9; --muted: #8b949e; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex; justify-content: center; align-items: center; min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px;
        }
        .container { background: var(--bg); padding: 40px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: var(--accent); font-size: 28px; }
        .logo p { color: var(--muted); font-size: 14px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text); }
        input, select { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; background: var(--bg); color: var(--text); margin-bottom: 16px; }
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .links { margin-top: 14px; text-align: center; }
        .links a { color: var(--accent); text-decoration: none; }
        .theme-btn { position: absolute; top: 20px; right: 20px; background: var(--bg); border: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; font-size: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <button class="theme-btn" onclick="toggleTheme()">🌙</button>
    <div class="container">
        <div class="logo">
            <h1><?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></h1>
            <p>Sign in with SSO (LDAP)</p>
            <?php if ($companyLabel !== ''): ?>
                <p><?php echo htmlspecialchars($companyLabel, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($ssoCompanies === []): ?>
            <p style="color:#d93025;">SSO is not configured for any company.</p>
        <?php else: ?>
            <?php if ($error !== ''): ?><p style="color:#d93025; margin-bottom:14px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (count($ssoCompanies) > 1): ?>
                    <label for="company_id">Company</label>
                    <select id="company_id" name="company_id" required>
                        <?php foreach ($ssoCompanies as $companyRow): ?>
                            <?php $cid = (int)($companyRow['id'] ?? 0); ?>
                            <option value="<?php echo $cid; ?>"<?php echo $cid === $selectedCompanyId ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)($companyRow['company'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="company_id" value="<?php echo (int)$selectedCompanyId; ?>">
                <?php endif; ?>
                <label for="username">Username</label>
                <input id="username" type="text" name="username" placeholder="LDAP username" required autocomplete="username">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" placeholder="LDAP password" required autocomplete="current-password">
                <button type="submit" title="Sign in with SSO">Sign in with SSO</button>
            </form>
        <?php endif; ?>
        <div class="links">
            <a href="<?php echo sanitize(BASE_URL); ?>login.php" title="Back to standard login">Back to standard login</a>
        </div>
    </div>
    <script>
        function toggleTheme() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'light' : 'dark');
            localStorage.setItem('theme', document.documentElement.getAttribute('data-theme'));
        }
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
    </script>
</body>
</html>
