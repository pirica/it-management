<?php
/**
 * Single RBAC chokepoint for CRUD POST mutations (create / edit / delete).
 *
 * Why: index-router and standalone entry files call one guard instead of duplicating
 * itm_require_crud_role_module_permission() in delete / create / edit handler blocks.
 */

declare(strict_types=1);

if (!function_exists('itm_crud_mutation_guard_entry')) {
    /**
     * Enforce RBAC once per POST mutation request on create.php / edit.php / delete.php / index.php.
     */
    function itm_crud_mutation_guard_entry($conn, $crudAction, $moduleSlug): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $slug = strtolower(trim((string)$moduleSlug));
        if ($slug === '') {
            $slug = strtolower(trim((string)basename(dirname((string)($_SERVER['SCRIPT_FILENAME'] ?? '')))));
        }

        $action = strtolower(trim((string)$crudAction));
        if ($action === '' || $action === 'index') {
            $recordId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            $action = $recordId > 0 ? 'edit' : 'create';
        }

        if (!in_array($action, ['create', 'edit', 'delete'], true)) {
            return;
        }

        itm_crud_enforce_mutation_access($conn, $action, $slug);
    }
}
