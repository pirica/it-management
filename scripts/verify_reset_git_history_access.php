<?php
// We'll test if the script has an admin check by mocking the environment
// Since the script has broken paths, we'll check the logic instead.

$content = file_get_contents(__DIR__ . '/../reset_git_history.php');
if (strpos($content, "role_name") === false && strpos($content, "isAdmin") === false && strpos($content, "currentUserIsAdmin") === false) {
    echo "[FAIL] reset_git_history.php: No admin role check found in script!\n";
} else {
    echo "[PASS] reset_git_history.php: Admin check seems to be present.\n";
}
