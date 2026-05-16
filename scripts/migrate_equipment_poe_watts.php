<?php
/**
 * One-time migration: add equipment_poe.watts and split legacy combined name values.
 *
 * Usage: php scripts/migrate_equipment_poe_watts.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/equipment_poe_helpers.php';

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

$colRes = mysqli_query($conn, "SHOW COLUMNS FROM `equipment_poe` LIKE 'watts'");
$hasWatts = ($colRes && mysqli_num_rows($colRes) > 0);

if (!$hasWatts) {
    $alterSql = "ALTER TABLE `equipment_poe`
        ADD COLUMN `watts` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
        AFTER `name`";
    if (!mysqli_query($conn, $alterSql)) {
        fwrite(STDERR, 'ALTER failed: ' . mysqli_error($conn) . "\n");
        exit(1);
    }
    echo "[OK] Added equipment_poe.watts column.\n";
} else {
    echo "[SKIP] equipment_poe.watts already exists.\n";
}

$res = mysqli_query($conn, 'SELECT id, name, watts FROM equipment_poe ORDER BY id ASC');
if (!$res) {
    fwrite(STDERR, 'SELECT failed: ' . mysqli_error($conn) . "\n");
    exit(1);
}

$updated = 0;
$stmt = mysqli_prepare($conn, 'UPDATE equipment_poe SET name = ?, watts = ? WHERE id = ?');
if (!$stmt) {
    fwrite(STDERR, 'Prepare failed: ' . mysqli_error($conn) . "\n");
    exit(1);
}

while ($row = mysqli_fetch_assoc($res)) {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $nameVal = trim((string)($row['name'] ?? ''));
    $wattsVal = trim((string)($row['watts'] ?? ''));

    if ($wattsVal === '' && strrpos($nameVal, ' - ') !== false) {
        $split = itm_equipment_poe_split_legacy_name($nameVal);
        $newName = $split['name'];
        $newWatts = $split['watts'];
        if ($newName !== $nameVal || $newWatts !== $wattsVal) {
            mysqli_stmt_bind_param($stmt, 'ssi', $newName, $newWatts, $id);
            if (!mysqli_stmt_execute($stmt)) {
                fwrite(STDERR, "Update failed for id {$id}: " . mysqli_stmt_error($stmt) . "\n");
                exit(1);
            }
            $updated++;
        }
    }
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
echo "[OK] Backfilled {$updated} equipment_poe row(s).\n";
exit(0);
