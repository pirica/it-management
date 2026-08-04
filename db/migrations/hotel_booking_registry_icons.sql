-- DML: repair mojibake hospitality module icons in modules_registry (matches includes/ui_config.php catalog).
UPDATE `modules_registry` SET `icon` = '🏨' WHERE `module_slug` = 'hotel_bookings';
UPDATE `modules_registry` SET `icon` = '🏨' WHERE `module_slug` = 'hotel_booking_hotels';
UPDATE `modules_registry` SET `icon` = '🛏️' WHERE `module_slug` = 'booking_rooms_types';
UPDATE `modules_registry` SET `icon` = '🚪' WHERE `module_slug` = 'hotel_booking_rooms';
UPDATE `modules_registry` SET `icon` = '✨' WHERE `module_slug` = 'hotel_booking_amenities';
UPDATE `modules_registry` SET `icon` = '🏷️' WHERE `module_slug` = 'hotel_booking_special_rates';
UPDATE `modules_registry` SET `icon` = '📋' WHERE `module_slug` = 'hotel_booking_portal_rate_plans';
UPDATE `modules_registry` SET `icon` = '✨' WHERE `module_slug` = 'hotel_booking_room_utilities';
UPDATE `modules_registry` SET `icon` = '🧹' WHERE `module_slug` = 'hotel_booking_housekeeping_statuses';
UPDATE `modules_registry` SET `icon` = '📅' WHERE `module_slug` = 'hotel_bookings_future';
UPDATE `modules_registry` SET `icon` = '🏠' WHERE `module_slug` = 'hotel_bookings_present';
UPDATE `modules_registry` SET `icon` = '📜' WHERE `module_slug` = 'hotel_bookings_history';
UPDATE `modules_registry` SET `icon` = '⚙️' WHERE `module_slug` = 'hotel_booking_settings';
