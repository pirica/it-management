# AGENT_NOTES.md - Catalogs

## 1. Module Purpose
Maintains a product catalog for IT equipment, including models, prices, images, and vendor links.

## 2. Key Tables
- **catalogs** — main catalog data.

## 3. Required Relationships
- **catalogs** → depends on **companies**.
- **catalogs** → depends on **equipment_types**.
- **catalogs** → depends on **suppliers** (via `supplier_id`).
- **catalogs** → depends on **manufacturers** (via `manufacturer_id`).

## 4. Business Rules (Critical for Agents)
- **Unique Model/Supplier**: The combination of `company_id`, `model`, and `supplier_id` must be unique.
- **Image URL**: Should be a valid URL for product images.

## 5. UI Behavior Requirements
- **Standard CRUD**.
- **Image Previews**: List view often displays a thumbnail of the product.

## 6. API Actions (If Applicable)
- **import_excel_rows** — handles bulk JSON import.

## 7. File Structure
- Standard CRUD structure.

## 8. Multi-Tenant Rules
- Scoped by `company_id`.

## 9. Audit Logging Requirements
- Managed via database triggers.

## 10. Common Pitfalls

- **Soft-delete + audit meta:** list hides `created_*`/`updated_*`/`deleted_*` and filters `deleted_at IS NULL`; view shows those six meta fields (`*_by` as employee name, `*_at` as `d-m-Y - H:i:s`); create/edit stamp `created_*`/`updated_*` via hidden inputs; delete soft-sets `deleted_by`/`deleted_at`. Helpers: `includes/itm_crud_audit_fields.php`. Inventory: `docs/list_soft-delete.txt`. [Cursor-Fixed]
- Soft-deleted rows still occupy unique keys — recreating the same name may collide until purged. [Cursor-Valid]
- Duplicate entries for the same model across different suppliers (if not intentional). [Cursor-Valid]

## 11. Examples of Safe Code Patterns

### Safe SELECT
```php
$stmt = $conn->prepare("SELECT * FROM catalogs WHERE company_id = ? AND manufacturer_id = ?");
$stmt->bind_param("ii", $companyId, $manufacturerId);
$stmt->execute();
```

### Safe INSERT
```php
$stmt = $conn->prepare("INSERT INTO catalogs (company_id, model, price, supplier_id) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isdi", $companyId, $model, $price, $supplierId);
$stmt->execute();
```

## 12. Module Owner Notes (Optional)
Useful for procurement planning and standardizing equipment.
