<?php
/**
 * Workstations - View Record
 * 
 * Delegates record viewing to the equipment module.
 */
$equipmentViewTitle = 'View Workstation';
$equipmentRequiredFlagField = 'is_workstation';
$equipmentViewBackPath = 'index.php';
$equipmentViewEditPath = '../equipment/edit.php';
require '../equipment/view.php';
