<?php
require_once __DIR__ . '/../config/config.php';
echo "Testing itm_table_has_column('audit_logs', 'employee_id')...\n";
var_dump(itm_table_has_column($conn, 'audit_logs', 'employee_id'));
echo "Testing itm_table_has_column('audit_logs', 'user_id')...\n";
var_dump(itm_table_has_column($conn, 'audit_logs', 'user_id'));
