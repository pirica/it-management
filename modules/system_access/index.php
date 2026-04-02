<?php
require '../../config/config.php';
require '../../includes/employee_system_access.php';

esa_ensure_table($conn);

function sa_build_query($params) {
    $normalized = [];
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $normalized[$k] = $v;
    }
    return http_build_query($normalized);
}

$messages = [];
$errors = [];
$csrfToken = itm_get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'import_system_access')) {
    itm_require_post_csrf();

    $importText = trim((string)($_POST['import_text'] ?? ''));
    if ($importText === '') {
        $errors[] = 'Import text is empty.';
    } else {
        $lines = preg_split('/\r\n|\n|\r/', $importText);
        $created = 0;
        $updated = 0;
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            $parts = str_getcsv($line);
            if (count($parts) < 2) {
                continue;
            }

            $code = trim((string)$parts[0]);
            $name = trim((string)$parts[1]);
            $activeRaw = strtolower(trim((string)($parts[2] ?? '1')));
            $active = in_array($activeRaw, ['1', 'true', 'active', 'yes', 'y'], true) ? 1 : 0;

            if ($code === '' || $name === '') {
                continue;
            }

            $find = mysqli_prepare($conn, 'SELECT id FROM system_access WHERE company_id = ? AND code = ? LIMIT 1');
            $findResult = false;
            if ($find) {
                mysqli_stmt_bind_param($find, 'is', $company_id, $code);
                mysqli_stmt_execute($find);
                $findResult = mysqli_stmt_get_result($find);
            }
            if ($findResult && mysqli_num_rows($findResult) === 1) {
                $row = mysqli_fetch_assoc($findResult);
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    $update = mysqli_prepare($conn, 'UPDATE system_access SET name = ?, active = ? WHERE id = ? AND company_id = ?');
                    if ($update) {
                        mysqli_stmt_bind_param($update, 'siii', $name, $active, $id, $company_id);
                        if (mysqli_stmt_execute($update)) {
                            $updated += 1;
                        }
                        mysqli_stmt_close($update);
                    }
                }
            } else {
                $insert = mysqli_prepare($conn, 'INSERT INTO system_access (company_id, code, name, active) VALUES (?, ?, ?, ?)');
                if ($insert) {
                    mysqli_stmt_bind_param($insert, 'issi', $company_id, $code, $name, $active);
                    if (mysqli_stmt_execute($insert)) {
                        $created += 1;
                    }
                    mysqli_stmt_close($insert);
                }
            }
            if ($find) {
                mysqli_stmt_close($find);
            }
        }

        $messages[] = "Import completed. Created: {$created}, Updated: {$updated}.";
    }
}

$searchRaw = trim((string)($_GET['search'] ?? ''));
$searchSql = '';
$bindSearch = '';
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $bindSearch = $searchPattern;
    $searchSql = " AND (
        CAST(id AS CHAR) LIKE ?
        OR code LIKE ?
        OR name LIKE ?
        OR CAST(active AS CHAR) LIKE ?
    )";
}

$sortableColumns = ['code', 'name', 'active'];
$sort = (string)($_GET['sort'] ?? 'name');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'name';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = '`' . str_replace('`', '``', $sort) . '` ' . $dir;

if (($_GET['export'] ?? '') === 'csv') {
    $rows = false;
    $exportSql = "SELECT code, name, active FROM system_access WHERE company_id = ?{$searchSql} ORDER BY {$sortSql}";
    $stmt = mysqli_prepare($conn, $exportSql);
    if ($stmt) {
        if ($bindSearch !== '') {
            mysqli_stmt_bind_param($stmt, 'issss', $company_id, $bindSearch, $bindSearch, $bindSearch, $bindSearch);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $company_id);
        }
        mysqli_stmt_execute($stmt);
        $rows = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="system_access_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Code', 'Name', 'Active']);
    while ($rows && ($row = mysqli_fetch_assoc($rows))) {
        fputcsv($output, [$row['code'] ?? '', $row['name'] ?? '', (int)($row['active'] ?? 0)]);
    }
    fclose($output);
    exit;
}

$items = false;
$itemsSql = "SELECT id, code, name, active FROM system_access WHERE company_id = ?{$searchSql} ORDER BY {$sortSql}";
$stmt = mysqli_prepare($conn, $itemsSql);
if ($stmt) {
    if ($bindSearch !== '') {
        mysqli_stmt_bind_param($stmt, 'issss', $company_id, $bindSearch, $bindSearch, $bindSearch, $bindSearch);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
    }
    mysqli_stmt_execute($stmt);
    $items = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Access</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>🛡️ System Access</h1>
                <a class="btn btn-primary" href="create.php">➕</a>
            </div>

            <?php foreach ($messages as $message): ?>
                <div class="alert alert-success" style="margin-bottom:10px;"><?php echo sanitize($message); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger" style="margin-bottom:10px;"><?php echo sanitize($error); ?></div>
            <?php endforeach; ?>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="systemAccessSearch">Search (all fields)</label>
                        <input type="text" id="systemAccessSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Use SQL wildcards, e.g. %%network%%">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn btn-sm">Clear</a>
                        <a href="?<?php echo sanitize(sa_build_query(['search' => $searchRaw, 'sort' => $sort, 'dir' => $dir, 'export' => 'csv'])); ?>" class="btn btn-sm">⬇ Export CSV</a>
                    </div>
                </form>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="action" value="import_system_access">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label for="systemAccessImport">Import CSV text (code,name,active)</label>
                        <textarea id="systemAccessImport" name="import_text" rows="4" placeholder="NET01,Network Access,1\nERP,ERP Access,1"></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm">📥 Import</button>
                </form>
            </div>

            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <?php foreach (['code' => 'Code', 'name' => 'Name', 'active' => 'Status'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($items && mysqli_num_rows($items)): while ($item = mysqli_fetch_assoc($items)): ?>
                        <tr>
                            <td><?php echo sanitize($item['code']); ?></td>
                            <td><?php echo sanitize($item['name']); ?></td>
                            <td>
                                <span class="badge <?php echo (int)$item['active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo (int)$item['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm" href="view.php?id=<?php echo (int)$item['id']; ?>">👁️</a>
                                <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$item['id']; ?>">✏️</a>
                                <form method="POST" action="delete.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                    <button class="btn btn-sm btn-danger" type="submit" onclick="return confirm('Delete system access record?');">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="4" style="text-align:center;">No system access records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
