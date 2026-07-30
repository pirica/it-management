# AGENT_NOTES.md - HK Maintenance Status

## 1. Module Purpose

Lookup for room maintenance types: **Out of Order** (`ooo`) and **Out of Service** (`oos`). Used by `hotel_booking_housekeeping_maintenance`.

## 2. Key Tables

- **hotel_booking_housekeeping_maintenance_status** — `name`, `code`, tenant-scoped, standard audit columns.

## 3. Seeds

`db/02_data.sql` seeds `ooo` / `oos` for company 1 with cross-company `INSERT IGNORE` replication.

## 4. UI

Flattened scaffold CRUD (`index.php`, `create.php`, `edit.php`, `view.php`, `list_all.php`, `delete.php`).
