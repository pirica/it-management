<?php
require __DIR__ . '/../bootstrap.php';
unset($_SESSION['hotel_booking_customer_id'], $_SESSION['hotel_booking_portal_user_id']);
header('Location: ' . APPURL . '/auth/login.php');
exit;
