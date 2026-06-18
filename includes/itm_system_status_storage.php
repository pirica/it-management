<?php
/**
 * Why: System Status Monitoring tab needs on-disk storage breakdown for Explorer and upload trees.
 */

if (!function_exists('itm_mysqli_stmt_fetch_all_assoc')) {
    require_once __DIR__ . '/itm_role_module_permissions.php';
}

function itm_system_status_format_bytes(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return $bytes . ' B';
}

/**
 * Why: Managed placeholders and agent docs are not user/upload content for Sub Storage totals.
 */
function itm_system_status_is_ignored_storage_file(string $filename): bool
{
    $base = basename(str_replace('\\', '/', $filename));
    if ($base === '.htaccess') {
        return true;
    }
    if (strcasecmp($base, 'index.html') === 0) {
        return true;
    }
    if (strcasecmp($base, 'AGENT_NOTES.md') === 0) {
        return true;
    }

    return false;
}

/**
 * Why: Parent folders with children need direct-file totals without re-counting subdirectories.
 *
 * @return array{bytes:int,files:int}
 */
function itm_system_status_directory_direct_metrics(string $absolutePath): array
{
    $bytes = 0;
    $files = 0;
    if (!is_dir($absolutePath) || !is_readable($absolutePath)) {
        return ['bytes' => 0, 'files' => 0];
    }

    try {
        foreach (new FilesystemIterator($absolutePath, FilesystemIterator::SKIP_DOTS) as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if (itm_system_status_is_ignored_storage_file($fileInfo->getFilename())) {
                continue;
            }
            $size = $fileInfo->getSize();
            if ($size !== false) {
                $bytes += (int)$size;
                $files++;
            }
        }
    } catch (Throwable $e) {
        return ['bytes' => 0, 'files' => 0];
    }

    return ['bytes' => $bytes, 'files' => $files];
}

/**
 * @return array{bytes:int,files:int}
 */
function itm_system_status_directory_metrics(string $absolutePath): array
{
    $bytes = 0;
    $files = 0;
    if (!is_dir($absolutePath) || !is_readable($absolutePath)) {
        return ['bytes' => 0, 'files' => 0];
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if (itm_system_status_is_ignored_storage_file($fileInfo->getFilename())) {
                continue;
            }
            $size = $fileInfo->getSize();
            if ($size !== false) {
                $bytes += (int)$size;
                $files++;
            }
        }
    } catch (Throwable $e) {
        return ['bytes' => 0, 'files' => 0];
    }

    return ['bytes' => $bytes, 'files' => $files];
}

/**
 * @param array<int,array{label:string,path:string,bytes:int,files:int,children?:array}> $children
 * @return array{label:string,path:string,bytes:int,files:int,children:array}
 */
function itm_system_status_storage_node(string $label, string $relativePath, array $children = []): array
{
    $absolutePath = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    $childBytes = 0;
    $childFiles = 0;
    foreach ($children as $child) {
        $childBytes += (int)($child['bytes'] ?? 0);
        $childFiles += (int)($child['files'] ?? 0);
    }

    if ($children) {
        $directMetrics = itm_system_status_directory_direct_metrics($absolutePath);
        $bytes = $childBytes + (int)$directMetrics['bytes'];
        $files = $childFiles + (int)$directMetrics['files'];
    } else {
        $metrics = itm_system_status_directory_metrics($absolutePath);
        $bytes = (int)$metrics['bytes'];
        $files = (int)$metrics['files'];
    }

    return [
        'label' => $label,
        'path' => $relativePath,
        'bytes' => $bytes,
        'files' => $files,
        'children' => $children,
    ];
}

/**
 * @return array<int,array{id:int,name:string}>
 */
function itm_system_status_load_companies($conn): array
{
    $rows = [];
    if (!$conn) {
        return $rows;
    }
    $res = mysqli_query($conn, 'SELECT id, company FROM companies ORDER BY id ASC');
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = ['id' => (int)$row['id'], 'name' => (string)$row['company']];
        }
    }

    return $rows;
}

/**
 * @return array<int,array{id:int,name:string}>
 */
function itm_system_status_load_departments_for_company($conn, int $companyId): array
{
    $rows = [];
    if (!$conn || $companyId <= 0) {
        return $rows;
    }
    $stmt = mysqli_prepare($conn, 'SELECT id, name FROM departments WHERE company_id = ? ORDER BY name ASC');
    if (!$stmt) {
        return $rows;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    foreach (itm_mysqli_stmt_fetch_all_assoc($stmt) as $row) {
        $rows[] = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * @return array<int,array{id:int,username:string,label:string}>
 */
function itm_system_status_load_users_for_company($conn, int $companyId): array
{
    $rows = [];
    if (!$conn || $companyId <= 0) {
        return $rows;
    }
    $sql = 'SELECT u.id, u.username, u.first_name, u.last_name
            FROM users u
            INNER JOIN user_companies uc ON uc.user_id = u.id AND uc.company_id = ?
            ORDER BY u.username ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $rows;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    foreach (itm_mysqli_stmt_fetch_all_assoc($stmt) as $row) {
        $fullName = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
        $label = $fullName !== '' ? $fullName . ' (' . $row['username'] . ')' : (string)$row['username'];
        $rows[] = [
            'id' => (int)$row['id'],
            'username' => (string)$row['username'],
            'label' => $label,
        ];
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * @return array{sections:array,total_bytes:int,total_files:int}
 */
function itm_system_status_build_storage_report($conn): array
{
    $sections = [];
    $totalBytes = 0;
    $totalFiles = 0;

    $explorerChildren = [];
    $companies = itm_system_status_load_companies($conn);
    foreach ($companies as $company) {
        $companyId = $company['id'];
        $companyPrefix = 'files/' . $companyId . '/';

        $segmentChildren = [];
        foreach (['Common' => 'Common/', 'Trash' => 'Trash/'] as $segmentLabel => $segmentDir) {
            $segmentChildren[] = itm_system_status_storage_node(
                $segmentLabel,
                $companyPrefix . $segmentDir
            );
        }

        $departmentChildren = [];
        $departmentsRoot = ROOT_PATH . 'files/' . $companyId . '/Departments/';
        $departmentIdsOnDisk = [];
        if (is_dir($departmentsRoot)) {
            foreach (scandir($departmentsRoot) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || !is_dir($departmentsRoot . $entry)) {
                    continue;
                }
                if (ctype_digit($entry)) {
                    $departmentIdsOnDisk[] = (int)$entry;
                }
            }
        }
        $departmentsById = [];
        foreach (itm_system_status_load_departments_for_company($conn, $companyId) as $dept) {
            $departmentsById[$dept['id']] = $dept['name'];
        }
        $allDeptIds = array_unique(array_merge(array_keys($departmentsById), $departmentIdsOnDisk));
        sort($allDeptIds);
        foreach ($allDeptIds as $deptId) {
            $deptName = $departmentsById[$deptId] ?? ('Department ' . $deptId);
            $departmentChildren[] = itm_system_status_storage_node(
                $deptName . ' (dept ' . $deptId . ')',
                $companyPrefix . 'Departments/' . $deptId . '/'
            );
        }
        $segmentChildren[] = itm_system_status_storage_node(
            'Departments/',
            $companyPrefix . 'Departments/',
            $departmentChildren
        );

        $privateChildren = [];
        $privateRoot = ROOT_PATH . 'files/' . $companyId . '/Private/';
        $usersByFolder = [];
        foreach (itm_system_status_load_users_for_company($conn, $companyId) as $user) {
            $usersByFolder[$user['username'] . '_' . $user['id']] = $user['label'];
        }
        if (is_dir($privateRoot)) {
            foreach (scandir($privateRoot) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || !is_dir($privateRoot . $entry)) {
                    continue;
                }
                $label = $usersByFolder[$entry] ?? $entry;
                $privateChildren[] = itm_system_status_storage_node(
                    $label,
                    $companyPrefix . 'Private/' . $entry . '/'
                );
            }
        }
        $segmentChildren[] = itm_system_status_storage_node(
            'Private/',
            $companyPrefix . 'Private/',
            $privateChildren
        );

        $explorerChildren[] = itm_system_status_storage_node(
            $company['name'] . ' (company ' . $companyId . ')',
            'files/' . $companyId . '/',
            $segmentChildren
        );
    }

    $sections[] = itm_system_status_storage_node(
        'Explorer — files/',
        'files/',
        $explorerChildren
    );

    foreach (['tickets_photos/' => 'tickets_photos/', 'images/' => 'images/', 'backups/' => 'backups/'] as $label => $path) {
        $sections[] = itm_system_status_storage_node($label, $path);
    }

    $floorPlanChildren = [];
    $floorPlansRoot = ROOT_PATH . 'floor_plans/';
    if (is_dir($floorPlansRoot)) {
        foreach (scandir($floorPlansRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($floorPlansRoot . $entry)) {
                continue;
            }
            if (!ctype_digit($entry)) {
                continue;
            }
            $companyId = (int)$entry;
            $companyName = 'Company ' . $companyId;
            foreach ($companies as $company) {
                if ($company['id'] === $companyId) {
                    $companyName = $company['name'];
                    break;
                }
            }
            $floorPlanChildren[] = itm_system_status_storage_node(
                $companyName . ' (company ' . $companyId . ')',
                'floor_plans/' . $companyId . '/'
            );
        }
    }
    $sections[] = itm_system_status_storage_node('floor_plans/', 'floor_plans/', $floorPlanChildren);

    foreach ($sections as $section) {
        $totalBytes += (int)$section['bytes'];
        $totalFiles += (int)$section['files'];
    }

    return [
        'sections' => $sections,
        'total_bytes' => $totalBytes,
        'total_files' => $totalFiles,
    ];
}

/**
 * @return array{database:string,tables:array,total_rows:int,total_size_mb:float,table_count:int}
 */
function itm_system_status_build_database_table_report($conn, string $databaseName): array
{
    $tables = [];
    $totalRows = 0;
    $totalSizeMb = 0.0;

    if (!$conn || $databaseName === '') {
        return [
            'database' => $databaseName,
            'tables' => [],
            'total_rows' => 0,
            'total_size_mb' => 0.0,
            'table_count' => 0,
        ];
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT table_name, table_rows,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
         FROM information_schema.TABLES
         WHERE table_schema = ?
         ORDER BY table_name ASC'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $databaseName);
        mysqli_stmt_execute($stmt);
        foreach (itm_mysqli_stmt_fetch_all_assoc($stmt) as $row) {
            $rowLower = array_change_key_case($row, CASE_LOWER);
            $rowCount = (int)($rowLower['table_rows'] ?? 0);
            $sizeMb = (float)($rowLower['size_mb'] ?? 0);
            $tables[] = [
                'name' => (string)($rowLower['table_name'] ?? ''),
                'rows' => $rowCount,
                'size_mb' => $sizeMb,
            ];
            $totalRows += $rowCount;
            $totalSizeMb += $sizeMb;
        }
        mysqli_stmt_close($stmt);
    }

    return [
        'database' => $databaseName,
        'tables' => $tables,
        'total_rows' => $totalRows,
        'total_size_mb' => $totalSizeMb,
        'table_count' => count($tables),
    ];
}

/**
 * @param array{label:string,path:string,bytes:int,files:int,children?:array} $node
 */
function itm_system_status_render_storage_node(array $node, int $depth = 0): void
{
    $hasChildren = !empty($node['children']);
    $indent = max(0, $depth) * 16;
    $sizeLabel = itm_system_status_format_bytes((int)$node['bytes']);
    $files = (int)$node['files'];

    if ($hasChildren) {
        echo '<details class="ss-storage-details" style="margin-left:' . (int)$indent . 'px;">';
        echo '<summary class="ss-storage-summary">';
        echo '<span class="ss-storage-label">' . sanitize($node['label']) . '</span>';
        echo '<span class="ss-storage-meta">' . sanitize($sizeLabel) . ' · ' . number_format($files) . ' files</span>';
        echo '<code class="ss-storage-path">' . sanitize($node['path']) . '</code>';
        echo '</summary>';
        echo '<div class="ss-storage-children">';
        foreach ($node['children'] as $child) {
            itm_system_status_render_storage_node($child, $depth + 1);
        }
        echo '</div></details>';
        return;
    }

    echo '<div class="ss-storage-leaf" style="margin-left:' . (int)$indent . 'px;">';
    echo '<span class="ss-storage-label">' . sanitize($node['label']) . '</span>';
    echo '<span class="ss-storage-meta">' . sanitize($sizeLabel) . ' · ' . number_format($files) . ' files</span>';
    echo '<code class="ss-storage-path">' . sanitize($node['path']) . '</code>';
    echo '</div>';
}
