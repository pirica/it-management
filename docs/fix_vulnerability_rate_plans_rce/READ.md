# Vulnerability Report: Remote Code Execution (RCE) via Cancellation Policy URL Uploads

## Vulnerability Details

- **Attacker**: An authenticated administrator or malicious employee with access to the portal rate plans module.
- **Controlled Input**: The `cancellation_policy_url` and `cancellation_policy_html` fields submitted via Create/Edit Rate Plan forms.
- **Attack Path**:
  1. The attacker navigates to the Create or Edit Rate Plan page (`modules/hotel_booking_portal_rate_plans/create.php` or `edit.php`).
  2. The attacker inputs a custom file path with a `.php` extension in the `cancellation_policy_url` field (e.g., `cancellation_policy/evil.php`).
  3. The attacker inputs arbitrary PHP code in the `cancellation_policy_html` field (e.g., `<?php system($_GET['cmd']); ?>`).
  4. Upon form submission, the backend calls `itm_hotel_booking_normalize_cancellation_policy_url()` which fails to validate the file extension.
  5. The backend then invokes `itm_hotel_booking_write_cancellation_policy_file()`, writing the malicious payload into `booking/cancellation_policy/evil.php`.
  6. The attacker accesses `/booking/cancellation_policy/evil.php` directly to execute arbitrary OS commands.
- **Impact**: Full Remote Code Execution (RCE) on the server hosting the application, leading to complete server takeover, database compromise, and tenant data exfiltration.
- **Primary Location**: `includes/itm_hotel_booking.php`

## Remediation

The vulnerability is remediated by strictly validating the file extension of the `cancellation_policy_url` in `itm_hotel_booking_normalize_cancellation_policy_url()` to allow only safe file extensions (`.html`, `.htm`, `.txt`). Any executable file extension is blocked, causing the file write operation to fail gracefully.
