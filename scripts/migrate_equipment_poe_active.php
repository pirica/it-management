<?php
/**
 * One-time migration: add equipment_poe.active with default 1 for existing rows.
 *
 * Usage: php scripts/migrate_equipment_poe_active.php
 */

declare(strict_types=1);

$host = getenv('ITM_DB_HOST') ?: '127.0.0.1';
$user = getenv('ITM_DB_USER') ?: 'root';
$pass = getenv('ITM_DB_PASS');
if ($pass === false) {
    $pass = 'itmanagement';
}
$name = getenv('ITM_DB_NAME') ?: 'itmanagement';

$conn = @mysqli_connect($host, $user, $pass, $name);
if (!$conn && $host === '127.0.0.1') {
    $conn = @mysqli_connect('localhost', $user, $pass, $name);
}
if (!$conn) {
    fwrite(STDERR, 'Database connection failed: ' . mysqli_connect_error() . "\n");
    exit(1);
}
mysqli_set_charset($conn, 'utf8mb4');

$colRes = mysqli_query($conn, "SHOW COLUMNS FROM `equipment_poe` LIKE 'active'");
$hasActive = ($colRes && mysqli_num_rows($colRes) > 0);

if (!$hasActive) {
    $alterSql = "ALTER TABLE `equipment_poe`
        ADD COLUMN `active` tinyint DEFAULT '1'
        AFTER `watts`";
    if (!mysqli_query($conn, $alterSql)) {
        fwrite(STDERR, 'ALTER failed: ' . mysqli_error($conn) . "\n");
        exit(1);
    }
    echo "[OK] Added equipment_poe.active column.\n";
} else {
    echo "[SKIP] equipment_poe.active already exists.\n";
}

if (!mysqli_query($conn, 'UPDATE equipment_poe SET active = 1 WHERE active IS NULL')) {
    fwrite(STDERR, 'Backfill failed: ' . mysqli_error($conn) . "\n");
    exit(1);
}

$updated = (int)mysqli_affected_rows($conn);
mysqli_close($conn);
echo "[OK] Set active=1 on {$updated} equipment_poe row(s) where needed.\n";
exit(0);
