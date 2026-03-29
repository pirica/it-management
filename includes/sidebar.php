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
        <li><a href="<?php echo BASE_URL; ?>modules/settings/" class="<?php echo $current_dir === 'settings' ? 'active' : ''; ?>">⚙️ Settings</a></li>
    </ul>

    <div class="sidebar-title">🏢 Management</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>modules/equipment/" class="<?php echo $current_dir === 'equipment' ? 'active' : ''; ?>">🖥️ Equipment</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/workstations/" class="<?php echo $current_dir === 'workstations' ? 'active' : ''; ?>">💻 Workstations</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/switch_ports/" class="<?php echo $current_dir === 'switch_ports' ? 'active' : ''; ?>">🔌 Switch Ports</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/tickets/" class="<?php echo $current_dir === 'tickets' ? 'active' : ''; ?>">🎫 Tickets</a></li>
    </ul>

    <div class="sidebar-title">👤 Employee</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>modules/employees/" class="<?php echo $current_dir === 'employees' ? 'active' : ''; ?>">👤 Employees</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/departments/" class="<?php echo $current_dir === 'departments' ? 'active' : ''; ?>">🏢 Departments</a></li>
    </ul>

    <div class="sidebar-title">🧰 Admin</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>modules/inventory/" class="<?php echo $current_dir === 'inventory' ? 'active' : ''; ?>">📦 Inventory</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/users/" class="<?php echo $current_dir === 'users' ? 'active' : ''; ?>">👥 Users</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/companies/" class="<?php echo $current_dir === 'companies' ? 'active' : ''; ?>">🌍 Companies</a></li>
    </ul>

    <div class="sidebar-title">🗂️ Reference Data</div>
    <ul class="sidebar-nav">
        <li><a href="<?php echo BASE_URL; ?>modules/it_locations/" class="<?php echo $current_dir === 'it_locations' ? 'active' : ''; ?>">📍 IT Locations</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/location_types/" class="<?php echo $current_dir === 'location_types' ? 'active' : ''; ?>">🧭 Location Types</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/equipment_types/" class="<?php echo $current_dir === 'equipment_types' ? 'active' : ''; ?>">🖥️ Equipment Types</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/equipment_statuses/" class="<?php echo $current_dir === 'equipment_statuses' ? 'active' : ''; ?>">✅ Equipment Statuses</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/manufacturers/" class="<?php echo $current_dir === 'manufacturers' ? 'active' : ''; ?>">🏭 Manufacturers</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/suppliers/" class="<?php echo $current_dir === 'suppliers' ? 'active' : ''; ?>">🚚 Suppliers</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/supplier_statuses/" class="<?php echo $current_dir === 'supplier_statuses' ? 'active' : ''; ?>">🟢 Supplier Statuses</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/racks/" class="<?php echo $current_dir === 'racks' ? 'active' : ''; ?>">🗄️ Racks</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/rack_statuses/" class="<?php echo $current_dir === 'rack_statuses' ? 'active' : ''; ?>">📶 Rack Statuses</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/ticket_categories/" class="<?php echo $current_dir === 'ticket_categories' ? 'active' : ''; ?>">🏷️ Ticket Categories</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/ticket_statuses/" class="<?php echo $current_dir === 'ticket_statuses' ? 'active' : ''; ?>">🚦 Ticket Statuses</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/ticket_priorities/" class="<?php echo $current_dir === 'ticket_priorities' ? 'active' : ''; ?>">🔥 Ticket Priorities</a></li>
        <li><a href="<?php echo BASE_URL; ?>modules/employee_statuses/" class="<?php echo $current_dir === 'employee_statuses' ? 'active' : ''; ?>">🧑‍💼 Employee Statuses</a></li>
    </ul>

    <ul class="sidebar-nav" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
        <li><a href="<?php echo BASE_URL; ?>logout.php">🚪 Logout</a></li>
    </ul>
</div>
