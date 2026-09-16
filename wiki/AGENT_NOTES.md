# AGENT_NOTES.md - Wiki

## 1. Module Purpose
Contains documentation and wiki-style content for the application.

## 10. Common Pitfalls

- Treat `AGENTS.md` and module `AGENT_NOTES.md` as authoritative over wiki pages. [Cursor-Valid]
- Do not “fix” production behaviour by syncing only wiki content. [Cursor-Valid]
- After `AGENTS.md` guardrail changes, update matching wiki pages and re-sync the [GitHub Wiki](https://github.com/pirica/it-management/wiki) (`it-management.wiki.git`).

## 12. Module Owner Notes (Optional)
Used for collaborative documentation. **Installation.md** documents `.env` database keys (`DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`) and **`ITM_DEV` / `APP_ENV`** local profile aligned with `.env.example` and `docs/ENV.md`. **Security.md** documents the pre-PR CI quartet and pentest verifier (`docs/report.md`).
