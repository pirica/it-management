# Security & Audits

This project includes basic security audit scripts to help verify defensive coverage.

## CSRF Coverage Audit

Run:

```bash
php scripts/check_csrf_coverage.php
```

## SQL Injection Coverage Audit

Run:

```bash
php scripts/check_sql_injection_coverage.php
```

## Notes

- Review findings before deploying.
- Keep PHP and MySQL patched.
- Use least-privilege DB credentials for production.
- Restrict file upload permissions and validate MIME types.
