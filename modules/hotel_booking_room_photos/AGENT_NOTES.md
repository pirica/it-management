# AGENT_NOTES.md - Room Photos

## 1. Module Purpose

This module manages the storage, metadata, and visual display of photo assets associated with hotel rooms within the hospitality management system.

---

## 2. Key Tables

- **hotel_booking_room_photos** — Stores metadata about uploaded room photos, including paths, sort order, cover photo status, and audit columns (`deleted_at` exists for legacy/list filtering but deletes are hard).

---

## 3. Required Relationships

- **hotel_booking_room_photos** → depends on **hotel_booking_rooms** (`room_id`, `ON DELETE CASCADE`)
- **hotel_booking_room_photos** → depends on **companies** (`company_id`, `ON DELETE CASCADE`)

---

## 4. Business Rules (Critical for Agents)

- **Hard delete policy:** On deletion (single, bulk, or clear table), permanently `DELETE` the database row and unlink the physical image file. Shared helpers: `itm_hotel_booking_room_photos_hard_delete()`, `itm_hotel_booking_photo_delete_files_for_rows()` in `includes/itm_hotel_booking.php`.
- **Cover uniqueness:** At most one `is_cover = 1` photo per `room_id` per company. Use `itm_hotel_booking_photo_clear_cover_for_parent()` on create/edit when setting cover.
- **Tenant isolation:** All operations are scoped by session `company_id`.
- **File integrity:** Allowed formats: JPG, JPEG, PNG, GIF, WEBP.
- **Multiple uploads:** Create form accepts multiple files in one POST.
- **Storage location:** `booking/images/{hotel_id}/room_photos/` (hotel resolved from `hotel_booking_rooms.hotel_id`).

---

## 5. UI Behavior Requirements

### Standard CRUD Views

- **View toggle:** Gallery grid (default) and table list.
- **Table view:** Thumbnail column, room label (`Room Number - Name`), sort order, cover/active badges, actions.
- **Export/import opt-out:** Table uses `data-itm-no-import-excel="1"`, `data-itm-no-export-excel="1"`, `data-itm-no-export-pdf="1"` — photos are upload-only; no Excel metadata import.
- **No sample data button:** No rows in `db/02_data_sample.sql` (binary assets).
- **RBAC:** `view` on `index.php` / `view.php`; `create` / `edit` / `delete` on respective entry files.
- **Form controls:** `active` and `is_cover` use the double-label checkbox pattern.
- **NO MIXED labels:** Emoji-only visible action labels with phrases in `title` only.

---

## 6. API Actions (If Applicable)

- `None` (standard CRUD pages only).

---

## 7. File Structure

- **index.php** — Gallery + table list, search, pagination, bulk delete.
- **create.php** — Multi-file upload form.
- **edit.php** — Metadata edit and optional file replace / room move.
- **delete.php** — Hard delete + file unlink (`itm_require_post_csrf()`).
- **view.php** — Detail with audit meta via `itm_crud_render_audit_cell_value()`.
- **list_all.php** — Standard list wrapper.
- **index.html** — Directory listing guard.

---

## 8. Multi-Tenant Rules

- Queries filter `company_id` from session.
- Upload paths include `company_id` and `room_id`.

---

## 9. Audit Logging Requirements

- Triggers `trg_hotel_booking_room_photos_audit_insert|update|delete` in `db/03_triggers.sql`. Hard `DELETE` fires the delete trigger.

---

## 10. Common Pitfalls

- **Do not soft-delete:** Use hard delete helpers; do not call `itm_crud_build_soft_delete_sql()` for this module.
- **Cover flag:** Clear sibling covers before setting `is_cover = 1`.
- **Filename collision:** Store as `hb_` + random hex + extension.
- **Directory permissions:** Use `itm_ensure_upload_directory()` for upload paths.

---

## 11. Examples of Safe Code Patterns

### Safe SELECT (tenant scoped)

```php
$stmt = $conn->prepare('SELECT * FROM hotel_booking_room_photos WHERE company_id = ? AND id = ? AND deleted_at IS NULL');
$stmt->bind_param('ii', $companyId, $id);
$stmt->execute();
```

### Hard delete (shared helper)

```php
itm_hotel_booking_room_photos_hard_delete($conn, $companyId, [$photoId]);
```

---

## 12. Module Owner Notes

- Core helpers: `includes/itm_hotel_booking.php`, `includes/bootstrap_helpers.php`.
- Regression: `php scripts/verify_hotel_booking.php` (hospitality slug list + delete hard-delete guard).
