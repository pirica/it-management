# Scripts Documentation

This directory contains scripts used for reproducing and verifying security vulnerabilities in the IT Management System.

## Reproduction Scripts
- **Repro Explorer Traversal**: `php docs/scripts/repro_explorer_traversal.php`
  - Validates if a user can access files outside their scoped directory.

## Verification Scripts
- **Verify Explorer Fix**: `php docs/scripts/verify_explorer_fix.php`
  - Validates that the path traversal vector in `modules/explorer/api.php` is blocked.

## Registration
All new scripts must be added to `docs/scripts/scripts.php`.
