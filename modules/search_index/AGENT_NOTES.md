# AGENT_NOTES.md - Search Index

## 1. Module Purpose

Admin CRUD over the denormalized **`search_index`** table used by the **command palette** (phase 2): per-tenant rows keyed by `(company_id, module_slug, record_id)` with `title`, `subtitle`, `keywords`, and FULLTEXT index on text columns.

Runtime sync: `includes/itm_search_index.php` (`itm_search_index_upsert`, `itm_search_index_remove`, `itm_search_index_after_module_save/delete`). Bulk backfill: [apply_search_index_backfill.php?run=1](http://localhost/it-management/scripts/apply_search_index_backfill.php?run=1).

## 2. Key Tables

- **search_index** — denormalized palette index (no soft-delete audit columns — `updated_at` only)

## 3. Required Relationships

- `company_id` → `companies` (`ON DELETE CASCADE`)
- Logical link to source module via `module_slug` + `record_id` (no FK to module tables)

## 4. Business Rules (Critical for Agents)

- Unique `uq_search` on `(company_id, module_slug, record_id)` — upserts must use the shared helper.
- Only **supported modules** (`itm_search_index_is_supported_module()`) should receive automatic sync hooks; extending support requires updating that allowlist and backfill script.
- Palette falls back to SQL `LIKE` when index empty or no FULLTEXT hits (`includes/itm_command_palette.php`).

## 5. UI Behavior Requirements

Flattened scaffold CRUD:

- **`company_id` hidden** — `search_index` is in `$hideCompanyIdTables` on list/view/create/edit/delete flows.
- `module_slug` is string slug, not registry id — search should match slug and title/keywords.
- Bulk clear can break palette performance until backfill runs again.
- **No Add sample data:** derived cache rows must come from source module saves or [apply_search_index_backfill.php?run=1&apply=1](http://localhost/it-management/scripts/apply_search_index_backfill.php?run=1&apply=1) — not `db/02_data_sample.sql` or `itm_seed_table_from_database_sql()`. Empty list shows a backfill link only.
- **No soft-delete:** table has no `deleted_at` — list/delete use hard `DELETE` and must not call `itm_crud_append_not_deleted_predicate()` (`$crudUsesSoftDelete = false` on all entry files).

## 9. Audit Logging Requirements

- **No** audit triggers on `search_index` (derived search cache, not business source of truth).

## 10. Common Pitfalls

- Editing index rows without updating source module leaves stale palette results — prefer re-save on source record or backfill.
- `keywords` may be long; FULLTEXT minimum word length affects match behavior on MySQL.

## 12. Module Owner Notes

- Helpers: `includes/itm_search_index.php` (loaded from `config/config.php` paths as needed)
- Canonical DDL: `db/01_schema.sql`
- Backfill: `php scripts/apply_search_index_backfill.php`
