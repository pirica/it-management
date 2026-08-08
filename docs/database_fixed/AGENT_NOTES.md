# AGENT_NOTES.md - database_fixed

## 1. Module Purpose

Notes for the cancellation-policy RCE remediation regarding **database** impact.

## 4. Business Rules (Critical for Agents)

- **No schema change** was required: the issue was application validation in `itm_hotel_booking_normalize_cancellation_policy_url()` (`includes/itm_hotel_booking.php`), not MySQL DDL.
- Do not import or apply anything from this folder as a database fix — remediation is PHP-only on live.
- Live allowlist: relative policy paths must use `.html`, `.htm`, or `.txt`. Defense in depth: `booking/cancellation_policy/.htaccess`.
- Obsolete `docs/fixed_files_vulnerability_*` snapshot trees were removed after live was fixed.

## 7. File Structure

- `DETAIL_CHANGES.md` — short historical summary (same facts as this file)
- No `database.sql` dump (removed; not needed)

## 10. Common Pitfalls

- Looking here for a SQL migration that does not exist. [Cursor-Valid]
