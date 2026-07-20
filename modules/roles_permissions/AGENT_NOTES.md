# AGENT_NOTES.md - Roles & Permissions

## 1. Module Purpose

Unified dashboard for tenant role management and the RBAC permission matrix. Replaces the need to edit `role_module_permissions` one row at a time for day-to-day configuration.

## 2. Key Tables

- **employee_roles** — role name and `active`
- **role_module_permissions** — per-role module flags (`can_view`, `can_create`, `can_edit`, `can_delete`, `can_import`, `can_export`)
- **role_hierarchy** — display order for the role sidebar (`hierarchy_order`)
- **modules_registry** — module rows shown in the matrix (`module_name` is stored in permission rows)
- **employees** — user counts per role in the sidebar

## 3. Required Relationships

- **role_module_permissions** → **companies**, **employee_roles**
- **employee_roles** → **companies**
- **role_hierarchy** → **employee_roles**, **companies**
- Enforcement helpers: `includes/itm_role_module_permissions.php`

## 4. Business Rules (Critical for Agents)

- Signed-in tenant users may open the module and browse roles plus the permission matrix.
- Only `itm_is_admin()` users may create/edit roles or save matrix changes (non-admins see a read-only matrix; AJAX mutations return HTTP 403).
- Tenant scope: all reads/writes use session `company_id` via `itm_resolve_active_company_id()`.
- The seeded **Admin** role (name match, case-insensitive) uses the `ALL` wildcard row in `role_module_permissions`; its matrix is read-only.
- **Fresh-import seeds:** `employee_roles`, `role_hierarchy`, `role_module_permissions`, and `role_assignment_rights` use name-based `INSERT … SELECT` (no hardcoded role ids). Role order per company: Admin, IT Manager, IT Assistant, Helpdesk, User.
- Matrix columns: **View**, **Add** (`can_create`), **Edit**, **Delete**, **Import**, **Export** — six flags aligned with RBAC enforcement.
- Effective flags: per-module row when present; otherwise inherit from `module_name = 'ALL'`; otherwise all flags false.
- Saving permissions upserts `(company_id, role_id, module_name)` rows; never overwrites the `ALL` wildcard via the matrix save path.
- New roles insert into `employee_roles` and append `role_hierarchy` with the next order value.
- **User counts** on role cards show **N active** (SQL alias `active_count`): employees where `employees.company_id = er.company_id`, `employees.role_id = er.id`, and HR employment status **Active** via `includes/itm_employee_employment_status.php`. Not session presence (dashboard **Online now**) and not all assigned employees regardless of HR status.
- Company module access remains the first visibility gate; this module configures the second RBAC layer.

## 5. UI Behavior Requirements

- Dual-pane layout patterned after `company_module_access`: Settings-managed list header (`data-itm-new-button-managed="server"`) with centered `$moduleListHeading` (sidebar icon + title via `itm_resolve_module_sidebar_icon()`), `new_button_position`-gated ➕ (`itm-list-new-button`, opens add-role modal), toolbar card (`margin-bottom:16px`), matrix card (`overflow:auto`), `Modules` column header, accent slug links, centred checkbox cells, and `badge` / `badge-danger` for system/inactive rows.
- Role cards show name, **active employee count** (`N active`, SQL alias `active_count`), and **System** badge for Admin.
- Toolbar: Check All, Uncheck All, Save (💾, admins only), server-side **Search (all fields)** on `module_slug` / `module_name` (GET `search`, emoji-only 🔙 reset preserves `role_id`), plus optional client-side matrix filter.
- Add role (➕) and edit role (✏️) modals update `employee_roles.name` via AJAX (admins only). List-header ➕ uses `title="Create"` and respects Settings **new button position** (left / right / left_right).
- Matrix table disables Excel/PDF export and Import Excel (`data-itm-no-export-excel="1"`, `data-itm-no-export-pdf="1"`, `data-itm-no-import-excel="1"`). Index table compliance honors the import opt-out (no `data-itm-db-import-endpoint` required — this is a permission matrix, not a CRUD row list).
- Action buttons follow emoji-only visible labels with descriptive `title` attributes.

## 6. API Actions (If Applicable)

- `ajax_action=save_permissions` — POST JSON bulk upsert of matrix rows (`permissions_json`).
- `ajax_action=create_role` — POST create role + hierarchy row.
- `ajax_action=update_role` — POST update role name (non-Admin roles only).

All AJAX handlers require CSRF (`itm_require_post_csrf()`) and administrator access.

## 7. File Structure

- `index.php` — dual-pane UI, AJAX handlers, inline modal markup
- `index.html` — directory listing placeholder
- `js/roles-permissions-matrix.js` — matrix toolbar, save, modals client logic

Legacy flat CRUD for raw tables remains under `modules/employee_roles/` and `modules/role_module_permissions/`.

## 8. Multi-Tenant Rules

- Scoped by active session `company_id`; hide `company_id` from UI.
- Role names are unique per company.

## 9. Audit Logging Requirements

- DB triggers on `employee_roles` and `role_module_permissions` log INSERT/UPDATE/DELETE to `audit_logs`.

## 10. Common Pitfalls

- Do not rename the seeded **Admin** role from this UI — matrix is read-only and update/create blocks Admin rows. [Cursor-Valid]
- Permission `module_name` values must match `modules_registry.module_name` for RBAC lookups. [Cursor-Valid]
- Keep `roles_permissions` in `itm_crud_rbac_exempt_module_slugs()` — the module uses its own admin gate for mutations. [Cursor-Valid]

## 11. Examples of Safe Code Patterns

```php
if (!$rpCanManage) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Administrator access required.']);
    exit;
}

rp_upsert_permission_row($conn, $companyId, $roleId, $moduleName, [
    'can_view' => 1,
    'can_export' => 1,
]);
```

## 12. Module Owner Notes (Optional)

Complements **Company Module Access** (company on/off) with role-level CRUD flags. Sidebar entry: Admin → **🛡️ Roles & Permissions**.

**Regression:** `php scripts/verify_roles_permissions.php`. **README screenshot:** `ITM_SCREENSHOT_ONLY=roles_permissions python3 scripts/take_screenshots_modules.py` → `docs/readme/roles_permissions.png`.
