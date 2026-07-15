# AGENT_NOTES.md - GitHub Workflows

## 1. Module Purpose
CI pipelines executed on push/PR.

## 7. File Structure
- **smoke.yml** — runs `bash scripts/smoke_test.sh` (PHP lint, CSRF audit, SQLi audit).

## 4. Business Rules (Critical for Agents)
- Keep workflow aligned with `scripts/smoke_test.sh`; do not expand smoke scope in YAML without updating `SCRIPTS.md` and `AGENTS.md` pointers.

## 10. Common Pitfalls

- Do not grow `smoke.yml` scope without updating `scripts/smoke_test.sh` and `scripts/SCRIPTS.md`. [Cursor-Valid]
- Keep CI PHP 7.4 aligned with Cloud Agent / Laragon ITM PHP. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
PHP 7.4 on Ubuntu; matches Cloud Agent smoke instructions.
