# AGENT_NOTES.md - Hotel Booking Room Type Base Prices

---

## 1. Module Purpose

This module manages the base pricing per night for each room type and hotel combination within a company. These base prices are used by the public booking portal and other reservation utilities to calculate nightly room rates.

---

## 2. Key Tables

- **hotel_booking_room_type_base_prices** — stores base price per night per room type and hotel.

---

## 3. Required Relationships

- **hotel_booking_room_type_base_prices** → depends on **companies** via `company_id` (`hb_room_type_base_prices_ibfk_company`, `ON DELETE CASCADE`)
- **hotel_booking_room_type_base_prices** → depends on **hotel_booking_hotels** via `hotel_id` (`hb_room_type_base_prices_ibfk_hotel`, `ON DELETE CASCADE`)
- **hotel_booking_room_type_base_prices** → depends on **booking_rooms_types** via `room_type_id` (`hb_room_type_base_prices_ibfk_type`, `ON DELETE CASCADE`)

---

## 4. Business Rules (Critical for Agents)

- Prices must be strictly scoped by `company_id`.
- There is a unique key constraint on the combination of `company_id`, `hotel_id`, and `room_type_id`.
- Normal base price calculations fallback to `0.00` if no record exists for a given room type and hotel.

---

## 5. UI Behavior Requirements

### Flattened CRUD (`modules/hotel_booking_room_type_base_prices/index.php`)

- Search, sort, server-side pagination (`records_per_page`)
- Bulk delete when `$totalRows >= $perPage`
- `$displayFieldColumns = $uiColumns` before search block when search uses `$displayFieldColumns`
- Hide `company_id` from list/view/forms
- Actions column: `class="itm-actions-cell"` and `data-itm-actions-origin="1"`
- Import: `data-itm-db-import-endpoint="index.php"` on the table that handles `import_excel_rows`
- **CSRF:** POST handlers use `cr_require_valid_csrf_token()` and forms include `csrf_token` from `itm_get_csrf_token()`.
- **`active` checkbox:** double-label `itm-checkbox-control` pattern

---

## 6. API Actions (If Applicable)

- **import_excel_rows** — JSON POST on `index.php` for bulk imports.

---

## 7. File Structure

- **index.php** — list view with search, sort, pagination, and bulk delete controls.
- **create.php** — create form for new base price entries.
- **edit.php** — update form for existing base price entries.
- **delete.php** — delete handler implementing soft-delete.
- **view.php** — detail view showing the audit columns.
- **list_all.php** — alternative list view wrapper.

---

## 8. Multi-Tenant Rules

- All queries filter by `company_id` from the session.

---

## 9. Audit Logging Requirements

### Database triggers

- Triggers `trg_hotel_booking_room_type_base_prices_audit_insert`, `trg_hotel_booking_room_type_base_prices_audit_update`, and `trg_hotel_booking_room_type_base_prices_audit_delete` write to `audit_logs` on any insertion, modification, or deletion.
- Actor context: `@app_employee_id`, `@app_company_id` from `config/config.php`.

---

## 10. Common Pitfalls

- Since parent tables (companies, hotel_booking_hotels, booking_rooms_types) cascade delete, child records in this table are deleted automatically.
- Do not bypass `company_id` scoping when updating or retrieving base prices.

---

## 11. Examples of Safe Code Patterns

### Safe SELECT

```php
$stmt = $conn->prepare('SELECT price_per_night FROM hotel_booking_room_type_base_prices WHERE company_id = ? AND hotel_id = ? AND room_type_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('iii', $companyId, $hotelId, $roomTypeId);
$stmt->execute();
```

### Safe INSERT

```php
$stmt = $conn->prepare('INSERT INTO hotel_booking_room_type_base_prices (company_id, hotel_id, room_type_id, price_per_night, active, created_by) VALUES (?, ?, ?, ?, 1, ?)');
$stmt->bind_param('iiidi', $companyId, $hotelId, $roomTypeId, $pricePerNight, $employeeId);
$stmt->execute();
```

---

## 12. Module Owner Notes (Optional)

Regression check: `php scripts/check_crud_audit_soft_delete.php` or `php scripts/run_tests.php`.
