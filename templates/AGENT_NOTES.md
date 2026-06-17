Read AGENTS.md 
Read README.md 
Read scripts/scripts.php 
Read phpunit/* 
Read scripts/api.php 
Read scripts/SCRIPTS.md
Read database.sql 
Read full project

On base on your learnings edit/update or create AGENT_NOTES.md is none exists for each modules/ on base of this (Module Template) don't use scripts to auto the process.
AGENT_NOTES.md (Module Template)
1. Module Purpose - Briefly describe what this module does and why it exists.
Example: This module manages workstation assets, including OS version, RAM, office location, and assignment history.

2. Key Tables - List only the tables this module owns or primarily interacts with.
Format:
table_name — purpose
table_name — purpose
Example:
workstations — main workstation records
workstation_ram — lookup table for RAM sizes

3. Required Relationships - Document any foreign keys or cross‑module dependencies.
Format:
This module → depends on X
This module → referenced by Y
Example:
Workstations link to employees via employee_id.
Workstations link to equipment when a workstation is also an asset.

4. Business Rules (Critical for Agents) - List the rules that must never be violated.
Examples:
A workstation cannot be assigned to an inactive employee.
OS version must exist in workstation_os_versions.
Deleting a workstation must archive assignment history, not remove it.

5. UI Behavior Requirements - Document UI constraints that agents must preserve.
Examples:
List view must support search, sort, pagination, and export.
Edit form must include CSRF token and permission checks.
Inline editing is allowed only for specific fields.

6. API Actions (If Applicable) - Document any API endpoints this module exposes.
Format:
action_name — purpose, required params, response format
Example:
get_workstations — returns filtered list
update_workstation — updates fields with CSRF + permission checks

7. File Structure - List the files in this module and their purpose.
Example:
index.php — list view
add.php — create form
edit.php — update form
delete.php — delete handler
ajax/update_status.php — async status updates

8. Multi‑Tenant Rules - Document any module‑specific scoping rules.
Examples:
All queries must filter by company_id.
Workstations cannot be moved between companies.

9. Audit Logging Requirements - Document what actions must be logged.
Examples:
Creating, editing, deleting, and reassigning workstations must log to audit_logs.
Include workstation_id in metadata.

10. Common Pitfalls - List mistakes agents must avoid.
Examples:
Do not delete workstation records directly — archive instead.
Do not allow OS version changes without validation.
Do not bypass assignment history updates.

11. Examples of Safe Code Patterns - Provide 1–2 examples of correct patterns for this module.
Example:
Safe SELECT php $stmt = $db->prepare("SELECT * FROM workstations WHERE company_id = ? AND id = ?"); $stmt->bind_param("ii", $companyId, $id); $stmt->execute(); Safe INSERT php $stmt = $db->prepare("INSERT INTO workstations (company_id, name, created_by) VALUES (?, ?, ?)"); $stmt->bind_param("isi", $companyId, $name, $userId); $stmt->execute();

12. Module Owner Notes (Optional) - Anything future maintainers or agents should know.
