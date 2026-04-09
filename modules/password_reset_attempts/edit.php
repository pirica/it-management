<?php
/**
 * Password Reset Attempts - Edit shim.
 * Why: reuse shared form behavior while keeping module's required flat file layout.
 */
$_GET['id'] = (int)($_GET['id'] ?? 0);
require 'create.php';
