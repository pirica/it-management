# AGENT_NOTES.md - IP Subnets

## 1. Module Purpose
Manages IP subnets (CIDR blocks), including gateways, DNS, and DHCP configuration.

## 2. Key Tables
- **ip_subnets** — main subnet data.

## 3. Required Relationships
- **ip_subnets** → depends on **companies**.
- **ip_subnets** → depends on **vlans**.

## 4. Business Rules (Critical for Agents)
- **Unique CIDR per Company**: A subnet block must be unique within a company.
- **CIDR Validation**: Must be a valid CIDR string (e.g., "192.168.1.0/24").
- **Gateway/DNS**: Gateway and DNS IPs should belong to the subnet range.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **List search:** matches `vlan_id` label via `itm_crud_fk_label_search_conditions()` in `includes/list_query.php`.
- **Subnet Stats**: View page often shows usage statistics (Free vs Used IPs).
- **Bulk generate**: View page and index list include **Generate host IPs** (before **Active** on the index table). Uses `itm_ipam_subnet_bulk_generate_ui()` and POST `generate_subnet_ips` (index redirects back to the list; view stays on the subnet). For `/31` and `/32`, `host_total` aligns with `max_hosts` when the standard `-2` formula yields zero.
- **Index empty-state colspan:** must use `count($uiColumns)` (not `$fieldColumns`) plus bulk/actions/generate-host-IPs columns to match the visible header row.
- **Network Discovery**: May trigger scans to find live hosts in the subnet.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- **index.php**, **view.php**, etc.
- **subnet_view_ips.php** — detailed view of all IPs in the subnet.
- **subnet_view_stats.php** — usage analytics.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Overlapping Subnets**: Defining subnets that overlap in range (e.g., /24 and /25). [Valid]-[2026-07-15]
- **Invalid Prefix**: Incorrect prefix length for the network IP. [Valid]-[2026-07-15]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM ip_subnets WHERE company_id = ? AND cidr = ?");
$stmt->bind_param("is", $companyId, $cidr);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Foundational for network configuration.
