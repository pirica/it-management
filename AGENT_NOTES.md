# AGENT_NOTES.md - Root Project

## 1. Module Purpose
The IT Management System is a multi-tenant legacy PHP application (PHP 7.4) designed to manage IT infrastructure, employees, budgets, and helpdesk operations with zero external dependencies.

## 4. Business Rules (Critical for Agents)
- **Security**: Mandatory CSRF protection on all POST requests. Prepared statements for all SQL queries using `mysqli`.
- **Multi-Tenancy**: All data (except Companies and certain maintenance logs) must be scoped by `company_id` from the session.
- **Architecture**: No Composer, No NPM. Use `config/config.php` for environment setup.

## 10. Common Pitfalls
- Bypassing the session-based company isolation.
- Introducing external libraries.
- Forgetting to update `database.sql` when changing the schema.

## 12. Module Owner Notes (Optional)
This is the entry point for the entire system. Refer to `AGENTS.md` for the authoritative process and technical standards.
