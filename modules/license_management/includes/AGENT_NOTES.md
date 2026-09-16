# AGENT_NOTES.md - License Management Includes

## 1. Module Purpose
Holds tab panes included from `modules/license_management/index.php`. Does not bootstrap its own session or database connection.

## 2. Key Tables
- **equipment** — assets listed on the Equipment tab.
- **equipment_software** — installed catalog software per asset.
- **software** — catalog rows for the Software select filter.
- **software_license_links** / **license_management** — optional license names shown per asset.

## 3. Required Relationships
- **equipment_software** → **equipment** and **software** (`company_id` scoped).
- **software_license_links** → **software** and **license_management**.

## 4. Business Rules (Critical for Agents)
- Included only when `tab=equipment` on the parent list (`index` / `list_all`).
- Software filter is GET `software_id`; empty = all catalog software that is installed on equipment.
- Search runs in PHP after `itm_software_license_list_equipment()` (name, hostname, serial, status, assignee, software, license labels).
- Read-only of license rows — no bulk delete, import, or sample-data on this tab. Equipment **View** / **Edit** open `modules/equipment/`.

## 5. UI Behavior Requirements
- Software `<select>` auto-submits on change.
- Equipment / software / license cells link to the matching `view.php` (labels, not raw IDs).
- Actions: emoji-only 🔎 View and ✏️ Edit to equipment `view.php` / `edit.php`; `itm-actions-cell` + `data-itm-actions-origin="1"`.
- Table opts out of table-tools import/export (`data-itm-no-import-excel`, `data-itm-no-export-*`).
- Pagination uses ⏮️ / ◀️ / ▶️ / ⏭️ and preserves `tab=equipment`, `software_id`, `search`.

## 6. API Actions (If Applicable)
- None. Parent `index.php` owns import AJAX and license CRUD POSTs.

## 7. File Structure
- **tab_equipment.php** — Equipment tab list, Software filter, search, pagination.
- **index.html** — directory listing placeholder.

## 8. Multi-Tenant Rules
- All queries use parent `$company_id`. Invalid `software_id` values are ignored.

## 9. Audit Logging Requirements
- None (read-only).

## 10. Common Pitfalls
- Do not require `config.php` from this folder — parent already loaded it.
- Default Licenses tab must keep `data-itm-db-import-endpoint="index.php"` on the license table (MBQA / index contract).

## 11. Examples of Safe Code Patterns
Use `itm_software_license_list_equipment($conn, $companyId, $softwareId)` from `includes/itm_software_license_link.php`.

## 12. Module Owner Notes (Optional)
Browser: [index.php?tab=equipment](http://localhost/it-management/modules/license_management/index.php?tab=equipment) (signed-in session).
