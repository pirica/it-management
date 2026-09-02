# AGENT_NOTES.md - Vault Org Recovery

## 1. Module Purpose

Admin-only inbox for **tenant-controlled vault org recovery**: Legal/HR-driven admin-assisted master-key recovery when company policy is enabled, the employee has consented, and an escrow snapshot exists. Complements zero-knowledge default in `docs/VAULT.md`.

## 2. Key Tables

- **vault_org_recovery_requests** — recovery workflow rows (status, legal reference, consent snapshot, requester/completer, audit columns).
- **companies** — `vault_org_recovery_enabled`, `vault_org_recovery_passphrase_hash`, `vault_org_recovery_escrow_key_encrypted` (policy; edit on Companies).
- **employees** — `vault_org_recovery_consent_at`, `vault_org_recovery_consent_reference`, `vault_key_escrow_encrypted` (consent + escrow; Profile → Vault Security).

## 3. Required Relationships

- `vault_org_recovery_requests.company_id` → `companies.id` (CASCADE).
- `vault_org_recovery_requests.employee_id` → `employees.id` (CASCADE).
- Requester/completer FKs → `employees.id` (SET NULL).

## 4. Business Rules

- **Admin only** (`itm_is_admin()`); non-admins redirected to dashboard.
- Company policy must be enabled before creating requests.
- Employee must have consent timestamp and non-empty escrow.
- **Create:** legal/HR reference required; status `pending`.
- **Complete:** admin authorization passphrase (company bcrypt hash) + decrypt escrow → one-time master key on view; clears employee escrow; status `completed`.
- **Reject:** pending → `rejected` with optional notes.
- All request mutations logged via `trg_vault_org_recovery_requests_audit_*` → `audit_logs`.

## 5. UI Behaviour

- Bespoke inbox (not flattened CRUD): `index.php` list + inline create form; `view.php` complete/reject + one-time key display.
- Table opts out of import/export (`data-itm-no-import-excel`, etc.).
- Pagination uses emoji-only controls per `AGENTS.md`.

## 6. API / Scripts

- Core: `includes/itm_vault_org_recovery.php` (loaded from `config.php`).
- Regression: `php scripts/verify_vault_org_recovery.php`.

## 7. Multi-tenancy

- Strict `company_id` scoping on all queries.

## 8. Security

- Escrow ciphertext on `employees`; never log plaintext master key.
- Authorization passphrase stored as bcrypt on `companies`.
- Employee escrow encrypted with per-company escrow key (server-side storage key derived from `DB_PASS` + company id).

## 9. Known Pitfalls

- Escrow updates only on vault key save after consent — remind employees to re-save key after granting consent.
- Disabling company policy does not auto-revoke employee consent rows.
- Migration `db/migrations/vault_org_recovery.sql` is destructive (`DROP` companies/employees/requests) — back up before apply on live DBs.

## 10. Related Docs

- `docs/VAULT.md` §2.E
- `modules/companies/AGENT_NOTES.md` (policy card on edit)
- `user-config.php` (consent UI)
