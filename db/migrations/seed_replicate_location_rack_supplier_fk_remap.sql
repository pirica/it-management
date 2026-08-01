-- DML only: remap cross-tenant FK ids on companies 2–5 after legacy @replicate_source_company_id copies.
-- Fresh imports get correct ids from db/02_data.sql; apply this on existing databases once (idempotent when already fixed).

UPDATE `it_locations` child
INNER JOIN `location_types` src ON src.`id` = child.`type_id` AND src.`company_id` <> child.`company_id`
INNER JOIN `location_types` tgt ON tgt.`company_id` = child.`company_id` AND tgt.`name` = src.`name`
SET child.`type_id` = tgt.`id`;

UPDATE `racks` child
INNER JOIN `it_locations` loc_src ON loc_src.`id` = child.`location_id` AND loc_src.`company_id` <> child.`company_id`
INNER JOIN `it_locations` loc_tgt ON loc_tgt.`company_id` = child.`company_id` AND loc_tgt.`name` = loc_src.`name`
SET child.`location_id` = loc_tgt.`id`;

UPDATE `racks` child
INNER JOIN `rack_statuses` src ON src.`id` = child.`status_id` AND src.`company_id` <> child.`company_id`
INNER JOIN `rack_statuses` tgt ON tgt.`company_id` = child.`company_id` AND tgt.`name` = src.`name`
SET child.`status_id` = tgt.`id`;

UPDATE `suppliers` child
INNER JOIN `supplier_statuses` src ON src.`id` = child.`status_id` AND src.`company_id` <> child.`company_id`
INNER JOIN `supplier_statuses` tgt ON tgt.`company_id` = child.`company_id` AND tgt.`name` = src.`name`
SET child.`status_id` = tgt.`id`;

UPDATE `idfs` child
INNER JOIN `it_locations` loc_src ON loc_src.`id` = child.`location_id` AND loc_src.`company_id` <> child.`company_id`
INNER JOIN `it_locations` loc_tgt ON loc_tgt.`company_id` = child.`company_id` AND loc_tgt.`name` = loc_src.`name`
SET child.`location_id` = loc_tgt.`id`;

UPDATE `idfs` child
INNER JOIN `racks` r_src ON r_src.`id` = child.`rack_id` AND r_src.`company_id` <> child.`company_id`
INNER JOIN `racks` r_tgt ON r_tgt.`company_id` = child.`company_id` AND r_tgt.`name` = r_src.`name`
SET child.`rack_id` = r_tgt.`id`;
