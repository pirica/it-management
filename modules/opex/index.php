<?php
/**
 * OPEX report — operating expenditure budget vs actual rollup.
 */

require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete (do not duplicate per handler).
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);


$itmBcrCategoryKind = 'opex';
$itmBcrTitle = 'OPEX';
$itmBcrHeadingEmoji = '📊';
$itmBcrHeadingTitle = 'OPEX report';

require ROOT_PATH . 'includes/itm_budget_category_report_bootstrap.php';
