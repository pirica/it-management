# Modules Overview

## Development guardrails

Module work must follow the wiki guardrails (also in repository `AGENTS.md`):

- [Foreign Keys & Display](Foreign-Keys) — labels, dropdowns, tenant-safe lookups
- [Import Excel (JSON endpoint)](Import-Excel) — `data-itm-db-import-endpoint` and `itm_handle_json_table_import`
- [IDF Synchronization](IDF-Synchronization) — rack/port/equipment table parity (protection zone)

## Module list

| Module | Description |
| --- | --- |
| **Equipment** | Manage IT equipment with Switch Port Manager |
| **IDFs** | Rack layout, positions, ports, and cable links |
| **IPAM** | VLANs, IP subnets (CIDR), and IP addresses linked to equipment; includes **Network Discovery** TCP scan under IP Subnets |
| **Rack planner** | Visual rack elevation and component placement |
| **Floor Plans** | Image/PDF/CAD gallery with nested folders, tags, optional IT Location link, drag-and-drop moves — [details](Floor-Plans) |
| **Printers** | Track printers and supplies |
| **Workstations** | Manage workstations |
| **Tickets** | Support ticket system |
| **Inventory** | Track supplies |
| **Users** | User management |
| **Departments** | Department management |
| **Employees** | Employee tracking |
| **Companies** | Multi-company support |
| **Budgeting** | Annual/Monthly Budgets, Forecasts, Expenses and Reports |
| **Audit Logs** | Change audit trail |

## Equipment

Track IT assets and related details, with support for image uploads and switch port integration.

## IDFs & rack planner

Visual rack layout, positions, ports, cable links, and drag-and-drop rack elevation. See [IDF Synchronization](IDF-Synchronization) before changing protection-zone code.

## IPAM & network discovery

VLANs, subnets, and IP addresses. **IP Subnets → Search → Network Discovery** scans an IPv4 range (up to 255 addresses) via TCP connect probes. See [Network Discovery & IP2WHOIS](Network-Discovery).

## Floor Plans

Reference Data → **Floor Plans** (`modules/floor_plans/`). Full capability table and move rules: [Floor Plans Gallery](Floor-Plans).

## Printers

Manage printer inventory and supply status.

## Workstations

Track workstation records and assignments.

## Tickets

Create and manage support tickets, including photo attachments in `tickets_photos/`.

## Inventory

Track consumables and stock levels.

## Users, departments, employees, companies

User access, organizational structure, employee records, and multi-company data partitioning (`company_id` scoping).

## Budgeting

Annual and monthly budgets, forecasts, expenses, and reports.

## Audit Logs

Traceable INSERT/UPDATE/DELETE history when audit logging is enabled in Settings.
