# AGENT_NOTES.md - IP Addresses

## 1. Module Purpose
Manages individual IP address assignments within subnets.

## 2. Key Tables
- **ip_addresses** — main IP record storage.

## 3. Required Relationships
- **ip_addresses** → depends on **companies**.
- **ip_addresses** → depends on **ip_subnets**.
- **ip_addresses** → links to **equipment** (via `equipment_id`).

## 4. Business Rules (Critical for Agents)
- **Unique IP per Subnet**: IP must be unique within a `subnet_id` and `company_id`.
- **Status Management**: Tracks status such as 'free', 'used', 'reserved', 'gateway'.
- **Equipment Link**: When an IP is assigned to equipment, it should update the `equipment_id` and `hostname`.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **List search:** focused index uses `itm_ipam_address_list_where_clause()` (subnet CIDR, equipment hostname/name); generic `list_all` fallback uses `itm_crud_fk_label_search_conditions()` in `includes/list_query.php`.
- **Quick Status Change**: Often allows changing status directly from the list.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Mismatched Subnets**: Ensure the IP address actually belongs to the CIDR range of the selected subnet. [Valid]-[2026-07-15]
- **Double Assignment**: Trying to assign the same IP to multiple devices. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM ip_addresses WHERE company_id = ? AND ip_text = ?");
$stmt->bind_param("is", $companyId, $ip);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Core of the IPAM (IP Address Management) system.
