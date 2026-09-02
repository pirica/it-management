-- Backfill ui_configuration.enable_all_error_reporting to off on existing databases.
-- Fresh imports and new rows use DEFAULT 0 in db/01_schema.sql; admins may re-enable in Settings.
-- Safe to re-run: only updates rows still set to 1.

UPDATE `ui_configuration` SET `enable_all_error_reporting` = 0 WHERE `enable_all_error_reporting` = 1;
