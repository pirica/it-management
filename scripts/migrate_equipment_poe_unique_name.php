<?php
/**
 * Ensure equipment_poe.name is unique per company (company_id + name).
 *
 * Why: PoE standards must not duplicate within a tenant; older DBs may lack the index.
 *
 * Usage: php scripts/migrate_equipment_poe_unique_name.php
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

$targetIndex = 'uq_equipment_poe_company_name';
$legacyIndex = 'name';

$dupRes = mysqli_query(
    $conn,
    'SELECT company_id, name, COUNT(*) AS row_count
     FROM equipment_poe
     GROUP BY company_id, name
     HAVING row_count > 1
     ORDER BY company_id, name'
);
$duplicates = [];
if ($dupRes) {
    while ($row = mysqli_fetch_assoc($dupRes)) {
        $duplicates[] = $row;
    }
}

if ($duplicates !== []) {
    fwrite(STDERR, "[FAIL] Duplicate PoE names found within the same company. Resolve these before adding UNIQUE:\n");
    foreach ($duplicates as $dup) {
        fwrite(STDERR, sprintf(
            "  company_id=%d name=%s (%d rows)\n",
            (int)$dup['company_id'],
            (string)$dup['name'],
            (int)$dup['row_count']
        ));
    }
    exit(1);
}

/**
 * @return array<string, array{non_unique: int, columns: array<int, string>}>
 */
function itm_poe_load_indexes(mysqli $conn): array
{
    $indexes = [];
    $res = mysqli_query($conn, 'SHOW INDEX FROM `equipment_poe`');
    if (!$res) {
        return $indexes;
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $keyName = (string)($row['Key_name'] ?? '');
        if ($keyName === '') {
            continue;
        }
        if (!isset($indexes[$keyName])) {
            $indexes[$keyName] = [
                'non_unique' => (int)($row['Non_unique'] ?? 1),
                'columns' => [],
            ];
        }
        $seq = (int)($row['Seq_in_index'] ?? 0);
        if ($seq > 0) {
            $indexes[$keyName]['columns'][$seq] = (string)($row['Column_name'] ?? '');
        }
    }

    return $indexes;
}

function itm_poe_find_company_name_unique_index(array $indexes): ?string
{
    foreach ($indexes as $keyName => $meta) {
        if ((int)$meta['non_unique'] !== 0 || $keyName === 'PRIMARY') {
            continue;
        }
        ksort($meta['columns']);
        $columns = array_values($meta['columns']);
        if ($columns === ['company_id', 'name']) {
            return (string)$keyName;
        }
    }

    return null;
}

$indexes = itm_poe_load_indexes($conn);
$existingUnique = itm_poe_find_company_name_unique_index($indexes);

if ($existingUnique === $targetIndex) {
    echo "[SKIP] Unique index {$targetIndex} (company_id, name) already exists.\n";
    mysqli_close($conn);
    exit(0);
}

if ($existingUnique !== null && $existingUnique !== $targetIndex) {
    $renameSql = "ALTER TABLE `equipment_poe` RENAME INDEX `{$existingUnique}` TO `{$targetIndex}`";
    if (!mysqli_query($conn, $renameSql)) {
        fwrite(STDERR, 'RENAME INDEX failed: ' . mysqli_error($conn) . "\n");
        exit(1);
    }
    echo "[OK] Renamed unique index {$existingUnique} -> {$targetIndex}.\n";
    mysqli_close($conn);
    exit(0);
}

if (isset($indexes[$legacyIndex]) || isset($indexes[$targetIndex])) {
    fwrite(STDERR, "[FAIL] equipment_poe has an unexpected index on name/company_id. Review SHOW INDEX FROM equipment_poe.\n");
    exit(1);
}

$alterSql = "ALTER TABLE `equipment_poe`
    ADD UNIQUE KEY `{$targetIndex}` (`company_id`, `name`)";
if (!mysqli_query($conn, $alterSql)) {
    fwrite(STDERR, 'ALTER failed: ' . mysqli_error($conn) . "\n");
    exit(1);
}

echo "[OK] Added unique index {$targetIndex} (company_id, name).\n";
mysqli_close($conn);
exit(0);
