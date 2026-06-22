<?php
/**
 * Fix script for Audit Logs module.
 *
 * This script programmatically fixes the identified bugs in
 * modules/audit_logs/index.php and modules/audit_logs/view.php.
 */

$filesToFix = [
    __DIR__ . '/../../modules/audit_logs/index.php',
    __DIR__ . '/../../modules/audit_logs/view.php',
];

foreach ($filesToFix as $file) {
    if (!is_file($file)) {
        echo "File not found: $file\n";
        continue;
    }

    echo "Fixing $file...\n";
    $content = file_get_contents($file);

    // 1. Fix employee_id -> user_id
    $content = str_replace('al.employee_id', 'al.user_id', $content);

    // 2. Fix u.email -> u.work_email
    $content = str_replace('u.email', 'u.work_email', $content);

    if (file_put_contents($file, $content) !== false) {
        echo "Successfully fixed $file\n";
    } else {
        echo "Failed to fix $file\n";
    }
}
