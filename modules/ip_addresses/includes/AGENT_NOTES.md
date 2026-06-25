# AGENT_NOTES.md - ip_addresses/includes

## 1. Module Purpose
Shared PHP partials and helpers for the IP Addresses module UI (list cells, forms, subnet linkage display).

## 3. Required Relationships
- Parent module: `modules/ip_addresses/`.
- Related: `modules/ip_subnets/`, `database.sql` tables `ip_addresses`, `ip_subnets`.

## 7. File Structure
- **partials/** — reusable render fragments (see `partials/AGENT_NOTES.md`).

## 8. Multi-Tenant Rules
- Helpers must receive/scoped `company_id`; never render another tenant's subnet or address labels.

## 12. Module Owner Notes (Optional)
Read parent `modules/ip_addresses/AGENT_NOTES.md` before editing list or FK label behaviour.
