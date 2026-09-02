<?php
/**
 * SAML SSO login entry — HTTP-Redirect AuthnRequest to IdP.
 */

include 'config/config.php';
require_once __DIR__ . '/includes/itm_saml_auth.php';

$error = '';
$companyHint = trim((string) ($_GET['company_id'] ?? $_GET['company'] ?? ''));
$selectedCompany = itm_sso_resolve_company_for_login($conn, $companyHint);
$selectedCompanyId = is_array($selectedCompany) ? (int) ($selectedCompany['id'] ?? 0) : 0;

if ($selectedCompanyId <= 0) {
    $error = 'Select a company with SAML SSO enabled.';
} elseif ((int) ($selectedCompany['sso_enabled'] ?? 0) !== 1) {
    $error = 'SSO is not enabled for this company.';
} elseif (strtolower(trim((string) ($selectedCompany['sso_provider'] ?? 'ldap'))) !== 'saml') {
    $error = 'This company uses LDAP SSO. Use the LDAP login page instead.';
} else {
    $config = itm_saml_decrypt_config($selectedCompany['sso_config_json_encrypted'] ?? '');
    if (!is_array($config) || $config['idp_sso_url'] === '' || $config['idp_x509_cert'] === '') {
        $error = 'SAML is not fully configured for this company.';
    } else {
        $requestId = itm_saml_generate_request_id();
        $_SESSION['itm_saml_request_id'] = $requestId;
        $_SESSION['itm_saml_company_id'] = $selectedCompanyId;
        $redirectUrl = itm_saml_redirect_login_url($config, $requestId);
        if ($redirectUrl === '') {
            $error = 'Could not build SAML login redirect.';
        } else {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAML SSO</title>
    <link rel="stylesheet" href="<?php echo sanitize(BASE_URL); ?>css/styles.css">
</head>
<body>
<div class="container" style="max-width:640px;margin:40px auto;">
    <div class="card">
        <h1 title="SAML SSO">🔐</h1>
        <?php if ($error !== ''): ?>
            <p class="alert alert-danger"><?php echo sanitize($error); ?></p>
            <p><a href="<?php echo sanitize(BASE_URL); ?>login.php" title="Back to login">🔙</a></p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
