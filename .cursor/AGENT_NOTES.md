# AGENT_NOTES.md - .cursor (project stub)

## 1. Module Purpose

This folder is a **pointer only**. Cursor rules and skills for IT Management are **not** stored in the git repo. Canonical files live at `C:\Users\NelsonSalvador\Downloads\laragon-portable\www\.cursor` (sibling of this project: `../.cursor`).

## 2. Key Tables

None.

## 3. Required Relationships

- **`../.cursor/rules/`** — always-on agent rules (`local-dev-browser-links.mdc`, `scripts-directory-standards.mdc`, `git-auto-commit-and-pr.mdc`).
- **`../.cursor/skills/sync-db-table-count-docs/SKILL.md`** — table-count documentation skill.
- **`AGENTS.md` step 7a** — browser-link contract (same as the local-dev-browser-links rule).

## 4. Business Rules (Critical for Agents)

- Use **only** `www/.cursor` for Cursor rules and `SKILL.md`.
- Do not recreate `rules/*.mdc` or `skills/**/SKILL.md` under `it-management/.cursor`.
- `.gitignore` ignores this directory except this `AGENT_NOTES.md`.

## 5. UI Behavior Requirements

Not applicable.

## 6. File Upload Rules

Not applicable.

## 7. API Actions

Not applicable.

## 8. Display Field

Not applicable.

## 9. Multi-Tenant Rules

Not applicable.

## 10. Audit Requirements

Not applicable.

## 11. Known Pitfalls

- Local `.git/info/exclude` may still list `.cursor/`, which made `SKILL.md` look untracked or disappear from `git status` even when present.
- The previous project skill delete was **local working-tree only** (never committed). The file is restored under `www/.cursor/skills/sync-db-table-count-docs/SKILL.md`.
- `empty_folders.php` skips `.cursor`.

## 12. Module Owner Notes

- Open the skill: `C:\Users\NelsonSalvador\Downloads\laragon-portable\www\.cursor\skills\sync-db-table-count-docs\SKILL.md`
- Invoke: `@sync-db-table-count-docs`
