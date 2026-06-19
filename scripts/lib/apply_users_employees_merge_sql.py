#!/usr/bin/env python3
"""
One-off transformer for database.sql: merge users into employees schema.
Run from repo root: python3 scripts/lib/apply_users_employees_merge_sql.py
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SQL_PATH = ROOT / "database.sql"

# Order matters: longest / most specific replacements first.
COLUMN_REPLACEMENTS = [
    ("granted_by_user_id", "granted_by_employee_id"),
    ("invited_by_user_id", "invited_by_employee_id"),
    ("created_by_user_id", "created_by_employee_id"),
    ("assigned_to_user_id", "assigned_to_employee_id"),
    ("assigned_by_user_id", "assigned_by_employee_id"),
    ("received_by_user_id", "received_by_employee_id"),
    ("cat_from_user_id", "cat_from_employee_id"),
    ("last_user_manual", "last_employee_manual"),
    ("last_user_id", "last_employee_id"),
    ("`user_id`", "`employee_id`"),
    (" AS user_id", " AS employee_id"),
]

TABLE_REPLACEMENTS = [
    ("user_sidebar_preferences", "employee_sidebar_preferences"),
    ("user_companies", "employee_companies"),
    ("user_roles", "employee_roles"),
]

IDENTIFIER_REPLACEMENTS = [
    ("uq_user_sidebar_pref", "uq_employee_sidebar_pref"),
    ("idx_user_sidebar_pref", "idx_employee_sidebar_pref"),
    ("uq_user_companies", "uq_employee_companies"),
    ("idx_user_companies", "idx_employee_companies"),
    ("fk_user_companies", "fk_employee_companies"),
    ("user_roles_ibfk", "employee_roles_ibfk"),
    ("fk_user_sidebar_pref", "fk_employee_sidebar_pref"),
    ("uq_ui_configuration_company_user", "uq_ui_configuration_company_employee"),
    ("unique_username_per_company", "uq_employees_company_username"),
    ("idx_users_company_email", "idx_employees_company_work_email"),
    ("idx_users_reset_token", "idx_employees_reset_token"),
    ("idx_users_reset_token_hash", "idx_employees_reset_token_hash"),
    ("idx_users_reset_token_expires_at", "idx_employees_reset_token_expires_at"),
    ("trg_user_roles_audit", "trg_employee_roles_audit"),
    ("trg_user_companies_audit", "trg_employee_companies_audit"),
    ("trg_user_sidebar_preferences_audit", "trg_employee_sidebar_preferences_audit"),
    ("employee_assignment_history_ibfk_assigned_by_user", "employee_assignment_history_ibfk_assigned_by_employee"),
    ("employee_assignment_history_ibfk_received_by_user", "employee_assignment_history_ibfk_received_by_employee"),
    ("audit_logs_ibfk_user", "audit_logs_ibfk_employee"),
    ("fk_attempts_user", "fk_attempts_employee"),
    ("password_folders_ibfk_user", "password_folders_ibfk_employee"),
    ("password_entries_ibfk_user", "password_entries_ibfk_employee"),
    ("bookmark_folders_ibfk_user", "bookmark_folders_ibfk_employee"),
    ("bookmarks_ibfk_user", "bookmarks_ibfk_employee"),
    ("private_contacts_ibfk_user", "private_contacts_ibfk_employee"),
    ("todo_categories_ibfk_user", "todo_categories_ibfk_employee"),
    ("explorer_ibfk_user", "explorer_ibfk_employee"),
]

ADMIN_EMPLOYEE_SEED = """
-- Data for `employees` (admin login identity merged from former users row id=1)
INSERT INTO `employees` (`id`, `company_id`, `first_name`, `last_name`, `display_name`, `work_email`, `username`, `password`, `role_id`, `access_level_id`, `employment_status_id`, `active`, `created_at`) VALUES ('1', '1', 'System', 'Admin', 'System Admin', 'admin@techcorp.example', 'admin', '$2y$12$r6nU8WO3jAsWGvJYIFdIAOOAPDRmBQfEpltxD5UoIwTx3k.K2KPIO', '1', '1', '1', '1', '2026-01-01 00:00:01');
"""

EMPLOYEES_AUTH_COLUMNS = """
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vault_key_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `role_id` int DEFAULT NULL,
  `access_level_id` int DEFAULT NULL,
"""


def apply_replacements(text: str, pairs: list[tuple[str, str]]) -> str:
    for old, new in pairs:
        text = text.replace(old, new)
    return text


def patch_employees_create(text: str) -> str:
    text = text.replace("  `user_id` int DEFAULT NULL,\n", "")
    text = text.replace(
        "  UNIQUE KEY `uq_employees_company_scope` (`company_id`,`user_id`),\n",
        "  UNIQUE KEY `uq_employees_company_username` (`company_id`,`username`),\n",
    )
    text = text.replace("  KEY `user_id` (`user_id`),\n", "")
    text = text.replace(
        "  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),\n",
        "",
    )
    marker = "  `username` varchar(100)"
    if marker in text and "`password` varchar(255)" not in text.split("CREATE TABLE `employees`")[1].split(") ENGINE=")[0]:
        text = text.replace(
            marker,
            EMPLOYEES_AUTH_COLUMNS + marker,
            1,
        )
    # Renumber / append FKs for role and access level before closing employees constraints block.
    insert_fk = (
        "  KEY `role_id` (`role_id`),\n"
        "  KEY `access_level_id` (`access_level_id`),\n"
        "  KEY `idx_employees_reset_token` (`reset_token`),\n"
        "  KEY `idx_employees_reset_token_hash` (`reset_token_hash`),\n"
        "  KEY `idx_employees_reset_token_expires_at` (`reset_token_expires_at`),\n"
        "  KEY `idx_employees_company_work_email` (`company_id`,`work_email`),\n"
    )
    if "KEY `idx_employees_reset_token`" not in text:
        text = text.replace(
            "  KEY `idx_employees_employee_type` (`employee_type_id`),\n",
            "  KEY `idx_employees_employee_type` (`employee_type_id`),\n" + insert_fk,
            1,
        )
    text = text.replace(
        "  CONSTRAINT `employees_ibfk_10` FOREIGN KEY (`employee_type_id`) REFERENCES `employee_type` (`id`) ON DELETE SET NULL\n) ENGINE=InnoDB AUTO_INCREMENT=217",
        "  CONSTRAINT `employees_ibfk_10` FOREIGN KEY (`employee_type_id`) REFERENCES `employee_type` (`id`) ON DELETE SET NULL,\n"
        "  CONSTRAINT `employees_ibfk_role` FOREIGN KEY (`role_id`) REFERENCES `employee_roles` (`id`) ON DELETE SET NULL,\n"
        "  CONSTRAINT `employees_ibfk_access_level` FOREIGN KEY (`access_level_id`) REFERENCES `access_levels` (`id`) ON DELETE SET NULL\n"
        ") ENGINE=InnoDB AUTO_INCREMENT=217",
        1,
    )
    if ADMIN_EMPLOYEE_SEED.strip() not in text:
        text = text.replace(
            ") ENGINE=InnoDB AUTO_INCREMENT=217 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n-- Table structure for `equipment`",
            ") ENGINE=InnoDB AUTO_INCREMENT=217 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n"
            + ADMIN_EMPLOYEE_SEED
            + "-- Table structure for `equipment`",
            1,
        )
    return text


def remove_users_table(text: str) -> str:
    pattern = re.compile(
        r"-- Table structure for `users`\nDROP TABLE IF EXISTS `users`;.*?"
        r"INSERT INTO `users`.*?;\n",
        re.DOTALL,
    )
    text, count = pattern.subn("", text, count=1)
    if count != 1:
        print("WARNING: users table block removal matched", count, "times", file=sys.stderr)
    # Remove users audit triggers
    trig = re.compile(
        r"DROP TRIGGER IF EXISTS `trg_users_audit_insert`;.*?CREATE TRIGGER `trg_users_audit_delete`.*?END;\n",
        re.DOTALL,
    )
    text, tcount = trig.subn("", text, count=1)
    if tcount != 1:
        print("WARNING: users audit trigger removal matched", tcount, "times", file=sys.stderr)
    return text


def patch_modules_registry(text: str) -> str:
    text = text.replace(
        'INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("user_companies", "User Companies", 1, 1);',
        'INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_companies", "Employee Companies", 1, 1);',
    )
    text = text.replace(
        'INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("user_roles", "User Roles", 1, 1);',
        'INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_roles", "Employee Roles", 1, 1);',
    )
    text = text.replace(
        'INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("user_sidebar_preferences", "User Sidebar Preferences", 0, 1);',
        'INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("employee_sidebar_preferences", "Employee Sidebar Preferences", 0, 1);',
    )
    text = text.replace(
        'INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`) VALUES ("users", "Users", 1, 1);\n',
        "",
    )
    text = text.replace(
        "      UNION ALL SELECT 'item' AS entry_type, 'users' AS entry_id, 'admin' AS section_id, 1 AS display_order\n",
        "      UNION ALL SELECT 'item' AS entry_type, 'employees' AS entry_id, 'admin' AS section_id, 1 AS display_order\n",
    )
    return text


def patch_cross_company_block(text: str) -> str:
    text = text.replace(
        "INSERT IGNORE INTO `user_companies` (`user_id`, `company_id`, `granted_by_user_id`, `created_at`)\n"
        "SELECT u.`id`, c.`id`, NULL, NOW()\n"
        "FROM `users` u\n"
        "CROSS JOIN `companies` c\n"
        "WHERE NOT EXISTS (\n"
        "    SELECT 1 FROM `user_companies` uc\n"
        "    WHERE uc.`user_id` = u.`id` AND uc.`company_id` = c.`id`\n"
        ");\n",
        "INSERT IGNORE INTO `employee_companies` (`employee_id`, `company_id`, `granted_by_employee_id`, `created_at`)\n"
        "SELECT e.`id`, c.`id`, NULL, NOW()\n"
        "FROM `employees` e\n"
        "CROSS JOIN `companies` c\n"
        "WHERE e.`password` IS NOT NULL\n"
        "  AND NOT EXISTS (\n"
        "    SELECT 1 FROM `employee_companies` ec\n"
        "    WHERE ec.`employee_id` = e.`id` AND ec.`company_id` = c.`id`\n"
        ");\n",
    )
    return text


def main() -> int:
    text = SQL_PATH.read_text(encoding="utf-8")
    original_len = len(text)

    for old, new in TABLE_REPLACEMENTS:
        text = text.replace(old, new)

    text = apply_replacements(text, COLUMN_REPLACEMENTS)
    text = apply_replacements(text, IDENTIFIER_REPLACEMENTS)
    text = text.replace("REFERENCES `users`", "REFERENCES `employees`")
    text = text.replace("'users'", "'employees'")
    text = patch_employees_create(text)
    text = remove_users_table(text)
    text = patch_modules_registry(text)
    text = patch_cross_company_block(text)

    # Audit trigger table names in JSON payloads
    text = text.replace("'user_sidebar_preferences'", "'employee_sidebar_preferences'")
    text = text.replace("'user_roles'", "'employee_roles'")
    text = text.replace("'user_companies'", "'employee_companies'")

    SQL_PATH.write_text(text, encoding="utf-8")
    print(f"Updated {SQL_PATH} ({original_len} -> {len(text)} bytes)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
