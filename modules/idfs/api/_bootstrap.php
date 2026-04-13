<?php
require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);

function idf_read_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function idf_require_csrf(array $data): void {
    $token = (string)($data['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    if (!itm_validate_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

function idf_ok(array $payload = []): void {
    echo json_encode(array_merge(['ok' => true], $payload));
    exit;
}

function idf_fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function idf_escape(mysqli $conn, ?string $s): string {
    return mysqli_real_escape_string($conn, (string)($s ?? ''));
}

/**
 * Why: Some deployments still have old status column shapes that break dynamic
 * status additions from the IDF UI.
 */
function idf_ensure_status_schema(mysqli $conn): void {
    static $alreadyChecked = false;
    if ($alreadyChecked) {
        return;
    }
    $alreadyChecked = true;

    $dbResult = mysqli_query($conn, 'SELECT DATABASE() AS db_name');
    $dbRow = $dbResult ? mysqli_fetch_assoc($dbResult) : null;
    $databaseName = (string)($dbRow['db_name'] ?? '');
    if ($databaseName === '') {
        return;
    }
    $databaseNameEscaped = mysqli_real_escape_string($conn, $databaseName);

    $readStatusColumn = static function (string $tableName) use ($conn, $databaseNameEscaped): ?array {
        $tableNameEscaped = mysqli_real_escape_string($conn, $tableName);
        $sql = "SELECT COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = '{$databaseNameEscaped}'
                  AND TABLE_NAME = '{$tableNameEscaped}'
                  AND COLUMN_NAME = 'status'
                LIMIT 1";
        $columnRes = mysqli_query($conn, $sql);
        return $columnRes ? mysqli_fetch_assoc($columnRes) : null;
    };

    $switchStatusColumn = $readStatusColumn('switch_status');
    if ($switchStatusColumn) {
        $switchType = strtolower((string)($switchStatusColumn['COLUMN_TYPE'] ?? ''));
        $switchCharLength = isset($switchStatusColumn['CHARACTER_MAXIMUM_LENGTH']) ? (int)$switchStatusColumn['CHARACTER_MAXIMUM_LENGTH'] : 0;
        $switchNeedsMigration = strncmp($switchType, 'enum(', 5) === 0 || ($switchCharLength > 0 && $switchCharLength < 50);
        if ($switchNeedsMigration) {
            mysqli_query(
                $conn,
                "ALTER TABLE `switch_status`
                 MODIFY `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unknown'"
            );
        }

        // Why: Support visual port status with color mapping.
        $colorCheckRes = mysqli_query($conn, "SHOW COLUMNS FROM `switch_status` LIKE 'color'");
        if ($colorCheckRes && mysqli_num_rows($colorCheckRes) === 0) {
            mysqli_query($conn, "ALTER TABLE `switch_status` ADD COLUMN `color` varchar(7) DEFAULT NULL AFTER `status`");

            // Seed default colors for standard statuses
            $colors = [
                'Up' => '#007bff',
                'Down' => '#dc3545',
                'Disabled' => '#6c757d',
                'Err-Disabled' => '#e83e8c',
                'Faulty' => '#fd7e14',
                'Free' => '#28a745',
                'Reserved' => '#ffc107',
                'Testing' => '#17a2b8',
                'Unknown' => '#adb5bd'
            ];
            foreach ($colors as $status => $hex) {
                $statusEsc = mysqli_real_escape_string($conn, $status);
                $hexEsc = mysqli_real_escape_string($conn, $hex);
                mysqli_query($conn, "UPDATE `switch_status` SET `color` = '{$hexEsc}' WHERE LOWER(`status`) = LOWER('{$statusEsc}') AND `color` IS NULL");
            }
        }
    }

    $idfPortsColumn = $readStatusColumn('idf_ports');
    $idfType = strtolower((string)($idfPortsColumn['COLUMN_TYPE'] ?? ''));
    if ($idfPortsColumn && !preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $idfType)) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `status_new` int DEFAULT NULL");
        mysqli_query(
            $conn,
            "UPDATE idf_ports p
             LEFT JOIN switch_status ss
               ON ss.company_id = p.company_id
              AND LOWER(ss.status) = LOWER(CAST(p.status AS CHAR))
             SET p.status_new = ss.id"
        );
        mysqli_query(
            $conn,
            "UPDATE idf_ports p
             LEFT JOIN switch_status ss
               ON ss.company_id = p.company_id
              AND LOWER(ss.status) = 'unknown'
             SET p.status_new = ss.id
             WHERE p.status_new IS NULL"
        );
        mysqli_query(
            $conn,
            "UPDATE idf_ports p
             JOIN (
               SELECT company_id, MIN(id) AS any_status_id
               FROM switch_status
               GROUP BY company_id
             ) ss ON ss.company_id = p.company_id
             SET p.status_new = ss.any_status_id
             WHERE p.status_new IS NULL"
        );
        mysqli_query($conn, "ALTER TABLE `idf_ports` DROP COLUMN `status`");
        mysqli_query($conn, "ALTER TABLE `idf_ports` CHANGE COLUMN `status_new` `status` int NOT NULL");
    }

    $statusIndexExists = false;
    $resStatusIndex = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = 'idf_ports_status_idx'");
    if ($resStatusIndex && mysqli_fetch_assoc($resStatusIndex)) {
        $statusIndexExists = true;
    }
    if (!$statusIndexExists) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD KEY `idf_ports_status_idx` (`status`)");
    }

    $statusFkExists = false;
    $resStatusFk = mysqli_query(
        $conn,
        "SELECT CONSTRAINT_NAME
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = '{$databaseNameEscaped}'
           AND TABLE_NAME = 'idf_ports'
           AND CONSTRAINT_NAME = 'idf_ports_ibfk_status'
         LIMIT 1"
    );
    if ($resStatusFk && mysqli_fetch_assoc($resStatusFk)) {
        $statusFkExists = true;
    }
    if (!$statusFkExists) {
        mysqli_query(
            $conn,
            "ALTER TABLE `idf_ports`
             ADD CONSTRAINT `idf_ports_ibfk_status`
             FOREIGN KEY (`status`) REFERENCES `switch_status` (`id`)"
        );
    }

    $readPortTypeColumn = static function () use ($conn, $databaseNameEscaped): ?array {
        $sql = "SELECT COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = '{$databaseNameEscaped}'
                  AND TABLE_NAME = 'idf_ports'
                  AND COLUMN_NAME = 'port_type'
                LIMIT 1";
        $res = mysqli_query($conn, $sql);
        return $res ? mysqli_fetch_assoc($res) : null;
    };

    $portTypeColumn = $readPortTypeColumn();
    $portTypeType = strtolower((string)($portTypeColumn['COLUMN_TYPE'] ?? ''));
    if ($portTypeColumn && !preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $portTypeType)) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `port_type_new` int DEFAULT NULL");
        mysqli_query(
            $conn,
            "UPDATE idf_ports p
             LEFT JOIN switch_port_types spt
               ON spt.company_id = p.company_id
              AND LOWER(spt.type) = LOWER(CAST(p.port_type AS CHAR))
             SET p.port_type_new = spt.id"
        );
        mysqli_query(
            $conn,
            "UPDATE idf_ports p
             LEFT JOIN switch_port_types spt
               ON spt.company_id = p.company_id
              AND LOWER(spt.type) = 'rj45'
             SET p.port_type_new = spt.id
             WHERE p.port_type_new IS NULL"
        );
        mysqli_query(
            $conn,
            "UPDATE idf_ports p
             JOIN (
               SELECT company_id, MIN(id) AS any_port_type_id
               FROM switch_port_types
               GROUP BY company_id
             ) spt ON spt.company_id = p.company_id
             SET p.port_type_new = spt.any_port_type_id
             WHERE p.port_type_new IS NULL"
        );
        mysqli_query($conn, "ALTER TABLE `idf_ports` DROP COLUMN `port_type`");
        mysqli_query($conn, "ALTER TABLE `idf_ports` CHANGE COLUMN `port_type_new` `port_type` int NOT NULL");
    }

    $portTypeIndexExists = false;
    $resPortTypeIndex = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = 'idf_ports_port_type_idx'");
    if ($resPortTypeIndex && mysqli_fetch_assoc($resPortTypeIndex)) {
        $portTypeIndexExists = true;
    }
    if (!$portTypeIndexExists) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD KEY `idf_ports_port_type_idx` (`port_type`)");
    }

    $portTypeFkExists = false;
    $resPortTypeFk = mysqli_query(
        $conn,
        "SELECT CONSTRAINT_NAME
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = '{$databaseNameEscaped}'
           AND TABLE_NAME = 'idf_ports'
           AND CONSTRAINT_NAME = 'idf_ports_ibfk_port_type'
         LIMIT 1"
    );
    if ($resPortTypeFk && mysqli_fetch_assoc($resPortTypeFk)) {
        $portTypeFkExists = true;
    }
    if (!$portTypeFkExists) {
        mysqli_query(
            $conn,
            "ALTER TABLE `idf_ports`
             ADD CONSTRAINT `idf_ports_ibfk_port_type`
             FOREIGN KEY (`port_type`) REFERENCES `switch_port_types` (`id`)"
        );
    }

    // Why: Support for Vertical/Horizontal port numbering layouts in idf_positions.
    $layoutColRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_positions` LIKE 'switch_port_numbering_layout_id'");
    if ($layoutColRes && mysqli_num_rows($layoutColRes) === 0) {
        mysqli_query($conn, "ALTER TABLE `idf_positions` ADD COLUMN `switch_port_numbering_layout_id` int DEFAULT NULL AFTER `port_count` ");
        mysqli_query($conn, "ALTER TABLE `idf_positions` ADD KEY `switch_port_numbering_layout_id` (`switch_port_numbering_layout_id`)");

        $hasLayoutFk = false;
        $resLayoutFk = mysqli_query(
            $conn,
            "SELECT CONSTRAINT_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = '{$databaseNameEscaped}'
               AND TABLE_NAME = 'idf_positions'
               AND CONSTRAINT_NAME = 'idf_positions_ibfk_layout'
             LIMIT 1"
        );
        if ($resLayoutFk && mysqli_fetch_assoc($resLayoutFk)) {
            $hasLayoutFk = true;
        }
        if (!$hasLayoutFk) {
            mysqli_query(
                $conn,
                "ALTER TABLE `idf_positions`
                 ADD CONSTRAINT `idf_positions_ibfk_layout`
                 FOREIGN KEY (`switch_port_numbering_layout_id`) REFERENCES `switch_port_numbering_layout` (`id`)
                 ON DELETE SET NULL"
            );
        }
    }

    // Why: Support for Vertical/Horizontal port numbering layouts in equipment.
    $eqLayoutColRes = mysqli_query($conn, "SHOW COLUMNS FROM `equipment` LIKE 'switch_port_numbering_layout_id'");
    if ($eqLayoutColRes && mysqli_num_rows($eqLayoutColRes) === 0) {
        mysqli_query($conn, "ALTER TABLE `equipment` ADD COLUMN `switch_port_numbering_layout_id` int DEFAULT NULL AFTER `switch_rj45_id` ");
        mysqli_query($conn, "ALTER TABLE `equipment` ADD KEY `switch_port_numbering_layout_id` (`switch_port_numbering_layout_id`)");

        $hasEqLayoutFk = false;
        $resEqLayoutFk = mysqli_query(
            $conn,
            "SELECT CONSTRAINT_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = '{$databaseNameEscaped}'
               AND TABLE_NAME = 'equipment'
               AND CONSTRAINT_NAME = 'equipment_ibfk_layout'
             LIMIT 1"
        );
        if ($resEqLayoutFk && mysqli_fetch_assoc($resEqLayoutFk)) {
            $hasEqLayoutFk = true;
        }
        if (!$hasEqLayoutFk) {
            mysqli_query(
                $conn,
                "ALTER TABLE `equipment`
                 ADD CONSTRAINT `equipment_ibfk_layout`
                 FOREIGN KEY (`switch_port_numbering_layout_id`) REFERENCES `switch_port_numbering_layout` (`id`)
                 ON DELETE SET NULL"
            );
        }
    }

    $ensureMappedIntColumn = static function (
        string $columnName,
        string $refTable,
        string $refMatchSql,
        string $indexName,
        string $fkName
    ) use ($conn, $databaseNameEscaped): void {
        $columnSql = "SELECT COLUMN_TYPE
                      FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = '{$databaseNameEscaped}'
                        AND TABLE_NAME = 'idf_ports'
                        AND COLUMN_NAME = '" . mysqli_real_escape_string($conn, $columnName) . "'
                      LIMIT 1";
        $columnRes = mysqli_query($conn, $columnSql);
        $columnRow = $columnRes ? mysqli_fetch_assoc($columnRes) : null;
        $columnType = strtolower((string)($columnRow['COLUMN_TYPE'] ?? ''));
        if (!$columnRow) {
            return;
        }

        if (!preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $columnType)) {
            $tmpColumn = $columnName . '_new';
            mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `{$tmpColumn}` int DEFAULT NULL");
            mysqli_query(
                $conn,
                "UPDATE idf_ports p
                 LEFT JOIN {$refTable} ref
                   ON ref.company_id = p.company_id
                  AND ({$refMatchSql})
                 SET p.`{$tmpColumn}` = ref.id"
            );
            mysqli_query($conn, "ALTER TABLE `idf_ports` DROP COLUMN `{$columnName}`");
            mysqli_query($conn, "ALTER TABLE `idf_ports` CHANGE COLUMN `{$tmpColumn}` `{$columnName}` int DEFAULT NULL");
        }

        $hasIndex = false;
        $indexRes = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = '{$indexName}'");
        if ($indexRes && mysqli_fetch_assoc($indexRes)) {
            $hasIndex = true;
        }
        if (!$hasIndex) {
            mysqli_query($conn, "ALTER TABLE `idf_ports` ADD KEY `{$indexName}` (`{$columnName}`)");
        }

        $hasFk = false;
        $fkRes = mysqli_query(
            $conn,
            "SELECT CONSTRAINT_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = '{$databaseNameEscaped}'
               AND TABLE_NAME = 'idf_ports'
               AND CONSTRAINT_NAME = '{$fkName}'
             LIMIT 1"
        );
        if ($fkRes && mysqli_fetch_assoc($fkRes)) {
            $hasFk = true;
        }
        if (!$hasFk) {
            mysqli_query(
                $conn,
                "ALTER TABLE `idf_ports`
                 ADD CONSTRAINT `{$fkName}`
                 FOREIGN KEY (`{$columnName}`) REFERENCES `{$refTable}` (`id`)
                 ON DELETE SET NULL"
            );
        }
    };

    $ensureMappedIntColumn(
        'vlan',
        'vlans',
        "LOWER(ref.vlan_number) = LOWER(CAST(p.vlan AS CHAR))
         OR LOWER(ref.vlan_name) = LOWER(CAST(p.vlan AS CHAR))",
        'idf_ports_vlan_idx',
        'idf_ports_ibfk_vlan'
    );
    $ensureMappedIntColumn(
        'speed',
        'equipment_fiber',
        "LOWER(ref.name) = LOWER(CAST(p.speed AS CHAR))",
        'idf_ports_speed_idx',
        'idf_ports_ibfk_speed'
    );
    $ensureMappedIntColumn(
        'poe',
        'equipment_poe',
        "LOWER(ref.name) = LOWER(CAST(p.poe AS CHAR))",
        'idf_ports_poe_idx',
        'idf_ports_ibfk_poe'
    );
}

/**
 * Why: Persist IDF port status as switch_status.id while still accepting either
 * a numeric id or a text status from callers.
 */
function idf_resolve_status_id(mysqli $conn, int $company_id, $rawStatus, string $fallback = 'Unknown'): int {
    $statusId = 0;
    if (is_numeric($rawStatus)) {
        $candidateId = (int)$rawStatus;
        if ($candidateId > 0) {
            $stmtById = mysqli_prepare(
                $conn,
                'SELECT id FROM switch_status WHERE company_id = ? AND id = ? LIMIT 1'
            );
            if ($stmtById) {
                mysqli_stmt_bind_param($stmtById, 'ii', $company_id, $candidateId);
                mysqli_stmt_execute($stmtById);
                $resById = mysqli_stmt_get_result($stmtById);
                $rowById = $resById ? mysqli_fetch_assoc($resById) : null;
                mysqli_stmt_close($stmtById);
                if ($rowById && isset($rowById['id'])) {
                    return (int)$rowById['id'];
                }
            }
        }
    }

    $statusName = trim((string)$rawStatus);
    if ($statusName !== '') {
        $stmtByName = mysqli_prepare(
            $conn,
            'SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = LOWER(?) LIMIT 1'
        );
        if ($stmtByName) {
            mysqli_stmt_bind_param($stmtByName, 'is', $company_id, $statusName);
            mysqli_stmt_execute($stmtByName);
            $resByName = mysqli_stmt_get_result($stmtByName);
            $rowByName = $resByName ? mysqli_fetch_assoc($resByName) : null;
            mysqli_stmt_close($stmtByName);
            if ($rowByName && isset($rowByName['id'])) {
                return (int)$rowByName['id'];
            }
        }
    }

    $fallbackName = trim($fallback) !== '' ? trim($fallback) : 'Unknown';
    $stmtFallback = mysqli_prepare(
        $conn,
        'SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = LOWER(?) LIMIT 1'
    );
    if ($stmtFallback) {
        mysqli_stmt_bind_param($stmtFallback, 'is', $company_id, $fallbackName);
        mysqli_stmt_execute($stmtFallback);
        $resFallback = mysqli_stmt_get_result($stmtFallback);
        $rowFallback = $resFallback ? mysqli_fetch_assoc($resFallback) : null;
        mysqli_stmt_close($stmtFallback);
        if ($rowFallback && isset($rowFallback['id'])) {
            return (int)$rowFallback['id'];
        }
    }

    $stmtFirst = mysqli_prepare($conn, 'SELECT id FROM switch_status WHERE company_id = ? ORDER BY id ASC LIMIT 1');
    if ($stmtFirst) {
        mysqli_stmt_bind_param($stmtFirst, 'i', $company_id);
        mysqli_stmt_execute($stmtFirst);
        $resFirst = mysqli_stmt_get_result($stmtFirst);
        $first = $resFirst ? mysqli_fetch_assoc($resFirst) : null;
        mysqli_stmt_close($stmtFirst);
        if ($first && isset($first['id'])) {
            $statusId = (int)$first['id'];
        }
    }

    return $statusId;
}

/**
 * Why: Persist IDF port type as switch_port_types.id while still accepting
 * either numeric ids or textual type labels.
 */
function idf_resolve_port_type_id(mysqli $conn, int $company_id, $rawPortType, string $fallback = 'RJ45'): int {
    if (is_numeric($rawPortType)) {
        $candidateId = (int)$rawPortType;
        if ($candidateId > 0) {
            $stmtById = mysqli_prepare(
                $conn,
                'SELECT id FROM switch_port_types WHERE company_id = ? AND id = ? LIMIT 1'
            );
            if ($stmtById) {
                mysqli_stmt_bind_param($stmtById, 'ii', $company_id, $candidateId);
                mysqli_stmt_execute($stmtById);
                $resById = mysqli_stmt_get_result($stmtById);
                $rowById = $resById ? mysqli_fetch_assoc($resById) : null;
                mysqli_stmt_close($stmtById);
                if ($rowById && isset($rowById['id'])) {
                    return (int)$rowById['id'];
                }
            }
        }
    }

    $portTypeName = trim((string)$rawPortType);
    if ($portTypeName !== '') {
        $stmtByName = mysqli_prepare(
            $conn,
            'SELECT id FROM switch_port_types WHERE company_id = ? AND LOWER(type) = LOWER(?) LIMIT 1'
        );
        if ($stmtByName) {
            mysqli_stmt_bind_param($stmtByName, 'is', $company_id, $portTypeName);
            mysqli_stmt_execute($stmtByName);
            $resByName = mysqli_stmt_get_result($stmtByName);
            $rowByName = $resByName ? mysqli_fetch_assoc($resByName) : null;
            mysqli_stmt_close($stmtByName);
            if ($rowByName && isset($rowByName['id'])) {
                return (int)$rowByName['id'];
            }
        }
    }

    $fallbackName = trim($fallback) !== '' ? trim($fallback) : 'RJ45';
    $stmtFallback = mysqli_prepare(
        $conn,
        'SELECT id FROM switch_port_types WHERE company_id = ? AND LOWER(type) = LOWER(?) LIMIT 1'
    );
    if ($stmtFallback) {
        mysqli_stmt_bind_param($stmtFallback, 'is', $company_id, $fallbackName);
        mysqli_stmt_execute($stmtFallback);
        $resFallback = mysqli_stmt_get_result($stmtFallback);
        $rowFallback = $resFallback ? mysqli_fetch_assoc($resFallback) : null;
        mysqli_stmt_close($stmtFallback);
        if ($rowFallback && isset($rowFallback['id'])) {
            return (int)$rowFallback['id'];
        }
    }

    $stmtFirst = mysqli_prepare($conn, 'SELECT id FROM switch_port_types WHERE company_id = ? ORDER BY id ASC LIMIT 1');
    if ($stmtFirst) {
        mysqli_stmt_bind_param($stmtFirst, 'i', $company_id);
        mysqli_stmt_execute($stmtFirst);
        $resFirst = mysqli_stmt_get_result($stmtFirst);
        $first = $resFirst ? mysqli_fetch_assoc($resFirst) : null;
        mysqli_stmt_close($stmtFirst);
        if ($first && isset($first['id'])) {
            return (int)$first['id'];
        }
    }

    return 0;
}

function idf_resolve_vlan_id(mysqli $conn, int $company_id, $rawVlan): ?int {
    if ($rawVlan === null || $rawVlan === '') return null;
    if (is_numeric($rawVlan)) {
        $candidate = (int)$rawVlan;
        $stmt = mysqli_prepare($conn, 'SELECT id FROM vlans WHERE company_id = ? AND id = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $company_id, $candidate);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($row) return (int)$row['id'];
        }
    }
    $value = trim((string)$rawVlan);
    if ($value === '') return null;
    $stmt = mysqli_prepare($conn, 'SELECT id FROM vlans WHERE company_id = ? AND (LOWER(vlan_number)=LOWER(?) OR LOWER(vlan_name)=LOWER(?)) LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iss', $company_id, $value, $value);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($row) return (int)$row['id'];
    }
    return null;
}

function idf_resolve_named_lookup_id(mysqli $conn, int $company_id, string $table, string $column, $raw): ?int {
    if ($raw === null || $raw === '') return null;
    if (is_numeric($raw)) {
        $candidate = (int)$raw;
        $stmtById = mysqli_prepare($conn, "SELECT id FROM {$table} WHERE company_id = ? AND id = ? LIMIT 1");
        if ($stmtById) {
            mysqli_stmt_bind_param($stmtById, 'ii', $company_id, $candidate);
            mysqli_stmt_execute($stmtById);
            $resById = mysqli_stmt_get_result($stmtById);
            $rowById = $resById ? mysqli_fetch_assoc($resById) : null;
            mysqli_stmt_close($stmtById);
            if ($rowById) return (int)$rowById['id'];
        }
    }
    $value = trim((string)$raw);
    if ($value === '') return null;
    $stmtByName = mysqli_prepare($conn, "SELECT id FROM {$table} WHERE company_id = ? AND LOWER({$column}) = LOWER(?) LIMIT 1");
    if ($stmtByName) {
        mysqli_stmt_bind_param($stmtByName, 'is', $company_id, $value);
        mysqli_stmt_execute($stmtByName);
        $resByName = mysqli_stmt_get_result($stmtByName);
        $rowByName = $resByName ? mysqli_fetch_assoc($resByName) : null;
        mysqli_stmt_close($stmtByName);
        if ($rowByName) return (int)$rowByName['id'];
    }
    return null;
}

idf_ensure_status_schema($conn);
