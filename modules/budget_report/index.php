<?php
/**
 * Budget Report — all GL categories (computed rollup).
 */

require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete (do not duplicate per handler).
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);


$itmBcrCategoryKind = null;
$itmBcrTitle = 'Budget Report';
$itmBcrHeadingEmoji = '📑';
$itmBcrHeadingTitle = 'Budget report';

require ROOT_PATH . 'includes/itm_budget_category_report_bootstrap.php';
