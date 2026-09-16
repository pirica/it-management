# Backup Tape Log & Role-Based Field Restrictions

Technical documentation for the Backup Tape Log module (`modules/backup_tape_log/`), monthly calendar auto-population, Sunday highlighting, today-only immutability locks, role-restricted field controls, and customized grid exports.

---

## 1. Intent & Purpose

The **Backup Tape Log** module provides IT administrators with a reliable, structured mechanism to track physical server backup tape rotations and restores. Rather than requiring manual row creation, the module auto-populates a complete month-to-view grid, enforcing operational discipline by locking historical records and restricting sensitive recovery actions to authorized personnel.

---

## 2. Monthly Grid Auto-Population

### Generation Logic
When an administrator selects a year, month, and server combination from the dashboard filters, the index view dynamically auto-populates the grid:
- The system automatically derives and renders one row for every single calendar day of the selected month.
- It calculates the correct day name (e.g. "Monday", "Tuesday") and derives the `tape_to_be_used` based on the designated rotation scheme.

### Sunday Highlighting
To assist operators scanning the log, the UI applies a visual yellow highlight (`background-color: #ffeb3b;` or standard theme warning swatch) on rows representing Sundays, making weekend transitions immediately identifiable.

---

## 3. Today-Only Immutability Locks

To protect the integrity of the system audit trail, records are subject to a strict date-matching lock:
- **Immutable History:** Only rows representing the **current calendar day** are editable or deletable by standard operators.
- **Historic Logs:** Historical entries (rows where `log_date` is in the past) are locked. Form inputs are disabled, and update POST handlers reject modification attempts, preserving the integrity of the backup verification log.

---

## 4. Role-Based Field Restrictions

Certain columns within the Backup Tape Log govern critical security operations and are protected by role-based filters:

| Protected Field | Purpose | Allowed Roles |
|---|---|---|
| **`tape_used_for_restore`** | Tracks which tape was loaded to restore files. | Admins (`itm_is_admin()`) or IT Department members (`department_id` resolving to IT). |
| **`ism_review`** | Registers that the IT Systems Manager has verified the month's tape log. | Admins or IT Department members. |

- **Behavior:** For non-IT personnel or standard tenant employees, these inputs render as read-only labels even on the current day's row, preventing unauthorized review sign-offs or false restore logs.

---

## 5. XLSX & PDF Grid Exports

The module implements highly customized spreadsheet and print layouts that bypass standard table-tools constraints:
- **Custom Header:** Exports dynamically inject a custom header carrying the Year, Month, Company name, Server, and Unit Number at the top of the page.
- **Grid Layout Preservation:** The Sunday yellow highlights, derived day names, and full 31-day structures are preserved exactly in both the Excel (XLSX) and PDF export files, matching the layout of the digital dashboard.

---

## 6. Verification & Operational Diagnostics

To verify monthly calendar generation, Sunday highlighting, immutability gates, and role-based IT department controls, run the following unit tests and checks:

```bash
# Run PHPUnit tests covering backup tape logs, field locks, and auto-population
php scripts/run_tests.php --filter BackupTapeLog
```
