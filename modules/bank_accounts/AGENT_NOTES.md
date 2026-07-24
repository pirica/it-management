# AGENT_NOTES.md - bank_accounts

RootFi-shaped bank register (`institution_name`, `account_name`, `balance`, EUR default). Not FK-linked to expenses in v1.

**No live sync:** optional `platform_id` and `platform_updated_at` are manual metadata only (no RootFi webhooks or inbound sync).
