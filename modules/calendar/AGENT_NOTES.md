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
- **Aggregated view only** — mutations happen in source modules, not on a calendar table.
- **Alerts:** include only alerts that have `end_datetime` populated.
- **Grid layout:** Monday–Sunday week columns (UK English labels).
- **Tenant isolation:** never mix companies on the grid.

## 5. UI Behavior Requirements
- Month/week grid with category/type colour coding.
- Filtering by event category or source type where implemented.

## 7. File Structure
- `index.php` — calendar UI and aggregated queries.

## 8. Multi-Tenant Rules
- Every fetch uses session `company_id`.

## 9. Audit Logging Requirements
- None on calendar itself; source modules log changes.

## 10. Common Pitfalls
- Timezone/date formatting mismatches with grid library.
- Omitting equipment or ticket due dates from sync when adding new calendar sources.

## 12. Module Owner Notes (Optional)
When adding a new date source, update calendar sync logic and this file in the same PR.
