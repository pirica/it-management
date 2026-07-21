# AGENT_NOTES.md - Departments

## 1. Module Purpose
Manages company departments and their contact info (email, extension, etc.).

## 2. Key Tables
- **departments** — main department storage.

## 3. Required Relationships
- **departments** → depends on **companies**.
- **departments** → referenced by **employees**, **cost_centers**, etc.

## 4. Business Rules (Critical for Agents)
- **Unique Name**: Name must be unique per company.
- **Tenant Scope**: Isolated per company.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Deleting a department with active employees or cost centers. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM departments WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO departments (company_id, name, code) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $companyId, $name, $code);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Primary organizational unit.
## Share (temporary QR / code)
- **Capable:** `itm_qr_share_capable_module_slugs()`.
- **UI:** Share buttons on index.php inline view block.
- **Wiring:** `includes/itm_crud_record_share.php`; public `join.php`; AJAX `index.php?ajax_action=create_share_session`. Company gate: `modules/share_modules/`.
- **Doc:** `docs/CRUD_RECORD_SHARE.md`.
