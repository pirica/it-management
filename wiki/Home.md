# IT Management System Wiki

Welcome to the **IT Management System** wiki.

This project is a PHP + MySQL web application for managing IT operations including equipment, printers, workstations, tickets, users, departments, employees, and company-specific data.

## Quick Links

- [Installation Guide](Installation)
- [Modules Overview](Modules)
- [Security & Audits](Security)

### Development guardrails (mirrors `AGENTS.md`)

- [Foreign Keys & Display](Foreign-Keys)
- [Import Excel (JSON endpoint)](Import-Excel)
- [IDF Synchronization](IDF-Synchronization)

## Tech Stack

- PHP 7.4.33
- MySQL 8.0+
- Apache 2.4+
- Vanilla JavaScript + CSS

## Core Features

- Full CRUD for major modules
- Multi-company support
- Light/Dark UI theme
- Photo uploads for equipment and ticket artifacts
- Built-in audit scripts for CSRF and SQL injection coverage

---

## Suggested Wiki Sidebar

You can use this structure in your GitHub Wiki sidebar:

1. Home
2. Installation
3. Modules
4. Security
5. Foreign Keys & Display
6. Import Excel
7. IDF Synchronization

Agent and contributor guardrails in the repository are defined in [`AGENTS.md`](../AGENTS.md); the wiki pages above summarize the FK, import, and IDF sync sections for human readers.
