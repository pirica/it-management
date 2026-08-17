<?php
/**
 * Companies Module - Create/Edit
 *
 * Provides a unified form for adding new companies or updating existing ones.
 * Implements strict input normalization (e.g., InCode uppercase) and
 * comprehensive audit logging for all changes.
 */

require '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'create', 'companies');

if (!itm_is_admin($conn, $_SESSION['employee_id'] ?? 0)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}
itm_ensure_companies_company_unique($conn);

// Determine if we are in Edit mode based on the presence of an ID
$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';
$csrfToken = itm_get_csrf_token();

/**
 * Why: Companies has a human-facing unique InCode; surfacing a specific message
 * prevents raw SQL errors and gives users a clear corrective action.
 */
function itm_companies_create_error_message($conn, ?Throwable $throwable = null) {
    $errorCode = (int)mysqli_errno($conn);
    $errorMessage = (string)mysqli_error($conn);

    if ($throwable !== null) {
        $throwableCode = (int)$throwable->getCode();
        if ($throwableCode > 0) {
            $errorCode = $throwableCode;
        }
        if ($errorMessage === '') {
            $errorMessage = (string)$throwable->getMessage();
        }
    }

    if ($errorCode === 1062 && stripos($errorMessage, 'companies.incode') !== false) {
        return 'InCode already in use. Please choose a different InCode.';
    }

    return itm_format_db_constraint_error($errorCode, $errorMessage);
}

// Default form data structure
$data = [
    'company' => '',
    'incode' => '',
    'city' => '',
    'country' => '',
    'phone' => '',
    'email' => '',
    'website' => '',
    'vat' => '',
    'unit_no' => '',
    'comments' => '',
    'active' => 1,
    'sso_enabled' => 0,
    'sso_jit_enabled' => 0,
    'sso_provider' => 'ldap',
];
$ldapConfig = itm_ldap_default_config();

// Load existing data if in Edit mode
if ($is_edit) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM companies WHERE id = ? AND id > 0 LIMIT 1');
    if ($stmt) {
        // Why: bind the prepared statement handle first so edit mode can load the target row safely.
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) === 1) {
            $data = mysqli_fetch_assoc($res);
            $ldapConfig = itm_ldap_decrypt_config($data['sso_config_json_encrypted'] ?? '');
            if (!is_array($ldapConfig)) {
                $ldapConfig = itm_ldap_default_config();
            }
        } else {
            $error = 'Company not found.';
            $is_edit = false;
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = 'Failed to load company.';
        $is_edit = false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    // Sanitize and normalize inputs
    $company = trim((string)($_POST['company'] ?? ''));
    $incode = strtoupper(substr(trim((string)($_POST['incode'] ?? '')), 0, 6));
    $city = trim((string)($_POST['city'] ?? ''));
    $country = trim((string)($_POST['country'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $website = trim((string)($_POST['website'] ?? ''));
    $vat = trim((string)($_POST['vat'] ?? ''));
    $unit_no = trim((string)($_POST['unit_no'] ?? ''));
    $comments = trim((string)($_POST['comments'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;
    $ssoEnabled = ($is_edit && isset($_POST['sso_enabled'])) ? 1 : 0;
    $ssoJitEnabled = ($is_edit && isset($_POST['sso_jit_enabled'])) ? 1 : 0;
    $ssoProvider = 'ldap';
    $ldapHost = trim((string)($_POST['ldap_host'] ?? ''));
    $ldapPort = (int)($_POST['ldap_port'] ?? 389);
    $ldapBindDn = trim((string)($_POST['ldap_bind_dn'] ?? ''));
    $ldapBindPassword = (string)($_POST['ldap_bind_password'] ?? '');
    $ldapBaseDn = trim((string)($_POST['ldap_base_dn'] ?? ''));
    $ldapUserFilter = trim((string)($_POST['ldap_user_filter'] ?? ''));
    $ldapUsernameAttr = trim((string)($_POST['ldap_username_attr'] ?? 'sAMAccountName'));
    $ldapEmailAttr = trim((string)($_POST['ldap_email_attr'] ?? 'mail'));

    $data = [
        'company' => $company,
        'incode' => $incode,
        'city' => $city,
        'country' => $country,
        'phone' => $phone,
        'email' => $email,
        'website' => $website,
        'vat' => $vat,
        'unit_no' => $unit_no,
        'comments' => $comments,
        'active' => $active,
        'sso_enabled' => $ssoEnabled,
        'sso_jit_enabled' => $ssoJitEnabled,
        'sso_provider' => $ssoProvider,
    ];

    if ($is_edit) {
        $ldapConfig = itm_ldap_normalize_config([
            'host' => $ldapHost,
            'port' => $ldapPort,
            'bind_dn' => $ldapBindDn,
            'bind_password' => $ldapBindPassword,
            'base_dn' => $ldapBaseDn,
            'user_filter' => $ldapUserFilter,
            'username_attr' => $ldapUsernameAttr,
            'email_attr' => $ldapEmailAttr,
        ]);
        if ($ldapBindPassword === '') {
            $existingRow = itm_fetch_audit_record($conn, 'companies', $id, (int)($_SESSION['company_id'] ?? 0));
            $existingConfig = itm_ldap_decrypt_config(is_array($existingRow) ? ($existingRow['sso_config_json_encrypted'] ?? '') : '');
            if (is_array($existingConfig)) {
                $ldapConfig['bind_password'] = (string)($existingConfig['bind_password'] ?? '');
            }
        }
    }

    if ($company === '') {
        $error = 'Company is required.';
    } else {
        if ($is_edit) {
            // Process UPDATE
            $old = itm_fetch_audit_record($conn, 'companies', $id, (int)($_SESSION['company_id'] ?? 0));
            if ($ssoEnabled === 1) {
                $ssoConfigEncrypted = itm_ldap_encrypt_config($ldapConfig);
                if ($ssoConfigEncrypted === null) {
                    $error = 'Failed to encrypt LDAP configuration.';
                } else {
                    $encryptedValue = (string)$ssoConfigEncrypted;
                }
            } else {
                $encryptedValue = (string)($old['sso_config_json_encrypted'] ?? '');
            }
            if ($error === '') {
            $sql = 'UPDATE companies SET company=?, incode=?, unit_no=?, city=?, country=?, phone=?, email=?, website=?, vat=?, comments=?, active=?, sso_enabled=?, sso_jit_enabled=?, sso_provider=?, sso_config_json_encrypted=? WHERE id=? AND id > 0';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssssssssssiiissi', $company, $incode, $unit_no, $city, $country, $phone, $email, $website, $vat, $comments, $active, $ssoEnabled, $ssoJitEnabled, $ssoProvider, $encryptedValue, $id);
                try {
                    if (mysqli_stmt_execute($stmt)) {
                        $auditData = $data;
                        $auditData['sso_config_json_encrypted'] = $ssoEnabled === 1 ? '[encrypted]' : '';
                        itm_log_audit($conn, 'companies', $id, 'UPDATE', $old, $auditData);
                        mysqli_stmt_close($stmt);
                        header('Location: index.php');
                        exit;
                    }
                    $error = itm_companies_create_error_message($conn);
                } catch (Throwable $t) {
                    $error = itm_companies_create_error_message($conn, $t);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = 'Failed to update company.';
            }
            }
        } else {
            // Process INSERT
            $sql = 'INSERT INTO companies (company, incode, unit_no, city, country, phone, email, website, vat, comments, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ssssssssssi', $company, $incode, $unit_no, $city, $country, $phone, $email, $website, $vat, $comments, $active);
                try {
                    if (mysqli_stmt_execute($stmt)) {
                        $newId = (int)mysqli_insert_id($conn);
                        itm_log_audit($conn, 'companies', $newId, 'INSERT', null, $data);
                        if (function_exists('itm_sync_modules_registry_from_filesystem')) {
                            itm_sync_modules_registry_from_filesystem($conn);
                        }
                        if (function_exists('itm_seed_company_module_access_for_company')) {
                            itm_seed_company_module_access_for_company($conn, $newId);
                        }
                        mysqli_stmt_close($stmt);
                        header('Location: index.php');
                        exit;
                    }
                    $error = itm_companies_create_error_message($conn);
                } catch (Throwable $t) {
                    $error = itm_companies_create_error_message($conn, $t);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = 'Failed to create company.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Companies';
}
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
        $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), basename(dirname($_SERVER['PHP_SELF'])), (string)($crud_title ?? ''));
    ?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="<?php echo $is_edit ? 'Edit company' : 'Add company'; ?>"><?php echo $is_edit ? '✏️' : '➕'; ?></h1>
            <?php echo itm_render_alert_errors($error ?? ''); ?>
            <div class="card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <div class="form-row">
                        <div class="form-group"><label>Company *</label><input type="text" name="company" required value="<?php echo htmlspecialchars((string)($data['company'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="form-group"><label>InCode</label><input type="text" name="incode" maxlength="6" size="6" value="<?php echo htmlspecialchars((string)($data['incode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="form-group"><label>Unit No.</label><input type="text" name="unit_no" maxlength="10" size="10" value="<?php echo htmlspecialchars((string)($data['unit_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>City</label><input type="text" name="city" value="<?php echo htmlspecialchars((string)($data['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="form-group"><label>Country</label><input type="text" name="country" value="<?php echo htmlspecialchars((string)($data['country'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo htmlspecialchars((string)($data['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars((string)($data['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Website</label><input type="url" name="website" value="<?php echo htmlspecialchars((string)($data['website'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="form-group"><label>VAT</label><input type="text" name="vat" value="<?php echo htmlspecialchars((string)($data['vat'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    </div>
                    <div class="form-group"><label>Comments</label><textarea name="comments" rows="4"><?php echo htmlspecialchars((string)($data['comments'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                    <div class="form-group">
                        <label class="itm-checkbox-control">
                            <input type="checkbox" name="active" value="1" <?php echo (int)($data['active'] ?? 0) === 1 ? 'checked' : ''; ?>>
                            <span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)($data['active'] ?? 0) === 1) ? '✅' : '❌'; ?></span></span>
                        </label>
                    </div>
                    <?php if ($is_edit): ?>
                    <div class="card" style="margin-top:16px;">
                        <h2 style="margin-bottom:12px;">SSO / LDAP</h2>
                        <div class="form-group">
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="sso_enabled" value="1" <?php echo (int)($data['sso_enabled'] ?? 0) === 1 ? 'checked' : ''; ?>>
                                <span>Enable LDAP SSO <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)($data['sso_enabled'] ?? 0) === 1) ? '✅' : '❌'; ?></span></span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="sso_jit_enabled" value="1" <?php echo (int)($data['sso_jit_enabled'] ?? 0) === 1 ? 'checked' : ''; ?>>
                                <span>JIT provision new LDAP users <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)($data['sso_jit_enabled'] ?? 0) === 1) ? '✅' : '❌'; ?></span></span>
                            </label>
                            <p class="form-hint" style="margin-top:6px;opacity:.85;">When enabled, first successful LDAP login creates an employee row and home-company grant when no match exists.</p>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>LDAP host</label><input type="text" name="ldap_host" value="<?php echo htmlspecialchars((string)($ldapConfig['host'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="form-group"><label>Port</label><input type="number" name="ldap_port" min="1" max="65535" value="<?php echo (int)($ldapConfig['port'] ?? 389); ?>"></div>
                        </div>
                        <div class="form-group"><label>Base DN</label><input type="text" name="ldap_base_dn" value="<?php echo htmlspecialchars((string)($ldapConfig['base_dn'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="form-group"><label>Bind DN</label><input type="text" name="ldap_bind_dn" value="<?php echo htmlspecialchars((string)($ldapConfig['bind_dn'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <div class="form-group"><label>Bind password</label><input type="password" name="ldap_bind_password" value="" placeholder="Leave blank to keep existing" autocomplete="new-password"></div>
                        <div class="form-group"><label>User filter</label><input type="text" name="ldap_user_filter" value="<?php echo htmlspecialchars((string)($ldapConfig['user_filter'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><small>Use <code>%username%</code> placeholder.</small></div>
                        <div class="form-row">
                            <div class="form-group"><label>Username attribute</label><input type="text" name="ldap_username_attr" value="<?php echo htmlspecialchars((string)($ldapConfig['username_attr'] ?? 'sAMAccountName'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="form-group"><label>Email attribute</label><input type="text" name="ldap_email_attr" value="<?php echo htmlspecialchars((string)($ldapConfig['email_attr'] ?? 'mail'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                        </div>
                        <p><a href="<?php echo sanitize(BASE_URL . 'sso-ldap.php?company_id=' . (int)$id); ?>" target="_blank" rel="noopener noreferrer" title="Open SSO login page">Open SSO login page</a> (new tab)</p>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;gap:10px;"><button class="btn btn-primary" type="submit">💾</button><a href="index.php" class="btn">🔙</a></div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script>
document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) {
        indicator.textContent = event.target.checked ? '✅' : '❌';
    }
});
</script>
</body>
</html>
