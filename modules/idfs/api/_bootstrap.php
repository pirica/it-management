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
 * Why: Refactor database schema to use ID-based relations for colors and statuses.
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

        // Why: Support visual port status with color mapping (relation to cable_colors).
        $colorIdCheckRes = mysqli_query($conn, "SHOW COLUMNS FROM `switch_status` LIKE 'color_id'");
        if ($colorIdCheckRes && mysqli_num_rows($colorIdCheckRes) === 0) {
            mysqli_query($conn, "ALTER TABLE `switch_status` ADD COLUMN `color_id` int DEFAULT NULL AFTER `status` ");
            mysqli_query($conn, "ALTER TABLE `switch_status` ADD KEY `color_id` (`color_id`) ");

            $hasColorIdFk = false;
            $resColorIdFk = mysqli_query(
                $conn,
                "SELECT CONSTRAINT_NAME
                 FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = '{$databaseNameEscaped}'
                   AND TABLE_NAME = 'switch_status'
                   AND CONSTRAINT_NAME = 'switch_status_ibfk_color'
                 LIMIT 1"
            );
            if ($resColorIdFk && mysqli_fetch_assoc($resColorIdFk)) {
                $hasColorIdFk = true;
            }
            if (!$hasColorIdFk) {
                mysqli_query(
                    $conn,
                    "ALTER TABLE `switch_status`
                     ADD CONSTRAINT `switch_status_ibfk_color`
                     FOREIGN KEY (`color_id`) REFERENCES `cable_colors` (`id`)
                     ON DELETE SET NULL"
                );
            }

            // Why: Migrate existing hex colors to relation.
            $oldColorRes = mysqli_query($conn, "SHOW COLUMNS FROM `switch_status` LIKE 'color'");
            if ($oldColorRes && mysqli_num_rows($oldColorRes) > 0) {
                mysqli_query(
                    $conn,
                    "UPDATE switch_status ss
                     JOIN cable_colors cc ON LOWER(cc.hex_color) = LOWER(ss.color) AND cc.company_id = ss.company_id
                     SET ss.color_id = cc.id
                     WHERE ss.color_id IS NULL"
                );
                mysqli_query($conn, "ALTER TABLE `switch_status` DROP COLUMN `color` ");
            }
        }
    }

    $idfPortsColumn = $readStatusColumn('idf_ports');
    if ($idfPortsColumn) {
        $idfType = strtolower((string)($idfPortsColumn['COLUMN_TYPE'] ?? ''));
        if (!preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $idfType)) {
            mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `status_id` int DEFAULT NULL");
            mysqli_query(
                $conn,
                "UPDATE idf_ports p
                 LEFT JOIN switch_status ss
                   ON ss.company_id = p.company_id
                  AND LOWER(ss.status) = LOWER(CAST(p.status AS CHAR))
                 SET p.status_id = ss.id"
            );
            mysqli_query(
                $conn,
                "UPDATE idf_ports p
                 LEFT JOIN switch_status ss
                   ON ss.company_id = p.company_id
                  AND LOWER(ss.status) = 'unknown'
                 SET p.status_id = ss.id
                 WHERE p.status_id IS NULL"
            );
            mysqli_query(
                $conn,
                "UPDATE idf_ports p
                 JOIN (
                   SELECT company_id, MIN(id) AS any_status_id
                   FROM switch_status
                   GROUP BY company_id
                 ) ss ON ss.company_id = p.company_id
                 SET p.status_id = ss.any_status_id
                 WHERE p.status_id IS NULL"
            );
            mysqli_query($conn, "ALTER TABLE `idf_ports` DROP COLUMN `status` ");
        } else {
            $statusNameCheck = mysqli_query($conn, "SHOW COLUMNS FROM `idf_ports` LIKE 'status'");
            if ($statusNameCheck && mysqli_num_rows($statusNameCheck) > 0) {
                 mysqli_query($conn, "ALTER TABLE `idf_ports` CHANGE COLUMN `status` `status_id` int NOT NULL ");
            }
        }

        $statusIndexExists = false;
        $resStatusIndex = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = 'idf_ports_status_idx'");
        if ($resStatusIndex && mysqli_fetch_assoc($resStatusIndex)) {
            $statusIndexExists = true;
        }
        if (!$statusIndexExists) {
            mysqli_query($conn, "ALTER TABLE `idf_ports` ADD KEY `idf_ports_status_idx` (`status_id`) ");
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
                 FOREIGN KEY (`status_id`) REFERENCES `switch_status` (`id`)"
            );
        }
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
        mysqli_query($conn, "ALTER TABLE `idf_ports` DROP COLUMN `port_type` ");
        mysqli_query($conn, "ALTER TABLE `idf_ports` CHANGE COLUMN `port_type_new` `port_type` int NOT NULL ");
    }

    $portTypeIndexExists = false;
    $resPortTypeIndex = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = 'idf_ports_port_type_idx'");
    if ($resPortTypeIndex && mysqli_fetch_assoc($resPortTypeIndex)) {
        $portTypeIndexExists = true;
    }
    if (!$portTypeIndexExists) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD KEY `idf_ports_port_type_idx` (`port_type`) ");
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


    // Persist selected cable color details on each IDF port row.
    $portCableColorRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_ports` LIKE 'cable_color'");
    if ($portCableColorRes && mysqli_num_rows($portCableColorRes) === 0) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `cable_color` varchar(100) DEFAULT NULL AFTER `poe_id` ");
    }

    $portHexColorRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_ports` LIKE 'hex_color'");
    if ($portHexColorRes && mysqli_num_rows($portHexColorRes) === 0) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `hex_color` varchar(7) DEFAULT NULL AFTER `cable_color` ");
    }

    // Why: IDF ports can contain RJ45 and SFP rows with the same visible port number for linked switch equipment.
    $resPosPortUnique = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = 'pos_port_unique'");
    $hasLegacyPosPortUnique = false;
    $legacyUniqueColumns = [];
    if ($resPosPortUnique) {
        while ($idxRow = mysqli_fetch_assoc($resPosPortUnique)) {
            $hasLegacyPosPortUnique = true;
            $seq = (int)($idxRow['Seq_in_index'] ?? 0);
            $col = (string)($idxRow['Column_name'] ?? '');
            if ($seq > 0 && $col !== '') {
                $legacyUniqueColumns[$seq] = $col;
            }
        }
    }
    if ($hasLegacyPosPortUnique) {
        ksort($legacyUniqueColumns);
        $legacyUniqueSignature = implode(',', array_values($legacyUniqueColumns));
        if ($legacyUniqueSignature !== 'company_id,position_id,port_no,port_type') {
            mysqli_query($conn, "ALTER TABLE `idf_ports` DROP INDEX `pos_port_unique`");
            mysqli_query($conn, "ALTER TABLE `idf_ports` ADD UNIQUE KEY `pos_port_unique` (`company_id`,`position_id`,`port_no`,`port_type`)");
        }
    }

    // Keep a denormalized cable hex color on links for historical snapshots and exports.
    $linkHexColorColRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_links` LIKE 'cable_color_hex'");
    if ($linkHexColorColRes && mysqli_num_rows($linkHexColorColRes) === 0) {
        mysqli_query($conn, "ALTER TABLE `idf_links` ADD COLUMN `cable_color_hex` varchar(7) DEFAULT NULL AFTER `cable_color_id` ");
        mysqli_query(
            $conn,
            "UPDATE idf_links l
             LEFT JOIN cable_colors cc ON cc.id = l.cable_color_id AND cc.company_id = l.company_id
             SET l.cable_color_hex = cc.hex_color
             WHERE l.cable_color_id IS NOT NULL
               AND (l.cable_color_hex IS NULL OR l.cable_color_hex = '')"
        );
    }

    // Support for ID-based cable colors in idf_links.
    $linkCableColorColRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_links` LIKE 'cable_color_id'");
    if ($linkCableColorColRes && mysqli_num_rows($linkCableColorColRes) === 0) {
        mysqli_query($conn, "ALTER TABLE `idf_links` ADD COLUMN `cable_color_id` int DEFAULT NULL AFTER `equipment_color_id` ");
        mysqli_query($conn, "ALTER TABLE `idf_links` ADD KEY `cable_color_id` (`cable_color_id`) ");

        $hasLinkColorIdFk = false;
        $resLinkColorIdFk = mysqli_query(
            $conn,
            "SELECT CONSTRAINT_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = '{$databaseNameEscaped}'
               AND TABLE_NAME = 'idf_links'
               AND CONSTRAINT_NAME = 'idf_links_ibfk_cable_color'
             LIMIT 1"
        );
        if ($resLinkColorIdFk && mysqli_fetch_assoc($resLinkColorIdFk)) {
            $hasLinkColorIdFk = true;
        }
        if (!$hasLinkColorIdFk) {
            mysqli_query(
                $conn,
                "ALTER TABLE `idf_links`
                 ADD CONSTRAINT `idf_links_ibfk_cable_color`
                 FOREIGN KEY (`cable_color_id`) REFERENCES `cable_colors` (`id`)
                 ON DELETE SET NULL"
            );
        }

        $oldLinkColorRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_links` LIKE 'cable_color'");
        if ($oldLinkColorRes && mysqli_num_rows($oldLinkColorRes) > 0) {
            mysqli_query(
                $conn,
                "UPDATE idf_links l
                 JOIN cable_colors cc ON LOWER(cc.color_name) = LOWER(l.cable_color) AND cc.company_id = l.company_id
                 SET l.cable_color_id = cc.id
                 WHERE l.cable_color_id IS NULL"
            );
            mysqli_query($conn, "ALTER TABLE `idf_links` DROP COLUMN `cable_color` ");
        }
    }

    // Why: Legacy audit triggers may still reference idf_links.cable_color after migration to cable_color_id.
    $linkCableColorLegacyRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_links` LIKE 'cable_color'");
    $linkHasLegacyCableColor = $linkCableColorLegacyRes && mysqli_num_rows($linkCableColorLegacyRes) > 0;
    if (!$linkHasLegacyCableColor) {
        mysqli_query($conn, "DROP TRIGGER IF EXISTS `trg_idf_links_audit_insert`");
        mysqli_query($conn, "DROP TRIGGER IF EXISTS `trg_idf_links_audit_update`");
        mysqli_query($conn, "DROP TRIGGER IF EXISTS `trg_idf_links_audit_delete`");

        mysqli_query(
            $conn,
            "CREATE TRIGGER `trg_idf_links_audit_insert` AFTER INSERT ON `idf_links` FOR EACH ROW BEGIN
               INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
               VALUES (
                 COALESCE(@app_company_id, NEW.`company_id`, 0),
                 @app_user_id,
                 @app_username,
                 @app_email,
                 'idf_links',
                 COALESCE(NEW.`id`, 0),
                 'INSERT',
                 NULL,
                 JSON_OBJECT(
                   'id', NEW.`id`,
                   'company_id', NEW.`company_id`,
                   'port_id_a', NEW.`port_id_a`,
                   'port_id_b', NEW.`port_id_b`,
                   'equipment_id', NEW.`equipment_id`,
                   'equipment_hostname', NEW.`equipment_hostname`,
                   'equipment_port_type', NEW.`equipment_port_type`,
                   'equipment_port', NEW.`equipment_port`,
                   'equipment_vlan_id', NEW.`equipment_vlan_id`,
                   'equipment_label', NEW.`equipment_label`,
                   'equipment_comments', NEW.`equipment_comments`,
                   'equipment_status_id', NEW.`equipment_status_id`,
                   'equipment_color_id', NEW.`equipment_color_id`,
                   'cable_color_id', NEW.`cable_color_id`,
                   'cable_color_hex', NEW.`cable_color_hex`,
                   'cable_label', NEW.`cable_label`,
                   'notes', NEW.`notes`,
                   'created_at', NEW.`created_at`
                 ),
                 @app_ip_address,
                 @app_user_agent
               );
             END"
        );
        mysqli_query(
            $conn,
            "CREATE TRIGGER `trg_idf_links_audit_update` AFTER UPDATE ON `idf_links` FOR EACH ROW BEGIN
               INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
               VALUES (
                 COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0),
                 @app_user_id,
                 @app_username,
                 @app_email,
                 'idf_links',
                 COALESCE(NEW.`id`, OLD.`id`, 0),
                 'UPDATE',
                 JSON_OBJECT(
                   'id', OLD.`id`,
                   'company_id', OLD.`company_id`,
                   'port_id_a', OLD.`port_id_a`,
                   'port_id_b', OLD.`port_id_b`,
                   'equipment_id', OLD.`equipment_id`,
                   'equipment_hostname', OLD.`equipment_hostname`,
                   'equipment_port_type', OLD.`equipment_port_type`,
                   'equipment_port', OLD.`equipment_port`,
                   'equipment_vlan_id', OLD.`equipment_vlan_id`,
                   'equipment_label', OLD.`equipment_label`,
                   'equipment_comments', OLD.`equipment_comments`,
                   'equipment_status_id', OLD.`equipment_status_id`,
                   'equipment_color_id', OLD.`equipment_color_id`,
                   'cable_color_id', OLD.`cable_color_id`,
                   'cable_color_hex', OLD.`cable_color_hex`,
                   'cable_label', OLD.`cable_label`,
                   'notes', OLD.`notes`,
                   'created_at', OLD.`created_at`
                 ),
                 JSON_OBJECT(
                   'id', NEW.`id`,
                   'company_id', NEW.`company_id`,
                   'port_id_a', NEW.`port_id_a`,
                   'port_id_b', NEW.`port_id_b`,
                   'equipment_id', NEW.`equipment_id`,
                   'equipment_hostname', NEW.`equipment_hostname`,
                   'equipment_port_type', NEW.`equipment_port_type`,
                   'equipment_port', NEW.`equipment_port`,
                   'equipment_vlan_id', NEW.`equipment_vlan_id`,
                   'equipment_label', NEW.`equipment_label`,
                   'equipment_comments', NEW.`equipment_comments`,
                   'equipment_status_id', NEW.`equipment_status_id`,
                   'equipment_color_id', NEW.`equipment_color_id`,
                   'cable_color_id', NEW.`cable_color_id`,
                   'cable_color_hex', NEW.`cable_color_hex`,
                   'cable_label', NEW.`cable_label`,
                   'notes', NEW.`notes`,
                   'created_at', NEW.`created_at`
                 ),
                 @app_ip_address,
                 @app_user_agent
               );
             END"
        );
        mysqli_query(
            $conn,
            "CREATE TRIGGER `trg_idf_links_audit_delete` AFTER DELETE ON `idf_links` FOR EACH ROW BEGIN
               INSERT INTO `audit_logs` (`company_id`, `user_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
               VALUES (
                 COALESCE(@app_company_id, OLD.`company_id`, 0),
                 @app_user_id,
                 @app_username,
                 @app_email,
                 'idf_links',
                 COALESCE(OLD.`id`, 0),
                 'DELETE',
                 JSON_OBJECT(
                   'id', OLD.`id`,
                   'company_id', OLD.`company_id`,
                   'port_id_a', OLD.`port_id_a`,
                   'port_id_b', OLD.`port_id_b`,
                   'equipment_id', OLD.`equipment_id`,
                   'equipment_hostname', OLD.`equipment_hostname`,
                   'equipment_port_type', OLD.`equipment_port_type`,
                   'equipment_port', OLD.`equipment_port`,
                   'equipment_vlan_id', OLD.`equipment_vlan_id`,
                   'equipment_label', OLD.`equipment_label`,
                   'equipment_comments', OLD.`equipment_comments`,
                   'equipment_status_id', OLD.`equipment_status_id`,
                   'equipment_color_id', OLD.`equipment_color_id`,
                   'cable_color_id', OLD.`cable_color_id`,
                   'cable_color_hex', OLD.`cable_color_hex`,
                   'cable_label', OLD.`cable_label`,
                   'notes', OLD.`notes`,
                   'created_at', OLD.`created_at`
                 ),
                 NULL,
                 @app_ip_address,
                 @app_user_agent
               );
             END"
        );
    }

    $ensureMappedIntColumn = static function (
        string $columnName,
        string $refTable,
        string $refMatchSql,
        string $indexName,
        string $fkName,
        ?string $newColumnName = null
    ) use ($conn, $databaseNameEscaped): void {
        $finalColumnName = $newColumnName ?? $columnName;
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
            mysqli_query($conn, "ALTER TABLE `idf_ports` CHANGE COLUMN `{$tmpColumn}` `{$finalColumnName}` int DEFAULT NULL");
        } else if ($columnName !== $finalColumnName) {
            mysqli_query($conn, "ALTER TABLE `idf_ports` CHANGE COLUMN `{$columnName}` `{$finalColumnName}` int DEFAULT NULL ");
        }

        $hasIndex = false;
        $indexRes = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = '{$indexName}'");
        if ($indexRes && mysqli_fetch_assoc($indexRes)) {
            $hasIndex = true;
        }
        if (!$hasIndex) {
            mysqli_query($conn, "ALTER TABLE `idf_ports` ADD KEY `{$indexName}` (`{$finalColumnName}`) ");
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
                 FOREIGN KEY (`{$finalColumnName}`) REFERENCES `{$refTable}` (`id`)
                 ON DELETE SET NULL"
            );
        }
    };

    $ensureMappedIntColumn(
        'vlan',
        'vlans',
        "LOWER(ref.vlan_number) = LOWER(CAST(p.vlan AS CHAR)) OR LOWER(ref.vlan_name) = LOWER(CAST(p.vlan AS CHAR))",
        'idf_ports_vlan_idx',
        'idf_ports_ibfk_vlan',
        'vlan_id'
    );
    $ensureMappedIntColumn(
        'speed',
        'equipment_fiber',
        "LOWER(ref.name) = LOWER(CAST(p.speed AS CHAR))",
        'idf_ports_speed_idx',
        'idf_ports_ibfk_speed',
        'speed_id'
    );
    $ensureMappedIntColumn(
        'poe',
        'equipment_poe',
        "LOWER(ref.name) = LOWER(CAST(p.poe AS CHAR))",
        'idf_ports_poe_idx',
        'idf_ports_ibfk_poe',
        'poe_id'
    );

    // Why: "None" selections in the UI should persist as SQL NULL (never 0 sentinel values).
    foreach (['vlan_id', 'speed_id', 'poe_id'] as $nullableFkColumn) {
        if (!idf_table_has_column($conn, 'idf_ports', $nullableFkColumn)) {
            continue;
        }
        $nullableFkColumnEscaped = mysqli_real_escape_string($conn, $nullableFkColumn);
        $nullableRes = mysqli_query(
            $conn,
            "SELECT IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = '{$databaseNameEscaped}'
               AND TABLE_NAME = 'idf_ports'
               AND COLUMN_NAME = '{$nullableFkColumnEscaped}'
             LIMIT 1"
        );
        $nullableRow = $nullableRes ? mysqli_fetch_assoc($nullableRes) : null;
        $isNullable = strtoupper((string)($nullableRow['IS_NULLABLE'] ?? 'YES')) === 'YES';
        if (!$isNullable) {
            mysqli_query($conn, "ALTER TABLE `idf_ports` MODIFY `{$nullableFkColumnEscaped}` int DEFAULT NULL");
        }
    }
}

function idf_resolve_status_id(mysqli $conn, int $company_id, $rawStatus, string $fallback = 'Unknown'): int {
    if (is_numeric($rawStatus) && (int)$rawStatus > 0) {
        return (int)$rawStatus;
    }
    $statusName = trim((string)$rawStatus);
    if ($statusName !== '') {
        $stmtByName = mysqli_prepare($conn, 'SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = LOWER(?) LIMIT 1');
        if ($stmtByName) {
            mysqli_stmt_bind_param($stmtByName, 'is', $company_id, $statusName);
            mysqli_stmt_execute($stmtByName);
            $resByName = mysqli_stmt_get_result($stmtByName);
            $rowByName = $resByName ? mysqli_fetch_assoc($resByName) : null;
            mysqli_stmt_close($stmtByName);
            if ($rowByName) return (int)$rowByName['id'];
        }
    }
    $stmtFallback = mysqli_prepare($conn, 'SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = LOWER(?) LIMIT 1');
    if ($stmtFallback) {
        mysqli_stmt_bind_param($stmtFallback, 'is', $company_id, $fallback);
        mysqli_stmt_execute($stmtFallback);
        $resFallback = mysqli_stmt_get_result($stmtFallback);
        $rowFallback = $resFallback ? mysqli_fetch_assoc($resFallback) : null;
        mysqli_stmt_close($stmtFallback);
        if ($rowFallback) return (int)$rowFallback['id'];
    }
    return 0;
}

function idf_resolve_port_type_id(mysqli $conn, int $company_id, $rawPortType, string $fallback = 'RJ45'): int {
    if (is_numeric($rawPortType) && (int)$rawPortType > 0) {
        return (int)$rawPortType;
    }
    $rawCandidates = [];
    $rawValue = trim((string)$rawPortType);
    $fallbackValue = trim((string)$fallback);
    if ($rawValue !== '') {
        $rawCandidates[] = $rawValue;
    }
    if ($fallbackValue !== '') {
        $rawCandidates[] = $fallbackValue;
    }

    foreach ($rawCandidates as $portTypeName) {
        $stmtByName = mysqli_prepare(
            $conn,
            'SELECT id, type FROM switch_port_types WHERE company_id = ? AND LOWER(type) = LOWER(?) LIMIT 1'
        );
        if ($stmtByName) {
            mysqli_stmt_bind_param($stmtByName, 'is', $company_id, $portTypeName);
            mysqli_stmt_execute($stmtByName);
            $resByName = mysqli_stmt_get_result($stmtByName);
            $rowByName = $resByName ? mysqli_fetch_assoc($resByName) : null;
            mysqli_stmt_close($stmtByName);
            if ($rowByName) {
                return (int)$rowByName['id'];
            }
        }
    }

    $stmtAllPortTypes = mysqli_prepare($conn, 'SELECT id, type FROM switch_port_types WHERE company_id = ?');
    if ($stmtAllPortTypes) {
        mysqli_stmt_bind_param($stmtAllPortTypes, 'i', $company_id);
        mysqli_stmt_execute($stmtAllPortTypes);
        $resAllPortTypes = mysqli_stmt_get_result($stmtAllPortTypes);
        $normalizedNeedleList = [];
        foreach ($rawCandidates as $portTypeCandidate) {
            $normalizedNeedle = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)$portTypeCandidate));
            if ($normalizedNeedle !== '') {
                $normalizedNeedleList[] = $normalizedNeedle;
            }
        }
        while ($resAllPortTypes && ($rowPortType = mysqli_fetch_assoc($resAllPortTypes))) {
            $candidateType = strtolower(preg_replace('/[^a-z0-9]+/', '', (string)($rowPortType['type'] ?? '')));
            if ($candidateType === '') {
                continue;
            }
            foreach ($normalizedNeedleList as $normalizedNeedle) {
                if ($normalizedNeedle !== '' && strpos($candidateType, $normalizedNeedle) !== false) {
                    mysqli_stmt_close($stmtAllPortTypes);
                    return (int)($rowPortType['id'] ?? 0);
                }
            }
        }
        mysqli_stmt_close($stmtAllPortTypes);
    }

    return 0;
}

function idf_resolve_vlan_id(mysqli $conn, int $company_id, $rawVlan): ?int {
    if ($rawVlan === null || $rawVlan === '') return null;
    if (is_numeric($rawVlan)) return (int)$rawVlan;
    $value = trim((string)$rawVlan);
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
    if (is_numeric($raw)) return (int)$raw;
    $value = trim((string)$raw);
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

function idf_table_has_column(mysqli $conn, string $table, string $column): bool {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'");
    return $res && mysqli_num_rows($res) > 0;
}

function idf_first_existing_column(mysqli $conn, string $table, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        $name = trim((string)$candidate);
        if ($name === '') {
            continue;
        }
        if (idf_table_has_column($conn, $table, $name)) {
            return $name;
        }
    }
    return null;
}

idf_ensure_status_schema($conn);
