# AGENT_NOTES.md - integration_accounts

RootFi-shaped chart rows (`nominal_code`, balances, optional `gl_account_id` bridge to internal `gl_accounts`). Used on bill/invoice line `integration_account_id`.

**No live sync:** optional `platform_id`, `platform_parent_id`, and `platform_updated_at` are manual metadata only (no RootFi webhooks or inbound sync). Leave NULL when not needed.
