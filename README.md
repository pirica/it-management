<p align="left"># IT Management System<br><br>A complete IT Asset Management System with PHP, MySQL, GitHub Copilot theme with light/dark mode.<br><br>### Features<br><br>✅ Complete CRUD operations for all modules<br>✅ GitHub Copilot theme with light/dark mode<br>✅ Equipment management with photo uploads<br>✅ Printer and workstation tracking<br>✅ Ticket management system<br>✅ Responsive design<br>✅ MySQLi implementation (no PDO)<br><br><br>### Installation<br><br>1. Extract files to your web root<br>2. Import `database.sql` into MySQL<br>
  3. Update database credentials in `config/config.php`<br>
  4. Create `images/` folder for equipment image uploads<br>5. Create `tickets_photos/` folder for ticket image uploads<br>6. Create `backups/` folder for backups<br>
  7. Access `http://localhost/it-management/`<br><br>### Modules<br><br>- Equipment - Manage IT equipment<br>- Printers - Track printers and supplies<br>- Workstations - Manage workstations<br>- Tickets - Support ticket system<br>- Inventory - Track supplies<br>- Users - User management<br>- Departments - Department management<br>- Employees - Employee tracking<br>- Companies - Multi-company support<br><br><br>  <br>### System Requirements<br><br>- PHP 8.4+<br>- MySQL 8.0+<br>- Apache 2.4+<br>- No Composer Needed!<br>###</p>

<br><br>### Security Checks<br><br>
- Run CSRF handler coverage audit:<br>`php scripts/check_csrf_coverage.php`<br>
- Run SQL injection static audit:<br>`php scripts/check_sql_injection_coverage.php`<br>
