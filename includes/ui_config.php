<?php

function itm_ui_config_defaults() {
    return [
        'table_actions_position' => 'left_right',
        'new_button_position' => 'left_right',
        'export_buttons_position' => 'left_right',
        'back_save_position' => 'left_right',
    ];
}

function itm_ui_allowed_positions() {
    return [
        'table_actions_position' => ['left_right', 'left', 'right'],
        'new_button_position' => ['left_right', 'left', 'right'],
        'export_buttons_position' => [
            'left_right',
            'left',
            'right',
            'bottom_right',
            'bottom_left',
            'top_right',
            'top_left',
            'top_bottom_right',
            'top_bottom_left',
        ],
        'back_save_position' => [
            'left_right',
            'left',
            'right',
            'bottom_right',
            'bottom_left',
            'top_right',
            'top_left',
            'top_bottom_right',
            'top_bottom_left',
        ],
    ];
}

function itm_ensure_ui_configuration_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `ui_configuration` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `table_actions_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `new_button_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `export_buttons_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `back_save_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_ui_configuration_company` (`company_id`),
        CONSTRAINT `fk_ui_configuration_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return mysqli_query($conn, $sql) === true;
}

function itm_get_ui_configuration($conn, $company_id) {
    $defaults = itm_ui_config_defaults();
    $company_id = (int)$company_id;

    if ($company_id <= 0 || !itm_ensure_ui_configuration_table($conn)) {
        return $defaults;
    }

    $sql = 'SELECT table_actions_position, new_button_position, export_buttons_position, back_save_position FROM ui_configuration WHERE company_id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $defaults;
    }

    mysqli_stmt_bind_param($stmt, 'i', $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        return $defaults;
    }

    return itm_normalize_ui_configuration($row);
}

function itm_normalize_ui_configuration($values) {
    $defaults = itm_ui_config_defaults();
    $allowed = itm_ui_allowed_positions();

    foreach ($defaults as $key => $defaultValue) {
        $value = isset($values[$key]) ? (string)$values[$key] : $defaultValue;
        $values[$key] = in_array($value, $allowed[$key], true) ? $value : $defaultValue;
    }

    return $values;
}

function itm_save_ui_configuration($conn, $company_id, $input) {
    $company_id = (int)$company_id;
    if ($company_id <= 0 || !itm_ensure_ui_configuration_table($conn)) {
        return false;
    }

    $config = itm_normalize_ui_configuration($input);

    $sql = 'INSERT INTO ui_configuration (company_id, table_actions_position, new_button_position, export_buttons_position, back_save_position)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                table_actions_position = VALUES(table_actions_position),
                new_button_position = VALUES(new_button_position),
                export_buttons_position = VALUES(export_buttons_position),
                back_save_position = VALUES(back_save_position)';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'issss',
        $company_id,
        $config['table_actions_position'],
        $config['new_button_position'],
        $config['export_buttons_position'],
        $config['back_save_position']
    );

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}
