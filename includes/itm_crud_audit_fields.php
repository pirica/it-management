<?php
/**
 * Shared audit-column + soft-delete helpers for flattened scaffold CRUD modules.
 *
 * Why: Keep list/view/form/delete contracts aligned across many duplicated module files
 * without inventing a shared HTML template.
 */

if (!function_exists('itm_crud_list_hidden_audit_fields')) {
    /**
     * Meta columns hidden on list/search (and list-driven form loops over $uiColumns).
     *
     * @return string[]
     */
    function itm_crud_list_hidden_audit_fields()
    {
        return [
            'deleted_by',
            'deleted_at',
            'created_by',
            'created_at',
            'updated_by',
            'updated_at',
        ];
    }
}

if (!function_exists('itm_crud_view_audit_fields')) {
    /**
     * Meta columns shown on view detail screens.
     *
     * @return string[]
     */
    function itm_crud_view_audit_fields()
    {
        return [
            'created_by',
            'created_at',
            'updated_by',
            'updated_at',
            'deleted_by',
            'deleted_at',
        ];
    }
}

if (!function_exists('itm_crud_form_hidden_audit_fields')) {
    /**
     * Meta columns rendered as hidden inputs on create/edit (server-stamped).
     *
     * @return string[]
     */
    function itm_crud_form_hidden_audit_fields()
    {
        return [
            'created_by',
            'created_at',
            'updated_by',
            'updated_at',
        ];
    }
}

if (!function_exists('itm_crud_is_list_hidden_audit_field')) {
    function itm_crud_is_list_hidden_audit_field($fieldName)
    {
        return in_array((string)$fieldName, itm_crud_list_hidden_audit_fields(), true);
    }
}

if (!function_exists('itm_crud_is_form_hidden_audit_field')) {
    function itm_crud_is_form_hidden_audit_field($fieldName)
    {
        return in_array((string)$fieldName, itm_crud_form_hidden_audit_fields(), true);
    }
}

if (!function_exists('itm_crud_is_view_audit_field')) {
    function itm_crud_is_view_audit_field($fieldName)
    {
        return in_array((string)$fieldName, itm_crud_view_audit_fields(), true);
    }
}

if (!function_exists('itm_crud_is_delete_form_hidden_field')) {
    function itm_crud_is_delete_form_hidden_field($fieldName)
    {
        return in_array((string)$fieldName, ['deleted_by', 'deleted_at'], true);
    }
}

if (!function_exists('itm_crud_filter_list_columns')) {
    /**
     * Keep business + active columns; drop list-hidden audit meta (and typically company_id via caller).
     *
     * @param array $columns DESCRIBE-style column arrays with Field key
     * @return array
     */
    function itm_crud_filter_list_columns(array $columns)
    {
        return array_values(array_filter($columns, function ($col) {
            $field = (string)($col['Field'] ?? '');
            return $field !== '' && !itm_crud_is_list_hidden_audit_field($field);
        }));
    }
}

if (!function_exists('itm_crud_append_not_deleted_predicate')) {
    /**
     * Append soft-delete filter to an existing WHERE clause string (may be empty).
     */
    function itm_crud_append_not_deleted_predicate($whereSql, $alias = '')
    {
        $whereSql = (string)$whereSql;
        $col = ($alias !== '' ? rtrim((string)$alias, '.') . '.' : '') . 'deleted_at';
        if (stripos($whereSql, 'deleted_at') !== false) {
            return $whereSql;
        }
        if (trim($whereSql) === '') {
            return ' WHERE ' . $col . ' IS NULL';
        }
        return $whereSql . ' AND ' . $col . ' IS NULL';
    }
}

if (!function_exists('itm_crud_stamp_create_audit')) {
    /**
     * Stamp create actor/time onto $data and matching $sqlValues when keys exist.
     *
     * @param array $data
     * @param array|null $sqlValues
     */
    function itm_crud_stamp_create_audit(array &$data, &$sqlValues = null)
    {
        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        global $conn;

        if ($employeeId > 0) {
            $data['created_by'] = $employeeId;
            if (is_array($sqlValues)) {
                $sqlValues['created_by'] = (string)$employeeId;
            }
        }
        $data['created_at'] = $now;
        if (is_array($sqlValues)) {
            $esc = (isset($conn) && $conn) ? mysqli_real_escape_string($conn, $now) : addslashes($now);
            $sqlValues['created_at'] = "'" . $esc . "'";
        }
        $data['deleted_by'] = null;
        $data['deleted_at'] = null;
        if (is_array($sqlValues)) {
            $sqlValues['deleted_by'] = 'NULL';
            $sqlValues['deleted_at'] = 'NULL';
        }
    }
}

if (!function_exists('itm_crud_stamp_update_audit')) {
    /**
     * Preserve created_* from form/hidden POST; stamp updated_by / updated_at.
     *
     * @param array $data
     * @param array|null $sqlValues
     * @param array|null $existingRow optional prior row (unused when POST already has created_*)
     */
    function itm_crud_stamp_update_audit(array &$data, &$sqlValues = null, $existingRow = null)
    {
        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        global $conn;

        if (is_array($existingRow)) {
            if ((!isset($data['created_by']) || $data['created_by'] === '' || $data['created_by'] === null)
                && array_key_exists('created_by', $existingRow)
            ) {
                $data['created_by'] = $existingRow['created_by'];
            }
            if (empty($data['created_at']) && !empty($existingRow['created_at'])) {
                $data['created_at'] = $existingRow['created_at'];
            }
        }

        if (is_array($sqlValues) && array_key_exists('created_by', $data)) {
            $sqlValues['created_by'] = ($data['created_by'] === null || $data['created_by'] === '')
                ? 'NULL'
                : (string)(int)$data['created_by'];
        }
        if (is_array($sqlValues) && !empty($data['created_at'])) {
            $esc = (isset($conn) && $conn)
                ? mysqli_real_escape_string($conn, (string)$data['created_at'])
                : addslashes((string)$data['created_at']);
            $sqlValues['created_at'] = "'" . $esc . "'";
        }

        if ($employeeId > 0) {
            $data['updated_by'] = $employeeId;
            if (is_array($sqlValues)) {
                $sqlValues['updated_by'] = (string)$employeeId;
            }
        }
        $data['updated_at'] = $now;
        if (is_array($sqlValues)) {
            $esc = (isset($conn) && $conn) ? mysqli_real_escape_string($conn, $now) : addslashes($now);
            $sqlValues['updated_at'] = "'" . $esc . "'";
        }
    }
}

if (!function_exists('itm_crud_build_soft_delete_sql')) {
    /**
     * Build soft-delete UPDATE for scaffold tables (server stamps deleted_by / deleted_at).
     */
    function itm_crud_build_soft_delete_sql($table, $whereSql, $employeeId)
    {
        $table = (string)$table;
        if ($table === '' || !function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($table)) {
            return '';
        }
        $emp = (int)$employeeId;
        // Why: Soft-delete mirrors inactive for status-driven modules; scaffold tables with active also flip to 0.
        $set = '`deleted_at`=NOW(), `deleted_by`=' . ($emp > 0 ? (string)$emp : 'NULL') . ', `active`=0';
        $whereSql = trim((string)$whereSql);
        if ($whereSql === '') {
            $whereSql = ' WHERE deleted_at IS NULL';
        } elseif (stripos($whereSql, 'deleted_at') === false) {
            $whereSql .= ' AND deleted_at IS NULL';
        }
        return 'UPDATE `' . $table . '` SET ' . $set . $whereSql;
    }
}

if (!function_exists('itm_crud_render_form_hidden_active_input')) {
    /**
     * Emit hidden active=1 for status-driven modules (business Active/Inactive lives on *_statuses FKs).
     */
    function itm_crud_render_form_hidden_active_input()
    {
        echo '<input type="hidden" name="active" value="1">' . "\n";
    }
}

if (!function_exists('itm_crud_force_active_live')) {
    /**
     * Server-stamp row active=1 on create/edit so soft-delete is the only path to active=0.
     *
     * @param array $data
     * @param array|null $sqlValues
     */
    function itm_crud_force_active_live(array &$data, &$sqlValues = null)
    {
        $data['active'] = 1;
        if (is_array($sqlValues)) {
            $sqlValues['active'] = '1';
        }
    }
}

if (!function_exists('itm_crud_is_status_driven_row_active_field')) {
    /**
     * Row active on status-driven modules is soft-delete only — hide from list/view UI.
     */
    function itm_crud_is_status_driven_row_active_field($fieldName)
    {
        return (string)$fieldName === 'active';
    }
}

if (!function_exists('itm_crud_render_status_label_badge')) {
    /**
     * Badge for *_statuses labels on list/view (not the row active boolean).
     *
     * @param string $label Status name
     * @param string $color Optional #RRGGBB from status lookup
     */
    function itm_crud_render_status_label_badge($label, $color = '')
    {
        $label = trim((string)$label);
        if ($label === '') {
            return '—';
        }
        $color = trim((string)$color);
        if ($color !== '' && preg_match('/^#[A-Fa-f0-9]{6}$/', $color)) {
            $safeColor = strtoupper($color);
            return '<span class="badge" title="' . sanitize($safeColor) . '" style="background:' . sanitize($safeColor) . ';color:#fff;">'
                . sanitize($label) . '</span>';
        }
        $lower = strtolower($label);
        $class = 'badge-warning';
        if (strpos($lower, 'active') !== false || strpos($lower, 'open') !== false || strpos($lower, 'online') !== false) {
            $class = 'badge-success';
        } elseif (
            strpos($lower, 'inactive') !== false
            || strpos($lower, 'closed') !== false
            || strpos($lower, 'terminat') !== false
            || strpos($lower, 'offline') !== false
            || strpos($lower, 'fail') !== false
        ) {
            $class = 'badge-danger';
        }
        return '<span class="badge ' . $class . '">' . sanitize($label) . '</span>';
    }
}

if (!function_exists('itm_crud_render_audit_cell_value')) {
    /**
     * Render audit meta for list/view cells. Returns null when $field is not an audit meta column.
     */
    function itm_crud_render_audit_cell_value($conn, $companyId, $field, $value)
    {
        $field = (string)$field;
        if ($field === 'created_by' || $field === 'updated_by' || $field === 'deleted_by') {
            if ($value === null || $value === '') {
                return '';
            }
            if (!is_object($conn) || !function_exists('itm_user_label_by_id_for_company')) {
                return sanitize((string)$value);
            }
            $label = itm_user_label_by_id_for_company($conn, (int)$companyId, $value);
            return sanitize($label !== '' ? $label : (string)$value);
        }
        if ($field === 'created_at' || $field === 'updated_at' || $field === 'deleted_at') {
            if (function_exists('itm_format_audit_timestamp_display')) {
                return sanitize(itm_format_audit_timestamp_display($value));
            }
            return sanitize((string)($value ?? ''));
        }
        return null;
    }
}

if (!function_exists('itm_crud_render_form_hidden_audit_inputs')) {
    /**
     * Emit hidden inputs for create/edit audit stamps.
     *
     * @param array $data current form row
     * @param string $crudAction create|edit
     */
    function itm_crud_render_form_hidden_audit_inputs(array $data, $crudAction = 'create')
    {
        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $isCreate = ((string)$crudAction === 'create');

        $createdBy = $isCreate
            ? ($employeeId > 0 ? (string)$employeeId : (string)($data['created_by'] ?? ''))
            : (string)($data['created_by'] ?? '');
        $createdAt = $isCreate
            ? $now
            : (string)($data['created_at'] ?? '');
        $updatedBy = $employeeId > 0 ? (string)$employeeId : (string)($data['updated_by'] ?? '');
        $updatedAt = $now;

        echo '<input type="hidden" name="created_by" value="' . sanitize($createdBy) . '">' . "\n";
        echo '<input type="hidden" name="created_at" value="' . sanitize($createdAt) . '">' . "\n";
        echo '<input type="hidden" name="updated_by" value="' . sanitize($updatedBy) . '">' . "\n";
        echo '<input type="hidden" name="updated_at" value="' . sanitize($updatedAt) . '">' . "\n";
    }
}

if (!function_exists('itm_crud_render_delete_hidden_audit_inputs')) {
    /**
     * Emit deleted_by / deleted_at hidden inputs on delete forms (server still re-stamps).
     */
    function itm_crud_render_delete_hidden_audit_inputs()
    {
        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        echo '<input type="hidden" name="deleted_by" value="' . (int)$employeeId . '">' . "\n";
        echo '<input type="hidden" name="deleted_at" value="' . sanitize($now) . '">' . "\n";
    }
}

if (!function_exists('itm_crud_load_soft_delete_module_slugs')) {
    /**
     * Read docs/list_soft-delete.txt (one slug per line; # comments ignored).
     *
     * @return string[]
     */
    function itm_crud_load_soft_delete_module_slugs($rootPath = null)
    {
        if ($rootPath === null) {
            $rootPath = defined('ROOT_PATH') ? ROOT_PATH : (dirname(__DIR__) . '/');
        }
        $path = rtrim((string)$rootPath, '/\\') . '/docs/list_soft-delete.txt';
        if (!is_readable($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }
        $slugs = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (preg_match('/^[a-z0-9_]+$/', $line)) {
                $slugs[$line] = $line;
            }
        }
        return array_values($slugs);
    }
}
