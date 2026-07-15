# AGENT_NOTES.md - ip_addresses/includes/partials

## 1. Module Purpose
HTML/PHP partial templates included by `modules/ip_addresses/index.php` and related screens.

## 5. UI Behavior Requirements
- FK columns must show subnet/name labels, not raw IDs.
- Match action-column markers (`itm-actions-cell`) when rendering tables.

## 10. Common Pitfalls

[Confirmed] No pitfalls documented

## 12. Module Owner Notes (Optional)
Keep partials free of standalone request handling — auth and CSRF belong in parent entry files.
