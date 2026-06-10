# AGENT_NOTES.md - Tickets

## 1. Module Purpose
The central helpdesk/ticketing module for managing support requests.

## 2. Key Tables
- **tickets** — main ticket storage.

## 3. Required Relationships
- **tickets** → depends on **companies**.
- **tickets** → depends on **ticket_categories**.
- **tickets** → depends on **ticket_priorities**.
- **tickets** → depends on **ticket_statuses**.
- **tickets** → links to **employees** (Requester).
- **tickets** → links to **users** (Assigned To).
- **tickets** → links to **equipment** (Affected Asset).

## 4. Business Rules (Critical for Agents)
- **Archiving**: Tickets are typically archived rather than deleted to preserve history.
- **Asset Link**: Tickets can be linked to specific equipment for asset lifecycle tracking.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Photo Upload**: Supports uploading photos/screenshots for troubleshooting.
- **Search & Filter**: Extensive filtering by status, priority, and assigned user.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.
- **archive.php** — dedicated handler for archiving closed tickets.

## 7. File Structure
- Standard CRUD structure + `archive.php`.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM tickets WHERE company_id = ? AND status_id = ?");
$stmt->bind_param("ii", $companyId, $statusId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The primary interface for IT support operations.
