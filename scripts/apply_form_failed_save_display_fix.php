<?php
/**
 * Apply cr_form_display_value + $sqlValues POST fix across CRUD module entry files.
 *
 * Why: Bulk-fix static failures from test_form_failed_save_display.php without
 * hand-editing hundreds of duplicated module files.
 *
 * CLI: php scripts/apply_form_failed_save_display_fix.php [--dry-run] [--module=name]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;max-width:720px;">';
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> This script writes PHP module files. Use <code>--dry-run</code> first:</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/apply_form_failed_save_display_fix.php --dry-run</pre>';
    echo '</body></html>';
    exit(1);
}

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/form_failed_save_test.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$onlyModules = [];
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--module=') === 0) {
        $onlyModules[] = substr($arg, 9);
    }
}

$helperFn = <<<'PHP'
function cr_form_display_value($value) {
    return itm_cr_form_display_value($value);
}

PHP;

$oldPostWithActive = <<<'OLD'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    cr_require_valid_csrf_token();

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');
        if ($isTinyInt || $name === 'active') {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int)$company_id;
            continue;
        }

        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = 'NULL';
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];
                $newValueEsc = mysqli_real_escape_string($conn, $newValueRaw);

                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "='" . $newValueEsc . "'";
                if (in_array('company_id', $available, true) && $company_id > 0) {
                    $findSql .= ' AND company_id=' . (int)$company_id;
                }
                $findSql .= ' LIMIT 1';
                $existing = mysqli_query($conn, $findSql);
                if ($existing && mysqli_num_rows($existing) > 0) {
                    $row = mysqli_fetch_assoc($existing);
                    $data[$name] = (string)(int)$row['id'];
                } else {
                    $insertFields = [cr_escape_identifier($labelCol)];
                    $insertValues = ["'" . $newValueEsc . "'"];
                    if (in_array('company_id', $available, true) && $company_id > 0) {
                        $insertFields[] = '`company_id`';
                        $insertValues[] = (string)(int)$company_id;
                    }
                    $insertSql = 'INSERT INTO ' . cr_escape_identifier($fkTable)
                        . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertValues) . ')';
                    $dbErrorCode = 0;
                    $dbErrorMessage = '';
                    if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                        $data[$name] = (string)(int)mysqli_insert_id($conn);
                    } else {
                        $errors[] = 'Could not add related value for ' . $name . '. ' . itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                        $data[$name] = 'NULL';
                    }
                }
                continue;
            }
        }

        $value = $_POST[$name] ?? null;
        if ($value === '' || $value === null) {
            $data[$name] = 'NULL';
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $normalizedNumeric = null;
            $numericError = '';
            if (!cr_validate_numeric_value($value, $col, $name, $normalizedNumeric, $numericError)) {
                $errors[] = $numericError;
                $data[$name] = 'NULL';
            } else {
                $data[$name] = $normalizedNumeric;
            }
        } else {
            $data[$name] = "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $data[$name];
            }
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
        } else {
            $sets = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $sets[] = cr_escape_identifier($name) . '=' . $data[$name];
            }
OLD;

$newPostWithActive = <<<'NEW'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    cr_require_valid_csrf_token();
    $sqlValues = [];

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');
        if ($isTinyInt || $name === 'active') {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            $sqlValues[$name] = (string) (int) $data[$name];
            continue;
        }

        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int) $company_id;
            $sqlValues[$name] = (string) (int) $company_id;
            continue;
        }

        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string) ($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = '';
                $sqlValues[$name] = 'NULL';
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];
                $newValueEsc = mysqli_real_escape_string($conn, $newValueRaw);

                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "='" . $newValueEsc . "'";
                if (in_array('company_id', $available, true) && $company_id > 0) {
                    $findSql .= ' AND company_id=' . (int) $company_id;
                }
                $findSql .= ' LIMIT 1';
                $existing = mysqli_query($conn, $findSql);
                if ($existing && mysqli_num_rows($existing) > 0) {
                    $row = mysqli_fetch_assoc($existing);
                    $data[$name] = (string) (int) $row['id'];
                    $sqlValues[$name] = (string) (int) $row['id'];
                } else {
                    $insertFields = [cr_escape_identifier($labelCol)];
                    $insertValues = ["'" . $newValueEsc . "'"];
                    if (in_array('company_id', $available, true) && $company_id > 0) {
                        $insertFields[] = '`company_id`';
                        $insertValues[] = (string) (int) $company_id;
                    }
                    $insertSql = 'INSERT INTO ' . cr_escape_identifier($fkTable)
                        . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertValues) . ')';
                    $dbErrorCode = 0;
                    $dbErrorMessage = '';
                    if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                        $resolvedId = (string) (int) mysqli_insert_id($conn);
                        $data[$name] = $resolvedId;
                        $sqlValues[$name] = $resolvedId;
                    } else {
                        $errors[] = 'Could not add related value for ' . $name . '. ' . itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                        $data[$name] = '';
                        $sqlValues[$name] = 'NULL';
                    }
                }
                continue;
            }
        }

        $value = $_POST[$name] ?? null;
        if ($value === '' || $value === null) {
            $data[$name] = '';
            $sqlValues[$name] = 'NULL';
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $normalizedNumeric = null;
            $numericError = '';
            if (!cr_validate_numeric_value($value, $col, $name, $normalizedNumeric, $numericError)) {
                $errors[] = $numericError;
                $data[$name] = '';
                $sqlValues[$name] = 'NULL';
            } else {
                $data[$name] = $normalizedNumeric;
                $sqlValues[$name] = $normalizedNumeric;
            }
        } else {
            $data[$name] = (string) $value;
            $sqlValues[$name] = "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $sqlValues[$name] ?? 'NULL';
            }
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
        } else {
            $sets = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $sets[] = cr_escape_identifier($name) . '=' . ($sqlValues[$name] ?? 'NULL');
            }
NEW;

$oldPostTinyintOnly = <<<'OLD'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    cr_require_valid_csrf_token();

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');
        if ($isTinyInt) {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int)$company_id;
            continue;
        }

        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = 'NULL';
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];
                $newValueEsc = mysqli_real_escape_string($conn, $newValueRaw);

                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "='" . $newValueEsc . "'";
                if (in_array('company_id', $available, true) && $company_id > 0) {
                    $findSql .= ' AND company_id=' . (int)$company_id;
                }
                $findSql .= ' LIMIT 1';
                $existing = mysqli_query($conn, $findSql);
                if ($existing && mysqli_num_rows($existing) > 0) {
                    $row = mysqli_fetch_assoc($existing);
                    $data[$name] = (string)(int)$row['id'];
                } else {
                    $insertFields = [cr_escape_identifier($labelCol)];
                    $insertValues = ["'" . $newValueEsc . "'"];
                    if (in_array('company_id', $available, true) && $company_id > 0) {
                        $insertFields[] = '`company_id`';
                        $insertValues[] = (string)(int)$company_id;
                    }
                    $insertSql = 'INSERT INTO ' . cr_escape_identifier($fkTable)
                        . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertValues) . ')';
                    $dbErrorCode = 0;
                    $dbErrorMessage = '';
                    if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                        $data[$name] = (string)(int)mysqli_insert_id($conn);
                    } else {
                        $errors[] = 'Could not add related value for ' . $name . '. ' . itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                        $data[$name] = 'NULL';
                    }
                }
                continue;
            }
        }

        $value = $_POST[$name] ?? null;
        if ($value === '' || $value === null) {
            $data[$name] = 'NULL';
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $normalizedNumeric = null;
            $numericError = '';
            if (!cr_validate_numeric_value($value, $col, $name, $normalizedNumeric, $numericError)) {
                $errors[] = $numericError;
                $data[$name] = 'NULL';
            } else {
                $data[$name] = $normalizedNumeric;
            }
        } else {
            $data[$name] = "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $data[$name];
            }
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
        } else {
            $sets = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $sets[] = cr_escape_identifier($name) . '=' . $data[$name];
            }
OLD;

$newPostTinyintOnly = $newPostWithActive;

/**
 * Regex fallback for index.php and other commented CRUD POST variants.
 */
function itm_patch_crud_post_sqlvalues_regex(string $content): array
{
    $notes = [];
    if (strpos($content, '$sqlValues') !== false) {
        return ['content' => $content, 'notes' => $notes];
    }
    if (!preg_match('/\$data\[\$name\]\s*=\s*["\']\'["\']\s*\.\s*mysqli_real_escape_string\s*\(/', $content)) {
        return ['content' => $content, 'notes' => $notes];
    }

    $patched = preg_replace(
        '/(if \(\$_SERVER\[\'REQUEST_METHOD\'\] === \'POST\' && in_array\(\$crud_action, \[\'create\', \'edit\'\], true\) \{\s*\R\s*cr_require_valid_csrf_token\(\);)/',
        "$1\n    \$sqlValues = [];",
        $content,
        1,
        $initCount
    );
    if ($initCount > 0) {
        $content = $patched;
        $notes[] = 'sqlValues init (regex)';
    }

    $content = preg_replace(
        '/(\$data\[\$name\] = isset\(\$_POST\[\$name\]\) \? 1 : 0;)\R(\s*)continue;/',
        "$1\n$2\$sqlValues[\$name] = (string) (int) \$data[\$name];\n$2continue;",
        $content
    );

    $content = preg_replace(
        '/(\$data\[\$name\] = \(int\)\$company_id;)\R(\s*)continue;/',
        "$1\n$2\$sqlValues[\$name] = (string) (int) \$company_id;\n$2continue;",
        $content
    );

    $content = preg_replace(
        '/(\$errors\[\] = \'Please wait for the new value to be created before saving\.\';)\R\s*\$data\[\$name\] = \'NULL\';/',
        "$1\n                \$data[\$name] = '';\n                \$sqlValues[\$name] = 'NULL';",
        $content
    );

    $content = preg_replace(
        '/(\$errors\[\] = \'Please wait for the new value to be created before saving\.\';)\R\s*\$data\[\$name\] = null;/',
        "$1\n                \$data[\$name] = '';\n                \$sqlValues[\$name] = 'NULL';",
        $content
    );

    $content = preg_replace(
        '/if \(itm_run_query\(\$conn, \$insertSql, \$dbErrorCode, \$dbErrorMessage\)\) \{\R\s*\$data\[\$name\] = \(string\)\(int\)mysqli_insert_id\(\$conn\);/',
        "if (itm_run_query(\$conn, \$insertSql, \$dbErrorCode, \$dbErrorMessage)) {\n                        \$resolvedId = (string) (int) mysqli_insert_id(\$conn);\n                        \$data[\$name] = \$resolvedId;\n                        \$sqlValues[\$name] = \$resolvedId;",
        $content
    );

    $content = preg_replace(
        '/if \(itm_run_query\(\$conn, \$insertSql, \$dbErrorCode, \$dbErrorMessage\)\) \{\R\s*\$data\[\$name\] = \(string\)\(int\)mysqli_insert_id\(\$conn\);/i',
        "if (itm_run_query(\$conn, \$insertSql, \$dbErrorCode, \$dbErrorMessage)) {\n                        \$resolvedId = (string) (int) mysqli_insert_id(\$conn);\n                        \$data[\$name] = \$resolvedId;\n                        \$sqlValues[\$name] = \$resolvedId;",
        $content
    );

    $content = preg_replace(
        '/if \(\$existing && mysqli_num_rows\(\$existing\) > 0\) \{\R\s*\$row = mysqli_fetch_assoc\(\$existing\);\R\s*\$data\[\$name\] = \(string\)\(int\)\$row\[\'id\'\];/',
        "if (\$existing && mysqli_num_rows(\$existing) > 0) {\n                    \$row = mysqli_fetch_assoc(\$existing);\n                    \$data[\$name] = (string) (int) \$row['id'];\n                    \$sqlValues[\$name] = (string) (int) \$row['id'];",
        $content
    );

    $content = preg_replace(
        '/(\$errors\[\] = \'Could not add related value for \' \. \$name \. \'\. \' \. itm_format_db_constraint_error\(\$dbErrorCode, \$dbErrorMessage\);)\R\s*\$data\[\$name\] = \'NULL\';/',
        "$1\n                        \$data[\$name] = '';\n                        \$sqlValues[\$name] = 'NULL';",
        $content
    );

    $content = preg_replace(
        '/if \(\$value === \'\' \|\| \$value === null\) \{\R\s*\$data\[\$name\] = \'NULL\';/',
        "if (\$value === '' || \$value === null) {\n            \$data[\$name] = '';\n            \$sqlValues[\$name] = 'NULL';",
        $content
    );

    $content = preg_replace(
        '/if \(\$value === \'\' \|\| \$value === null\) \{\R\s*\$data\[\$name\] = null;/',
        "if (\$value === '' || \$value === null) {\n            \$data[\$name] = '';\n            \$sqlValues[\$name] = 'NULL';",
        $content
    );

    $content = preg_replace(
        '/(\$errors\[\] = \$numericError;)\R\s*\$data\[\$name\] = \'NULL\';/',
        "$1\n                \$data[\$name] = '';\n                \$sqlValues[\$name] = 'NULL';",
        $content
    );

    $content = preg_replace(
        '/(\$errors\[\] = \$numericError;)\R\s*\$data\[\$name\] = null;/',
        "$1\n                \$data[\$name] = '';\n                \$sqlValues[\$name] = 'NULL';",
        $content
    );

    $content = preg_replace(
        '/(\$data\[\$name\] = \$normalizedNumeric;)\R(\s*)\}/',
        "$1\n$2    \$sqlValues[\$name] = \$normalizedNumeric;\n$2}",
        $content
    );

    $content = preg_replace(
        '/\$data\[\$name\] = "\'" \. mysqli_real_escape_string\(\$conn, \$value\) \. "\'";/',
        '$data[$name] = (string) $value;' . "\n            \$sqlValues[\$name] = \"'\" . mysqli_real_escape_string(\$conn, \$value) . \"'\";",
        $content
    );

    $content = preg_replace(
        '/\$values\[\] = \$data\[\$name\];/',
        '$values[] = $sqlValues[$name] ?? \'NULL\';',
        $content
    );

    $content = preg_replace(
        '/\$sets\[\] = cr_escape_identifier\(\$name\) \. \'=\' \. \$data\[\$name\];/',
        '$sets[] = cr_escape_identifier($name) . \'=\' . ($sqlValues[$name] ?? \'NULL\');',
        $content
    );

    if (strpos($content, '$sqlValues') !== false && !in_array('sqlValues regex loop', $notes, true)) {
        $notes[] = 'sqlValues regex loop';
    }

    return ['content' => $content, 'notes' => $notes];
}

/**
 * @return array{changed: bool, notes: array<int, string>}
 */
function itm_apply_form_display_fix_to_file(string $path, bool $dryRun): array
{
    global $helperFn, $oldPostWithActive, $newPostWithActive, $oldPostTinyintOnly, $newPostTinyintOnly;

    $notes = [];
    $content = (string) file_get_contents($path);
    $original = $content;

    if (strpos($content, 'function cr_form_display_value') === false
        && preg_match('/function\s+cr_humanize_field\s*\(/', $content)) {
        $content = preg_replace(
            '/(\/\*\*[\s\S]*?\*\/\s*)?function\s+cr_humanize_field\s*\(/',
            $helperFn . '$0',
            $content,
            1,
            $count
        );
        if ($count > 0) {
            $notes[] = 'added cr_form_display_value';
        }
    }

    $displayReplacement = '$displayVal = cr_form_display_value($data[$name] ?? \'\');';
    $newContent = preg_replace(
        '/\$val\s*=\s*\$data\[\$name\]\s*\?\?\s*(?:\'\'|"")\s*;\s*\R\s*\$displayVal\s*=\s*\(\$val\s*===\s*[\'"]NULL[\'"]\)\s*\?\s*(?:\'\'|"")\s*:\s*\(string\)\$val\s*;/',
        $displayReplacement,
        $content,
        -1,
        $displayCount
    );
    if ($displayCount > 0) {
        $content = $newContent;
        $notes[] = 'updated displayVal';
    }

    if (strpos($content, '$sqlValues') === false
        && strpos($content, '$data[$name] = "\'" . mysqli_real_escape_string') !== false) {
        if (strpos($content, $oldPostWithActive) !== false) {
            $content = str_replace($oldPostWithActive, $newPostWithActive, $content);
            $notes[] = 'patched POST (active tinyint)';
        } elseif (strpos($content, $oldPostTinyintOnly) !== false) {
            $content = str_replace($oldPostTinyintOnly, $newPostTinyintOnly, $content);
            $notes[] = 'patched POST (tinyint only)';
        } else {
            $regexPatch = itm_patch_crud_post_sqlvalues_regex($content);
            $content = $regexPatch['content'];
            foreach ($regexPatch['notes'] as $regexNote) {
                $notes[] = $regexNote;
            }
        }
    }

    if (strpos($content, 'function cr_form_display_value') === false
        && preg_match('/\$displayVal\s*=\s*cr_form_display_value/', $content)) {
        $content = preg_replace(
            '/<\?php\s*\R/',
            "<?php\n" . $helperFn . "\n",
            $content,
            1,
            $topHelperCount
        );
        if ($topHelperCount > 0) {
            $notes[] = 'added cr_form_display_value (top)';
        }
    }

    $changed = $content !== $original;
    if ($changed && !$dryRun) {
        file_put_contents($path, $content);
    }

    return ['changed' => $changed, 'notes' => $notes];
}

$changedFiles = 0;
$skipped = 0;

foreach (glob(ROOT_PATH . 'modules/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
    $module = basename($moduleDir);
    if ($onlyModules && !in_array($module, $onlyModules, true)) {
        continue;
    }

    foreach (['index.php', 'create.php', 'edit.php', 'view.php', 'delete.php', 'list_all.php'] as $entry) {
        $path = $moduleDir . '/' . $entry;
        if (!is_file($path)) {
            continue;
        }

        $scan = itm_form_failed_save_test_scan_file($path);
        if ($scan['status'] !== 'fail') {
            continue;
        }

        $result = itm_apply_form_display_fix_to_file($path, $dryRun);
        if ($result['changed']) {
            $changedFiles++;
            $rel = 'modules/' . $module . '/' . $entry;
            fwrite(STDOUT, ($dryRun ? '[dry-run] ' : '') . $rel . ': ' . implode(', ', $result['notes']) . "\n");
        } else {
            $skipped++;
            fwrite(STDOUT, "[manual] modules/{$module}/{$entry}: {$scan['notes']}\n");
        }
    }
}

fwrite(STDOUT, "\nDone. Files updated: {$changedFiles}, need manual review: {$skipped}\n");

if (!$dryRun && $changedFiles > 0) {
    $verify = itm_form_failed_save_test_run(ROOT_PATH . 'modules', ['static' => true, 'runtime' => false]);
    fwrite(
        STDOUT,
        "Static re-scan: static_fail={$verify['summary']['static_fail']}, modules={$verify['summary']['modules']}\n"
    );
    exit($verify['summary']['static_fail'] > 0 ? 1 : 0);
}

exit(0);
