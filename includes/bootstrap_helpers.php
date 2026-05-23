<?php
/**
 * Shared bootstrap helpers.
 *
 * Why: Keep foundational helpers in one file so identifier and schema
 * checks do not drift across bootstrap entry points.
 */

/**
 * Validates that a string can be safely used as a SQL identifier.
 */
if (!function_exists('itm_is_safe_identifier')) {
    function itm_is_safe_identifier($name) {
        return is_string($name) && preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;
    }
}

/**
 * Checks whether a database table contains a given column.
 */
if (!function_exists('itm_table_has_column')) {
    function itm_table_has_column($conn, $table, $column) {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($column)) {
            $cache[$key] = false;
            return false;
        }

        $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $cache[$key] = false;
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $cache[$key] = ($res && mysqli_num_rows($res) > 0);
        mysqli_stmt_close($stmt);
        return $cache[$key];
    }
}

/**
 * Whether a table column accepts NULL (sample seed may omit optional FK values).
 */
if (!function_exists('itm_table_column_is_nullable')) {
    function itm_table_column_is_nullable($conn, $table, $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column . '.nullable';
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($column)) {
            $cache[$key] = false;
            return false;
        }

        $sql = 'SELECT IS_NULLABLE FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $cache[$key] = false;
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res) ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        $cache[$key] = is_array($row) && strtoupper((string)($row['IS_NULLABLE'] ?? '')) === 'YES';

        return $cache[$key];
    }
}

/**
 * Converts a database column name into a label for validation and error messages.
 */
if (!function_exists('itm_humanize_field_name')) {
    function itm_humanize_field_name($field) {
        $label = trim((string)$field);
        if ($label === '') {
            return '';
        }

        $map = [
            'department_id' => 'Department Name',
            'office_key_card_department_id' => 'Office Key Card Department',
            'opera_username' => 'OPERA Username',
            'onq_ri' => 'OnQ R&I',
            'hu_the_lobby' => 'HU & The Lobby',
        ];

        if (isset($map[$label])) {
            return $map[$label];
        }

        if ($label === 'id') {
            return 'ID';
        }

        $label = preg_replace('/_id$/', '', $label);
        $label = str_replace('_', ' ', (string)$label);

        return ucwords($label);
    }
}

/**
 * Whether a column name typically represents a dropdown/FK selection in forms.
 */
if (!function_exists('itm_field_looks_like_fk_select')) {
    function itm_field_looks_like_fk_select($field) {
        $name = strtolower(trim((string)$field));
        if ($name === '') {
            return false;
        }
        if (preg_match('/_id$/', $name) === 1) {
            return true;
        }
        if (preg_match('/_by(_user_id)?$/', $name) === 1) {
            return true;
        }
        return in_array($name, ['company_id', 'created_by', 'updated_by', 'approved_by'], true);
    }
}

/**
 * Form display value: never show SQL quote wrappers after a failed save.
 */
if (!function_exists('itm_cr_form_display_value')) {
    function itm_cr_form_display_value($value) {
        if ($value === null || $value === '' || $value === 'NULL') {
            return '';
        }
        $text = (string) $value;
        if (strlen($text) >= 2 && $text[0] === "'" && substr($text, -1) === "'") {
            $inner = substr($text, 1, -1);
            return str_replace(["\\'", '\\\\'], ["'", '\\'], $inner);
        }
        return $text;
    }
}

/**
 * Why: Older imports left it_locations.type_id NOT NULL while the UI allows -- Select --.
 */
if (!function_exists('itm_ensure_it_locations_type_id_nullable')) {
    function itm_ensure_it_locations_type_id_nullable($conn) {
        static $checked = false;
        if ($checked || !($conn instanceof mysqli)) {
            return true;
        }
        $checked = true;

        $stmt = mysqli_prepare(
            $conn,
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        $table = 'it_locations';
        $column = 'type_id';
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row || strtoupper((string) ($row['IS_NULLABLE'] ?? 'NO')) === 'YES') {
            return true;
        }

        mysqli_query($conn, 'ALTER TABLE `it_locations` DROP FOREIGN KEY `it_locations_ibfk_2`');
        mysqli_query($conn, 'ALTER TABLE `it_locations` MODIFY `type_id` int DEFAULT NULL');
        mysqli_query(
            $conn,
            'ALTER TABLE `it_locations` ADD CONSTRAINT `it_locations_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `location_types` (`id`) ON DELETE SET NULL'
        );
        return true;
    }
}

/**
 * Why: Legacy DBs may lack tenant-scoped uniqueness on location type names.
 */
if (!function_exists('itm_ensure_location_types_company_name_unique')) {
    function itm_ensure_location_types_company_name_unique($conn) {
        static $checked = false;
        if ($checked || !($conn instanceof mysqli)) {
            return true;
        }
        $checked = true;

        $targetIndex = 'uq_location_types_company_name';
        $stmt = mysqli_prepare(
            $conn,
            'SELECT INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND NON_UNIQUE = 0
               AND INDEX_NAME <> ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX'
        );
        if (!$stmt) {
            return false;
        }
        $table = 'location_types';
        $primary = 'PRIMARY';
        mysqli_stmt_bind_param($stmt, 'ss', $table, $primary);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $res = mysqli_stmt_get_result($stmt);
        $indexes = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $indexName = (string) ($row['INDEX_NAME'] ?? '');
            if ($indexName === '') {
                continue;
            }
            $indexes[$indexName][] = (string) ($row['COLUMN_NAME'] ?? '');
        }
        mysqli_stmt_close($stmt);

        $hasTarget = false;
        $legacyComposite = [];
        foreach ($indexes as $indexName => $columns) {
            if ($columns === ['company_id', 'name']) {
                if ($indexName === $targetIndex) {
                    $hasTarget = true;
                } else {
                    $legacyComposite[] = $indexName;
                }
            }
        }

        if ($hasTarget) {
            foreach ($legacyComposite as $legacyIndex) {
                mysqli_query($conn, 'ALTER TABLE `location_types` DROP INDEX `' . str_replace('`', '``', $legacyIndex) . '`');
            }
            return true;
        }

        if (count($legacyComposite) > 0) {
            mysqli_query(
                $conn,
                'ALTER TABLE `location_types` RENAME INDEX `'
                . str_replace('`', '``', $legacyComposite[0])
                . '` TO `'
                . $targetIndex
                . '`'
            );
            for ($i = 1, $legacyCount = count($legacyComposite); $i < $legacyCount; $i++) {
                mysqli_query(
                    $conn,
                    'ALTER TABLE `location_types` DROP INDEX `'
                    . str_replace('`', '``', $legacyComposite[$i])
                    . '`'
                );
            }
            return true;
        }

        return mysqli_query(
            $conn,
            'ALTER TABLE `location_types` ADD UNIQUE KEY `'
            . $targetIndex
            . '` (`company_id`, `name`)'
        ) === true;
    }
}

/**
 * Why: Legacy DBs may allow duplicate company names without a unique index on `company`.
 */
if (!function_exists('itm_ensure_companies_company_unique')) {
    function itm_ensure_companies_company_unique($conn) {
        static $checked = false;
        if ($checked || !($conn instanceof mysqli)) {
            return true;
        }
        $checked = true;

        $stmt = mysqli_prepare(
            $conn,
            'SELECT INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND NON_UNIQUE = 0
               AND INDEX_NAME <> ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX'
        );
        if (!$stmt) {
            return false;
        }
        $table = 'companies';
        $primary = 'PRIMARY';
        mysqli_stmt_bind_param($stmt, 'ss', $table, $primary);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $res = mysqli_stmt_get_result($stmt);
        $indexes = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $indexName = (string) ($row['INDEX_NAME'] ?? '');
            if ($indexName === '') {
                continue;
            }
            $indexes[$indexName][] = (string) ($row['COLUMN_NAME'] ?? '');
        }
        mysqli_stmt_close($stmt);

        foreach ($indexes as $columns) {
            if ($columns === ['company']) {
                return true;
            }
        }

        return mysqli_query(
            $conn,
            'ALTER TABLE `companies` ADD UNIQUE KEY `company` (`company`)'
        ) === true;
    }
}

/**
 * Why: Upload trees are created with 0775 for the app user; Apache must not execute scripts placed there.
 */
if (!function_exists('itm_upload_dir_htaccess_upload_policy')) {
    function itm_upload_dir_htaccess_upload_policy()
    {
        return <<<'HTACCESS'
# ITM upload hardening — do not remove (managed by itm_ensure_upload_directory)
Options -Indexes -ExecCGI -MultiViews
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_authz_core.c>
    <FilesMatch "(?i)\.(php|phtml|php3|php4|php5|phar|cgi|pl|py|asp|aspx|jsp|sh|exe|bat|cmd)$">
        Require all denied
    </FilesMatch>
</IfModule>
<IfModule !mod_authz_core.c>
    <FilesMatch "(?i)\.(php|phtml|php3|php4|php5|phar|cgi|pl|py|asp|aspx|jsp|sh|exe|bat|cmd)$">
        Order allow,deny
        Deny from all
    </FilesMatch>
</IfModule>
RemoveHandler .php .phtml .phar .cgi .pl .py
RemoveType .php .phtml .phar .cgi .pl .py
HTACCESS;
    }
}

if (!function_exists('itm_upload_dir_htaccess_deny_all_policy')) {
    function itm_upload_dir_htaccess_deny_all_policy()
    {
        return <<<'HTACCESS'
# ITM backup hardening — do not remove (managed by itm_ensure_upload_directory)
Options -Indexes -ExecCGI
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
HTACCESS;
    }
}

/**
 * Creates a directory (0775) and writes Apache rules so uploaded files are not executed.
 *
 * @param string $directory Absolute path with or without trailing separator
 * @param string $policy upload|deny_all
 * @return bool
 */
if (!function_exists('itm_ensure_upload_directory')) {
    function itm_ensure_upload_directory($directory, $policy = 'upload')
    {
        $directory = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $directory), DIRECTORY_SEPARATOR);
        if ($directory === '') {
            return false;
        }

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        $marker = 'ITM upload hardening';
        if ($policy === 'deny_all') {
            $marker = 'ITM backup hardening';
            $htaccessBody = itm_upload_dir_htaccess_deny_all_policy();
        } else {
            $htaccessBody = itm_upload_dir_htaccess_upload_policy();
        }

        $htaccessPath = $directory . DIRECTORY_SEPARATOR . '.htaccess';
        $shouldWriteHtaccess = true;
        if (is_file($htaccessPath)) {
            $existing = @file_get_contents($htaccessPath);
            $shouldWriteHtaccess = !is_string($existing) || strpos($existing, $marker) === false;
        }
        if ($shouldWriteHtaccess) {
            @file_put_contents($htaccessPath, $htaccessBody, LOCK_EX);
        }

        if ($policy !== 'deny_all') {
            $indexPath = $directory . DIRECTORY_SEPARATOR . 'index.html';
            if (!is_file($indexPath)) {
                @file_put_contents($indexPath, "<!DOCTYPE html><html><head><title></title></head><body></body></html>\n", LOCK_EX);
            }
        }

        return is_dir($directory) && is_file($htaccessPath);
    }
}
