# AGENT_NOTES.md - Password Entries

## 1. Module Purpose

Flattened CRUD exposure for `password_entries` (vault credential rows). **Primary UX** is `modules/passwords/` (masking, clipboard, vault unlock, `itm_encrypt()` at rest). This module lists ciphertext/metadata at company scope for maintenance — **not** a secure end-user vault screen.

## 2. Key Tables

- **password_entries** — encrypted `password` TEXT, account/login/website/comments per `employee_id`

## 3. Required Relationships

- **password_entries** → **companies** (`company_id`, `ON DELETE CASCADE`)
- **password_entries** → **employees** (`employee_id`, `ON DELETE CASCADE`)
- **password_entries** → **password_folders** (`folder_id`, `ON DELETE CASCADE`)

## 4. Business Rules (Critical for Agents)

- **Private data — no audit trail** (no DB triggers / no `itm_log_audit()` on this table).
- Passwords module stores `password` encrypted via `itm_encrypt()` + `$_SESSION['vault_key']`; scaffold create/edit may write plaintext unless extended — prefer `modules/passwords/` for credential changes.
- Canonical module scopes `employee_id = $_SESSION['employee_id']`; this CRUD does not enforce that filter by default.
- Hide `company_id` in UI; render `employee_id` and `folder_id` as labels.

## 5. UI Behavior Requirements

- Scaffold list/view shows DB column values — **do not** treat as production vault UI.
- Boolean `active` uses badge/checkbox patterns per scaffold.

## 6. API / AJAX

- `import_excel_rows` on `index.php` — importing secrets via Excel is high risk; vault module uses dedicated import handlers instead.

## 7. Pitfalls

- Never add audit triggers or PHP audit hooks for this table.
- QR/share from scaffold may snapshot sensitive fields — company `share_modules` gate still applies.
- For decryption in UI, users must use `modules/passwords/` with vault unlocked.
