# AGENT_NOTES.md - Room Photos

## 1. Module Purpose

This module manages the storage, metadata, and visual display of photo assets associated with hotel rooms within the hospitality management system.

---

## 2. Key Tables

- **hotel_booking_room_photos** — Stores metadata about uploaded room photos, including paths, sort order, cover photo status, and soft-delete/audit attributes.

---

## 3. Required Relationships

- **hotel_booking_room_photos** → depends on **hotel_booking_rooms** (`room_id`, `ON DELETE CASCADE`)
- **hotel_booking_room_photos** → depends on **companies** (`company_id`, `ON DELETE CASCADE`)

---

## 4. Business Rules (Critical for Agents)

- **Hard Delete Policy:** On deletion (single delete, bulk delete, or clear table), both the database record and the corresponding physical image file on disk MUST be permanently (hard) deleted.
- **Tenant Isolation:** All operations (list, create, edit, delete, view) are strictly filtered and scoped by the active company session ID (`company_id`).
- **File Integrity:** Allowed image formats are restricted to JPG, JPEG, PNG, GIF, and WEBP.
- **Multiple Uploads:** Multiple photos can be uploaded simultaneously during creation.
- **Storage Location:** Uploaded files are securely stored under `images/hotel_booking/{company_id}/room/{room_id}/`.

---

## 5. UI Behavior Requirements

### Standard CRUD Views

- **View Toggle:** Supports switching between a tabular list view ("Table View") and a visual grid card gallery ("Gallery View").
- **Table view:** Includes a photo thumbnail column, room reference label (displaying `Room Number - Name`), sort order, cover badge, active status badge, and action controls.
- **Form Controls:** Checkbox fields for `active` and `is_cover` strictly follow the double-label pattern and update indicators dynamically.
- **No Mixed Labels:** All buttons, form actions, and headers follow the strict emoji-only visible label policy.

---

## 6. API Actions (If Applicable)

- `None` (standard CRUD pages only).

---

## 7. File Structure

- **index.php** — Main landing list page with Table and Gallery views.
- **create.php** — Creation form supporting multiple photo uploads.
- **edit.php** — Edit metadata (sort order, active status, is_cover) or replace current photo.
- **delete.php** — Handles permanent hard-deletion of records and physical image files.
- **view.php** — Detailed record view showing metadata and full photo preview.
- **list_all.php** — Standard CRUD alternate list wrapper.
- **index.html** — Prevent directory listings.

---

## 8. Multi-Tenant Rules

- Queries are scoped strictly to the session `company_id`.
- Directory paths are generated using `company_id` to maintain strict tenant folder isolation.

---

## 9. Audit Logging Requirements

- Audited via standard triggers `trg_hotel_booking_room_photos_audit_insert`, `trg_hotel_booking_room_photos_audit_update`, and `trg_hotel_booking_room_photos_audit_delete` defined in `db/03_triggers.sql`.

---

## 10. Common Pitfalls

- **Do not soft-delete:** Ensure deletion permanently drops the record and removes the file on disk.
- **Filename Collision:** Ensure files are saved with randomized unique names (`hb_` prefix with random hex) using system helpers.
- **Directory Permissions:** Ensure directory chains are created securely using helper functions.

---

## 11. Examples of Safe Code Patterns

### Safe SELECT (Tenant Scoped)

```php
$stmt = $conn->prepare('SELECT * FROM hotel_booking_room_photos WHERE company_id = ? AND id = ?');
$stmt->bind_param('ii', $companyId, $id);
$stmt->execute();
```

### Safe HARD DELETE

```php
$stmt = $conn->prepare('DELETE FROM hotel_booking_room_photos WHERE id = ? AND company_id = ?');
$stmt->bind_param('ii', $id, $companyId);
$stmt->execute();
```

---

## 12. Module Owner Notes

- Core helpers: `includes/itm_hotel_booking.php` and `includes/bootstrap_helpers.php`.
