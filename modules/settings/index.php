<?php
require '../../config/config.php';

$message = '';
$error = '';

function backup_filename() {
    return 'backup_' . date('d_M_Y') . '_' . date('His') . '.sql';
}

function build_sql_backup($conn) {
    $dump = "-- IT Management SQL Backup\n";
    $dump .= '-- Generated at: ' . date('Y-m-d H:i:s') . " UTC\n";
    $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tablesRes = mysqli_query($conn, 'SHOW TABLES');
    if (!$tablesRes) {
        return false;
    }

    while ($tableRow = mysqli_fetch_array($tablesRes)) {
        $table = $tableRow[0];

        $createRes = mysqli_query($conn, 'SHOW CREATE TABLE `' . $table . '`');
        $createRow = $createRes ? mysqli_fetch_assoc($createRes) : null;
        if (!$createRow || !isset($createRow['Create Table'])) {
            continue;
        }

        $dump .= '-- Table structure for `' . $table . "`\n";
        $dump .= 'DROP TABLE IF EXISTS `' . $table . "`;\n";
        $dump .= $createRow['Create Table'] . ";\n\n";

        $dataRes = mysqli_query($conn, 'SELECT * FROM `' . $table . '`');
        if (!$dataRes) {
            continue;
        }

        if (mysqli_num_rows($dataRes) > 0) {
            $dump .= '-- Data for `' . $table . "`\n";
        }

        while ($dataRow = mysqli_fetch_assoc($dataRes)) {
            $columns = array_map(static function ($col) {
                return '`' . $col . '`';
            }, array_keys($dataRow));

            $values = [];
            foreach ($dataRow as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . mysqli_real_escape_string($conn, (string)$value) . "'";
                }
            }

            $dump .= 'INSERT INTO `' . $table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
        }
        if (mysqli_num_rows($dataRes) > 0) {
            $dump .= "\n";
        }
    }

    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $dump;
}

function apply_sql_file($conn, $sqlText) {
    $lines = preg_split('/\R/', $sqlText);
    if ($lines === false) {
        return false;
    }

    $statement = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0 || strpos($trimmed, '*/') === 0) {
            continue;
        }

        $statement .= $line . "\n";
        if (substr(rtrim($line), -1) === ';') {
            if (!mysqli_query($conn, $statement)) {
                return false;
            }
            $statement = '';
        }
    }

    if (trim($statement) !== '') {
        if (!mysqli_query($conn, $statement)) {
            return false;
        }
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_backup') {
        $filename = backup_filename();
        $fullPath = BACKUP_PATH . $filename;
        $dump = build_sql_backup($conn);

        if ($dump === false) {
            $error = 'Unable to generate SQL backup.';
        } elseif (file_put_contents($fullPath, $dump) === false) {
            $error = 'Unable to write backup file.';
        } else {
            $message = 'Backup created: ' . $filename;
        }
    }

    if ($action === 'delete_backup') {
        $file = basename((string)($_POST['file'] ?? ''));
        $target = BACKUP_PATH . $file;
        if ($file === '' || !is_file($target)) {
            $error = 'Backup file not found.';
        } elseif (!unlink($target)) {
            $error = 'Unable to delete backup.';
        } else {
            $message = 'Deleted backup: ' . $file;
        }
    }

    if ($action === 'import_backup') {
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please upload a valid SQL file to import.';
        } else {
            $name = $_FILES['sql_file']['name'] ?? '';
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== 'sql') {
                $error = 'Only .sql files are supported.';
            } else {
                $content = file_get_contents($_FILES['sql_file']['tmp_name']);
                if ($content === false || !apply_sql_file($conn, $content)) {
                    $error = 'Import failed. Please verify SQL file syntax.';
                } else {
                    $message = 'Import completed successfully.';
                }
            }
        }
    }
}

if (isset($_GET['download'])) {
    $downloadFile = basename((string)$_GET['download']);
    $downloadPath = BACKUP_PATH . $downloadFile;
    if ($downloadFile !== '' && is_file($downloadPath)) {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $downloadFile . '"');
        header('Content-Length: ' . filesize($downloadPath));
        readfile($downloadPath);
        exit;
    }
}

$backupFiles = [];
if (is_dir(BACKUP_PATH)) {
    $files = scandir(BACKUP_PATH);
    if ($files !== false) {
        foreach ($files as $file) {
            if (substr($file, -4) === '.sql' && is_file(BACKUP_PATH . $file)) {
                $backupFiles[] = [
                    'name' => $file,
                    'size' => filesize(BACKUP_PATH . $file) ?: 0,
                    'modified' => filemtime(BACKUP_PATH . $file) ?: 0,
                ];
            }
        }
    }
}

usort($backupFiles, static function ($a, $b) {
    return $b['modified'] <=> $a['modified'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>⚙️ Settings</h1>
            <p style="margin-bottom:20px;">Options: create full SQL backup, import/export/delete backups, and view all backup files.</p>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo sanitize($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h2>Create Full SQL Backup</h2></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="create_backup">
                        <button class="btn btn-primary" type="submit">Create Backup Now</button>
                    </form>
                </div>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h2>Import Backup</h2></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="import_backup">
                        <div class="form-group" style="margin:0;min-width:260px;">
                            <label for="sql_file">SQL File</label>
                            <input type="file" id="sql_file" name="sql_file" accept=".sql" required>
                        </div>
                        <button class="btn" type="submit">Import SQL</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>All Backups</h2></div>
                <div class="card-body" style="overflow:auto;">
                    <table>
                        <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Size (KB)</th>
                            <th>Last Modified (UTC)</th>
                            <th>Options</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($backupFiles)): ?>
                            <tr><td colspan="4" style="text-align:center;">No backups yet. Example: backup_01_MAR_2026.sql</td></tr>
                        <?php else: ?>
                            <?php foreach ($backupFiles as $backup): ?>
                                <tr>
                                    <td><?php echo sanitize($backup['name']); ?></td>
                                    <td><?php echo number_format($backup['size'] / 1024, 2); ?></td>
                                    <td><?php echo gmdate('Y-m-d H:i:s', (int)$backup['modified']); ?></td>
                                    <td style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <a class="btn btn-sm" href="index.php?download=<?php echo urlencode($backup['name']); ?>">Export</a>
                                        <form method="post" onsubmit="return confirm('Delete this backup file?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="file" value="<?php echo sanitize($backup['name']); ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
