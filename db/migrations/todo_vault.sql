-- Todo vault: encrypt personal task title/description at rest (see modules/todo/todo_vault_helpers.php).
-- Apply on existing databases; fresh installs include these columns in db/01_schema.sql.

ALTER TABLE `todo` DROP INDEX `uq_todo_company_scope`;

ALTER TABLE `todo`
  MODIFY `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  ADD COLUMN `title_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' AFTER `title`;

ALTER TABLE `todo` ADD UNIQUE KEY `uq_todo_company_scope` (`company_id`, `created_by`, `id`);
