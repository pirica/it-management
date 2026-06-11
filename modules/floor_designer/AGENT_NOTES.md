# AGENT_NOTES.md - Floor Designer

## 1. Module Purpose
Provides an interactive tool for designing and visualizing floor plans, including the placement of network points, access points, and other infrastructure.

## 2. Key Tables
- **floor_designer** — main design metadata (name, location, shape).
- **floor_designer_points** — stores individual points (RJ45, SFP, APs) on the designer.

## 3. Required Relationships
- **floor_designer** → depends on **companies**.
- **floor_designer** → depends on **it_locations**.
- **floor_designer** → depends on **floor_plans** (for background image).
- **floor_designer_points** → depends on **floor_designer**.
- **floor_designer_points** → depends on **switch_port_types** (for point types).
- **floor_designer_points** → depends on **equipment** (for linked switches).
- **floor_designer_points** → depends on **switch_ports**.
- **floor_designer_points** → depends on **cable_colors**.

## 4. Business Rules (Critical for Agents)
- **Coordinate System**: Points use `x` and `y` coordinates (decimal) relative to the designer container.
- **Layer Toggling**: Supports toggling visibility for different point types (RJ45, Fiber, APs).
- **Rotation**: APs and other points support rotation metadata.

## 5. UI Behavior Requirements
- **Interactive Map**: Uses SVG or Canvas (check implementation) for point placement.
- **AJAX Saving**: Points are often saved asynchronously to prevent page reloads.
- **Standard CRUD**: Supports creating and managing multiple designs.

## 6. API Actions (If Applicable)
- **save_point** — (AJAX) updates point coordinates or metadata.
- **delete_point** — (AJAX) removes a point.

## 7. File Structure
- Standard CRUD for `floor_designer` (`index.php`, `edit.php`, etc.).
- Integrated JS/CSS for the interactive designer.

## 8. Multi-Tenant Rules
- All designs and points are strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers for both designer and points.

## 10. Common Pitfalls
- **Mismatched IDs**: Ensure `switch_port_id` belongs to the selected `switch_id`.
- **Coordinate Drift**: Be careful when modifying container sizes as it might affect point placement if not handled relatively.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM floor_designer WHERE company_id = ? AND id = ?");
$stmt->bind_param("ii", $companyId, $id);
$stmt->execute();
```

### Safe INSERT (Point)
```php
$stmt = $conn->prepare("INSERT INTO floor_designer_points (company_id, floor_designer_id, point_type_id, x, y) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiidd", $companyId, $designerId, $typeId, $x, $y);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Highly visual module; requires testing across different screen resolutions.
