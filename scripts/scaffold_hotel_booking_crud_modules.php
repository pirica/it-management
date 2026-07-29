<?php
/**
 * Scaffold flattened CRUD modules from modules/customer_statuses template.
 * CLI: php scripts/scaffold_hotel_booking_crud_modules.php
 */
$root = dirname(__DIR__);
$source = $root . '/modules/customer_statuses';
$targets = [
    'booking_rooms_types' => ['table' => 'booking_rooms_types', 'title' => 'Room Types'],
    'hotel_booking_housekeeping_statuses' => ['table' => 'hotel_booking_housekeeping_statuses', 'title' => 'HK Statuses'],
    'hotel_bookings_future' => ['table' => 'hotel_bookings_future', 'title' => 'Booking Future Status'],
    'hotel_bookings_present' => ['table' => 'hotel_bookings_present', 'title' => 'Booking Present Status'],
    'hotel_bookings_history' => ['table' => 'hotel_bookings_history', 'title' => 'Booking History Status'],
    'hotel_booking_room_utilities' => ['table' => 'hotel_booking_room_utilities', 'title' => 'Room Utilities'],
];

$files = ['index.php', 'create.php', 'edit.php', 'view.php', 'delete.php', 'list_all.php', 'index.html'];

foreach ($targets as $slug => $meta) {
    $dest = $root . '/modules/' . $slug;
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    foreach ($files as $file) {
        $srcPath = $source . '/' . $file;
        if (!is_file($srcPath)) {
            continue;
        }
        $content = file_get_contents($srcPath);
        $content = str_replace("customer_statuses", $meta['table'], $content);
        $content = str_replace('Customer Statuses', $meta['title'], $content);
        $content = str_replace('$crud_title = \'customer_statuses\'', '$crud_title = \'' . $meta['table'] . '\'', $content);
        if ($file === 'index.php' && strpos($content, '$displayFieldColumns') === false) {
            // ensure alias exists - customer_statuses should have it
        }
        file_put_contents($dest . '/' . $file, $content);
    }
    $notes = $root . '/templates/AGENT_NOTES.md';
    if (is_file($notes) && !is_file($dest . '/AGENT_NOTES.md')) {
        copy($notes, $dest . '/AGENT_NOTES.md');
    }
    echo "Scaffolded {$slug}\n";
}

// Hotels: copy license_management if exists else customer_statuses with table name
$hotelSource = is_dir($root . '/modules/license_management') ? $root . '/modules/license_management' : $source;
foreach (['hotel_booking_hotels', 'hotel_booking_rooms'] as $slug) {
    $meta = $slug === 'hotel_booking_hotels'
        ? ['table' => 'hotel_booking_hotels', 'title' => 'Hotels']
        : ['table' => 'hotel_booking_rooms', 'title' => 'Hotel Rooms'];
    $dest = $root . '/modules/' . $slug;
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    foreach ($files as $file) {
        $srcPath = $hotelSource . '/' . $file;
        if (!is_file($srcPath)) {
            $srcPath = $source . '/' . $file;
        }
        if (!is_file($srcPath)) {
            continue;
        }
        $content = file_get_contents($srcPath);
        $fromTable = $slug === 'hotel_booking_hotels' ? 'license_management' : 'license_management';
        if (strpos($content, 'license_management') !== false) {
            $content = str_replace('license_management', $meta['table'], $content);
            $content = str_replace('License Management', $meta['title'], $content);
        } else {
            $content = str_replace('customer_statuses', $meta['table'], $content);
            $content = str_replace('Customer Statuses', $meta['title'], $content);
        }
        file_put_contents($dest . '/' . $file, $content);
    }
    echo "Scaffolded {$slug}\n";
}

echo "Done.\n";
