-- Sample seed for patches_updates "Created by" FK testing
-- Safe to run on a tenant with existing reference data.
INSERT INTO `patches_updates`
(`company_id`, `equipment_id`, `hostname`, `ip`, `date`, `last_user_department`, `problem`, `troubleshooting`, `status_id`, `level_id` , `created_by`)
SELECT
    1,
    e.`id`,
    'sample-host-created-by-user1',
    '192.168.1.150',
    CURRENT_DATE,
    'IT',
    'Sample record to validate Created by full name rendering.',
    'Initial diagnostic entry.',
    s.`id`,
    l.`id`,
    1
FROM `equipment` e
JOIN `patches_updates_status` s ON s.`company_id` = 1 AND s.`active` = 1
JOIN `patches_updates_level` l ON l.`company_id` = 1
WHERE e.`company_id` = 1
ORDER BY e.`id`, s.`id`, l.`id`
LIMIT 1;
