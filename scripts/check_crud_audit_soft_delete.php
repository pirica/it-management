#!/usr/bin/env php
<?php
/**
 * Static check: scaffold soft-delete + audit UI contracts for docs/list_soft-delete.txt.
 *
 * Exit 0 when all in-scope module index.php files pass; else exit 1.
 */

$root = dirname(__DIR__) . '/';
require_once $root . 'includes/itm_crud_audit_fields.php';

$slugs = itm_crud_load_soft_delete_module_slugs($root);
$failures = [];

foreach ($slugs as $slug) {
    $index = $root . 'modules/' . $slug . '/index.php';
    if (!is_file($index)) {
        $failures[] = "{$slug}: missing index.php";
        continue;
    }
    $src = (string)file_get_contents($index);

    if (strpos($src, 'itm_crud_is_list_hidden_audit_field') === false
        && strpos($src, "['deleted_by', 'deleted_at', 'created_by', 'created_at', 'updated_by', 'updated_at']") === false
    ) {
        $failures[] = "{$slug}: list does not hide audit meta fields";
    }
    if (strpos($src, '$viewColumns') === false) {
        $failures[] = "{$slug}: missing \$viewColumns for view audit meta";
    }
    if (strpos($src, 'itm_crud_append_not_deleted_predicate') === false
        && strpos($src, 'deleted_at IS NULL') === false
    ) {
        $failures[] = "{$slug}: list missing deleted_at IS NULL filter";
    }
    if (strpos($src, 'itm_crud_build_soft_delete_sql') === false
        && preg_match("/DELETE FROM '\s*\.\s*cr_escape_identifier/", $src)
    ) {
        $failures[] = "{$slug}: still hard-deletes without soft-delete helper";
    }

    foreach (glob($root . 'modules/' . $slug . '/delete.php') ?: [] as $del) {
        $d = (string)file_get_contents($del);
        if ($d !== '' && substr_count($d, "\n") > 30
            && strpos($d, 'itm_crud_build_soft_delete_sql') === false
            && preg_match("/DELETE FROM/", $d)
        ) {
            $failures[] = "{$slug}: delete.php hard DELETE without soft-delete helper";
        }
    }
}

if ($failures) {
    fwrite(STDERR, "check_crud_audit_soft_delete: " . count($failures) . " issue(s)\n");
    foreach ($failures as $f) {
        fwrite(STDERR, " - {$f}\n");
    }
    exit(1);
}

echo "check_crud_audit_soft_delete: OK (" . count($slugs) . " modules)\n";
exit(0);
