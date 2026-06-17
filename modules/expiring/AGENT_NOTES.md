# AGENT_NOTES.md - Expiring

## 1. Module Purpose
Read-only dashboard for upcoming and past expirations. Aggregates dates from **equipment** (warranty, certificate) and **alerts** (end dates) — there is **no** `expiring` table.

## 2. Key Tables (read-only sources)
- **equipment** — `warranty_expiry`, `certificate_expiry` (joined to **warranty_types** for labels).
- **alerts** — `end_datetime` (only alerts with a valid end date are listed; null/empty end dates count as "unknown").

## 3. Required Relationships
- **equipment** → **companies**, **warranty_types**.
- **alerts** → **companies**, visibility via assigned user / creator (same rules as Alerts module).

## 4. Business Rules (Critical for Agents)
- **No CRUD on this module** — Tier D bespoke smoke; do not add an `expiring` table or standard delete/import flows.
- **Badge thresholds:** expired (red), ≤30 days (red), ≤90 days (warning), else success (`expiring_days_left_badge()`).
- **Alert visibility:** respects private vs global alert rules when counting/listing alert expirations.
- **Date parsing:** supports `Y-m-d`, `d/m/Y`, `m/d/Y` via `expiring_parse_date()`.

## 5. UI Behavior Requirements
- Summary counts per field (expired, unknown, &lt;30d, &gt;60d).
- Tabbed or sectioned lists for certificate vs warranty rows.
- Badge rendering via `expiring_days_left_badge()` (expired red, ≤30d red, ≤90d warning, else success).
- No bulk delete / import / sample-data CRUD on a dedicated table.
- `data-itm-no-export-*` may be set on filter cards where exports should omit controls.

## 6. API Actions (If Applicable)
- None — read-only dashboard; no JSON import or AJAX mutation endpoints.

## 7. File Structure
- `index.php` — dashboard, queries equipment + alerts, helper functions for badges/dates.

## 8. Multi-Tenant Rules
- All queries filter by active `company_id` from session.

## 9. Audit Logging Requirements
- Read-only aggregator — no writes; source modules (equipment, alerts) log their own changes via triggers.

## 10. Common Pitfalls
- Do not document or query a fictional **`expiring`** table.
- Warranty join may fall back without `warranty_types` when join fails — preserve fallback query path.
- Alert visibility must mirror Alerts module rules (global vs private) when counting alert expirations.

## 11. Examples of Safe Code Patterns

### Date parsing helper (multi-format)
```php
$parsed = expiring_parse_date($rawDate); // supports Y-m-d, d/m/Y, m/d/Y
if ($parsed instanceof DateTimeImmutable) {
    $daysLeft = (int)$today->diff($parsed)->format('%r%a');
}
```

## 12. Module Owner Notes (Optional)
Module browser QA: Tier D navigation smoke only (`list`, `search`, `sort`).
