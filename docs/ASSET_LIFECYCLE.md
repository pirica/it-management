# Asset Lifecycle & Depreciation

Equipment financial lifecycle tracking: stage transitions, straight-line depreciation, disposal audit, and Reports Hub visibility.

## Schema (`equipment`)

| Column | Purpose |
|--------|---------|
| `lifecycle_stage` | `procurement`, `in_service`, `maintenance`, `retired`, `disposed` (see `itm_asset_lifecycle_stages()`) |
| `depreciation_start_date` | Start of straight-line schedule |
| `useful_life_months` | Depreciation period |
| `salvage_value` | Floor book value |
| `disposal_date` / `disposal_reason` | Disposal audit |

Event log: `equipment_lifecycle_events` — stage changes, disposal, and depreciation snapshots.

Helpers: `includes/itm_asset_depreciation.php`.

## UI

- **Equipment edit** — lifecycle fields on `modules/equipment/create.php`.
- **Equipment view** — lifecycle card, timeline, and **Record disposal** form when not yet disposed (`modules/equipment/view.php` POST `record_asset_disposal` + CSRF).
- **Reports Hub** — [modules/reports/index.php](http://localhost/it-management/modules/reports/index.php) doughnut chart **Asset Lifecycle Stages** via `get_asset_lifecycle_stage_summary()` in `modules/reports/api/helpers.php`.

## Disposal workflow

`itm_asset_lifecycle_record_disposal($conn, $companyId, $equipmentId, $disposalDate, $disposalReason, $employeeId)`:

1. Sets `lifecycle_stage = disposed`, `disposal_date`, `disposal_reason`.
2. Logs `equipment_lifecycle_events` row (`event_type = disposal`).
3. Enqueues `equipment.disposed` integration webhooks when subscribers exist.

View form: disposal date (defaults today) + required reason; redirects with `?disposal=1` on success.

## Depreciation math

`itm_asset_depreciation_compute_book_value()` applies straight-line depreciation from `purchase_cost`, `salvage_value`, and `useful_life_months` as of a reference date (default today).

Monthly cron: `php scripts/run_asset_depreciation.php`.

## Regression

```bash
php scripts/verify_asset_depreciation.php
```

Browser: [verify_asset_depreciation.php?run=1](http://localhost/it-management/scripts/verify_asset_depreciation.php?run=1) (Admin session).

## Migrations

Existing databases: `db/migrations/equipment_lifecycle.sql` (destructive — back up first). Canonical shape in `db/01_schema.sql`.
