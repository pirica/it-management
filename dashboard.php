<?php
require 'config/config.php';

$company = mysqli_query($conn, "SELECT * FROM companies WHERE id = $company_id");
$company_data = mysqli_fetch_assoc($company);

$equipment_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM equipment WHERE company_id = $company_id AND active = 1"))['count'];
$workstations_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM workstations WHERE company_id = $company_id AND active = 1"))['count'];
$tickets_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM tickets WHERE company_id = $company_id"))['count'];
$employees_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE company_id = $company_id"))['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - IT Management</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <h1>📊 Dashboard</h1>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">Welcome to <?php echo sanitize($company_data['name']); ?></p>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Equipment</div>
                        <div class="stat-number"><?php echo $equipment_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Workstations</div>
                        <div class="stat-number"><?php echo $workstations_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Tickets</div>
                        <div class="stat-number"><?php echo $tickets_count; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Employees</div>
                        <div class="stat-number"><?php echo $employees_count; ?></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h2>Settings</h2></div>
                    <div class="card-body">
                        <p>Manage backups and system maintenance options from one page.</p>
                        <a class="btn btn-primary" href="<?php echo BASE_URL; ?>modules/settings/">Open Settings</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>System Information</h2>
                    </div>
                    <div class="card-body">
                        <p><strong>Company:</strong> <?php echo sanitize($company_data['name']); ?></p>
                        <p><strong>Industry:</strong> <?php echo sanitize($company_data['industry']); ?></p>
                        <p><strong>Location:</strong> <?php echo sanitize($company_data['city'] . ', ' . $company_data['country']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/theme.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
