# AGENT_NOTES.md - Configuration Items (CMDB Lite)

## 1. Module Purpose

Tenant-scoped configuration items (CIs) and dependency relationships for blast-radius / impact analysis. Auto-syncs equipment and IDF records into CIs; manual edges for applications and services.

## 2. Key Tables

- **configuration_item_types** — CI type catalog (builtin + equipment_type-linked + custom)
- **configuration_items** — CI records (`external_ref`, optional `record_module_slug` / `record_id`)
- **configuration_item_relationships** — directed edges (`depends_on`, `hosts`, `connects_to`, `runs_on`)

## 3. Required Relationships

- **configuration_items** → **configuration_item_types** (`ci_type_id`, RESTRICT)
- **configuration_item_relationships** → **configuration_items** (`parent_ci_id`, `child_ci_id`, CASCADE)

## 4. Business Rules (Critical for Agents)

- Edge semantics: `parent_ci_id` is upstream/provider; `child_ci_id` is downstream/dependent (`depends_on` = child depends on parent).
- Cycle detection on add via `itm_cmdb_would_create_cycle()` in `includes/itm_cmdb.php`.
- Auto-sync: equipment, IDF (`hosts` for rack equipment), **ip_subnets** (`connects_to` from equipment IPs), **system_access** (Application CI type).
- Change requests: `modules/change_requests/` picks affected CIs from impact graph (`includes/itm_change_requests.php`).
- `external_ref` format: `{module_slug}:{record_id}` (e.g. `equipment:123`).

## 5. UI Behavior Requirements

- Flattened CRUD list on `index.php`; bespoke `view.php` with Details / Relationships / Impact tabs.
- Impact tab: read-only SVG tree (org-chart style layout).
- Equipment and IDF `view.php` embed CMDB card via `includes/itm_cmdb_card.php`.

## 6. API Actions (If Applicable)

- **GET `api.php?action=impact&id=`** — BFS subgraph JSON
- **POST `action=add_relationship`** — CSRF + cycle check
- **POST `action=delete_relationship`** — soft-delete edge

## 7. File Structure

- **index.php** — list CRUD
- **view.php** — bespoke detail shell (sidebar + header + `styles.css`); Details / Relationships / Impact tabs; toolbar edit + delete
- **create.php**, **edit.php**, **delete.php**, **list_all.php** — scaffold wrappers
- **api.php** — JSON impact / relationship mutations

## 8. Multi-Tenant Rules

- All queries filter `company_id` from session.

## 9. Audit Logging Requirements

- `trg_configuration_items_audit_*`, `trg_configuration_item_types_audit_*`, `trg_configuration_item_relationships_audit_*` in `db/03_triggers.sql`.

## 10. Common Pitfalls

- Do not skip cycle check when adding relationships.
- `record_module_slug` / `record_id` are set by auto-sync — avoid overwriting on manual edit unless intentional.
- Unique `(company_id, external_ref)` — one CI per synced source record.

## 11. Examples of Safe Code Patterns

```php
$ci = itm_cmdb_find_ci_by_record($conn, $companyId, 'equipment', $equipmentId);
$graph = itm_cmdb_build_impact_graph($conn, $companyId, (int)$ci['id']);
```

## 12. Module Owner Notes (Optional)

- Regression: `php scripts/verify_cmdb.php`
- **Add sample data:** `itm_seed_insert_configuration_items_sample_rows()` — three demo CIs plus `depends_on` / `runs_on` edges; empty gate uses `itm_seed_tenant_row_count()` (live rows only).
- Core helper: `includes/itm_cmdb.php`
- Types admin: `modules/configuration_item_types/`
