# AGENT_NOTES.md - IDFs

## 1. Module Purpose
Manages Intermediate Distribution Frames (IDFs), including their location, rack layout, and contained devices.

## 2. Key Tables
- **idfs** — main IDF metadata.

## 3. Required Relationships
- **idfs** → depends on **companies**.
- **idfs** → depends on **it_locations**.
- **idfs** → depends on **racks** (optional).

## 4. Business Rules (Critical for Agents)
- **Protection Zone:** Do not modify logic or structure unless explicitly requested (see AGENTS.md §3).
- **IDF sync guardrail (mandatory):** all Create/Edit/Update/Delete/Copy/Move across IDF workflows must keep `idf_ports`, `switch_ports`, `equipment`, `idf_device_type`, `idf_positions`, `idfs`, and `idf_links` synchronized — use transactions; rollback on failure. Run `php scripts/idfs_sync_human_test.php` after changes.
- **Unique Name:** IDF name must be unique per company.
- **Physical Link:** Every IDF should be tied to a physical location.
- **API:** `modules/idfs/api/` handles async position/port/link operations — see that folder's `AGENT_NOTES.md`.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Integrated View**: Shows the rack elevation, ports, and links for the IDF.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.
- **api/** — handles async updates for rack positions and port configurations.

## 7. File Structure
- **index.php** — main list view.
- **view.php** — detailed IDF dashboard.
- **device.php** — management of devices within the IDF.
- **port_visualizer_helper.php** — logic for rendering port grids.

## 8. Multi-Tenant Rules
- Strictly scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls
- **Configuration Complexity**: IDFs have many related tables; ensure all relations are handled during creation/deletion.

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM idfs WHERE company_id = ? AND name = ?");
$stmt->bind_param("is", $companyId, $name);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
The central module for network physical infrastructure.
