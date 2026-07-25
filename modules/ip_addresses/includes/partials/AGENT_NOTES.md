# AGENT_NOTES.md - ip_addresses/includes/partials

## 1. Module Purpose
HTML/PHP partial templates included by `modules/ip_addresses/index.php` and related screens.

## 5. UI Behavior Requirements
- **Settings list contract:** Search card (`Search (all fields)`), sort headers ▲/▼, server pagination (`itm_resolve_records_per_page()`), `data-itm-db-import-endpoint="index.php"`, Actions `th`/`td` with `itm-actions-cell` + `data-itm-actions-origin="1"`.
- **Thin-router audits:** Static checks merge this file from `index.php` via `itm_ui_merge_thin_router_audit_content()`; IPAM search/sort/pagination helpers in `itm_ui_list_contract_checks.php` detect `itm_ipam_*` list wiring.
- FK columns must show subnet/name labels, not raw IDs.

## 10. Common Pitfalls

[Confirmed] No pitfalls documented

## 12. Module Owner Notes (Optional)
Keep partials free of standalone request handling — auth and CSRF belong in parent entry files.
