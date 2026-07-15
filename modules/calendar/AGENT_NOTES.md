# AGENT_NOTES.md - Calendar

## 1. Module Purpose
Central calendar grid aggregating time-sensitive records from multiple modules into one company-scoped view.

## 2. Key Tables (read-only sources)
- **events** — scheduled events.
- **event_categories** — colour/label for events.
- **alerts** — only rows with **`end_datetime`** set.
- **tickets** — tasks with **`due_date`**.
- **equipment** — **`certificate_expiry`** and **`warranty_expiry`**.
- **patches_updates** — patch/update schedule dates (when configured in calendar sync).

## 3. Required Relationships
- All sources → **companies** (tenant filter on every query).

## 4. Business Rules (Critical for Agents)
- **No calendar table** — the grid aggregates source modules. Day-to-day mutations happen in those source modules, **except** ICS import on this module, which inserts rows into **events** for the active company.
- **Alerts:** include only alerts that have `end_datetime` populated.
- **Grid layout:** Monday–Sunday week columns (UK English labels).
- **Tenant isolation:** never mix companies on the grid.

## 5. UI Behavior Requirements
- Month/week/day/year views via `?view=` (`month` default).
- Monday–Sunday week columns (UK English labels).
- ICS import form (`POST` + `ics_file` upload) creates rows in **events** for the active company.
- ICS export via `?export=ics` (events in ±1 year window).
- Integrated sources skipped when parent module disabled (`has_module_access()` per slug).
- Filtering by event category or source type where implemented; colour coding from `event_categories` / priority colours.
- **Responsive:** side panel stacks above calendar below 768px; week/day grids scroll horizontally on narrow screens.

## 6. API Actions (If Applicable)
- **ICS import** (POST `index.php`, `ics_file`) — parses `BEGIN:VEVENT` blocks, inserts into **events** with `company_id` scope; RFC 5545 line folding handled.
- **ICS export** (GET `index.php?export=ics`) — streams `text/calendar` for tenant events in range.
- No standalone `api.php`; aside from ICS import on this module, mutations happen in source modules.

## 7. File Structure
- `index.php` — calendar grid, ICS import/export, aggregated queries for events/alerts/tickets/equipment/patches.

## 8. Multi-Tenant Rules
- Every fetch uses session `company_id`.

## 9. Audit Logging Requirements
- None on calendar itself; source modules log changes.

## 10. Common Pitfalls
- Timezone/date formatting mismatches with grid library. [Valid]-[2026-07-15]
- Omitting equipment or ticket due dates from sync when adding new calendar sources. [Valid]-[2026-07-15]
- Including alerts without `end_datetime` — calendar query requires end date for multi-day span rendering. [Valid]-[2026-07-15]
- Fetching integrated sources when `company_module_access` has disabled the parent module — always gate with `has_module_access()`. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Gated alert fetch (end_datetime required)
```php
if (has_module_access($conn, $companyId, 'alerts')) {
    $sql = "SELECT a.*, ec.name AS category_name, ec.color AS category_color
            FROM alerts a
            LEFT JOIN event_categories ec ON ec.id = a.category_id AND ec.company_id = a.company_id
            WHERE a.company_id = ? AND a.end_datetime IS NOT NULL
              AND a.end_datetime BETWEEN ? AND ?";
}
```

## 12. Module Owner Notes (Optional)
When adding a new date source, update calendar sync logic and this file in the same PR.
