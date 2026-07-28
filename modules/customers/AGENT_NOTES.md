# AGENT_NOTES.md - customers

Tenant AR customer master (separate from `suppliers`). Linked from `invoices.customer_id` (optional; `contact_name` remains for display fallback).

## Attachments
Multi-file uploads on create/edit (Equipment-style dropzone); `finance/{company_id}/customers/{customer_code or customer-{id}}/`. Download via `attachment.php`.
