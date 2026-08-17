<?php
/**
 * SAML Assertion Consumer Service — receives POST SAMLResponse from IdP.
 */

include 'config/config.php';
require_once __DIR__ . '/includes/itm_saml_auth.php';

$error = '';
$companyId = (int) ($_SESSION['itm_saml_company_id'] ?? 0);
$expectedRequestId = (string) ($_SESSION['itm_saml_request_id'] ?? '');
$samlResponse = (string) ($_POST['SAMLResponse'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $samlResponse === '') {
    $error = 'Missing SAML response.';
} elseif ($companyId <= 0) {
    $error = 'SSO session expired. Start sign-in again.';
} else {
    $result = itm_saml_auth_attempt($conn, $companyId, $samlResponse, $expectedRequestId);
    unset($_SESSION['itm_saml_request_id'], $_SESSION['itm_saml_company_id']);
    if (!empty($result['ok']) && is_array($result['employee'] ?? null)) {
        $redirect = itm_sso_finalize_employee_login_session($conn, $result['employee']);
        if ($redirect === 'dashboard') {
            header('Location: ' . BASE_URL . 'dashboard.php');
            exit;
        }
        if ($redirect === 'user-config') {
            header('Location: ' . BASE_URL . 'user-config.php');
            exit;
        }
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
    $error = (string) ($result['error'] ?? 'SAML authentication failed.');
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
        <p class="alert alert-danger"><?php echo sanitize($error); ?></p>
        <p><a href="<?php echo sanitize(BASE_URL); ?>sso-saml.php?company_id=<?php echo (int) $companyId; ?>" title="Try SAML sign-in again">🔐</a>
        <a href="<?php echo sanitize(BASE_URL); ?>login.php" title="Back to login">🔙</a></p>
    </div>
</div>
</body>
</html>
