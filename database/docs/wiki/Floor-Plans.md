# Floor Plans Gallery

Reference Data → **Floor Plans** (`modules/floor_plans/`) stores building layouts and drawings per company.

![Floor Plans gallery](../docs/readme/floor_plans.png)

## Capabilities

| Capability | Details |
| --- | --- |
| **Files** | Images (JPEG, PNG, GIF, WebP), PDF, AutoCAD (DWG, DXF, DWF, DWS); 20 MB per file |
| **Folders** | Nested folder tree; create, rename, delete (empty only), and **move** into another folder or root |
| **Tags** | Comma-separated tags on upload; shared tag list per company. Evaluated via a company-scoped junction table (`floor_plan_item_tags`) |
| **IT Locations** | Optional nullable link from each file to an IT Location (`it_location_id`) |
| **Moves** | Drag file cards onto folders (or **Unfiled**); drag folders onto another folder or **All files (root)** |
| **Views** | Gallery index (default), table view (`list_all.php`), file detail/preview |
| **Storage** | `floor_plans/{company_id}/` (see `FLOOR_PLAN_*` constants in `config/config.php`) |

## Move folder

1. Open a folder in the sidebar.
2. Use **Move folder** → choose **Move into** (target folder or **— Root —**), or drag the folder in the tree.

The module blocks moving a folder into itself or its subfolders and rejects duplicate names at the same level.

## Setup

1. Import the Floor Plans section from `database/01_schema.sql`, `database/02_triggers.sql`, and `database/03_data.sql` on existing databases (`floor_plan_folders`, `floor_plan_tags`, `floor_plans`, `floor_plan_item_tags`).
2. Create the `floor_plans/` directory at the project root (company subfolders are created automatically).
3. If tables are missing, the gallery shows an explicit migration message instead of a generic company error.

## Related documentation

- [Installation](Installation) — directory and migration steps
- [Modules Overview](Modules)
- [Import Excel](Import-Excel) — index table JSON import for modules that support it
