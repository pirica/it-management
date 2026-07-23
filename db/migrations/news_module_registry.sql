-- Rename CVE Feed module registry row to News (existing databases that already have slug cve).
UPDATE `modules_registry`
SET `module_slug` = 'news', `module_name` = 'News', `icon` = '📰'
WHERE `module_slug` = 'cve';

INSERT INTO `modules_registry` (`module_slug`, `module_name`, `is_system_module`, `active`, `icon`)
SELECT 'news', 'News', 0, 1, '📰'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `modules_registry` WHERE `module_slug` = 'news' LIMIT 1);
