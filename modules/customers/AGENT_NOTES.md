# AGENT_NOTES.md - customers

Tenant AR customer master (separate from `suppliers`). Linked from `invoices.customer_id` (optional; `contact_name` remains for display fallback).

## Attachments
Multi-file uploads on create/edit (Equipment-style dropzone); `finance/{company_id}/customers/{customer_code or customer-{id}}/`. Download via `attachment.php`.

## Hotel bookings
`view.php` lists related `hotel_bookings` rows (room, dates, payment) with links to `modules/hotel_bookings/view.php`. Bookings require `customer_id` on every reservation.
