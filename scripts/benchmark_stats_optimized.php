<?php
/**
 * Benchmark for user-config.php stats gathering optimization.
 * Compares the performance of 31 individual queries vs 1 consolidated query.
 * Uses itm_mysqli_stmt_fetch_assoc for compatibility.
 */

define('ITM_CLI_SCRIPT', true);
require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Benchmark: user-config.php stats optimization');
$nl = itm_script_output_nl();

$user_id = 1; // Assuming a standard test user ID
$company_id = 1;

$stat_definitions = [
    ['table' => 'alerts', 'field' => 'assigned_to_employee_id', 'label' => 'Assigned Alerts', 'slug' => 'alerts'],
    ['table' => 'alerts', 'field' => 'created_by', 'label' => 'Created Alerts', 'slug' => 'alerts'],
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

$iterations = 10;
echo "Running benchmarks ($iterations iterations)..." . $nl . $nl;

// --- 1. ORIGINAL PATTERN (Loop of queries) ---
$startOriginal = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $all_stats_original = [];
    foreach ($stat_definitions as $def) {
        $sql = "SELECT COUNT(*) AS cnt FROM `{$def['table']}` WHERE `{$def['field']}` = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $cnt);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            $all_stats_original[] = array_merge($def, ['count' => (int)$cnt]);
        }
    }
}
$endOriginal = microtime(true);
$originalTime = $endOriginal - $startOriginal;
echo "Original Loop: " . number_format($originalTime, 4) . "s" . $nl;

// --- 2. OPTIMIZED PATTERN (Single consolidated query) ---
$startOptimized = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $all_stats_optimized = [];
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
                $counts = itm_mysqli_stmt_fetch_assoc($stmt);
                if (is_array($counts)) {
                    foreach ($stat_definitions as $index => $def) {
                        $all_stats_optimized[] = array_merge($def, ['count' => (int)($counts['stat_' . $index] ?? 0)]);
                    }
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}
$endOptimized = microtime(true);
$optimizedTime = $endOptimized - $startOptimized;
echo "Optimized Single Query: " . number_format($optimizedTime, 4) . "s" . $nl;

// --- VERIFICATION ---
$reduction = (($originalTime - $optimizedTime) / max(0.001, $originalTime)) * 100;
echo $nl . "Performance Improvement: " . number_format($reduction, 2) . "%" . $nl;

$match = true;
if (count($all_stats_original) !== count($all_stats_optimized)) {
    $match = false;
} else {
    foreach ($all_stats_original as $index => $stat) {
        if ($stat['count'] !== $all_stats_optimized[$index]['count']) {
            echo "Mismatch at index $index (" . $stat['label'] . "): " . $stat['count'] . " vs " . $all_stats_optimized[$index]['count'] . $nl;
            $match = false;
        }
    }
}

if ($match) {
    echo itm_script_format_status_line("[PASS] Results matched perfectly between both methods.") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] Results mismatch detected!") . $nl;
    itm_script_output_end();
    exit(1);
}

itm_script_output_end();
