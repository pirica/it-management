<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h3>⚙️ IT Manager</h3>
        <p><?php echo sanitize($_SESSION['company_name'] ?? 'Company'); ?></p>
    </div>

    <div class="sidebar-title">📊 Dashboard</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">📈 Dashboard</a></li>
    </ul>

    <div class="sidebar-title">🏢 Management</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>modules/equipment/" class="<?php echo $current_dir === 'equipment' ? 'active' : ''; ?>">🖥️ Equipment</a></li>
          <li><a href="<?php echo BASE_URL; ?>modules/workstations/" class="<?php echo $current_dir === 'workstations' ? 'active' : ''; ?>">💻 Workstations</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/tickets/" class="<?php echo $current_dir === 'tickets' ? 'active' : ''; ?>">🎫 Tickets</a></li>
    </ul>

    <div class="sidebar-title">⚙️ Settings</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>modules/inventory/" class="<?php echo $current_dir === 'inventory' ? 'active' : ''; ?>">📦 Inventory</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/users/" class="<?php echo $current_dir === 'users' ? 'active' : ''; ?>">👥 Users</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/departments/" class="<?php echo $current_dir === 'departments' ? 'active' : ''; ?>">🏢 Departments</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/employees/" class="<?php echo $current_dir === 'employees' ? 'active' : ''; ?>">👤 Employees</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/companies/" class="<?php echo $current_dir === 'companies' ? 'active' : ''; ?>">🌍 Companies</a></li>
    </ul>

    <ul class="sidebar-nav" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
        <li><a href="<?php echo BASE_URL; ?>logout.php">🚪 Logout</a></li>
    </ul>
</div>
