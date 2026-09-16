<?php
$_GET['id'] = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// Edit uses the shared create.php form/handler so equipment field changes
// (including printer_scan) stay consistent in both flows.
require 'create.php';
