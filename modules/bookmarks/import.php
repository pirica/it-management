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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed.');
    }

    if (isset($_FILES['import_file']) && is_uploaded_file($_FILES['import_file']['tmp_name'])) {
        $filename = $_FILES['import_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $bookmarks_to_import = [];

        if ($ext === 'html') {
            $content = file_get_contents($_FILES['import_file']['tmp_name']);
            $bookmarks_to_import = bkm_parse_html_bookmarks($content);
        } elseif ($ext === 'csv') {
            $handle = fopen($_FILES['import_file']['tmp_name'], 'r');
            // Skip header row
            fgetcsv($handle);
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
        }

        if (!empty($bookmarks_to_import)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO bookmarks (company_id, employee_id, title, url, notes) VALUES (?, ?, ?, ?, ?)");
            $count = 0;
            foreach ($bookmarks_to_import as $b) {
                // Validation: Only http/https
                if (!preg_match('/^https?:\/\//i', $b['url'])) continue;

                mysqli_stmt_bind_param($stmt, 'iisss', $company_id, $user_id, $b['title'], $b['url'], $b['notes']);
                if (mysqli_stmt_execute($stmt)) {
                    $count++;
                }
            }
            $success = "Successfully imported $count bookmarks.";
        } else {
            $errors[] = "No valid bookmarks found in the file.";
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
    <title>Import Bookmarks - IT Management</title>
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
                    <label>Select File (HTML from browser, or CSV)</label>
                    <input type="file" name="import_file" accept=".html,.csv">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">📤 Import</button>
                    <a href="index.php" class="btn">🔙 Back</a>
                </div>
            </form>
        </div>
        <div style="margin-top: 20px;">
            <h3>Instructions</h3>
            <p><strong>HTML:</strong> Export your bookmarks from Chrome, Firefox, or Edge as an HTML file and upload it here.</p>
            <p><strong>CSV:</strong> Upload a CSV file with columns: <code>Title, URL, Notes</code>. The first row (header) will be skipped.</p>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
