<?php
require '../../config/config.php';
require './helpers.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$errors = [];
$success = '';
$skippedImports = [];
$importedRows = [];

$all_folders = bkm_get_folders($conn, $company_id, $user_id);
$folder_tree = bkm_build_folder_tree($all_folders);
$foldersById = [];
foreach ($all_folders as $folderRow) {
    $foldersById[(int)$folderRow['id']] = $folderRow;
}
$import_folder_id = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : (isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    if (isset($_FILES['import_file']) && is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        $filename = $_FILES['import_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $bookmarkCount = 0;
        $foldersCreated = 0;
        $importedUrlKeys = [];
        $entriesSeen = 0;

        if ($ext === 'html') {
            $content = file_get_contents($_FILES['import_file']['tmp_name']);
            $entries = bkm_parse_html_bookmark_entries($content);
            $base_folder_id = (int)($_POST['folder_id'] ?? 0) ?: null;
            $folderCache = [];

            foreach ($entries as $b) {
                $entriesSeen++;
                $target_folder_id = bkm_resolve_import_folder_path(
                    $conn,
                    $company_id,
                    $user_id,
                    $b['folder_path'],
                    $base_folder_id,
                    $folderCache,
                    $foldersCreated
                );
                $folderLabel = bkm_format_import_folder_label($b['folder_path'], $target_folder_id, $foldersById);
                $result = bkm_try_import_bookmark(
                    $conn,
                    $company_id,
                    $user_id,
                    $target_folder_id,
                    $b['title'],
                    $b['url'],
                    $b['notes'],
                    $importedUrlKeys
                );

                if ($result['imported']) {
                    $bookmarkCount++;
                    $importedRows[] = [
                        'title' => $b['title'],
                        'url' => $b['url'],
                        'summary' => bkm_format_import_success_summary($folderLabel),
                        'row_class' => 'bkm-import-row-success',
                    ];
                } else {
                    $skippedImports[] = [
                        'title' => $b['title'],
                        'url' => $b['url'],
                        'summary' => bkm_format_import_skip_summary($result['skip_reason'], $folderLabel),
                        'row_class' => bkm_import_skip_row_class($result['skip_reason']),
                    ];
                }
            }
        } elseif ($ext === 'csv') {
            $handle = fopen($_FILES['import_file']['tmp_name'], 'r');
            // Skip header row
            fgetcsv($handle);
            $bookmarks_to_import = [];
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) >= 2) {
                    $bookmarks_to_import[] = [
                        'title' => $data[0],
                        'url' => $data[1],
                        'notes' => $data[2] ?? ''
                    ];
                }
            }
            fclose($handle);

            $folder_id = (int)($_POST['folder_id'] ?? 0) ?: null;
            $folderLabel = bkm_format_import_folder_label([], $folder_id, $foldersById);

            foreach ($bookmarks_to_import as $b) {
                $entriesSeen++;
                $result = bkm_try_import_bookmark(
                    $conn,
                    $company_id,
                    $user_id,
                    $folder_id,
                    $b['title'],
                    $b['url'],
                    $b['notes'],
                    $importedUrlKeys
                );

                if ($result['imported']) {
                    $bookmarkCount++;
                    $importedRows[] = [
                        'title' => $b['title'],
                        'url' => $b['url'],
                        'summary' => bkm_format_import_success_summary($folderLabel),
                        'row_class' => 'bkm-import-row-success',
                    ];
                } else {
                    $skippedImports[] = [
                        'title' => $b['title'],
                        'url' => $b['url'],
                        'summary' => bkm_format_import_skip_summary($result['skip_reason'], $folderLabel),
                        'row_class' => bkm_import_skip_row_class($result['skip_reason']),
                    ];
                }
            }
        }

        if ($bookmarkCount > 0) {
            $success = 'Successfully imported ' . $bookmarkCount . ' bookmark' . ($bookmarkCount === 1 ? '' : 's');
            if ($foldersCreated > 0) {
                $success .= ' into ' . $foldersCreated . ' new folder' . ($foldersCreated === 1 ? '' : 's');
            }
            if (!empty($skippedImports)) {
                $success .= ' (' . count($skippedImports) . ' skipped)';
            }
            $success .= '.';
        } elseif ($entriesSeen > 0) {
            $errors[] = 'No bookmarks were imported. See the list below.';
        } else {
            $errors[] = 'No valid bookmarks found in the file.';
        }
    } else {
        $errors[] = "Please select a file to import.";
    }
}

$csrfToken = itm_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Import Bookmarks';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .bkm-import-results-table tr.bkm-import-row-success td {
            background-color: #d4edda;
        }
        .bkm-import-results-table tr.bkm-import-row-duplicate td {
            background-color: #f8d7da;
        }
        .bkm-import-results-table tr.bkm-import-row-invalid td {
            background-color: #fff3cd;
        }
        [data-theme="dark"] .bkm-import-results-table tr.bkm-import-row-success td {
            background-color: rgba(40, 167, 69, 0.22);
        }
        [data-theme="dark"] .bkm-import-results-table tr.bkm-import-row-duplicate td {
            background-color: rgba(220, 53, 69, 0.22);
        }
        [data-theme="dark"] .bkm-import-results-table tr.bkm-import-row-invalid td {
            background-color: rgba(255, 193, 7, 0.22);
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
        <h1>Import Bookmarks</h1>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo sanitize($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul><?php foreach ($errors as $e): ?><li><?php echo sanitize($e); ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($importedRows)): ?>
            <div class="card" style="margin-bottom:16px;">
                <h3>Imported (<?php echo count($importedRows); ?>)</h3>
                <table class="table bkm-import-results-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>URL</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($importedRows as $row): ?>
                            <tr class="<?php echo sanitize($row['row_class']); ?>">
                                <td><?php echo sanitize($row['title']); ?></td>
                                <td><?php echo sanitize($row['url']); ?></td>
                                <td><?php echo sanitize($row['summary']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if (!empty($skippedImports)): ?>
            <div class="card" style="margin-bottom:16px;">
                <h3>Not imported (<?php echo count($skippedImports); ?>)</h3>
                <table class="table bkm-import-results-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>URL</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($skippedImports as $row): ?>
                            <tr<?php echo $row['row_class'] !== '' ? ' class="' . sanitize($row['row_class']) . '"' : ''; ?>>
                                <td><?php echo sanitize($row['title']); ?></td>
                                <td><?php echo sanitize($row['url']); ?></td>
                                <td><?php echo sanitize($row['summary']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <div class="form-group">
                    <label for="importFolder">Folder</label>
                    <select id="importFolder" name="folder_id">
                        <option value="0"<?php echo $import_folder_id === 0 ? ' selected' : ''; ?>>Root</option>
                        <?php echo bkm_render_folder_options($folder_tree, $import_folder_id ?: null); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select File (HTML from browser, or CSV)</label>
                    <input type="file" name="import_file" accept=".html,.csv">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">📤 Import</button>
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
            </form>
        </div>
        <div style="margin-top: 20px;">
            <h3>Instructions</h3>
            <p><strong>HTML:</strong> Export your bookmarks from Chrome, Firefox, or Edge as an HTML file and upload it here. Folder headings (<code>&lt;H3&gt;</code>) in the file are created automatically and bookmarks are imported into the matching folder.</p>
            <p><strong>CSV:</strong> Upload a CSV file with columns: <code>Title, URL, Notes</code>. The first row (header) will be skipped.</p>
            <p><strong>Folder:</strong> Choose <code>Root</code> or a parent folder. HTML imports nest file folders under that target; CSV imports place every row in the selected folder.</p>
            <p><strong>URLs:</strong> Only <code>http://</code>, <code>https://</code>, and <code>ftp://</code> links are imported. Each employee may have a URL only once (any folder). Imported rows are highlighted green; duplicate URL skips are highlighted red (<code>Reason → Folder</code>).</p>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
