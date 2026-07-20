<?php
/**
 * Employees Module - List All Redirect
 */

require '../../config/config.php';
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);

header('Location: index.php');
exit;
