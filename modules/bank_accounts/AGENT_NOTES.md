# AGENT_NOTES.md - bank_accounts

Bank register (`institution_name`, `account_name`, `balance`, EUR default). Used as the payment source on `finance_payment_allocations` for bills and invoices.

## Attachments
Multi-file uploads on create/edit; storage `finance/{company_id}/bank_accounts/{account_number or bank-{id}}/`. Download via `attachment.php`.
