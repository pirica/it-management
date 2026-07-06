<?php
/**
 * Script catalog for Equipment Performance Optimization
 */

return [
    'auto_fix_performance_equipment' => [
        'path' => 'docs/bolt/fix_equipment/auto_fix_vuln.php',
        'description' => 'Applies performance optimizations to the Equipment module (lazy loading, minimal joins).',
        'access' => 'CLI'
    ]
];
