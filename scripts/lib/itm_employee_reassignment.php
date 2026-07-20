<?php
/**
 * Employee reassignment plan helpers (generate_reassignment.php).
 *
 * Why: Centralise skip rules and column discovery so the report explains what to
 * reassign, skip, or handle manually before deleting an employee.
 */

if (!function_exists('itm_employee_reassignment_skip_employee_id_tables')) {
    /**
     * Tables where employee_id must not be bulk-reassigned to another user.
     *
     * @return array<string, string> table => reason
     */
    function itm_employee_reassignment_skip_employee_id_tables(): array
    {
        return [
            'audit_logs' => 'Historical actor — admin delete sets employee_id NULL; do not reassign.',
            'attempts' => 'Login attempts — deleted with the employee.',
            'password_entries' => 'Private vault — deleted with the employee.',
            'password_folders' => 'Private vault — deleted with the employee.',
            'bookmarks' => 'Private bookmarks — deleted with the employee.',
            'bookmark_folders' => 'Private bookmark folders — deleted with the employee.',
            'private_contacts' => 'Private address book — deleted with the employee.',
            'employee_companies' => 'Tenant access grants — delete or re-grant manually for the target user.',
            'ui_configuration' => 'Per-user UI/API settings — delete or copy manually.',
            'employee_system_access' => 'System access rows — usually deleted; recreate for target if needed.',
            'employees' => 'Not updated via employee_id on this table.',
        ];
    }
}

if (!function_exists('itm_employee_reassignment_related_columns')) {
    /**
     * Non-employee_id columns that may reference employees (manual / optional reassignment).
     *
     * @return array<string, array<int, array{column:string,action:string,reason:string}>>
     */
    function itm_employee_reassignment_related_columns(): array
    {
        return [
            'alerts' => [
                ['column' => 'assigned_to_employee_id', 'action' => 'optional_reassign', 'reason' => 'Assign open alerts to the target user, or NULL on admin delete.'],
                ['column' => 'created_by', 'action' => 'optional_null', 'reason' => 'Creator stamp — often left NULL on delete.'],
            ],
            'equipment' => [
                ['column' => 'assigned_to_employee_id', 'action' => 'optional_null', 'reason' => 'Equipment assignee — NULL on admin delete.'],
            ],
            'events' => [
                ['column' => 'assigned_to_employee_id', 'action' => 'optional_reassign', 'reason' => 'Reassign or NULL before delete.'],
            ],
            'tickets' => [
                ['column' => 'assigned_to_employee_id', 'action' => 'optional_null', 'reason' => 'Ticket assignee — NULL on admin delete.'],
                ['column' => 'created_by_employee_id', 'action' => 'optional_reassign', 'reason' => 'Admin delete reassigns to another employee in company or deletes tickets.'],
            ],
            'todo' => [
                ['column' => 'assigned_to_employee_id', 'action' => 'optional_reassign', 'reason' => 'CSV assignee list — review before bulk update.'],
            ],
            'employee_assignment_history' => [
                ['column' => 'assigned_by_employee_id', 'action' => 'optional_null', 'reason' => 'Actor FK — NULL on admin delete.'],
                ['column' => 'received_by_employee_id', 'action' => 'optional_null', 'reason' => 'Actor FK — NULL on admin delete.'],
            ],
            'employee_companies' => [
                ['column' => 'granted_by_employee_id', 'action' => 'optional_null', 'reason' => 'Grant actor — NULL on admin delete.'],
            ],
            'registration_invitations' => [
                ['column' => 'invited_by_employee_id', 'action' => 'optional_null', 'reason' => 'Inviter — NULL on admin delete.'],
            ],
            'inventory_items' => [
                ['column' => 'last_employee_id', 'action' => 'optional_null', 'reason' => 'Last holder stamp — NULL on admin delete.'],
            ],
            'explorer' => [
                ['column' => 'employee_id', 'action' => 'skip', 'reason' => 'Explorer favourites — NULL on admin delete (not ownership transfer).'],
            ],
            'request_password' => [
                ['column' => 'requested_by_employee_id', 'action' => 'optional_reassign', 'reason' => 'Workflow actor — review before reassign.'],
            ],
            'todo_categories' => [
                ['column' => 'cat_from_employee_id', 'action' => 'skip', 'reason' => 'Personal todo categories — deleted with employee.'],
            ],
        ];
    }
}

if (!function_exists('itm_employee_reassignment_load_employee')) {
    /**
     * @return array<string, mixed>|null
     */
    function itm_employee_reassignment_load_employee(mysqli $conn, int $employeeId): ?array
    {
        if ($employeeId <= 0) {
            return null;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, company_id, username, first_name, last_name, work_email, deleted_at
             FROM employees WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_employee_reassignment_table_has_column')) {
    function itm_employee_reassignment_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        $safeTable = mysqli_real_escape_string($conn, $table);
        $safeColumn = mysqli_real_escape_string($conn, $column);
        $res = mysqli_query($conn, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
        return (bool)($res && mysqli_num_rows($res) > 0);
    }
}

if (!function_exists('itm_employee_reassignment_count_rows')) {
    function itm_employee_reassignment_count_rows(
        mysqli $conn,
        string $table,
        string $column,
        int $employeeId,
        ?int $companyId = null
    ): int {
        $safeTable = mysqli_real_escape_string($conn, $table);
        $sql = "SELECT COUNT(*) AS c FROM `$safeTable` WHERE `$column` = $employeeId";
        if ($companyId !== null && itm_employee_reassignment_table_has_column($conn, $table, 'company_id')) {
            $sql .= ' AND company_id = ' . (int)$companyId;
        }
        $res = mysqli_query($conn, $sql);
        $row = ($res) ? mysqli_fetch_assoc($res) : null;

        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('itm_employee_reassignment_build_update_sql')) {
    function itm_employee_reassignment_build_update_sql(
        string $table,
        string $column,
        int $fromId,
        int $toId,
        ?int $companyId = null,
        bool $hasCompanyId = false
    ): string {
        $targetToken = $toId > 0 ? (string)$toId : '<TARGET_EMPLOYEE_ID>';
        $sql = "UPDATE `$table` SET `$column` = $targetToken WHERE `$column` = $fromId";
        if ($companyId !== null && $hasCompanyId) {
            $sql .= ' AND company_id = ' . (int)$companyId;
        }

        return $sql . ';';
    }
}

if (!function_exists('itm_employee_reassignment_list_employees')) {
    /**
     * @return array<int, array{id:int,company_id:int,label:string}>
     */
    function itm_employee_reassignment_list_employees(mysqli $conn, int $companyId = 0, int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));
        $sql = 'SELECT id, company_id, username, first_name, last_name
                FROM employees
                WHERE deleted_at IS NULL';
        if ($companyId > 0) {
            $sql .= ' AND company_id = ' . (int)$companyId;
        }
        $sql .= ' ORDER BY company_id ASC, id ASC LIMIT ' . $limit;

        $rows = [];
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $name = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
            $username = (string)($row['username'] ?? '');
            $label = '#' . (int)$row['id'] . ' · co ' . (int)$row['company_id'];
            if ($username !== '') {
                $label .= ' · ' . $username;
            }
            if ($name !== '') {
                $label .= ' · ' . $name;
            }
            $rows[] = [
                'id' => (int)$row['id'],
                'company_id' => (int)$row['company_id'],
                'label' => $label,
            ];
        }

        return $rows;
    }
}

if (!function_exists('itm_employee_reassignment_fetch_inbound_fks')) {
    /**
     * @return array<int, array{table:string,constraint:string,delete_rule:string,column:string}>
     */
    function itm_employee_reassignment_fetch_inbound_fks(mysqli $conn): array
    {
        $rows = [];
        $sql = "
            SELECT ku.TABLE_NAME, ku.COLUMN_NAME, rc.CONSTRAINT_NAME, rc.DELETE_RULE
            FROM information_schema.REFERENTIAL_CONSTRAINTS rc
            JOIN information_schema.KEY_COLUMN_USAGE ku
              ON rc.CONSTRAINT_SCHEMA = ku.CONSTRAINT_SCHEMA
             AND rc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME
            WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
              AND rc.REFERENCED_TABLE_NAME = 'employees'
            ORDER BY ku.TABLE_NAME, ku.COLUMN_NAME
        ";
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = [
                'table' => (string)$row['TABLE_NAME'],
                'column' => (string)$row['COLUMN_NAME'],
                'constraint' => (string)$row['CONSTRAINT_NAME'],
                'delete_rule' => (string)$row['DELETE_RULE'],
            ];
        }

        return $rows;
    }
}

if (!function_exists('itm_employee_reassignment_build_plan')) {
    /**
     * @return array{
     *   ok:bool,
     *   message:string,
     *   from:?array<string,mixed>,
     *   to:?array<string,mixed>,
     *   reassign:array<int,array{table:string,row_count:int,sql:string}>,
     *   skip:array<int,array{table:string,row_count:int,reason:string}>,
     *   related:array<int,array{table:string,column:string,row_count:int,action:string,reason:string,sql:string}>,
     *   inbound_fks:array<int,array{table:string,column:string,constraint:string,delete_rule:string,row_count:int}>
     * }
     */
    function itm_employee_reassignment_build_plan(mysqli $conn, int $fromId, int $toId): array
    {
        $from = itm_employee_reassignment_load_employee($conn, $fromId);
        $previewOnly = ($toId <= 0);
        $to = $previewOnly ? null : itm_employee_reassignment_load_employee($conn, $toId);

        if ($from === null) {
            return ['ok' => false, 'message' => 'Source employee not found.', 'from' => null, 'to' => $to, 'reassign' => [], 'skip' => [], 'related' => [], 'inbound_fks' => [], 'preview_only' => $previewOnly];
        }
        if (!$previewOnly && $to === null) {
            return ['ok' => false, 'message' => 'Target employee not found.', 'from' => $from, 'to' => null, 'reassign' => [], 'skip' => [], 'related' => [], 'inbound_fks' => [], 'preview_only' => false];
        }
        if (!$previewOnly && $fromId === $toId) {
            return ['ok' => false, 'message' => 'Source and target must be different employees.', 'from' => $from, 'to' => $to, 'reassign' => [], 'skip' => [], 'related' => [], 'inbound_fks' => [], 'preview_only' => false];
        }

        $fromCompanyId = (int)$from['company_id'];
        if (!$previewOnly) {
            $toCompanyId = (int)$to['company_id'];
            if ($fromCompanyId !== $toCompanyId) {
                return [
                    'ok' => false,
                    'message' => 'Source and target must belong to the same company (source company_id='
                        . $fromCompanyId . ', target company_id=' . $toCompanyId . ').',
                    'from' => $from,
                    'to' => $to,
                    'reassign' => [],
                    'skip' => [],
                    'related' => [],
                    'inbound_fks' => [],
                    'preview_only' => false,
                ];
            }
        }

        if (!empty($from['deleted_at'])) {
            return ['ok' => false, 'message' => 'Source employee is soft-deleted.', 'from' => $from, 'to' => $to, 'reassign' => [], 'skip' => [], 'related' => [], 'inbound_fks' => [], 'preview_only' => $previewOnly];
        }

        $skipMap = itm_employee_reassignment_skip_employee_id_tables();
        $reassign = [];
        $skip = [];

        $res = mysqli_query($conn, 'SHOW TABLES');
        while ($res && ($row = mysqli_fetch_row($res))) {
            $table = (string)$row[0];
            if (!itm_employee_reassignment_table_has_column($conn, $table, 'employee_id')) {
                continue;
            }

            $hasCompany = itm_employee_reassignment_table_has_column($conn, $table, 'company_id');
            $count = itm_employee_reassignment_count_rows($conn, $table, 'employee_id', $fromId, $fromCompanyId);

            if (isset($skipMap[$table])) {
                $skip[] = [
                    'table' => $table,
                    'row_count' => $count,
                    'reason' => $skipMap[$table],
                ];
                continue;
            }

            $reassign[] = [
                'table' => $table,
                'row_count' => $count,
                'sql' => itm_employee_reassignment_build_update_sql(
                    $table,
                    'employee_id',
                    $fromId,
                    $toId,
                    $fromCompanyId,
                    $hasCompany
                ),
            ];
        }

        usort($reassign, static function ($a, $b) {
            return strcmp($a['table'], $b['table']);
        });
        usort($skip, static function ($a, $b) {
            return strcmp($a['table'], $b['table']);
        });

        $related = [];
        foreach (itm_employee_reassignment_related_columns() as $table => $columns) {
            if (!itm_employee_reassignment_table_has_column($conn, $table, 'employee_id')
                && $table !== 'explorer') {
                // still allow tables listed only for non-employee_id columns
            }
            foreach ($columns as $meta) {
                $column = (string)$meta['column'];
                if (!itm_employee_reassignment_table_has_column($conn, $table, $column)) {
                    continue;
                }
                $hasCompany = itm_employee_reassignment_table_has_column($conn, $table, 'company_id');
                $count = itm_employee_reassignment_count_rows($conn, $table, $column, $fromId, $fromCompanyId);
                $action = (string)$meta['action'];
                $sql = '';
                if ($action === 'optional_reassign' && $count > 0) {
                    $sql = itm_employee_reassignment_build_update_sql(
                        $table,
                        $column,
                        $fromId,
                        $toId,
                        $fromCompanyId,
                        $hasCompany
                    );
                } elseif ($action === 'optional_null' && $count > 0) {
                    $sql = "UPDATE `$table` SET `$column` = NULL WHERE `$column` = $fromId";
                    if ($hasCompany) {
                        $sql .= ' AND company_id = ' . $fromCompanyId;
                    }
                    $sql .= ';';
                }

                $related[] = [
                    'table' => $table,
                    'column' => $column,
                    'row_count' => $count,
                    'action' => $action,
                    'reason' => (string)$meta['reason'],
                    'sql' => $sql,
                ];
            }
        }

        $inboundFks = [];
        foreach (itm_employee_reassignment_fetch_inbound_fks($conn) as $fk) {
            $count = itm_employee_reassignment_count_rows(
                $conn,
                $fk['table'],
                $fk['column'],
                $fromId,
                itm_employee_reassignment_table_has_column($conn, $fk['table'], 'company_id') ? $fromCompanyId : null
            );
            $fk['row_count'] = $count;
            $inboundFks[] = $fk;
        }

        return [
            'ok' => true,
            'message' => $previewOnly
                ? 'Preview for source employee only — set a target id to build apply-ready SQL.'
                : 'Plan built — review sections below before apply.',
            'from' => $from,
            'to' => $to,
            'reassign' => $reassign,
            'skip' => $skip,
            'related' => $related,
            'inbound_fks' => $inboundFks,
            'preview_only' => $previewOnly,
        ];
    }
}

if (!function_exists('itm_employee_reassignment_apply_plan')) {
    /**
     * @param array<int, array{table:string,row_count:int,sql:string}> $reassignRows
     * @return array{ok:bool,message:string,errors:array<int,string>,updated:array<int,string>}
     */
    function itm_employee_reassignment_apply_plan(mysqli $conn, array $reassignRows): array
    {
        $errors = [];
        $updated = [];

        mysqli_begin_transaction($conn);

        foreach ($reassignRows as $row) {
            if ((int)$row['row_count'] <= 0) {
                continue;
            }
            $sql = rtrim((string)$row['sql'], ';');
            if ($sql === '' || !mysqli_query($conn, $sql)) {
                $errors[] = $row['table'] . ': ' . mysqli_error($conn);
                break;
            }
            if (mysqli_affected_rows($conn) > 0) {
                $updated[] = (string)$row['table'];
            }
        }

        if ($errors !== []) {
            mysqli_rollback($conn);
            return ['ok' => false, 'message' => 'Apply aborted — transaction rolled back.', 'errors' => $errors, 'updated' => []];
        }

        mysqli_commit($conn);

        return [
            'ok' => true,
            'message' => 'employee_id reassignment applied. Review related-column SQL manually; then delete the source employee.',
            'errors' => [],
            'updated' => $updated,
        ];
    }
}
