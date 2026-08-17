<?php
declare(strict_types=1);
function itm_script_browser_how_to_use(): string {
    return '<code>php scripts/verify_ticket_productivity.php</code>';
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Ticket Productivity Verification');
$nl = itm_script_output_nl(); $failures = 0;
function tp_fail($m){global $failures,$nl;$failures++;echo colorText('[FAIL] '.$m,'fail').$nl;}
function tp_pass($m){global $nl;echo colorText('[PASS] '.$m,'pass').$nl;}
foreach (['includes/itm_ticket_csat.php','ticket-csat.php','modules/tickets/merge.php','modules/ticket_canned_responses/index.php'] as $f) {
    is_file(ROOT_PATH.$f) ? tp_pass("File: $f") : tp_fail("Missing: $f");
}
$stmt=mysqli_prepare($conn,'SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
mysqli_stmt_bind_param($stmt,'s',$t='ticket_canned_responses');mysqli_stmt_execute($stmt);
$r=mysqli_stmt_get_result($stmt)->fetch_assoc();mysqli_stmt_close($stmt);
((int)($r['c']??0)>0)?tp_pass('ticket_canned_responses table'):tp_fail('ticket_canned_responses table');
$token=itm_ticket_csat_build_token(1,1); $v=itm_ticket_csat_verify_token($token);
(is_array($v)&& (int)$v['ticket_id']===1)?tp_pass('CSAT token'):tp_fail('CSAT token');
$emp=itm_script_test_employee_create($conn,1,['script_slug'=>'verify-ticket-productivity']);
if($emp){tp_pass('test employee');itm_script_test_employee_register_teardown($conn,(int)$emp['id']);} else tp_fail('test employee');
if($failures){echo colorText("$failures failure(s)",'fail').$nl;exit(1);}
echo colorText('All ticket productivity checks passed.','pass').$nl;exit(0);
