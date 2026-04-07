<?php
/**
 * Workstations - Entry Point
 * 
 * This module delegates all functionality to the central 'equipment' 
 * module, filtering specifically for workstation assets.
 */
$equipmentModuleTitle = '💻 Workstations';
$equipmentFlagField = 'is_workstation';
$equipmentSearchPlaceholder = 'Use SQL wildcards, e.g. %%desk%%';
$equipmentModuleBasePath = '../equipment/';
$equipmentViewPath = '';
$equipmentEditPath = '../equipment/';
$equipmentAllowCreate = false;
$equipmentAllowDelete = false;
$equipmentAllowImport = false;
require '../equipment/index.php';
