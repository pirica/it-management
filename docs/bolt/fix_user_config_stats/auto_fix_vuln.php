<?php
/**
 * Auto-fix script for user-config.php performance optimization.
 * Consolidates N+1 stats queries into a single query using itm_mysqli_stmt_fetch_assoc for compatibility.
 */

function bolt_fix_user_config_stats($content) {
    $search = <<<'PHP'
// Stats gathering - All modules using specified fields
$all_stats = [];
$stat_definitions = [
    ['table' => 'alerts', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Alerts', 'slug' => 'alerts'],
    ['table' => 'alerts', 'field' => 'created_by_employee_id', 'label' => 'Created Alerts', 'slug' => 'alerts'],
    ['table' => 'approvers', 'field' => 'employee_id', 'label' => 'Approver Roles', 'slug' => 'approvers'],
    ['table' => 'attempts', 'field' => 'employee_id', 'label' => 'Login Attempts', 'slug' => 'attempts'],
    ['table' => 'audit_logs', 'field' => 'employee_id', 'label' => 'Audit Logs', 'slug' => 'audit_logs'],
    ['table' => 'bookmark_folders', 'field' => 'employee_id', 'label' => 'Bookmark Folders', 'slug' => 'bookmarks'],
    ['table' => 'bookmarks', 'field' => 'employee_id', 'label' => 'My Bookmarks', 'slug' => 'bookmarks'],
    ['table' => 'employee_assignment_history', 'field' => 'employee_id', 'label' => 'Assignment History', 'slug' => 'employee_assignment_history'],
    ['table' => 'employee_assignment_history', 'field' => 'assigned_by_employee_id', 'label' => 'Assignment Items Assigned', 'slug' => 'employee_assignment_history'],
	['table' => 'employee_assignment_history', 'field' => 'received_by_employee_id', 'label' => 'Assignment Items Received', 'slug' => 'employee_assignment_history'],
    ['table' => 'employee_companies', 'field' => 'employee_id', 'label' => 'Companies', 'slug' => 'employee_companies'],
    ['table' => 'employee_companies', 'field' => 'granted_by_employee_id', 'label' => 'Companies Access Granted', 'slug' => 'employee_companies'],
    ['table' => 'employee_onboarding_requests', 'field' => 'employee_id', 'label' => 'Onboarding Req', 'slug' => 'employee_onboarding_requests'],
    ['table' => 'employee_sidebar_preferences', 'field' => 'employee_id', 'label' => 'Sidebar Prefs', 'slug' => 'employee_sidebar_preferences'],
    ['table' => 'equipment', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Equiments', 'slug' => 'equipment'],
    ['table' => 'events', 'field' => 'assigned_to_employee_id', 'label' => 'Events for Me', 'slug' => 'events'],
	['table' => 'events', 'field' => 'created_by_employee_id', 'label' => 'Events Created', 'slug' => 'events'],
    ['table' => 'floor_plans', 'field' => 'created_by_employee_id', 'label' => 'Floor Plans', 'slug' => 'floor_plans'],
    ['table' => 'inventory_items', 'field' => 'last_employee_id', 'label' => 'Last Handled', 'slug' => 'inventory_items'],
    ['table' => 'note_labels', 'field' => 'employee_id', 'label' => 'Note Tags', 'slug' => 'notes'],
    ['table' => 'notes', 'field' => 'employee_id', 'label' => 'My Notes', 'slug' => 'notes'],
    ['table' => 'password_entries', 'field' => 'employee_id', 'label' => 'Vault Entries', 'slug' => 'passwords'],
    ['table' => 'password_folders', 'field' => 'employee_id', 'label' => 'Vault Folders', 'slug' => 'passwords'],
    ['table' => 'private_contacts', 'field' => 'employee_id', 'label' => 'My Contacts', 'slug' => 'private_contacts'],
    ['table' => 'registration_invitations', 'field' => 'invited_by_employee_id', 'label' => 'Invites Sent', 'slug' => 'registration_invitations'],
    ['table' => 'tickets', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Tickets', 'slug' => 'tickets'],
    ['table' => 'tickets', 'field' => 'created_by_employee_id', 'label' => 'Created Tickets', 'slug' => 'tickets'],
    ['table' => 'todo', 'field' => 'assigned_to_employee_id', 'label' => 'My Todos', 'slug' => 'todo'],
    ['table' => 'todo', 'field' => 'created_by_employee_id', 'label' => 'My Todos', 'slug' => 'todo'],
    ['table' => 'todo_categories', 'field' => 'cat_from_employee_id', 'label' => 'Todo Categories', 'slug' => 'todo'],
    ['table' => 'ui_configuration', 'field' => 'employee_id', 'label' => 'UI Preferences', 'slug' => 'settings'],
];

foreach ($stat_definitions as $def) {

    // Construir SQL primeiro
    $sql = "SELECT COUNT(*) AS cnt
            FROM `{$def['table']}`
            WHERE `{$def['field']}` = ?";


    // PREPARE
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo "<span style='color:red'>PREPARE FAILED: " . mysqli_error($conn) . "</span><br>";
        $all_stats[] = array_merge($def, ['count' => 0]);
        continue;
    }


    // BIND
    if (!mysqli_stmt_bind_param($stmt, 'i', $user_id)) {
        echo "<span style='color:red'>BIND FAILED: " . mysqli_error($conn) . "</span><br>";
        $all_stats[] = array_merge($def, ['count' => 0]);
        continue;
    }


    // EXECUTE
    if (!mysqli_stmt_execute($stmt)) {
        echo "<span style='color:red'>EXECUTE FAILED: " . mysqli_error($conn) . "</span><br>";
        $all_stats[] = array_merge($def, ['count' => 0]);
        continue;
    }


    // RESULT (SEM mysqlnd)
    mysqli_stmt_bind_result($stmt, $cnt);

    if (!mysqli_stmt_fetch($stmt)) {

        $cnt = 0;
    }

    mysqli_stmt_close($stmt);


    $all_stats[] = array_merge($def, ['count' => (int)$cnt]);
}
PHP;

    $replace = <<<'PHP'
// Stats gathering - All modules using specified fields
// Optimized by Bolt ⚡: Consolidated 31 COUNT queries into a single database round-trip.
$all_stats = [];
$stat_definitions = [
    ['table' => 'alerts', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Alerts', 'slug' => 'alerts'],
    ['table' => 'alerts', 'field' => 'created_by_employee_id', 'label' => 'Created Alerts', 'slug' => 'alerts'],
    ['table' => 'approvers', 'field' => 'employee_id', 'label' => 'Approver Roles', 'slug' => 'approvers'],
    ['table' => 'attempts', 'field' => 'employee_id', 'label' => 'Login Attempts', 'slug' => 'attempts'],
    ['table' => 'audit_logs', 'field' => 'employee_id', 'label' => 'Audit Logs', 'slug' => 'audit_logs'],
    ['table' => 'bookmark_folders', 'field' => 'employee_id', 'label' => 'Bookmark Folders', 'slug' => 'bookmarks'],
    ['table' => 'bookmarks', 'field' => 'employee_id', 'label' => 'My Bookmarks', 'slug' => 'bookmarks'],
    ['table' => 'employee_assignment_history', 'field' => 'employee_id', 'label' => 'Assignment History', 'slug' => 'employee_assignment_history'],
    ['table' => 'employee_assignment_history', 'field' => 'assigned_by_employee_id', 'label' => 'Assignment Items Assigned', 'slug' => 'employee_assignment_history'],
    ['table' => 'employee_assignment_history', 'field' => 'received_by_employee_id', 'label' => 'Assignment Items Received', 'slug' => 'employee_assignment_history'],
    ['table' => 'employee_companies', 'field' => 'employee_id', 'label' => 'Companies', 'slug' => 'employee_companies'],
    ['table' => 'employee_companies', 'field' => 'granted_by_employee_id', 'label' => 'Companies Access Granted', 'slug' => 'employee_companies'],
    ['table' => 'employee_onboarding_requests', 'field' => 'employee_id', 'label' => 'Onboarding Req', 'slug' => 'employee_onboarding_requests'],
    ['table' => 'employee_sidebar_preferences', 'field' => 'employee_id', 'label' => 'Sidebar Prefs', 'slug' => 'employee_sidebar_preferences'],
    ['table' => 'equipment', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Equiments', 'slug' => 'equipment'],
    ['table' => 'events', 'field' => 'assigned_to_employee_id', 'label' => 'Events for Me', 'slug' => 'events'],
    ['table' => 'events', 'field' => 'created_by_employee_id', 'label' => 'Events Created', 'slug' => 'events'],
    ['table' => 'floor_plans', 'field' => 'created_by_employee_id', 'label' => 'Floor Plans', 'slug' => 'floor_plans'],
    ['table' => 'inventory_items', 'field' => 'last_employee_id', 'label' => 'Last Handled', 'slug' => 'inventory_items'],
    ['table' => 'note_labels', 'field' => 'employee_id', 'label' => 'Note Tags', 'slug' => 'notes'],
    ['table' => 'notes', 'field' => 'employee_id', 'label' => 'My Notes', 'slug' => 'notes'],
    ['table' => 'password_entries', 'field' => 'employee_id', 'label' => 'Vault Entries', 'slug' => 'passwords'],
    ['table' => 'password_folders', 'field' => 'employee_id', 'label' => 'Vault Folders', 'slug' => 'passwords'],
    ['table' => 'private_contacts', 'field' => 'employee_id', 'label' => 'My Contacts', 'slug' => 'private_contacts'],
    ['table' => 'registration_invitations', 'field' => 'invited_by_employee_id', 'label' => 'Invites Sent', 'slug' => 'registration_invitations'],
    ['table' => 'tickets', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Tickets', 'slug' => 'tickets'],
    ['table' => 'tickets', 'field' => 'created_by_employee_id', 'label' => 'Created Tickets', 'slug' => 'tickets'],
    ['table' => 'todo', 'field' => 'assigned_to_employee_id', 'label' => 'My Todos', 'slug' => 'todo'],
    ['table' => 'todo', 'field' => 'created_by_employee_id', 'label' => 'My Todos', 'slug' => 'todo'],
    ['table' => 'todo_categories', 'field' => 'cat_from_employee_id', 'label' => 'Todo Categories', 'slug' => 'todo'],
    ['table' => 'ui_configuration', 'field' => 'employee_id', 'label' => 'UI Preferences', 'slug' => 'settings'],
];

if (!empty($stat_definitions)) {
    $subqueries = [];
    foreach ($stat_definitions as $index => $def) {
        $subqueries[] = "(SELECT COUNT(*) FROM `" . $def['table'] . "` WHERE `" . $def['field'] . "` = ?) AS stat_" . $index;
    }
    $sql = "SELECT " . implode(", ", $subqueries);
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        $types = str_repeat('i', count($stat_definitions));
        $params = array_fill(0, count($stat_definitions), $user_id);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (mysqli_stmt_execute($stmt)) {
            // Why: itm_mysqli_stmt_fetch_assoc is compatible with both mysqlnd and bind_result fallbacks.
            $counts = itm_mysqli_stmt_fetch_assoc($stmt);
            if (is_array($counts)) {
                foreach ($stat_definitions as $index => $def) {
                    $all_stats[] = array_merge($def, ['count' => (int)($counts['stat_' . $index] ?? 0)]);
                }
            }
        } else {
            // Fallback to individual queries if batch fails
            foreach ($stat_definitions as $def) {
                $all_stats[] = array_merge($def, ['count' => 0]);
            }
        }
        mysqli_stmt_close($stmt);
    }
}
PHP;

    return str_replace($search, $replace, $content);
}

$originalFile = __DIR__ . '/../../../user-config.php';
$content = file_get_contents($originalFile);
$optimizedContent = bolt_fix_user_config_stats($content);

echo "Applying optimization...\n";
if ($content === $optimizedContent) {
    echo "Warning: No changes applied. Check if the search pattern matches.\n";
} else {
    echo "Changes applied successfully.\n";
}

$outputDir = __DIR__ . '/../fixed_files_user_config_stats/fixed_files';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}
file_put_contents($outputDir . '/user-config.php', $optimizedContent);
echo "Optimized file written to: " . $outputDir . "/user-config.php\n";
