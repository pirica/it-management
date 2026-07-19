# AGENT_NOTES.md - ip_subnets/includes/partials

## 1. Module Purpose
Partial templates for IP Subnets list and detail UI.

## 5. UI Behavior Requirements
- **Settings list contract:** Search card (`Search (all fields)`), sort headers ▲/▼, server pagination (`itm_resolve_records_per_page()`), `data-itm-db-import-endpoint="index.php"`, Actions `th`/`td` with `itm-actions-cell` + `data-itm-actions-origin="1"`.
- **Thin-router audits:** Static checks merge this file from `index.php` via `itm_ui_merge_thin_router_audit_content()`.

## 10. Common Pitfalls

- Index list inserts a **Generate host IPs** column immediately before **Active** — keep header/body colspan in sync when changing list columns. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Coordinate column changes with `modules/ip_addresses/` when subnet labels are shared. Index list inserts a **Generate host IPs** column immediately before **Active**; keep header/body colspan in sync when changing list columns.
