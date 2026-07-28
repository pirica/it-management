# AGENT_NOTES.md - Approvals Stage

## 1. Module Purpose
Lookup table for different stages in the approval workflow (e.g., "Finance Review", "GM Approval").

## 2. Key Tables
- **approvals_stage** — stores approval stage names and descriptions.

## 3. Required Relationships
- **approvals_stage** → depends on **companies**.
- **approvals_stage** → referenced by **approvals**.

## 4. Business Rules (Critical for Agents)
- **Unique Stage Name**: The `stage` name must be unique within a `company_id`.

## 5. UI Behavior Requirements
- **Standard CRUD**.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers (`trg_approvals_stage_audit_*`).

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- **Deleting In-Use Stages**: Deleting a stage that is currently referenced by an approval record may cause issues or be blocked by FK constraints. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM approvals_stage WHERE company_id = ?");
$stmt->bind_param("i", $companyId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO approvals_stage (company_id, stage, description) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $companyId, $stageName, $description);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Standardize stage names across companies where possible for reporting consistency.
## Share (temporary QR / code)
- **Capable:** `itm_qr_share_capable_module_slugs()`.
- **UI:** Share buttons on index.php inline view block.
- **Wiring:** `includes/itm_crud_record_share.php`; public `join.php`; AJAX `index.php?ajax_action=create_share_session`. Company gate: `modules/share_modules/`.
- **Doc:** `docs/CRUD_RECORD_SHARE.md`.
