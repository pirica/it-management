# AGENT_NOTES.md - idfs/api

## 1. Module Purpose
AJAX API for IDF rack/position/port/link operations. Keeps `idf_ports`, `switch_ports`, `equipment`, `idf_positions`, `idfs`, and `idf_links` synchronized.

## 4. Business Rules (Critical for Agents)
- **Protection Zone** — change only when explicitly requested.
- **IDF sync guardrail (mandatory):** partial cross-table updates are forbidden; use transactions and rollback on failure.
- Endpoints include: `position_save`, `position_delete`, `position_copy`, `position_move`, `position_reorder`, `link_create`, `link_delete`, `port_update`, `ports_sync`, `ports_regen`, `switch_port_row`, `cable_color_add`, `switch_status_add`.
- Run `php scripts/idfs_sync_human_test.php` after any workflow change.

## 7. File Structure
- `_bootstrap.php` — shared API bootstrap.
- `*.php` — one action per file.
- `index.html` — directory listing guard.

## 12. Module Owner Notes (Optional)
Parent: `modules/idfs/AGENT_NOTES.md`. Human-flow regression is mandatory per `AGENTS.md`.
