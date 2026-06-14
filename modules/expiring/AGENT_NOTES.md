# AGENT_NOTES.md - Expiring

## 1. Module Purpose
Tracks assets or contracts with expiration dates (e.g., "Warranties", "Domain Names", "SSL Certificates").

## 2. Key Tables
- **expiring** — main storage for expiring items.

## 3. Required Relationships
- **expiring** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Threshold Alerts**: Often used to trigger notifications as expiration dates approach.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Color Coding**: Items nearing expiration are often highlighted (e.g., Red for expired, Yellow for 30 days).

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 12. Module Owner Notes (Optional)
Used for proactive maintenance and renewal management.
