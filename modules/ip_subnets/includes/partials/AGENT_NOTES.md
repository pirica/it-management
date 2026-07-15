# AGENT_NOTES.md - ip_subnets/includes/partials

## 1. Module Purpose
Partial templates for IP Subnets list and detail UI.

## 10. Common Pitfalls

- Index list inserts a **Generate host IPs** column immediately before **Active** — keep header/body colspan in sync when changing list columns. [Cursor-Valid]

## 12. Module Owner Notes (Optional)
Coordinate column changes with `modules/ip_addresses/` when subnet labels are shared. Index list inserts a **Generate host IPs** column immediately before **Active**; keep header/body colspan in sync when changing list columns.
