<?php
if (!defined('ITM_CLI_SCRIPT')) define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../../../../../config/config.php';

/**
 * Passwords Functional Test
 *
 * Verifies core AJAX operations for the Passwords module including
 * folder creation, entry saving, and parent folder mapping.
 */

function test_save_folder($user_id, $csrfToken) {
    echo "Testing save_folder (Root)... ";
    $params = [
        'action' => 'save_folder',
        'csrf_token' => $csrfToken,
        'name' => 'Root Test Folder ' . uniqid(),
        'parent_id' => '0'
    ];
    $res = call_ajax($params);
    if ($res && $res['ok']) {
        echo "PASS\n";
    } else {
        echo "FAIL: " . ($res['message'] ?? 'Unknown error') . "\n";
        return false;
    }

    echo "Testing save_folder (Child)... ";
    $params = [
        'action' => 'save_folder',
        'csrf_token' => $csrfToken,
        'name' => 'Child Test Folder ' . uniqid(),
        'parent_id' => '1'
    ];
    $res = call_ajax($params);
    if ($res && $res['ok']) {
        echo "PASS\n";
    } else {
        echo "FAIL: " . ($res['message'] ?? 'Unknown error') . "\n";
        return false;
    }
    return true;
}

function test_save_entry($user_id, $csrfToken) {
    echo "Testing save_entry... ";
    $_SESSION['vault_key'] = hash('sha256', 'test_key');
    $params = [
        'action' => 'save_entry',
        'csrf_token' => $csrfToken,
        'account' => 'Functional Test Account',
        'password' => 'test_password',
        'folder_id' => '0'
    ];
    $res = call_ajax($params);
    if ($res && $res['ok']) {
        echo "PASS\n";
        return true;
    } else {
        echo "FAIL: " . ($res['message'] ?? 'Unknown error') . "\n";
        return false;
    }
}

function call_ajax($params) {
    $_POST = $params;
    $oldDir = getcwd();
    chdir(__DIR__ . '/../../../../../modules/passwords');
    ob_start();
    include 'ajax_handler.php';
    $output = ob_get_clean();
    chdir($oldDir);
    return json_decode($output, true);
}

$user_id = 1;
$_SESSION['user_id'] = $user_id;
$csrfToken = itm_get_csrf_token();
$success = true;
$success &= test_save_folder($user_id, $csrfToken);
$success &= test_save_entry($user_id, $csrfToken);
if (PHP_SAPI === 'cli' && basename($_SERVER['PHP_SELF']) === 'PasswordsFunctionalTest.php') exit($success ? 0 : 1);
