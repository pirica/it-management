<?php
require '../../config/config.php';

$items = mysqli_query($conn, "SELECT * FROM companies WHERE company_id = $company_id");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Companies Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include '../../includes/header.php'; ?>
            <div class="content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h1>Companies Management</h1>
                    <a href="create.php" class="btn btn-primary">+ Add Companies</a>
                </div>
                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px;">
                                    📝 Module development in progress...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="../../js/theme.js"></script>
</body>
</html>
