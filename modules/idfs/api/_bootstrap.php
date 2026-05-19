<?php
require_once __DIR__ . '/../../../config/config.php';

if (!defined('IDF_INCLUDE_HELPERS_ONLY')) {
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
}

if (!isset($company_id)) {
    $company_id = (int)($_SESSION['company_id'] ?? 0);
}

function idf_read_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function idf_require_csrf(array $data) {
    $token = (string)($data['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
    if (!itm_validate_csrf_token($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

function idf_ok(array $payload = []) {
    echo json_encode(array_merge(['ok' => true], $payload));
    exit;
}

function idf_fail(string $msg, int $code = 400) {
    http_response_code($code);
    $friendly = function_exists('itm_humanize_api_error_message')
        ? itm_humanize_api_error_message($msg)
        : $msg;
    if ($friendly === '') {
        $friendly = 'We could not complete this action. Please try again.';
    }
    echo json_encode(['ok' => false, 'error' => $friendly]);
    exit;
}

function idf_escape(mysqli $conn, $s) {
    return mysqli_real_escape_string($conn, (string)($s ?? ''));
}

/**
 * Why: Refactor database schema to use ID-based relations for colors and statuses.
 */
function idf_ensure_status_schema(mysqli $conn) {
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

    $readStatusColumn = static function (string $tableName) use ($conn, $databaseNameEscaped) {
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

    $readPortTypeColumn = static function () use ($conn, $databaseNameEscaped) {
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

    $idfLinkNullableFkColumns = [
        'equipment_rj45_speed_id' => ['after' => 'equipment_vlan_id', 'fk' => 'idf_links_ibfk_rj45_speed', 'ref' => 'rj45_speed'],
        'equipment_fiber_port_id' => ['after' => 'equipment_rj45_speed_id', 'fk' => 'idf_links_ibfk_fiber_port', 'ref' => 'equipment_fiber'],
        'equipment_fiber_patch_id' => ['after' => 'equipment_fiber_port_id', 'fk' => 'idf_links_ibfk_fiber_patch', 'ref' => 'equipment_fiber_patch'],
        'equipment_fiber_rack_id' => ['after' => 'equipment_fiber_patch_id', 'fk' => 'idf_links_ibfk_fiber_rack', 'ref' => 'equipment_fiber_rack'],
        'equipment_to_idf_id' => ['after' => 'equipment_fiber_rack_id', 'fk' => 'idf_links_ibfk_to_idf', 'ref' => 'idfs'],
        'equipment_to_rack_id' => ['after' => 'equipment_to_idf_id', 'fk' => 'idf_links_ibfk_to_rack', 'ref' => 'racks'],
        'equipment_to_location_id' => ['after' => 'equipment_to_rack_id', 'fk' => 'idf_links_ibfk_to_location', 'ref' => 'it_locations'],
    ];
    foreach ($idfLinkNullableFkColumns as $linkColumn => $linkMeta) {
        if (!idf_table_has_column($conn, 'idf_links', $linkColumn)) {
            mysqli_query($conn, "ALTER TABLE `idf_links` ADD COLUMN `{$linkColumn}` int DEFAULT NULL AFTER `{$linkMeta['after']}`");
        }
        $linkIndexName = $linkColumn;
        $linkIndexRes = mysqli_query($conn, "SHOW INDEX FROM `idf_links` WHERE Key_name = '{$linkIndexName}'");
        if (!$linkIndexRes || !mysqli_fetch_assoc($linkIndexRes)) {
            mysqli_query($conn, "ALTER TABLE `idf_links` ADD KEY `{$linkIndexName}` (`{$linkColumn}`)");
        }
        $hasLinkFk = false;
        $linkFkRes = mysqli_query(
            $conn,
            "SELECT CONSTRAINT_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = '{$databaseNameEscaped}'
               AND TABLE_NAME = 'idf_links'
               AND CONSTRAINT_NAME = '{$linkMeta['fk']}'
             LIMIT 1"
        );
        if ($linkFkRes && mysqli_fetch_assoc($linkFkRes)) {
            $hasLinkFk = true;
        }
        if (!$hasLinkFk) {
            mysqli_query(
                $conn,
                "ALTER TABLE `idf_links`
                 ADD CONSTRAINT `{$linkMeta['fk']}`
                 FOREIGN KEY (`{$linkColumn}`) REFERENCES `{$linkMeta['ref']}` (`id`)
                 ON DELETE SET NULL"
            );
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
                   'equipment_rj45_speed_id', NEW.`equipment_rj45_speed_id`,
                   'equipment_fiber_port_id', NEW.`equipment_fiber_port_id`,
                   'equipment_fiber_patch_id', NEW.`equipment_fiber_patch_id`,
                   'equipment_fiber_rack_id', NEW.`equipment_fiber_rack_id`,
                   'equipment_to_idf_id', NEW.`equipment_to_idf_id`,
                   'equipment_to_rack_id', NEW.`equipment_to_rack_id`,
                   'equipment_to_location_id', NEW.`equipment_to_location_id`,
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
                   'equipment_rj45_speed_id', OLD.`equipment_rj45_speed_id`,
                   'equipment_fiber_port_id', OLD.`equipment_fiber_port_id`,
                   'equipment_fiber_patch_id', OLD.`equipment_fiber_patch_id`,
                   'equipment_fiber_rack_id', OLD.`equipment_fiber_rack_id`,
                   'equipment_to_idf_id', OLD.`equipment_to_idf_id`,
                   'equipment_to_rack_id', OLD.`equipment_to_rack_id`,
                   'equipment_to_location_id', OLD.`equipment_to_location_id`,
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
                   'equipment_rj45_speed_id', NEW.`equipment_rj45_speed_id`,
                   'equipment_fiber_port_id', NEW.`equipment_fiber_port_id`,
                   'equipment_fiber_patch_id', NEW.`equipment_fiber_patch_id`,
                   'equipment_fiber_rack_id', NEW.`equipment_fiber_rack_id`,
                   'equipment_to_idf_id', NEW.`equipment_to_idf_id`,
                   'equipment_to_rack_id', NEW.`equipment_to_rack_id`,
                   'equipment_to_location_id', NEW.`equipment_to_location_id`,
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
                   'equipment_rj45_speed_id', OLD.`equipment_rj45_speed_id`,
                   'equipment_fiber_port_id', OLD.`equipment_fiber_port_id`,
                   'equipment_fiber_patch_id', OLD.`equipment_fiber_patch_id`,
                   'equipment_fiber_rack_id', OLD.`equipment_fiber_rack_id`,
                   'equipment_to_idf_id', OLD.`equipment_to_idf_id`,
                   'equipment_to_rack_id', OLD.`equipment_to_rack_id`,
                   'equipment_to_location_id', OLD.`equipment_to_location_id`,
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
        $newColumnName = null
    ) use ($conn, $databaseNameEscaped) {
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

    $hasRj45SpeedTable = false;
    $rj45SpeedTableRes = mysqli_query($conn, "SHOW TABLES LIKE 'rj45_speed'");
    if ($rj45SpeedTableRes && mysqli_num_rows($rj45SpeedTableRes) > 0) {
        $hasRj45SpeedTable = true;
    }
    if ($hasRj45SpeedTable) {
        if (!idf_table_has_column($conn, 'idf_ports', 'rj45_speed_id')) {
            mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `rj45_speed_id` int DEFAULT NULL AFTER `speed_id`");
        }

        // Why: Recover legacy rows where RJ45 speed values were temporarily persisted in speed_id.
        mysqli_query(
            $conn,
            "UPDATE idf_ports p
             LEFT JOIN switch_port_types spt
               ON spt.id = p.port_type
              AND spt.company_id = p.company_id
             LEFT JOIN equipment_fiber ef
               ON ef.id = p.speed_id
              AND ef.company_id = p.company_id
             LEFT JOIN rj45_speed rs
               ON rs.id = p.speed_id
              AND rs.company_id = p.company_id
             SET p.rj45_speed_id = COALESCE(p.rj45_speed_id, rs.id),
                 p.speed_id = CASE
                    WHEN UPPER(REPLACE(REPLACE(TRIM(COALESCE(spt.type, 'RJ45')), ' ', ''), '+', 'PLUS')) = 'RJ45'
                     AND rs.id IS NOT NULL
                     AND ef.id IS NULL
                    THEN NULL
                    ELSE p.speed_id
                 END
             WHERE p.speed_id IS NOT NULL"
        );

        // Why: Keep columns mutually exclusive by port type to prevent mixed FK writes.
        mysqli_query(
            $conn,
            "UPDATE idf_ports p
             LEFT JOIN switch_port_types spt
               ON spt.id = p.port_type
              AND spt.company_id = p.company_id
             SET p.rj45_speed_id = CASE
                    WHEN UPPER(REPLACE(REPLACE(TRIM(COALESCE(spt.type, 'RJ45')), ' ', ''), '+', 'PLUS')) = 'RJ45'
                    THEN p.rj45_speed_id
                    ELSE NULL
                 END"
        );

        $hasRj45SpeedIndex = false;
        $rj45SpeedIndexRes = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = 'idf_ports_rj45_speed_idx'");
        if ($rj45SpeedIndexRes && mysqli_fetch_assoc($rj45SpeedIndexRes)) {
            $hasRj45SpeedIndex = true;
        }
        if (!$hasRj45SpeedIndex) {
            mysqli_query($conn, "ALTER TABLE `idf_ports` ADD KEY `idf_ports_rj45_speed_idx` (`rj45_speed_id`) ");
        }

        $hasRj45SpeedFk = false;
        $rj45SpeedFkRes = mysqli_query(
            $conn,
            "SELECT CONSTRAINT_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = '{$databaseNameEscaped}'
               AND TABLE_NAME = 'idf_ports'
               AND CONSTRAINT_NAME = 'idf_ports_ibfk_rj45_speed'
             LIMIT 1"
        );
        if ($rj45SpeedFkRes && mysqli_fetch_assoc($rj45SpeedFkRes)) {
            $hasRj45SpeedFk = true;
        }
        if (!$hasRj45SpeedFk) {
            mysqli_query(
                $conn,
                "ALTER TABLE `idf_ports`
                 ADD CONSTRAINT `idf_ports_ibfk_rj45_speed`
                 FOREIGN KEY (`rj45_speed_id`) REFERENCES `rj45_speed` (`id`)
                 ON DELETE SET NULL"
            );
        }
    }

    if (!idf_table_has_column($conn, 'idf_ports', 'fiber_ports_number')) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `fiber_ports_number` int DEFAULT NULL AFTER `rj45_speed_id`");
    }
    if (!idf_table_has_column($conn, 'idf_ports', 'switch_port_numbering_layout_id')) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `switch_port_numbering_layout_id` int DEFAULT NULL AFTER `fiber_ports_number`");
    }
    if (!idf_table_has_column($conn, 'idf_ports', 'management_id')) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `management_id` int DEFAULT NULL AFTER `switch_port_numbering_layout_id`");
    }
    if (!idf_table_has_column($conn, 'idf_ports', 'fiber_patch_id')) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `fiber_patch_id` int DEFAULT NULL AFTER `speed_id`");
    }
    if (!idf_table_has_column($conn, 'idf_ports', 'fiber_rack_id')) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `fiber_rack_id` int DEFAULT NULL AFTER `fiber_patch_id`");
    }
    if (!idf_table_has_column($conn, 'idf_ports', 'to_idf_id')) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `to_idf_id` int DEFAULT NULL AFTER `fiber_rack_id`");
    }
    if (!idf_table_has_column($conn, 'idf_ports', 'to_rack_id')) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `to_rack_id` int DEFAULT NULL AFTER `to_idf_id`");
    }
    if (!idf_table_has_column($conn, 'idf_ports', 'to_location_id')) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD COLUMN `to_location_id` int DEFAULT NULL AFTER `to_rack_id`");
    }

    $hasFiberCountIndex = false;
    $fiberCountIndexRes = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = 'idf_ports_fiber_ports_number_idx'");
    if ($fiberCountIndexRes && mysqli_fetch_assoc($fiberCountIndexRes)) {
        $hasFiberCountIndex = true;
    }
    if (!$hasFiberCountIndex) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD KEY `idf_ports_fiber_ports_number_idx` (`fiber_ports_number`)");
    }

    $hasLayoutIndex = false;
    $layoutIndexRes = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = 'idf_ports_layout_idx'");
    if ($layoutIndexRes && mysqli_fetch_assoc($layoutIndexRes)) {
        $hasLayoutIndex = true;
    }
    if (!$hasLayoutIndex) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD KEY `idf_ports_layout_idx` (`switch_port_numbering_layout_id`)");
    }

    $hasManagementIndex = false;
    $managementIndexRes = mysqli_query($conn, "SHOW INDEX FROM `idf_ports` WHERE Key_name = 'idf_ports_management_idx'");
    if ($managementIndexRes && mysqli_fetch_assoc($managementIndexRes)) {
        $hasManagementIndex = true;
    }
    if (!$hasManagementIndex) {
        mysqli_query($conn, "ALTER TABLE `idf_ports` ADD KEY `idf_ports_management_idx` (`management_id`)");
    }

    $ensureIdfPortsFk = static function (string $fkName, string $columnName, string $refTable) use ($conn, $databaseNameEscaped): void {
        if (!idf_table_has_column($conn, 'idf_ports', $columnName)) {
            return;
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
    $ensureIdfPortsFk('idf_ports_ibfk_fiber_ports_number', 'fiber_ports_number', 'equipment_fiber_count');
    $ensureIdfPortsFk('idf_ports_ibfk_fiber_patch', 'fiber_patch_id', 'equipment_fiber_patch');
    $ensureIdfPortsFk('idf_ports_ibfk_fiber_rack', 'fiber_rack_id', 'equipment_fiber_rack');
    $ensureIdfPortsFk('idf_ports_ibfk_to_idf', 'to_idf_id', 'idfs');
    $ensureIdfPortsFk('idf_ports_ibfk_to_rack', 'to_rack_id', 'racks');
    $ensureIdfPortsFk('idf_ports_ibfk_to_location', 'to_location_id', 'it_locations');
    $ensureIdfPortsFk('idf_ports_ibfk_layout', 'switch_port_numbering_layout_id', 'switch_port_numbering_layout');
    $ensureIdfPortsFk('idf_ports_ibfk_management', 'management_id', 'equipment_environment');

    if (!idf_table_has_column($conn, 'switch_ports', 'management_id')) {
        mysqli_query($conn, "ALTER TABLE `switch_ports` ADD COLUMN `management_id` int DEFAULT NULL AFTER `to_location_id`");
    }
    $hasSwitchPortsManagementIndex = false;
    $switchPortsManagementIndexRes = mysqli_query($conn, "SHOW INDEX FROM `switch_ports` WHERE Key_name = 'idx_switch_ports_management'");
    if ($switchPortsManagementIndexRes && mysqli_fetch_assoc($switchPortsManagementIndexRes)) {
        $hasSwitchPortsManagementIndex = true;
    }
    if (!$hasSwitchPortsManagementIndex) {
        mysqli_query($conn, "ALTER TABLE `switch_ports` ADD KEY `idx_switch_ports_management` (`management_id`)");
    }
    $hasSwitchPortsManagementFk = false;
    $switchPortsManagementFkRes = mysqli_query(
        $conn,
        "SELECT CONSTRAINT_NAME
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = '{$databaseNameEscaped}'
           AND TABLE_NAME = 'switch_ports'
           AND CONSTRAINT_NAME = 'switch_ports_ibfk_management'
         LIMIT 1"
    );
    if ($switchPortsManagementFkRes && mysqli_fetch_assoc($switchPortsManagementFkRes)) {
        $hasSwitchPortsManagementFk = true;
    }
    if (!$hasSwitchPortsManagementFk) {
        mysqli_query(
            $conn,
            "ALTER TABLE `switch_ports`
             ADD CONSTRAINT `switch_ports_ibfk_management`
             FOREIGN KEY (`management_id`) REFERENCES `equipment_environment` (`id`)
             ON DELETE SET NULL"
        );
    }

    // Why: "Unmanaged" is the safe default when no management environment has been selected.
    mysqli_query(
        $conn,
        "UPDATE idf_ports p
         JOIN equipment_environment ee
           ON ee.company_id = p.company_id
          AND LOWER(ee.name) = 'unmanaged'
         SET p.management_id = ee.id
         WHERE (p.management_id IS NULL OR p.management_id = 0)"
    );
    mysqli_query(
        $conn,
        "UPDATE switch_ports sp
         JOIN equipment_environment ee
           ON ee.company_id = sp.company_id
          AND LOWER(ee.name) = 'unmanaged'
         SET sp.management_id = ee.id
         WHERE (sp.management_id IS NULL OR sp.management_id = 0)"
    );

    // Why: "None" selections in the UI should persist as SQL NULL (never 0 sentinel values).
    foreach (['vlan_id', 'speed_id', 'poe_id', 'rj45_speed_id', 'fiber_ports_number', 'switch_port_numbering_layout_id', 'management_id'] as $nullableFkColumn) {
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

function idf_ensure_unknown_status_id(mysqli $conn, int $company_id): int {
    $unknownLabel = 'Unknown';
    $stmtUnknown = mysqli_prepare(
        $conn,
        'SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = LOWER(?) ORDER BY id ASC LIMIT 1'
    );
    if ($stmtUnknown) {
        mysqli_stmt_bind_param($stmtUnknown, 'is', $company_id, $unknownLabel);
        mysqli_stmt_execute($stmtUnknown);
        $resUnknown = mysqli_stmt_get_result($stmtUnknown);
        $rowUnknown = $resUnknown ? mysqli_fetch_assoc($resUnknown) : null;
        mysqli_stmt_close($stmtUnknown);
        if ($rowUnknown) {
            return (int)($rowUnknown['id'] ?? 0);
        }
    }

    $grayColorId = 0;
    $grayLabel = 'Gray';
    $stmtGray = mysqli_prepare(
        $conn,
        'SELECT id FROM cable_colors WHERE company_id = ? AND LOWER(color_name) = LOWER(?) ORDER BY id ASC LIMIT 1'
    );
    if ($stmtGray) {
        mysqli_stmt_bind_param($stmtGray, 'is', $company_id, $grayLabel);
        mysqli_stmt_execute($stmtGray);
        $resGray = mysqli_stmt_get_result($stmtGray);
        $rowGray = $resGray ? mysqli_fetch_assoc($resGray) : null;
        mysqli_stmt_close($stmtGray);
        if ($rowGray) {
            $grayColorId = (int)($rowGray['id'] ?? 0);
        }
    }

    $stmtInsertUnknown = mysqli_prepare(
        $conn,
        "INSERT INTO switch_status (company_id, status, color_id)
         VALUES (?, 'Unknown', NULLIF(?, 0))"
    );
    if ($stmtInsertUnknown) {
        mysqli_stmt_bind_param($stmtInsertUnknown, 'ii', $company_id, $grayColorId);
        mysqli_stmt_execute($stmtInsertUnknown);
        mysqli_stmt_close($stmtInsertUnknown);
        $insertedId = (int)mysqli_insert_id($conn);
        if ($insertedId > 0) {
            return $insertedId;
        }
    }

    $stmtAny = mysqli_prepare($conn, 'SELECT id FROM switch_status WHERE company_id = ? ORDER BY id ASC LIMIT 1');
    if ($stmtAny) {
        mysqli_stmt_bind_param($stmtAny, 'i', $company_id);
        mysqli_stmt_execute($stmtAny);
        $resAny = mysqli_stmt_get_result($stmtAny);
        $rowAny = $resAny ? mysqli_fetch_assoc($resAny) : null;
        mysqli_stmt_close($stmtAny);
        if ($rowAny) {
            return (int)($rowAny['id'] ?? 0);
        }
    }

    return 0;
}

function idf_resolve_status_id(mysqli $conn, int $company_id, $rawStatus, string $fallback = 'Unknown'): int {
    if (is_numeric($rawStatus) && (int)$rawStatus > 0) {
        $requestedId = (int)$rawStatus;
        $stmtById = mysqli_prepare($conn, 'SELECT id FROM switch_status WHERE company_id = ? AND id = ? LIMIT 1');
        if ($stmtById) {
            mysqli_stmt_bind_param($stmtById, 'ii', $company_id, $requestedId);
            mysqli_stmt_execute($stmtById);
            $resById = mysqli_stmt_get_result($stmtById);
            $rowById = $resById ? mysqli_fetch_assoc($resById) : null;
            mysqli_stmt_close($stmtById);
            if ($rowById) {
                return (int)$rowById['id'];
            }
        }
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

    return idf_ensure_unknown_status_id($conn, $company_id);
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
            $normalizedNeedle = preg_replace('/[^a-z0-9]+/i', '', strtolower((string)$portTypeCandidate));
            if ($normalizedNeedle !== '') {
                $normalizedNeedleList[] = $normalizedNeedle;
            }
        }
        while ($resAllPortTypes && ($rowPortType = mysqli_fetch_assoc($resAllPortTypes))) {
            $candidateType = preg_replace('/[^a-z0-9]+/i', '', strtolower((string)($rowPortType['type'] ?? '')));
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

function idf_resolve_vlan_id(mysqli $conn, int $company_id, $rawVlan) {
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

function idf_resolve_named_lookup_id(mysqli $conn, int $company_id, string $table, string $column, $raw) {
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

function idf_first_existing_column(mysqli $conn, string $table, array $candidates) {
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

/**
 * Why: switch_ports.to_patch_port defaults to literal "0" in legacy seed/sync paths; treat it as empty in UI and mirrors.
 */
function idf_normalize_port_label_value($value): string
{
    $normalized = trim((string)$value);
    if ($normalized === '' || $normalized === '0' || strcasecmp($normalized, 'null') === 0) {
        return '';
    }
    return $normalized;
}

/**
 * Why: switch_ports.comments and legacy mirrors often store literal "0" instead of an empty note.
 */
function idf_normalize_port_notes_value($value): string
{
    $normalized = trim((string)$value);
    if ($normalized === '' || $normalized === '0' || strcasecmp($normalized, 'null') === 0) {
        return '';
    }
    return $normalized;
}

/**
 * Cable link rows in idf_links drive the Link column and peer sync — not merely linked equipment on the position.
 */
function idf_port_has_cable_link(array $portRow): bool
{
    return (int)($portRow['link_id'] ?? 0) > 0;
}

/**
 * Why: equipment.switch_port_numbering_layout_id must be NULL when unset; FK rejects 0 and orphan tenant ids.
 */
function idf_normalize_switch_port_numbering_layout_id(mysqli $conn, int $company_id, $raw): int
{
    if ($company_id <= 0 || $raw === null || $raw === '') {
        return 0;
    }
    if (!is_numeric((string)$raw)) {
        return 0;
    }
    $candidate = (int)$raw;
    if ($candidate <= 0) {
        return 0;
    }

    $stmtScoped = mysqli_prepare(
        $conn,
        'SELECT id FROM switch_port_numbering_layout WHERE company_id = ? AND id = ? LIMIT 1'
    );
    if ($stmtScoped) {
        mysqli_stmt_bind_param($stmtScoped, 'ii', $company_id, $candidate);
        mysqli_stmt_execute($stmtScoped);
        $resScoped = mysqli_stmt_get_result($stmtScoped);
        $rowScoped = $resScoped ? mysqli_fetch_assoc($resScoped) : null;
        mysqli_stmt_close($stmtScoped);
        if ($rowScoped) {
            return (int)$rowScoped['id'];
        }
    }

    $stmtLegacy = mysqli_prepare(
        $conn,
        'SELECT id FROM switch_port_numbering_layout WHERE id = ? LIMIT 1'
    );
    if ($stmtLegacy) {
        mysqli_stmt_bind_param($stmtLegacy, 'i', $candidate);
        mysqli_stmt_execute($stmtLegacy);
        $resLegacy = mysqli_stmt_get_result($stmtLegacy);
        $rowLegacy = $resLegacy ? mysqli_fetch_assoc($resLegacy) : null;
        mysqli_stmt_close($stmtLegacy);
        if ($rowLegacy) {
            return (int)$rowLegacy['id'];
        }
    }

    return 0;
}

/**
 * Why: Rack UI defaults Numbering Layout to Vertical (switch) or Horizontal (other devices) per tenant;
 * API copy/save must match so positions are not left NULL when the form would show a default.
 */
function idf_default_switch_port_numbering_layout_id(mysqli $conn, int $company_id, string $device_type_name): int
{
    if ($company_id <= 0) {
        return 0;
    }
    $deviceType = strtolower(trim($device_type_name));
    if ($deviceType === '' || $deviceType === 'ups') {
        return 0;
    }
    $preferredName = ($deviceType === 'switch') ? 'vertical' : 'horizontal';

    $stmtPreferred = mysqli_prepare(
        $conn,
        "SELECT id
         FROM switch_port_numbering_layout
         WHERE company_id = ?
           AND LOWER(TRIM(name)) = ?
         ORDER BY id ASC
         LIMIT 1"
    );
    if ($stmtPreferred) {
        mysqli_stmt_bind_param($stmtPreferred, 'is', $company_id, $preferredName);
        mysqli_stmt_execute($stmtPreferred);
        $resPreferred = mysqli_stmt_get_result($stmtPreferred);
        $rowPreferred = $resPreferred ? mysqli_fetch_assoc($resPreferred) : null;
        mysqli_stmt_close($stmtPreferred);
        if ($rowPreferred) {
            return (int)($rowPreferred['id'] ?? 0);
        }
    }

    $stmtAny = mysqli_prepare(
        $conn,
        "SELECT id
         FROM switch_port_numbering_layout
         WHERE company_id = ?
         ORDER BY name ASC, id ASC
         LIMIT 1"
    );
    if (!$stmtAny) {
        return 0;
    }
    mysqli_stmt_bind_param($stmtAny, 'i', $company_id);
    mysqli_stmt_execute($stmtAny);
    $resAny = mysqli_stmt_get_result($stmtAny);
    $rowAny = $resAny ? mysqli_fetch_assoc($resAny) : null;
    mysqli_stmt_close($stmtAny);

    return (int)($rowAny['id'] ?? 0);
}

/**
 * Why: Copy/save may receive 0 from COALESCE joins; normalize each candidate then tenant default.
 */
function idf_resolve_position_switch_port_numbering_layout_id(
    mysqli $conn,
    int $company_id,
    string $device_type_name,
    $effectiveRaw,
    $positionRaw = null
): int {
    foreach ([$effectiveRaw, $positionRaw] as $raw) {
        if ($raw === null || $raw === '') {
            continue;
        }
        $normalized = idf_normalize_switch_port_numbering_layout_id($conn, $company_id, $raw);
        if ($normalized > 0) {
            return $normalized;
        }
    }

    return idf_default_switch_port_numbering_layout_id($conn, $company_id, $device_type_name);
}

/**
 * Why: Copy/save handlers need the same device-type labels the rack modal uses for layout defaults.
 */
function idf_lookup_idf_device_type_name(mysqli $conn, int $company_id, int $device_type_id): string
{
    if ($company_id <= 0 || $device_type_id <= 0) {
        return '';
    }

    $stmtType = mysqli_prepare(
        $conn,
        "SELECT idfdevicetype_name
         FROM idf_device_type
         WHERE company_id = ? AND id = ?
         LIMIT 1"
    );
    if (!$stmtType) {
        return '';
    }
    mysqli_stmt_bind_param($stmtType, 'ii', $company_id, $device_type_id);
    mysqli_stmt_execute($stmtType);
    $resType = mysqli_stmt_get_result($stmtType);
    $rowType = $resType ? mysqli_fetch_assoc($resType) : null;
    mysqli_stmt_close($stmtType);

    return strtolower(trim((string)($rowType['idfdevicetype_name'] ?? '')));
}

/**
 * SQL fragment: blank out placeholder patch-port labels before COALESCE/INSERT.
 */
function idf_sql_normalize_port_label_expr(string $columnSql): string
{
    return "NULLIF(NULLIF(TRIM({$columnSql}), ''), '0')";
}

/**
 * Why: Clearing idf_links (or CASCADE-delete from port removal) must still reset the survivor port's mirrored
 * idf_ports + switch_ports visuals; otherwise peers keep link-colored state with no unlink affordance (regen UX).
 *
 * Mirrors the post-delete port/switch resets in link_delete.php for a single idf_ports.id endpoint.
 *
 * Call only after matching idf_links rows are deleted (explicitly or about to CASCADE).
 */
function idf_reset_idf_port_visual_unlink_state(
    mysqli $conn,
    int $company_id,
    int $idfPortId,
    int $unknownStatusId,
    int $grayColorId,
    string $switchPortLabelColumn
): void {
    if ($company_id <= 0 || $idfPortId <= 0 || $unknownStatusId <= 0) {
        return;
    }
    if (function_exists('itm_is_safe_identifier') && !itm_is_safe_identifier($switchPortLabelColumn)) {
        throw new RuntimeException('Unsafe switch port patch column name');
    }

    $clearConnected = '';
    $grayColorName = 'Gray';
    $grayHexColor = '#808080';

    $stmtPortClear = mysqli_prepare(
        $conn,
        "UPDATE idf_ports
         SET connected_to = ?,
             status_id = NULLIF(?, 0),
             cable_color = ?,
             hex_color = ?,
             label = NULL,
             notes = NULL
         WHERE id = ?
         LIMIT 1"
    );
    if (!$stmtPortClear) {
        throw new RuntimeException('DB prepare failed clearing idf_ports link state');
    }
    mysqli_stmt_bind_param($stmtPortClear, 'sissi', $clearConnected, $unknownStatusId, $grayColorName, $grayHexColor, $idfPortId);
    if (!mysqli_stmt_execute($stmtPortClear)) {
        $sqlErr = mysqli_stmt_error($stmtPortClear);
        mysqli_stmt_close($stmtPortClear);
        throw new RuntimeException('DB error clearing idf_ports link state: ' . $sqlErr);
    }
    mysqli_stmt_close($stmtPortClear);

    $stmtSwitchClear = mysqli_prepare(
        $conn,
        "UPDATE switch_ports sp
         JOIN idf_ports pr ON pr.id = ?
         JOIN idf_positions p
           ON p.company_id = pr.company_id
          AND (
               p.id = pr.position_id
               OR p.position_no = pr.position_id
          )
         LEFT JOIN switch_port_types spt
           ON spt.id = pr.port_type
          AND spt.company_id = pr.company_id
         SET sp.status_id = NULLIF(?, 0),
             sp.color_id = NULLIF(?, 0),
             sp.comments = NULL,
             sp.{$switchPortLabelColumn} = NULL
         WHERE sp.company_id = ?
           AND p.company_id = sp.company_id
           AND CONVERT(CAST(p.equipment_id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
               = CONVERT(CAST(sp.equipment_id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
           AND sp.port_number = pr.port_no
           AND (
                CONVERT(CAST(sp.port_type AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(CAST(pr.port_type AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                OR (
                    sp.port_type REGEXP '^[0-9]+$'
                    AND CAST(sp.port_type AS UNSIGNED) = pr.port_type
                )
                OR
                CONVERT(sp.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(COALESCE(spt.type, 'RJ45') USING utf8mb4) COLLATE utf8mb4_unicode_ci
                OR CONVERT(sp.port_type USING utf8mb4) COLLATE utf8mb4_unicode_ci
                    = CONVERT(CAST(spt.id AS CHAR) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                OR (
                    sp.port_type REGEXP '^[0-9]+$'
                    AND CAST(sp.port_type AS UNSIGNED) = spt.id
                )
                OR CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(sp.port_type, '')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
                   = CONVERT(UPPER(REPLACE(REPLACE(TRIM(COALESCE(spt.type, 'RJ45')), ' ', ''), '+', 'PLUS')) USING utf8mb4) COLLATE utf8mb4_unicode_ci
           )
        "
    );
    if (!$stmtSwitchClear) {
        throw new RuntimeException('DB prepare failed clearing switch port link state');
    }
    mysqli_stmt_bind_param($stmtSwitchClear, 'iiii', $idfPortId, $unknownStatusId, $grayColorId, $company_id);
    if (!mysqli_stmt_execute($stmtSwitchClear)) {
        $sqlErr = mysqli_stmt_error($stmtSwitchClear);
        mysqli_stmt_close($stmtSwitchClear);
        throw new RuntimeException('DB error clearing switch port link state: ' . $sqlErr);
    }
    mysqli_stmt_close($stmtSwitchClear);
}

/**
 * Why: Ports regen deletes idf_ports (CASCADE idf_links) but peers must reuse the same status/gray anchors as unlink.
 */
function idf_unlink_peer_sync_ids(mysqli $conn, int $company_id): array {
    $unknownStatusId = idf_resolve_status_id($conn, $company_id, 'Unknown', 'Unknown');
    if ($unknownStatusId <= 0) {
        throw new RuntimeException('Unable to resolve Unknown status for peer unlink cleanup');
    }
    $grayColorName = 'Gray';
    $grayHexColor = '#808080';
    $grayColorId = idf_resolve_named_lookup_id($conn, $company_id, 'cable_colors', 'color_name', $grayColorName);
    if ($grayColorId === null || (int)$grayColorId <= 0) {
        $stmtGrayByHex = mysqli_prepare(
            $conn,
            "SELECT id
             FROM cable_colors
             WHERE company_id = ?
               AND UPPER(hex_color) = UPPER(?)
             ORDER BY id ASC
             LIMIT 1"
        );
        if ($stmtGrayByHex) {
            mysqli_stmt_bind_param($stmtGrayByHex, 'is', $company_id, $grayHexColor);
            mysqli_stmt_execute($stmtGrayByHex);
            $resGrayByHex = mysqli_stmt_get_result($stmtGrayByHex);
            $grayRow = $resGrayByHex ? mysqli_fetch_assoc($resGrayByHex) : null;
            mysqli_stmt_close($stmtGrayByHex);
            if ($grayRow) {
                $grayColorId = (int)($grayRow['id'] ?? 0);
            }
        }
    }
    if ($grayColorId === null || (int)$grayColorId <= 0) {
        $stmtInsertGray = mysqli_prepare(
            $conn,
            "INSERT IGNORE INTO cable_colors (company_id, color_name, hex_color)
             VALUES (?, ?, ?)"
        );
        if ($stmtInsertGray) {
            mysqli_stmt_bind_param($stmtInsertGray, 'iss', $company_id, $grayColorName, $grayHexColor);
            mysqli_stmt_execute($stmtInsertGray);
            mysqli_stmt_close($stmtInsertGray);
        }
        $grayColorId = idf_resolve_named_lookup_id($conn, $company_id, 'cable_colors', 'color_name', $grayColorName);
    }
    $grayColorId = (int)($grayColorId ?? 0);
    if ($grayColorId <= 0) {
        throw new RuntimeException('Unable to resolve Gray cable_color for peer unlink cleanup');
    }
    return ['unknown_status_id' => $unknownStatusId, 'gray_color_id' => $grayColorId];
}

function idf_port_type_link_family(string $typeLabel): string {
    $normalized = preg_replace('/[^a-z0-9]+/i', '', strtolower(trim($typeLabel)));
    if ($normalized !== '' && strpos($normalized, 'sfp') !== false) {
        return 'fiber';
    }
    return 'rj45';
}

function idf_ports_are_link_compatible(string $typeLabelA, string $typeLabelB): bool {
    return idf_port_type_link_family($typeLabelA) === idf_port_type_link_family($typeLabelB);
}

function idf_port_type_link_mismatch_message(string $typeLabelA, string $typeLabelB): string {
    $displayA = trim($typeLabelA) !== '' ? trim($typeLabelA) : (idf_port_type_link_family($typeLabelA) === 'fiber' ? 'SFP' : 'RJ45');
    $displayB = trim($typeLabelB) !== '' ? trim($typeLabelB) : (idf_port_type_link_family($typeLabelB) === 'fiber' ? 'SFP' : 'RJ45');
    return 'Cannot link ' . $displayA . ' to ' . $displayB
        . '. RJ45 ports can only connect to RJ45 ports on another device.'
        . ' Fiber (SFP) ports can only connect to fiber ports on another device.';
}

function idf_fetch_port_type_labels(mysqli $conn, int $company_id, array $portIds): array {
    $portIds = array_values(array_unique(array_filter(array_map('intval', $portIds), static function ($id) {
        return $id > 0;
    })));
    if (!$portIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($portIds), '?'));
    $types = str_repeat('i', count($portIds));
    $sql = "SELECT pr.id AS port_id, COALESCE(spt.type, 'RJ45') AS port_type_label
            FROM idf_ports pr
            LEFT JOIN switch_port_types spt
              ON spt.id = pr.port_type
             AND spt.company_id = pr.company_id
            WHERE pr.company_id = ? AND pr.id IN ($placeholders)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    $bindTypes = 'i' . $types;
    $bindValues = array_merge([$company_id], $portIds);
    mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $labels = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $labels[(int)($row['port_id'] ?? 0)] = (string)($row['port_type_label'] ?? 'RJ45');
    }
    mysqli_stmt_close($stmt);
    return $labels;
}

function idf_resolve_port_position(mysqli $conn, int $company_id, int $port_id): ?array
{
    if ($company_id <= 0 || $port_id <= 0) {
        return null;
    }

    $selectSql = "SELECT
            pr.id AS port_id,
            pr.port_no,
            p.id AS position_id,
            p.position_no,
            p.device_name,
            p.idf_id,
            p.equipment_id AS position_equipment_id,
            i.company_id
         FROM idf_ports pr
         JOIN idf_positions p
           ON p.company_id = pr.company_id
          AND %s
         JOIN idfs i ON i.id = p.idf_id
         WHERE pr.company_id = ?
           AND pr.id = ?
         LIMIT 1";

    $matchModes = [
        'p.id = pr.position_id',
        'p.position_no = pr.position_id
          AND NOT EXISTS (
              SELECT 1
              FROM idf_positions p_actual
              WHERE p_actual.company_id = pr.company_id
                AND p_actual.id = pr.position_id
              LIMIT 1
          )',
    ];

    foreach ($matchModes as $joinCondition) {
        $stmt = mysqli_prepare($conn, sprintf($selectSql, $joinCondition));
        if (!$stmt) {
            continue;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $company_id, $port_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($row && (int)($row['position_id'] ?? 0) > 0) {
            return $row;
        }
    }

    return null;
}

function idf_parse_linked_equipment_id($raw): int
{
    $equipmentIdRaw = trim((string)$raw);
    if ($equipmentIdRaw === '' || !ctype_digit($equipmentIdRaw)) {
        return 0;
    }

    $equipmentId = (int)$equipmentIdRaw;
    return $equipmentId > 0 ? $equipmentId : 0;
}

function idf_generate_unlinked_equipment_token(): string
{
    return (string)random_int(1000, 9999) . '-' . (string)random_int(1000, 9999);
}

idf_ensure_status_schema($conn);
require_once __DIR__ . '/../idf_positions_schema.php';
idf_ensure_idf_positions_capacity_columns($conn);
