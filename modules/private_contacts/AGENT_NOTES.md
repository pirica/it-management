# AGENT_NOTES.md - Private Contacts

## 1. Module Purpose
Per-user private address book (not the shared company Contacts module). Stores personal contacts with photos, favourites, labels, and organisation fields.

## 2. Key Tables
- **private_contacts** — contact records scoped by `user_id` and `company_id`.

## 3. Required Relationships
- **private_contacts** → depends on **companies**, **users**.
- Photos stored under `files/{company_id}/Private/{username}_{user_id}/private_contacts/`.

## 4. Business Rules (Critical for Agents)
- **Strict user isolation:** all queries must include `user_id = logged-in user`. Never show another user's private contacts.
- Favourite toggle and delete are POST + CSRF (`index_logic.php`).
- Distinct from `modules/contacts/` (company directory / Protection Zone).

## 5. UI Behavior Requirements
- Custom list with search, favourite star (AJAX), photo thumbnails.
- `data-itm-db-import-endpoint` on index table for Excel import.
- Actions column uses `itm-actions-cell` markers.

## 7. File Structure
- `index.php` — HTML list view.
- `index_logic.php` — auth, POST handlers, contact query.
- `create.php`, `edit.php`, `view.php`, `delete.php`, `list_all.php` — CRUD screens.

## 8. Multi-Tenant Rules
- `company_id` plus **mandatory** `user_id` filter on every SELECT/UPDATE/DELETE.

## 9. Audit Logging Requirements
- Follow global audit settings for INSERT/UPDATE/DELETE.

## 10. Common Pitfalls
- Do not reuse company contacts visibility rules — this module is user-private only.
- Photo paths must stay inside the user's Private explorer segment.
- Do not drop `user_id` from DELETE/WHERE clauses.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM private_contacts WHERE user_id = ? AND company_id = ?");
$stmt->bind_param("ii", $userId, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Not in Protection Zone — standard CRUD fixes allowed, but preserve per-user privacy on every code path.
