#!/usr/bin/env php
<?php
/**
 * Apply soft-delete + audit-column UI patches to scaffold CRUD modules listed in
 * docs/list_soft-delete.txt.
 *
 * Usage:
 *   php scripts/apply_crud_audit_soft_delete.php           # dry-run
 *   php scripts/apply_crud_audit_soft_delete.php --apply    # write changes
 */

define('ITM_CLI_SCRIPT', true);
putenv('ITM_SKIP_DB_TESTS=1');

$root = dirname(__DIR__) . '/';
require_once $root . 'includes/itm_crud_audit_fields.php';

$apply = in_array('--apply', $argv ?? [], true);
$slugs = itm_crud_load_soft_delete_module_slugs($root);
if ($slugs === []) {
    fwrite(STDERR, "No slugs loaded from docs/list_soft-delete.txt\n");
    exit(1);
}

$changed = 0;
$scanned = 0;

foreach ($slugs as $slug) {
    $dir = $root . 'modules/' . $slug;
    if (!is_dir($dir)) {
        echo "[SKIP] missing module dir: {$slug}\n";
        continue;
    }
    foreach (glob($dir . '/*.php') ?: [] as $file) {
        $scanned++;
        $original = file_get_contents($file);
        if ($original === false) {
            continue;
        }
        // Skip thin wrappers that only require index.php
        if (substr_count($original, "\n") < 30 && preg_match("/require\s+['\"]index\\.php['\"]/", $original)) {
            continue;
        }
        if (strpos($original, 'function cr_manageable_columns') === false
            && strpos($original, 'DELETE FROM') === false
            && strpos($original, '$uiColumns') === false
        ) {
            continue;
        }

        $updated = itm_apply_crud_audit_soft_delete_to_source($original, $slug);
        if ($updated === $original) {
            continue;
        }
        $rel = str_replace($root, '', $file);
        if ($apply) {
            if (file_put_contents($file, $updated) === false) {
                echo "[FAIL] write {$rel}\n";
                exit(1);
            }
            echo "[APPLY] {$rel}\n";
        } else {
            echo "[DRY] {$rel}\n";
        }
        $changed++;
    }
}

echo ($apply ? "Applied" : "Would change") . " {$changed} file(s); scanned {$scanned}.\n";
exit(0);

/**
 * @param string $src
 * @param string $slug
 * @return string
 */
function itm_apply_crud_audit_soft_delete_to_source($src, $slug)
{
    $out = $src;

    // 1) cr_manageable_columns — keep only id excluded (audit meta stays available).
    $out = preg_replace(
        '/function cr_manageable_columns\(\$columns\)\s*\{\s*return array_values\(array_filter\(\$columns, function \(\$c\) \{\s*return !in_array\(\$c\[\'Field\'\],\s*\[[^\]]+\],\s*true\);\s*\}\)\);\s*\}/s',
        "function cr_manageable_columns(\$columns) {\n"
        . "    // Why: Keep audit meta available for view/hidden forms/POST; list hides via itm_crud_is_list_hidden_audit_field.\n"
        . "    return array_values(array_filter(\$columns, function (\$c) {\n"
        . "        return (\$c['Field'] ?? '') !== 'id';\n"
        . "    }));\n"
        . "}",
        $out,
        1,
        $countManageable
    );

    // Alerts-style expanded denylist → same id-only filter.
    if (!$countManageable) {
        $out = preg_replace(
            '/function cr_manageable_columns\(\$columns\)\s*\{\s*return array_values\(array_filter\(\$columns, function \(\$c\) \{\s*return !in_array\(\$c\[\'Field\'\],\s*\[[^\]]*deleted_by[^\]]*\],\s*true\);\s*\}\)\);\s*\}/s',
            "function cr_manageable_columns(\$columns) {\n"
            . "    // Why: Keep audit meta available for view/hidden forms/POST; list hides via itm_crud_is_list_hidden_audit_field.\n"
            . "    return array_values(array_filter(\$columns, function (\$c) {\n"
            . "        return (\$c['Field'] ?? '') !== 'id';\n"
            . "    }));\n"
            . "}",
            $out,
            1
        );
    }

    // 2) cr_render_cell_value — inject audit rendering after active badge block (or at start of function).
    if (strpos($out, 'itm_crud_render_audit_cell_value') === false
        && preg_match('/function cr_render_cell_value\(\$table, \$field, \$value\)\s*\{/', $out)
    ) {
        $inject = "function cr_render_cell_value(\$table, \$field, \$value) {\n"
            . "    if (function_exists('itm_crud_render_audit_cell_value')) {\n"
            . "        \$auditHtml = itm_crud_render_audit_cell_value(\$GLOBALS['conn'] ?? null, (int)(\$GLOBALS['company_id'] ?? 0), \$field, \$value);\n"
            . "        if (\$auditHtml !== null) {\n"
            . "            return \$auditHtml;\n"
            . "        }\n"
            . "    }\n";
        $out = preg_replace(
            '/function cr_render_cell_value\(\$table, \$field, \$value\)\s*\{\s*/',
            $inject,
            $out,
            1
        );
    }

    // 3) $uiColumns filter — hide list audit fields; add $viewColumns after $displayFieldColumns.
    if (strpos($out, 'itm_crud_is_list_hidden_audit_field') === false
        && strpos($out, '$uiColumns = array_values(array_filter($fieldColumns') !== false
    ) {
        $out = preg_replace(
            '/\$uiColumns = array_values\(array_filter\(\$fieldColumns, function \(\$col\) use \(\$hideCompanyIdTables\) \{\s*'
            . 'if \(\(\$col\[\'Field\'\] \?\? \'\'\) !== \'company_id\'\) \{\s*'
            . 'return true;\s*'
            . '\}\s*'
            . 'return !in_array\(\(string\)\(\$GLOBALS\[\'crud_table\'\] \?\? \'\'\), \$hideCompanyIdTables, true\);\s*'
            . '\}\)\);/s',
            "\$uiColumns = array_values(array_filter(\$fieldColumns, function (\$col) use (\$hideCompanyIdTables) {\n"
            . "    \$fieldName = (string)(\$col['Field'] ?? '');\n"
            . "    if (function_exists('itm_crud_is_list_hidden_audit_field') && itm_crud_is_list_hidden_audit_field(\$fieldName)) {\n"
            . "        return false;\n"
            . "    }\n"
            . "    if (\$fieldName !== 'company_id') {\n"
            . "        return true;\n"
            . "    }\n"
            . "    return !in_array((string)(\$GLOBALS['crud_table'] ?? ''), \$hideCompanyIdTables, true);\n"
            . "}));",
            $out,
            1
        );
    }

    if (strpos($out, '$viewColumns =') === false
        && strpos($out, '$displayFieldColumns = $uiColumns;') !== false
    ) {
        $out = str_replace(
            "// Why: Search and list share visible columns; alias matches role/ui_configuration modules.\n\$displayFieldColumns = \$uiColumns;",
            "// Why: Search and list share visible columns; alias matches role/ui_configuration modules.\n"
            . "\$displayFieldColumns = \$uiColumns;\n\n"
            . "// Why: View shows create/update/delete audit stamps while list hides them.\n"
            . "\$viewColumns = array_values(array_filter(\$fieldColumns, function (\$col) use (\$hideCompanyIdTables) {\n"
            . "    \$fieldName = (string)(\$col['Field'] ?? '');\n"
            . "    if (\$fieldName !== 'company_id') {\n"
            . "        return true;\n"
            . "    }\n"
            . "    return !in_array((string)(\$GLOBALS['crud_table'] ?? ''), \$hideCompanyIdTables, true);\n"
            . "}));",
            $out
        );
        // Alternate comment wording
        if (strpos($out, '$viewColumns =') === false) {
            $out = preg_replace(
                '/\$displayFieldColumns = \$uiColumns;/',
                "\$displayFieldColumns = \$uiColumns;\n\n"
                . "// Why: View shows create/update/delete audit stamps while list hides them.\n"
                . "\$viewColumns = array_values(array_filter(\$fieldColumns, function (\$col) use (\$hideCompanyIdTables) {\n"
                . "    \$fieldName = (string)(\$col['Field'] ?? '');\n"
                . "    if (\$fieldName !== 'company_id') {\n"
                . "        return true;\n"
                . "    }\n"
                . "    return !in_array((string)(\$GLOBALS['crud_table'] ?? ''), \$hideCompanyIdTables, true);\n"
                . "}));",
                $out,
                1
            );
        }
    }

    // 4) Append not-deleted predicate after company WHERE for list builder.
    if (strpos($out, 'itm_crud_append_not_deleted_predicate') === false) {
        $out = preg_replace(
            '/(\$where = \'\';\s*'
            . 'if \(\$hasCompany && \$company_id > 0\) \{\s*'
            . '\$where = \' WHERE company_id=\' \. \(int\)\$company_id;\s*'
            . '\})/s',
            "$1\n"
            . "if (function_exists('itm_crud_append_not_deleted_predicate')) {\n"
            . "    \$where = itm_crud_append_not_deleted_predicate(\$where);\n"
            . "}",
            $out,
            1
        );
    }

    // companyTotalRows path without deleted filter — append there too when simple company WHERE.
    if (strpos($out, 'companyCountSql') !== false && substr_count($out, 'itm_crud_append_not_deleted_predicate') < 2) {
        // leave company totals as all live rows via same $where where reused
    }

    // 5) View loop uses $viewColumns
    $out = preg_replace(
        '/foreach \(\$uiColumns as \$col\): \$f = \$col\[\'Field\'\]; \?>/',
        'foreach ($viewColumns as $col): $f = $col[\'Field\']; ?>',
        $out
    );

    // 6) Create/edit: after csrf hidden, render audit hidden inputs; skip form widgets for audit list-hidden on $uiColumns (already filtered).
    if (strpos($out, 'itm_crud_render_form_hidden_audit_inputs') === false
        && strpos($out, 'name="csrf_token"') !== false
        && strpos($out, "in_array(\$crud_action, ['create', 'edit']") !== false
    ) {
        $out = preg_replace(
            '/(<form method="POST"[^>]*>\s*'
            . '<input type="hidden" name="csrf_token" value="<\?php echo sanitize\(\$csrfToken\); \?>">\s*)/s',
            "$1"
            . "                    <?php\n"
            . "                    if (function_exists('itm_crud_render_form_hidden_audit_inputs')) {\n"
            . "                        itm_crud_render_form_hidden_audit_inputs(\$data, (string)\$crud_action);\n"
            . "                    }\n"
            . "                    ?>\n",
            $out,
            1
        );
    }

    // 7) POST stamp create/update before INSERT/UPDATE building — after field loop prep.
    if (strpos($out, 'itm_crud_stamp_create_audit') === false
        && strpos($out, "\$crud_action === 'create'") !== false
        && strpos($out, 'foreach ($fieldColumns as $col)') !== false
    ) {
        // Inject before INSERT construction common marker
        $out = preg_replace(
            '/(if \(\$crud_action === \'create\'\) \{\s*)(\$fields =|\$columnsToInsert|\/\/ INSERT)/',
            "$1"
            . "if (function_exists('itm_crud_stamp_create_audit')) {\n"
            . "            itm_crud_stamp_create_audit(\$data, \$sqlValues);\n"
            . "        }\n"
            . "        $2",
            $out,
            1
        );
        $out = preg_replace(
            '/(if \(\$crud_action === \'edit\'\) \{\s*)(\$sets =|\$update|\/\/ UPDATE)/',
            "$1"
            . "if (function_exists('itm_crud_stamp_update_audit')) {\n"
            . "            itm_crud_stamp_update_audit(\$data, \$sqlValues, \$data);\n"
            . "        }\n"
            . "        $2",
            $out,
            1
        );
    }

    // 8) Soft-delete: replace DELETE FROM patterns with helper.
    if (strpos($out, 'itm_crud_build_soft_delete_sql') === false) {
        $out = preg_replace_callback(
            '/\$deleteSql\s*=\s*\'DELETE FROM \'\s*\.\s*cr_escape_identifier\(\$crud_table\)\s*\.\s*\$where(\s*\.\s*\' LIMIT 1\')?\s*;/',
            function ($m) {
                $limit = !empty($m[1]);
                return "\$deleteSql = function_exists('itm_crud_build_soft_delete_sql')\n"
                    . "        ? itm_crud_build_soft_delete_sql(\$crud_table, \$where, (int)(\$_SESSION['employee_id'] ?? 0))"
                    . ($limit ? " . ''" : "")
                    . "\n"
                    . "        : ('DELETE FROM ' . cr_escape_identifier(\$crud_table) . \$where"
                    . ($limit ? " . ' LIMIT 1'" : "")
                    . ");";
            },
            $out
        );
        // Without LIMIT variant sometimes wrapped differently
        $out = preg_replace(
            '/\$deleteSql\s*=\s*\'DELETE FROM \'\s*\.\s*cr_escape_identifier\(\$crud_table\)\s*\.\s*\$where\s*\.\s*\' LIMIT 1\'\s*;/',
            "\$deleteSql = function_exists('itm_crud_build_soft_delete_sql')\n"
            . "        ? itm_crud_build_soft_delete_sql(\$crud_table, \$where, (int)(\$_SESSION['employee_id'] ?? 0))\n"
            . "        : ('DELETE FROM ' . cr_escape_identifier(\$crud_table) . \$where . ' LIMIT 1');",
            $out
        );
    }

    // 9) Delete forms — inject hidden audit after csrf on row/bulk forms posting to delete.php
    if (strpos($out, 'itm_crud_render_delete_hidden_audit_inputs') === false
        && strpos($out, 'action="delete.php"') !== false
    ) {
        $out = preg_replace(
            '/(<form[^>]*action="delete\.php"[^>]*>\s*'
            . '<input type="hidden" name="csrf_token" value="[^"]*">\s*)/s',
            "$1\n"
            . "                                        <?php if (function_exists('itm_crud_render_delete_hidden_audit_inputs')) { itm_crud_render_delete_hidden_audit_inputs(); } ?>\n",
            $out
        );
        // PHP-echo csrf variant
        $out = preg_replace(
            '/(<form[^>]*action="delete\.php"[^>]*>\s*'
            . '<input type="hidden" name="csrf_token" value="<\?php echo sanitize\(\$csrfToken\); \?>">\s*)/s',
            "$1"
            . "                                        <?php if (function_exists('itm_crud_render_delete_hidden_audit_inputs')) { itm_crud_render_delete_hidden_audit_inputs(); } ?>\n",
            $out
        );
    }

    return $out;
}
