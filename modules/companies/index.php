<?php
require '../../config/config.php';

$result = mysqli_query($conn, "SELECT * FROM companies ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>🌍 Companies</h1>
                <a href="create.php" class="btn btn-primary">+ Add Company</a>
            </div>
            <div class="card">
                <table>
                    <thead>
                    <tr>
                        <th>ID</th><th>Name</th><th>Code</th><th>Industry</th><th>Status</th><th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo sanitize($row['name']); ?></td>
                                <td><?php echo sanitize($row['company_code'] ?? '-'); ?></td>
                                <td><?php echo sanitize($row['industry'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge <?php echo (int)$row['active'] === 1 ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo (int)$row['active'] === 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="btn btn-sm" href="create.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                                    <a class="btn btn-sm btn-danger" href="delete.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Delete this company?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No companies found.</td></tr>
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
