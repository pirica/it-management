<?php require '../../config/config.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Add Employees</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
    <div class="container">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include '../../includes/header.php'; ?>
            <div class="content">
                <h1>Add Employees</h1>
                <div class="card">
                    <form method="POST">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <a href="index.php" class="btn">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="../../js/theme.js"></script>
</body>
</html>
