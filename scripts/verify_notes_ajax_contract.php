<?php
/**
 * Verifies Notes AJAX single_delete returns ok:false when no row is mutated.
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/notes_visibility.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_user.php';

itm_script_output_begin('Notes AJAX Contract Verification');

$nl = itm_script_output_nl();
echo 'Verifying Notes AJAX blocked single_delete response...' . $nl;

$company_id = 1;
$owner = itm_script_test_user_create($conn, $company_id, ['script_slug' => 'verify-notes-ajax-owner']);
$attacker = itm_script_test_user_create($conn, $company_id, ['script_slug' => 'verify-notes-ajax-attacker']);
if (!is_array($owner) || !is_array($attacker)) {
    echo colorText('[FAIL] Unable to create disposable test users.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
itm_script_test_user_register_teardown($conn, (int)$owner['id']);
itm_script_test_user_register_teardown($conn, (int)$attacker['id']);

$ownerId = (int)$owner['id'];
$attackerId = (int)$attacker['id'];
$secret = 'AJAX_CONTRACT_' . uniqid();
$title = 'Private';
$stmtInsert = $conn->prepare('INSERT INTO notes (company_id, user_id, title, content, active) VALUES (?, ?, ?, ?, 1)');
$stmtInsert->bind_param('iiss', $company_id, $ownerId, $title, $secret);
$stmtInsert->execute();
$noteId = (int)$stmtInsert->insert_id;
$stmtInsert->close();

$code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_GET['ajax_action'] = 'single_delete';
function itm_validate_csrf_token(\$token) { return true; }
require '" . realpath(dirname(__DIR__) . '/config/config.php') . "';
require '" . realpath(ROOT_PATH . 'includes/notes_visibility.php') . "';
\$_SESSION['user_id'] = " . $attackerId . ";
\$_SESSION['company_id'] = " . $company_id . ";
\$_SESSION['username'] = " . var_export((string)$attacker['username'], true) . ";
\$_POST['csrf_token'] = 'test';
\$_POST['id'] = " . $noteId . ";
chdir('" . realpath(dirname(__DIR__) . '/modules/notes') . "');
ob_start();
include 'index.php';
echo ob_get_clean();
?>";

$tmp = tempnam(sys_get_temp_dir(), 'notes-ajax');
file_put_contents($tmp, $code);
$phpBin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
$output = shell_exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($tmp) . ' 2>&1');
unlink($tmp);

$decoded = json_decode(trim((string)$output), true);
$stillThere = null;
$check = $conn->prepare('SELECT content FROM notes WHERE id = ? AND active = 1');
$check->bind_param('i', $noteId);
$check->execute();
$res = $check->get_result()->fetch_assoc();
$stillThere = is_array($res) && ($res['content'] ?? '') === $secret;

$pass = is_array($decoded)
    && ($decoded['ok'] ?? null) === false
    && $stillThere;

if ($pass) {
    echo colorText('[PASS] Blocked single_delete returned ok:false and note unchanged.', 'pass') . $nl;
} else {
    echo colorText('[FAIL] Expected ok:false with unchanged note; output: ' . trim((string)$output), 'fail') . $nl;
}

$conn->query('DELETE FROM notes WHERE id = ' . (int)$noteId);
itm_script_output_end();
exit($pass ? 0 : 1);
