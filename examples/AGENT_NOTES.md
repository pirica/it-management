# AGENT_NOTES.md - examples

## 1. Module Purpose

Copy-paste `.env` templates for local development and production-style deployments. These are **not** loaded at runtime — operators copy a file to project root as `.env`. Canonical reference: **`docs/ENV.md`**. Full key catalog: **`.env.example`**.

## 7. File Structure

| File | Use |
|------|-----|
| `env.development.sample` | Local Laragon / Dunebox (`ITM_DEV=1`, `APP_ENV=development`, Dunebox `DB_PORT=3307`) |
| `env.production.sample` | Production-style (`APP_ENV=production`, `ITM_APP_URL`, no dev shortcuts) |

## 8. Known Pitfalls

- Do not commit a real `.env` from these templates — restrict permissions on production hosts.
- `ITM_DEV` / `APP_ENV` label the deployment only; verbose PHP errors still follow per-employee **Settings → UI Configuration → enable all error reporting**.
