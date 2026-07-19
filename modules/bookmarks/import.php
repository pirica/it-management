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

$all_folders = bkm_get_folders($conn, $company_id, $user_id);
$folder_tree = bkm_build_folder_tree($all_folders);
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

        if ($ext === 'html') {
            $content = file_get_contents($_FILES['import_file']['tmp_name']);
            $entries = bkm_parse_html_bookmark_entries($content);
            $base_folder_id = (int)($_POST['folder_id'] ?? 0) ?: null;
            $folderCache = [];

            foreach ($entries as $b) {
                if (!preg_match('/^https?:\/\//i', $b['url'])) {
                    continue;
                }
                $target_folder_id = bkm_resolve_import_folder_path(
                    $conn,
                    $company_id,
                    $user_id,
                    $b['folder_path'],
                    $base_folder_id,
                    $folderCache,
                    $foldersCreated
                );
                if (bkm_insert_import_bookmark($conn, $company_id, $user_id, $target_folder_id, $b['title'], $b['url'], $b['notes'])) {
                    $bookmarkCount++;
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
            foreach ($bookmarks_to_import as $b) {
                if (!preg_match('/^https?:\/\//i', $b['url'])) {
                    continue;
                }
                if (bkm_insert_import_bookmark($conn, $company_id, $user_id, $folder_id, $b['title'], $b['url'], $b['notes'])) {
                    $bookmarkCount++;
                }
            }
        }

        if ($bookmarkCount > 0) {
            $success = 'Successfully imported ' . $bookmarkCount . ' bookmark' . ($bookmarkCount === 1 ? '' : 's');
            if ($foldersCreated > 0) {
                $success .= ' into ' . $foldersCreated . ' new folder' . ($foldersCreated === 1 ? '' : 's');
            }
            $success .= '.';
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
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
