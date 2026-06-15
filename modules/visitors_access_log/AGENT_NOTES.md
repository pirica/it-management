# AGENT_NOTES.md - Visitors Access Log

## 1. Module Purpose
Tracks and manages a manual log of visitors entering physical IT areas or offices.

## 2. Key Tables
- **visitors_access_log** — main log records.

## 3. Required Relationships
- **visitors_access_log** → depends on **companies**.

## 4. Business Rules (Critical for Agents)
- **Timestamps**: Automatically captures `date_time_in` and `date_time_out`.
- **Pre-Approval**: Tracks who authorized the visit.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Inline Editing**: Often supports quick updates for signing visitors out.

## 9. Audit Logging Requirements
- Managed via database triggers (`trg_visitors_access_log_audit_*`).

## 12. Module Owner Notes (Optional)
Physical security compliance tool.
