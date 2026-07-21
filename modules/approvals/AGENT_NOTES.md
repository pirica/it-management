# AGENT_NOTES.md - Approvals

## 1. Module Purpose
Manages the approval workflow for forecast revisions. It tracks the stage, status, and comments of an approval process for a specific revision.

## 2. Key Tables
- **approvals** — tracks approval records for forecast revisions.

## 3. Required Relationships
- **approvals** → depends on **companies**.
- **approvals** → depends on **forecast_revisions** (via `forecast_revision_id`).
- **approvals** → depends on **approvals_stage** (via `stage`).
- **approvals** → depends on **users** (via `approved_by`).

## 4. Business Rules (Critical for Agents)
- **One Approval per Revision**: Only one approval record is allowed per `company_id` and `forecast_revision_id`.
- **Active Only**: Approvals are typically only managed for active forecast revisions.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Status Indicators**: Visual cues for approved, rejected, or pending status.

## 6. API Actions (If Applicable)
- **import_excel_rows** — (in `index.php`) handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure: `index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers (`trg_approvals_audit_*`).

## 10. Common Pitfalls

- **Add sample data:** requires tenant `forecast_revisions` and `approvals_stage` rows — `itm_seed_insert_approvals_sample_row()` in `includes/itm_sample_data_seed.php` seeds parents first and resolves FKs by tenant id (not hardcoded template ids from `db/02_data.sql`).
- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- **Mismatched Stages**: Ensure the `stage` ID corresponds to a valid record in `approvals_stage`. [Cursor-Valid]
- **Approved Date**: Ensure `approved_at` is updated only when the status changes to an approved state. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM approvals WHERE company_id = ? AND forecast_revision_id = ?");
$stmt->bind_param("ii", $companyId, $revisionId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO approvals (company_id, forecast_revision_id, stage, status, approved_by) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiisi", $companyId, $revisionId, $stageId, $status, $employeeId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Approval workflows are sensitive. Ensure that `approved_by` matches the authenticated user performing the action.
