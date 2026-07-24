# AGENT_NOTES.md - tax_rates

Tenant VAT rate lookup (`name`, `rate_percent`). Seeded 6/13/23% for company 1; replicated to other companies in `02_data.sql`. Referenced by expenses and finance line items.

- **Add sample data:** copies the full company-1 catalog from live template tenant (`itm_seed_copy_finance_lookup_rows_from_template_company()`) or `db/02_data_sample.sql` (same three VAT rows as `db/02_data.sql`). Empty gate uses `itm_seed_tenant_row_count()`. Regression: `php scripts/verify_finance_sample_data_seed.php`.
