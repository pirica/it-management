<?php
/**
 * Debug equipment create DB errors lost after mysqli_rollback().
 *
 * Reproduces a deliberate equipment INSERT failure (NULL status_id) inside a transaction,
 * then compares mysqli_errno()/mysqli_error() and itm_format_db_constraint_error() output
 * before vs after mysqli_rollback(). Use when create.php shows the generic message
 * "We could not save your changes. Review the required fields and try again." instead
 * of a column-specific FK/null error.
 *
 * Browser: scripts/debug_equipment_create_rollback_errno.php?company_id=1
 * CLI: php scripts/debug_equipment_create_rollback_errno.php --company_id=1
 */
declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Equipment Create Rollback Errno Debug');

$nl = itm_script_output_nl();

function decrd_info(string $message): void
{
    global $nl;
    echo colorText('[INFO] ' . $message, 'info') . $nl;
}

function decrd_pass(string $message): void
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

function decrd_fail(string $message): void
{
    global $nl;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

/**
 * @return array{company_id:int}
 */
function decrd_parse_args(): array
{
    $args = [
        'company_id' => '1',
    ];

    if (PHP_SAPI === 'cli') {
        global $argv;
        foreach (array_slice($argv ?? [], 1) as $arg) {
            if (strpos($arg, '--') !== 0) {
                continue;
            }
            $pair = explode('=', substr($arg, 2), 2);
            $key = $pair[0] ?? '';
            $value = $pair[1] ?? '';
            if ($key !== '' && array_key_exists($key, $args)) {
                $args[$key] = $value;
            }
        }
    } elseif (isset($_GET['company_id']) && trim((string)$_GET['company_id']) !== '') {
        $args['company_id'] = trim((string)$_GET['company_id']);
    }

    return [
        'company_id' => max(1, (int)$args['company_id']),
    ];
}

$options = decrd_parse_args();
$companyId = (int)$options['company_id'];

if (!isset($conn) || !($conn instanceof mysqli)) {
    decrd_fail('Database connection is unavailable.');
    exit(1);
}

decrd_info('Company ID: ' . $companyId);
decrd_info('Probing deliberate equipment INSERT with status_id = NULL inside a transaction.');

$probeName = 'debug-rollback-errno-' . date('YmdHis');
$invalidTypeId = 999999999;

mysqli_begin_transaction($conn);
$insertOk = mysqli_query(
    $conn,
    'INSERT INTO equipment (company_id, equipment_type_id, name, status_id, printer_color_capable, printer_scan, active)
     VALUES (' . $companyId . ', ' . $invalidTypeId . ", '" . mysqli_real_escape_string($conn, $probeName) . "', NULL, 0, 0, 1)"
);

decrd_info('INSERT returned: ' . ($insertOk ? 'true' : 'false'));

$errnoBefore = (int)mysqli_errno($conn);
$errorBefore = (string)mysqli_error($conn);
echo $nl . '--- Before mysqli_rollback() ---' . $nl;
decrd_info('mysqli_errno: ' . $errnoBefore);
decrd_info('mysqli_error: ' . ($errorBefore !== '' ? $errorBefore : '(empty)'));
decrd_info('itm_format_db_constraint_error: ' . itm_format_db_constraint_error($errnoBefore, $errorBefore));

mysqli_rollback($conn);

$errnoAfter = (int)mysqli_errno($conn);
$errorAfter = (string)mysqli_error($conn);
echo $nl . '--- After mysqli_rollback() ---' . $nl;
decrd_info('mysqli_errno: ' . $errnoAfter);
decrd_info('mysqli_error: ' . ($errorAfter !== '' ? $errorAfter : '(empty)'));
decrd_info('itm_format_db_constraint_error: ' . itm_format_db_constraint_error($errnoAfter, $errorAfter));

echo $nl;
if ($errnoBefore > 0 && $errorBefore !== '') {
    decrd_pass('MySQL reported a concrete error before rollback.');
} else {
    decrd_fail('No MySQL error captured before rollback — probe may need a different failing INSERT.');
}

if ($errnoAfter === 0 && $errorAfter === '') {
    decrd_fail(
        'mysqli_rollback() cleared errno/error. modules/equipment/create.php must capture mysqli_errno()/mysqli_error() before rollback or users see the generic save message.'
    );
    exit(1);
}

decrd_pass('mysqli error state survived rollback (create.php can read errno after rollback on this PHP/MySQL build).');
exit(0);
