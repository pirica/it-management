<?php
/**
 * Main scripts registration file for documentation and verification.
 */

return [
    [
        'name' => 'Repro Explorer Traversal',
        'path' => 'docs/scripts/repro_explorer_traversal.php',
        'description' => 'Simulates Path Traversal exploit in Explorer module zip action.',
        'type' => 'reproduction'
    ],
    [
        'name' => 'Verify Explorer Fix',
        'path' => 'docs/scripts/verify_explorer_fix.php',
        'description' => 'Verifies the fix for Path Traversal in Explorer module.',
        'type' => 'verification'
    ]
];
