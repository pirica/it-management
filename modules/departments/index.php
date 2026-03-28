<?php
require '../../config/config.php';
$items = mysqli_query($conn, "SELECT * FROM departments WHERE company_id = $company_id ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>🏢 Departments</h1>
                <a class="btn btn-primary" href="create.php">➕</a>
            </div>
            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($items && mysqli_num_rows($items)): while ($d = mysqli_fetch_assoc($items)): ?>
                        <tr>
                            <td><?php echo (int)$d['id']; ?></td>
                            <td><?php echo sanitize($d['name']); ?></td>
                            <td><?php echo sanitize($d['description'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo (int)$d['active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo (int)$d['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm" href="view.php?id=<?php echo (int)$d['id']; ?>">👁️</a>
                                <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$d['id']; ?>">✏️</a>
                                <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$d['id']; ?>" onclick="return confirm('Delete department?');">🗑️</a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align:center;">No departments found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
