# AGENT_NOTES.md - Modules

## 1. Module Purpose
Contains the core functional units of the application. Each subdirectory here represents a standalone or integrated module (e.g., Equipment, Tickets, Users).

## 4. Business Rules (Critical for Agents)
- Each module subdirectory must contain its own `AGENT_NOTES.md`.
- Standard CRUD modules often share a common structure (`index.php`, `create.php`, `edit.php`, `delete.php`, `view.php`).

## 8. Multi-Tenant Rules
- Almost all modules here are strictly scoped by `company_id`.
