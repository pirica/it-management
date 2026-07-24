# AGENT_NOTES.md - tax_rates

Tenant VAT rate lookup (`name`, `rate_percent`). Seeded 6/13/23% for company 1; replicated to other companies in `02_data.sql`. Referenced by expenses and finance line items.

- **Add sample data:** empty gate uses `itm_seed_tenant_row_count()` (live rows only — `deleted_at IS NULL`), matching the list query. Regression: `php scripts/verify_finance_sample_data_seed.php`, `php scripts/check_crud_sample_data_live_row_gate.php`.
