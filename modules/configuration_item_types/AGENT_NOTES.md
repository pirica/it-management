# AGENT_NOTES.md - Configuration Item Types

## 1. Module Purpose

Admin CRUD for CI type definitions per tenant (Server, Switch, Application, Service, IDF, equipment-type mirrors, custom types).

## 2. Key Tables

- **configuration_item_types** — `name`, optional `source_slug`, `icon`

## 3. Required Relationships

- Referenced by **configuration_items** (`ci_type_id`, RESTRICT on delete)

## 4. Business Rules (Critical for Agents)

- `source_slug` `builtin:*` and `equipment_type:{id}` rows are seeded by `itm_cmdb_seed_types_for_company()` — do not duplicate names per company.
- Custom types: leave `source_slug` NULL.

## 5. UI Behavior Requirements

- Flattened CRUD scaffold; hide `company_id`.

## 6. API Actions (If Applicable)

- N/A

## 7. File Structure

- Standard flattened CRUD entry files.

## 8. Multi-Tenant Rules

- `company_id` scoped.

## 9. Audit Logging Requirements

- `trg_configuration_item_types_audit_*` triggers.

## 10. Common Pitfalls

- Deleting a type in use fails RESTRICT from `configuration_items`.

## 11. Examples of Safe Code Patterns

```php
itm_cmdb_seed_types_for_company($conn, $companyId, $employeeId);
```

## 12. Module Owner Notes (Optional)

- Parent module: `modules/configuration_items/AGENT_NOTES.md`
