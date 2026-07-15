# AGENT_NOTES.md - Floor Designer Points

## 1. Module Purpose
Helper module to manage the individual points/elements placed within the Floor Designer.

## 2. Key Tables
- **floor_designer_points** — stores coordinates, types, and links for each point.

## 3. Required Relationships
- **floor_designer_points** → depends on **companies**.
- **floor_designer_points** → depends on **floor_designer**.
- **floor_designer_points** → links to **switch_ports**, **equipment**, and **cable_colors**.

## 4. Business Rules (Critical for Agents)
- **Point Normalization**: Type names like 'Access Point' should be normalized (e.g., hyphenated in CSS classes) for UI consistency.
- **ID-Based Updates**: AJAX handlers must explicitly map empty or '0' values for foreign keys to NULL.

## 5. UI Behavior Requirements
- **Modal Configuration**: Clicking a point in the designer should open a modal to edit its properties.
- **Drag & Drop**: Points can be moved on the designer, updating their `x` and `y` values.

## 6. API Actions (If Applicable)
- **save_point** — updates point data.

## 7. File Structure
- Standard CRUD files, but primarily interacted with via AJAX from the `floor_designer` module.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Coordinate Reset**: Updating point metadata must preserve its spatial coordinates (`x`, `y`). [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe UPDATE (Metadata Only)
```php
$stmt = $conn->prepare("UPDATE floor_designer_points SET label = ?, point_type_id = ? WHERE id = ? AND company_id = ?");
$stmt->bind_param("siii", $label, $typeId, $id, $companyId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
This module is the "data" layer for the visual designer.
