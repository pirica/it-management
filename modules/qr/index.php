<?php
$crud_table = 'qr_codes';
$crud_title = 'QR Generator';
$crud_action = $crud_action ?? 'index';
require '../../config/config.php';
require __DIR__ . '/includes/handlers.php';
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials/render.php';
