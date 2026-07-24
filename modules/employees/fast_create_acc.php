<?php
/**
 * Employees module — fast account creator (module-scoped RBAC).
 *
 * Browser UI for the active session company. CLI / seed bundle: scripts/fast_create_acc.php.
 */

declare(strict_types=1);

require '../../config/config.php';
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);
require_once '../../includes/itm_fk_option_labels.php';
require_once ROOT_PATH . 'scripts/lib/itm_demo_module_users_seed.php';
require_once '../../includes/itm_company_session.php';
$authEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
$itm_fast_create_acc_company_id = itm_fast_create_acc_resolve_company_id($conn, $authEmployeeId);
if ($itm_fast_create_acc_company_id <= 0) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}
$itm_fast_create_acc_back_href = 'index.php';
require __DIR__ . '/fast_create_acc_browser.php';
