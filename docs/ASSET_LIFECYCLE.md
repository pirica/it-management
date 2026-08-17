# Asset Lifecycle & Depreciation

Equipment financial lifecycle tracking: stage transitions, straight-line depreciation, disposal audit, and Reports Hub visibility.

## Schema (`equipment`)

| Column | Purpose |
|--------|---------|
| `lifecycle_stage` | `procurement`, `in_service`, `maintenance`, `retired`, `disposed`, `written_off` (see `itm_asset_lifecycle_stages()`) |
| `depreciation_start_date` | Start of straight-line schedule |
| `useful_life_months` | Depreciation period |
| `salvage_value` | Floor book value |
| `disposal_date` / `disposal_reason` | Disposal audit |

Event log: `equipment_lifecycle_events` — stage changes and disposal notes.

Helpers: `includes/itm_asset_depreciation.php`.

## UI

- **Equipment edit/view** — lifecycle fields on `modules/equipment/` forms.
- **Reports Hub** — [modules/reports/index.php](http://localhost/it-management/modules/reports/index.php) doughnut chart **Asset Lifecycle Stages** via `get_asset_lifecycle_stage_summary()` in `modules/reports/api/helpers.php`.

## Depreciation math

`itm_asset_depreciation_compute_book_value()` applies straight-line depreciation from `purchase_cost`, `salvage_value`, and `useful_life_months` as of a reference date (default today).

## Regression

```bash
php scripts/verify_asset_depreciation.php
```

Browser: [verify_asset_depreciation.php?run=1](http://localhost/it-management/scripts/verify_asset_depreciation.php?run=1) (Admin session).

## Migrations

Existing databases: `db/migrations/equipment_lifecycle.sql` (destructive — back up first). Canonical shape in `db/01_schema.sql`.
