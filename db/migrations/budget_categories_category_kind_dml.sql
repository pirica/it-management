-- Backfill budget_categories.category_kind for CAPEX/OPEX reports on existing databases.
-- Requires category_kind column (budget_categories_category_kind.sql or fresh db/ import).
-- Safe to re-run: only updates rows whose kind still differs from canonical name mapping.

UPDATE `budget_categories` SET `category_kind` = 'revenue' WHERE `name` = 'Revenue' AND `category_kind` <> 'revenue';
UPDATE `budget_categories` SET `category_kind` = 'opex' WHERE `name` = 'Operating Expense' AND `category_kind` <> 'opex';
UPDATE `budget_categories` SET `category_kind` = 'capex' WHERE `name` = 'Capital Expense' AND `category_kind` <> 'capex';
