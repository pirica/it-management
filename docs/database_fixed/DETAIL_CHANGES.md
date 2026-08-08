# Database Fixes & Detail Changes

## Summary of Changes

No database schema modifications or migrations were necessary to remediate the RCE vulnerability.

## Detailed Explanation

- **Reason**: The vulnerability was caused by insufficient file extension validation in `itm_hotel_booking_normalize_cancellation_policy_url()` within `includes/itm_hotel_booking.php`.
- **Resolution**: Whitelist relative policy paths to `.html` / `.htm` / `.txt` in live PHP (not a SQL change), plus `booking/cancellation_policy/.htaccess`. See `AGENT_NOTES.md` in this folder.
- Duplicate `docs/fixed_files_vulnerability_*` trees were deleted once live was fixed.
