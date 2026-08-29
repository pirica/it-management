# AGENT_NOTES.md - Hotel Booking Room Type Base Prices

---

## 1. Module Purpose

This module manages the base pricing of room types per hotel within a company. It defines the default price per night for a given room type at a specific hotel.

---

## 2. Key Tables

- **hotel_booking_room_type_base_prices** — stores the base price per night configuration, scoped by company, hotel, and room type.

---

## 3. Required Relationships

- **hotel_booking_room_type_base_prices** → depends on **companies** (`company_id`, `ON DELETE CASCADE`)
- **hotel_booking_room_type_base_prices** → depends on **hotel_booking_hotels** (`hotel_id`, `ON DELETE CASCADE`)
- **hotel_booking_room_type_base_prices** → depends on **booking_rooms_types** (`room_type_id`, `ON DELETE CASCADE`)

---

## 4. Business Rules (Critical for Agents)

- A unique constraint `uq_hb_room_type_base_prices` enforces that only one base price record can exist for a specific combination of `company_id`, `hotel_id`, and `room_type_id`.
- The `price_per_night` value must be a valid non-negative decimal.

---

## 5. UI Behavior Requirements

### Flattened CRUD (`modules/hotel_booking_room_type_base_prices/index.php` with `$crud_table`)

- Support search, sorting, and server-side pagination with the record limit set by `itm_resolve_records_per_page()`.
- Bulk delete/Clear table options are shown when `$totalRows >= $perPage`.
- Search queries visible fields, including support for FK label searches like searching for hotel name or room type name instead of numeric IDs.
- Hide **`company_id`** from list/view/forms (`$hideCompanyIdTables` includes `hotel_booking_room_type_base_prices`).
- **`hotel_id`** and **`room_type_id`** on list/view show hotel and room-type **names** via `cr_fk_label_for_id()` + `$GLOBALS['fkMap']` in every `cr_render_cell_value()` copy (`index.php`, `list_all.php`, `view.php`, `edit.php`).
- **`active`** on list/view uses Active/Inactive badges (audit scaffold); create/edit use checkbox double-label pattern. Schema column is **`tinyint(1)`** (matches `check_crud_boolean_cell_display.php`). No other tinyint checkbox columns on this table.
- Table headers and Actions cells must include `class="itm-actions-cell"` and `data-itm-actions-origin="1"`.
- CSRF validation is enforced on create/edit/delete operations using `cr_require_valid_csrf_token()`.
- Active checkbox is rendered using the double-label `itm-checkbox-control` pattern.

---

## 6. API Actions (If Applicable)

- **import_excel_rows** — JSON POST on `index.php` to import base prices from Excel or CSV.

---

## 7. File Structure

- **index.php** — primary list view with search, pagination, and bulk delete features.
- **create.php** — form wrapper that configures `$crud_action = 'create'` and loads `index.php`.
- **edit.php** — separate form view to edit existing records.
- **delete.php** — form wrapper that configures `$crud_action = 'delete'` and loads `index.php` to handle deletions.
- **view.php** — separate details view showing all record attributes.
- **list_all.php** — alternative full table view.

---

## 8. Multi-Tenant Rules

- All pricing records are strictly filtered by the logged-in employee's `company_id`.
- Foreign key options (hotels and room types dropdowns) are company-scoped.

---

## 9. Audit Logging Requirements

### Database triggers

- Automated audit logging is handled by the MySQL triggers in `db/03_triggers.sql`:
  - `trg_hotel_booking_room_type_base_prices_audit_insert`
  - `trg_hotel_booking_room_type_base_prices_audit_update`
  - `trg_hotel_booking_room_type_base_prices_audit_delete`
- Actor context: `@app_employee_id`, `@app_company_id` are populated automatically on database session establishment.

---

## 10. Common Pitfalls

- Since parent-child references (hotels/room types) use `ON DELETE CASCADE`, deleting a hotel or a room type will automatically delete all associated base price records.
- Ensure that the unique constraint `uq_hb_room_type_base_prices` is handled gracefully. Inserting or updating a record to have a duplicate `company_id` + `hotel_id` + `room_type_id` will trigger a database constraint violation.

---

## 11. Examples of Safe Code Patterns

### Safe SELECT

```php
$stmt = $conn->prepare('SELECT * FROM hotel_booking_room_type_base_prices WHERE company_id = ? AND id = ?');
$stmt->bind_param('ii', $companyId, $id);
$stmt->execute();
```

### Safe INSERT

```php
$stmt = $conn->prepare('INSERT INTO hotel_booking_room_type_base_prices (company_id, hotel_id, room_type_id, price_per_night) VALUES (?, ?, ?, ?)');
$stmt->bind_param('iiid', $companyId, $hotelId, $roomTypeId, $pricePerNight);
$stmt->execute();
```

---

## 12. Module Owner Notes (Optional)

- Regression scripts: `php scripts/verify_hotel_booking.php` and `php scripts/run_tests.php` to verify overall hospitality features.
