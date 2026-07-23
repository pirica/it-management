# Foreign Keys & Display Guardrails

These rules mirror `AGENTS.md` and apply to every module with duplicated entry files (`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`, `list_all.php`).

## Core principle

Never show raw foreign-key numeric IDs in list or detail screens when a related label exists. Render human-readable values (`name`, `title`, `username`, hostname, color name, and similar).

## Tenant-safe lookup order

1. **Company-scoped first:** `WHERE id = ? AND company_id = ?`
2. **Id-only fallback:** use only when the scoped row is missing, to preserve legacy or shared reference rows.

Apply the same order for list/view label resolution and for edit-form option loading.

## List and view screens

- Open `index.php` and `view.php` and confirm every `*_id` column shows a label, not `equipment_id=5` or `level_id=23`.
- Propagate display renderer changes to **both** `index.php` and `view.php` before commit.
- Audit visible columns in `list_all.php` against `db/` and `information_schema` relationships.

## Edit forms and dropdowns

- If a saved FK value is not returned by the current company-scoped options query, **append or load that saved row** so the form does not fall back to `-- Select --`.
- Apply FK option-loading fixes consistently across all duplicated entry files that share the same helpers.

## Created-by and user reference fields

For `created_by`, `updated_by`, `approved_by`, and `*_by_user_id`:

| Screen | Requirement |
|--------|-------------|
| List / view | Never show raw user IDs when a user row exists |
| Create / edit | User dropdown with human-readable labels (not free-text numeric input) |
| Label format | Prefer `first_name + last_name`; use `username` only when full name is empty |
| Missing options | Append/load persisted user ID when company-scoped options are incomplete |

## Switch Status module (`modules/switch_status/`)

- Preserve persisted FK selections when tenant-scoped option queries omit the saved row.
- For `color_id`, resolve `hex_color` with tenant-scoped lookup first (`id` + `company_id`), then global-by-`id` fallback for legacy rows.
- Keep `index.php`, `edit.php`, and `view.php` aligned for FK fallback and color preview behavior.

## Mandatory column + SQL relation audit

Before finishing a module change:

- Audit **all columns and SQL relations** for the module folder.
- Include declared FK constraints **and** relation-like `*_id` columns without an explicit FK constraint.
- Replace raw keys with meaningful values wherever possible (hostname, status, VLAN name, device/position labels, and similar).

**Hard fail:** if a related label exists but any visible screen still shows a raw FK ID, the task is not complete.

## Pre-commit checklist

- [ ] `index.php` and `view.php` — FK columns show labels
- [ ] `edit.php` — persisted FK values stay selected when scoped options are incomplete
- [ ] Fallback lookups are tenant-safe (scoped first, id-only for legacy)
- [ ] Display renderer changes applied to both list and detail flows
- [ ] User reference fields follow created-by UX rules
- [ ] `switch_status` color/FK rules applied when that module changed

## Related documentation

- [Import Excel (JSON endpoint)](Import-Excel) — table import must not break FK-backed columns
- [IDF Synchronization](IDF-Synchronization) — rack/device FK and port parity rules
- Repository source: `AGENTS.md` (Module Consistency Guardrail section)
