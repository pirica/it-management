# AGENT_NOTES.md - ip_subnets/includes

## 1. Module Purpose
Shared PHP partials and helpers for the IP Subnets module.

## 3. Required Relationships
- Parent: `modules/ip_subnets/`.
- Referenced by **ip_addresses** via subnet FK.

## 7. File Structure
- **partials/** — render fragments for subnet list/forms.

## 8. Multi-Tenant Rules
- Company-scoped queries only.
