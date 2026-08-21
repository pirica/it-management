---
name: sync-db-table-count-docs
description: >-
  Sync IT Management documentation when MySQL table count changes. Reads
  count_db_tables.php, verify_database_schema.php, and db/01_schema.sql CREATE
  TABLE count; updates README, handoff, db/AGENT_NOTES, FEATURE_ROADMAP, and
  scripts/SCRIPTS.md. Use when the user reports Actual tables (MySQL), when
  number_db_tables.txt changes, or after adding tables to db/01_schema.sql.
---

# Sync DB table count documentation

## Authoritative count (resolve before editing docs)

1. `php scripts/count_db_tables.php` — echoes count; writes `scripts/number_db_tables.txt`
2. `php scripts/verify_database_schema.php` — prints `Actual tables (MySQL): N`
3. Must match `grep -c '^CREATE TABLE' db/01_schema.sql`

If any differ, fix schema/import first — **do not** patch docs to a wrong number.

**Windows Dunebox:** use full `php.exe` path from `AGENTS.md` → Setup & Debugging.

## Files to update (same PR)

Replace stale table-count prose with the resolved **N**:

| File | Section |
|------|---------|
| `db/AGENT_NOTES.md` | **Fresh-import scale (canonical bundle)** |
| `README.md` | Database Structure Overview intro, **Tables** stats row, category **Total** row |
| `handoff.md` | Domain intro (§3-style sizing) and §4.4 distinct tables |
| `docs/FEATURE_ROADMAP.md` | Opening platform stats (`N tables`) |
| `scripts/SCRIPTS.md` | `count_db_tables.php` catalog row |

`scripts/number_db_tables.txt` is maintained by `count_db_tables.php` — do not hand-edit unless the script was not run.

## Do not change

- README category breakdown rows unless intentionally reconciling sums
- Unrelated **205** (e.g. Microsoft Support Atom feed count in news docs)
- Hardcoded counts inside application PHP

## Find stale copy

```bash
rg "205 table|209 table|205 distinct|\*\*205\*\*.*table|\*\*209\*\*.*table|Fresh.*import.*[0-9]+ tables" \
  README.md handoff.md db/AGENT_NOTES.md docs/FEATURE_ROADMAP.md scripts/SCRIPTS.md
```

## Verification

```bash
php scripts/count_db_tables.php
php scripts/verify_database_schema.php
rg "205 table|209 table|205 distinct" README.md handoff.md db/AGENT_NOTES.md docs/FEATURE_ROADMAP.md scripts/SCRIPTS.md
```

## Agent reply links (mandatory)

Document probes with full localhost markdown links; tell the user to open in a new browser tab:

- [count_db_tables.php](http://localhost/it-management/scripts/count_db_tables.php) (no login)
- [verify_database_schema.php?run=1](http://localhost/it-management/scripts/verify_database_schema.php?run=1)

See `.cursor/rules/local-dev-browser-links.mdc` and `AGENTS.md` step **7a**.

## Related AGENTS.md docs matrix

When `db/` scale changes, also confirm `handoff.md` §4.4 cross-references and `db/AGENT_NOTES.md` pointers to `README.md` stay aligned.
