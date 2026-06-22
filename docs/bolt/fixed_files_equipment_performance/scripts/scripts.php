<?php
/**
 * Registered scripts for the equipment performance optimization.
 */
return [
    'profile_equipment' => [
        'path' => 'docs/bolt/scripts/bolt_profile_equipment.php',
        'description' => 'Profiles MySQL query count and timing for the equipment module index page.'
    ],
    'auto_fix' => [
        'path' => 'docs/bolt/fix_equipment_performance/auto_fix_vuln.php',
        'description' => 'Applies the performance optimization to modules/equipment/index.php.'
    ]
];
