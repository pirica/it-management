-- Restore sidebar emoji labels after non-UTF8 import corruption.
-- Run with: mysql --default-character-set=utf8mb4 -uroot -p itmanagement < scripts/fixes/2026-05-07_restore_sidebar_emojis.sql

UPDATE equipment_types
SET field_edit_emoji = CASE LOWER(name)
  WHEN 'switch' THEN '🔀'
  WHEN 'server' THEN '🖥️'
  WHEN 'router' THEN '🌐'
  WHEN 'firewall' THEN '🛡️'
  WHEN 'port patch panel' THEN '➿'
  WHEN 'access point' THEN '📶'
  WHEN 'workstation' THEN '💻'
  WHEN 'pos' THEN '🏧'
  WHEN 'printer' THEN '🖨️'
  WHEN 'phone' THEN '📞'
  WHEN 'cctv' THEN '🎥'
  WHEN 'other' THEN '📦'
  ELSE field_edit_emoji
END;

UPDATE idf_device_type
SET field_edit_emoji = CASE LOWER(idfdevicetype_name)
  WHEN 'switch' THEN '🔀'
  WHEN 'server' THEN '🖥️'
  WHEN 'patch_panel' THEN '➿'
  WHEN 'ups' THEN '🔋'
  WHEN 'other' THEN '📦'
  ELSE field_edit_emoji
END;

SELECT COUNT(*) AS bad_equipment_emojis
FROM equipment_types
WHERE field_edit_emoji LIKE '%?%';

SELECT COUNT(*) AS bad_idf_emojis
FROM idf_device_type
WHERE field_edit_emoji LIKE '%?%';
