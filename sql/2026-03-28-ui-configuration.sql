CREATE TABLE IF NOT EXISTS `ui_configuration` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ui_configuration` (`company_id`, `table_actions_position`, `new_button_position`, `export_buttons_position`, `back_save_position`)
SELECT c.id, 'left_right', 'left_right', 'left_right', 'left_right'
FROM `companies` c
LEFT JOIN `ui_configuration` uic ON uic.company_id = c.id
WHERE uic.company_id IS NULL;
