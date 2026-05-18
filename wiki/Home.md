# IT Management System Wiki

A complete **IT Asset Management System** built with PHP and MySQL, with multi-company support.

Captured screenshots and diagrams below use paths under `docs/readme/` in the repository (same assets as [README.md](../README.md)).

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

- Complete CRUD operations across modules
- GitHub Copilot-inspired light/dark theme
- Equipment management with photo uploads
- Printer and workstation tracking
- Ticket management system
- Floor Plans gallery (nested folders, tags, image/PDF/CAD uploads)
- Responsive design
- API (`scripts/api.php`)

## Tech stack

- PHP 7.4.33 (MySQLi — no PDO)
- MySQL 8.0+
- Apache 2.4+
- Vanilla JavaScript + `css/styles.css`
- No Composer required

## Screenshots

Local Laragon-style install at `http://localhost/it-management/` (default light theme after sign-in).

### Dashboard

Tenant overview with quick stats and settings shortcut.

![Dashboard overview](../docs/readme/dashboard.png)

### Equipment

Module list with search, sort, and table tools (export / import).

![Equipment module list](../docs/readme/equipment.png)

### IDF rack

Visual rack layout with positions, port grid, and linked device management.

![IDF rack view](../docs/readme/idf.png)

### Rack planner

Drag-and-drop rack elevation with patch panels, switches, and servers by RU.

![Rack planner](../docs/readme/rack_planner.png)

### Floor Plans

Gallery with nested folders, tags, and uploads (images, PDF, AutoCAD); optional link to IT Locations; drag-and-drop moves. See [Floor Plans Gallery](Floor-Plans).

![Floor Plans gallery](../docs/readme/floor_plans.png)

## Architecture

High-level request flow from web entry points through shared core into company-scoped MySQL data and audit logging.

![Architecture overview](../docs/readme/architecture.png)

### Database schema

Core table relationships for the company-scoped multi-tenant data model.

![Database schema overview](../docs/readme/database-diagram.png)

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

Agent and contributor guardrails in the repository are defined in [`AGENTS.md`](../AGENTS.md). Wiki pages **Foreign Keys**, **Import Excel**, and **IDF Synchronization** summarize those sections for human readers.
