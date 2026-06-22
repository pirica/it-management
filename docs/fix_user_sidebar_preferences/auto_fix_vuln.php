<?php
/**
 * Auto-fix for Sidebar Prefs RBAC vulnerability.
 * Generates a patched version of modules/employee_sidebar_preferences/index.php.
 */

$source = __DIR__ . '/../../modules/employee_sidebar_preferences/index.php';
$targetDir = __DIR__ . '/../fixed_files_vulnerability_contacts/fixed_files';
$target = $targetDir . '/sidebar_preferences_index.php';

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

if (!file_exists($source)) {
    die("Source file not found: $source\n");
}

$content = file_get_contents($source);

// Add itm_require_crud_role_module_permission for create/edit
// Also fix the logic bug where 'create' was unreachable.
$search = "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && \$crud_action === 'edit') {\n    cr_require_valid_csrf_token();";
$replace = "if (\$_SERVER['REQUEST_METHOD'] === 'POST' && in_array(\$crud_action, ['create', 'edit'], true)) {\n    itm_require_crud_role_module_permission(\$conn, \$crud_action, 'employee_sidebar_preferences');\n    cr_require_valid_csrf_token();";

if (strpos($content, "in_array(\$crud_action, ['create', 'edit'], true)") !== false) {
    echo "File already patched or contains RBAC check for create/edit.\n";
    copy($source, $target);
} else {
    $patchedContent = str_replace($search, $replace, $content);
    if (file_put_contents($target, $patchedContent)) {
        echo "Successfully generated fixed file: $target\n";
    } else {
        echo "Failed to write fixed file.\n";
    }
}
