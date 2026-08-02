# AGENT_NOTES.md - Note Labels

## 1. Module Purpose
Per-user label/tag lookup for the Notes module. Stores distinct label strings a user can assign when organising notes.

## 2. Key Tables & Columns
- **note_labels** — label name per `employee_id` and `company_id`.
  - Added new standard  / hidden metadata columns:
    - `active` tinyint(1) DEFAULT '1' 
    - `deleted_by` int DEFAULT NULL 
    - `deleted_at` timestamp NULL DEFAULT NULL
    - `created_by` int DEFAULT NULL
    - `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
    - `updated_by` int DEFAULT NULL
    - `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP

## 3. Required Relationships
- **note_labels** → depends on **companies**, **employees** / **users**.
- **note_labels** → referenced by **notes** (tag filtering and import mapping).

## 4. Business Rules (Critical for Agents)
- Labels are scoped to both `company_id` and `employee_id` (private to the creating user).

## 5. UI Behavior Requirements
- Standard flattened CRUD via dynamic schema.
- List/search/sort/pagination/export/import per module standards.
- Hidden metadata columns (`deleted_by`, `deleted_at`, `created_by`, `created_at`, `updated_by`, `updated_at`) are automatically excluded from the visible `$uiColumns` to keep lists and details clean.
- The `active` status remains visible and manageable (represented by checkbox in create/edit forms).
- Hidden user-tracking metadata (`created_by` and `updated_by`) are populated with the active `$logged_user_id` and submitted via `<input type="hidden">` fields in forms.

## 6. API Actions (If Applicable)
- **import_excel_rows** (JSON POST on `index.php`) — template JSON import handler as other flattened CRUD modules (`import_excel_rows` in `index.php`).

## 8. Multi-Tenant Rules
- Filter by `company_id` and `employee_id` on all reads/writes.

## 9. Audit Logging Requirements
- Triggers `trg_note_labels_audit_insert`, `trg_note_labels_audit_update`, and `trg_note_labels_audit_delete` are updated in `db/03_triggers.sql` to capture and log the new metadata columns (`created_by`, `created_at`, `updated_by`, `updated_at`, `deleted_by`, `deleted_at`) in their JSON payloads.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Do not expose another user's labels when `employee_id` filtering is added — today list queries are company-scoped only (known gap; see section 5). [Cursor-Valid]

## 11. Code Examples

### Safe per-user label query
```php
$stmt = $conn->prepare('SELECT id, label, active FROM note_labels WHERE company_id = ? AND employee_id = ? ORDER BY label ASC');
$stmt->bind_param('ii', $companyId, $employeeId);
```

## 12. Module Owner Notes (Optional)
Behaviour matches the standard CRUD template but runs against `note_labels` with local PHP copies.
