# IT Management System Wiki

A complete **IT Asset Management System** built with PHP and MySQL, with multi-company support.

Screenshots use stable raw URLs from the repository `docs/readme/` folder (same assets as [README.md](https://github.com/pirica/it-management/blob/master/README.md)).

## Quick Links

- [Installation](Installation)
- [Modules Overview](Modules)
- [Floor Plans Gallery](Floor-Plans)
- [Network Discovery & IP2WHOIS](Network-Discovery)
- [Security & Audits](Security)

### Development guardrails (mirrors `AGENTS.md`)

- [Foreign Keys & Display](Foreign-Keys)
- [Import Excel (JSON endpoint)](Import-Excel)
- [IDF Synchronization](IDF-Synchronization)

## Features

- Complete CRUD operations across modules (248 database tables after fresh `db/` import)
- Multi-company tenant switching with RBAC permission matrix
- GitHub Copilot-inspired light/dark theme
- Equipment management with Switch Port Manager and equipment type facades
- Explorer multi-tenant file storage with vault-gated private folders
- Ticket management, problem management, and post-ticket CSAT surveys
- Floor Plans gallery (nested folders, tags, image/PDF/CAD uploads)
- Appointments self-service IT visit booking
- Hotel guest booking portal and partner distribution API
- Saved report views and scheduled exports
- Per-employee locale settings (money symbol, date/time formats)
- Integration API (`scripts/api.php`) and paid-tier API v2 (`modules/api_v2/router.php`)
- Live Chat and Knowledge Base chatbot widget

## Tech stack

- PHP **7.4.33** (MySQLi — no PDO; maintained target — not PHP 8.x primary)
- MySQL 8.0+
- Apache 2.4+
- Vanilla JavaScript + `css/styles.css`
- No Composer required

## Screenshots

Local install at [http://localhost/it-management/](http://localhost/it-management/) (Dunebox or Laragon).

### Dashboard

Tenant overview with quick stats and settings shortcut.

![Dashboard overview](https://raw.githubusercontent.com/pirica/it-management/master/docs/readme/dashboard.png)

### Equipment

Module list with search, sort, and table tools (export / import).

![Equipment module list](https://raw.githubusercontent.com/pirica/it-management/master/docs/readme/equipment.png)

### IDF rack

Visual rack layout with positions, port grid, and linked device management.

![IDF rack view](https://raw.githubusercontent.com/pirica/it-management/master/docs/readme/idf.png)

### Rack planner

Drag-and-drop rack elevation with patch panels, switches, and servers by RU.

![Rack planner](https://raw.githubusercontent.com/pirica/it-management/master/docs/readme/rack_planner.png)

### Floor Plans

Gallery with nested folders, tags, and uploads (images, PDF, AutoCAD); optional link to IT Locations; drag-and-drop moves. See [Floor Plans Gallery](Floor-Plans).

![Floor Plans gallery](https://raw.githubusercontent.com/pirica/it-management/master/docs/readme/floor_plans.png)

## Architecture

High-level request flow from web entry points through shared core into company-scoped MySQL data and audit logging.

![Architecture overview](https://raw.githubusercontent.com/pirica/it-management/master/docs/readme/architecture.png)

### Database schema

Core table relationships for the company-scoped multi-tenant data model.

![Database schema overview](https://raw.githubusercontent.com/pirica/it-management/master/docs/readme/database-diagram.png)

---

## Suggested Wiki Sidebar

1. Home
2. Installation
3. Modules
4. Floor Plans
5. Network Discovery
6. Security
7. Foreign Keys & Display
8. Import Excel
9. IDF Synchronization

Agent and contributor guardrails in the repository are defined in [`AGENTS.md`](https://github.com/pirica/it-management/blob/master/AGENTS.md). Wiki pages **Foreign Keys**, **Import Excel**, and **IDF Synchronization** summarize those sections for human readers.
