<?php
/**
 * Webmail module regression checks.
 *
 * CLI: php scripts/verify_webmail_module.php
 * Browser: scripts/verify_webmail_module.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_webmail_module.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/webmail/</code>, <code>includes/itm_email.php</code> webmail log options, or session-scoped mailbox rules on <code>emails</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modules/webmail/includes/webmail_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Webmail Verification');

$nl = itm_script_output_nl();
$failures = 0;
$companyId = 1;
$employeeId = 1;

function webmail_verify_fail(string $message): void
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function webmail_verify_pass(string $message): void
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

/**
 * @param array{rows: array<int, array<string, mixed>>} $listResult
 */
function webmail_verify_list_contains_id(array $listResult, int $id): bool
{
    foreach ($listResult['rows'] as $row) {
        if ((int)($row['id'] ?? 0) === $id) {
            return true;
        }
    }

    return false;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    webmail_verify_fail('No database connection.');
    exit(1);
}

$registryStmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? LIMIT 1');
$slug = 'webmail';
if ($registryStmt) {
    mysqli_stmt_bind_param($registryStmt, 's', $slug);
    mysqli_stmt_execute($registryStmt);
    mysqli_stmt_bind_result($registryStmt, $registryId);
    $hasRegistry = mysqli_stmt_fetch($registryStmt);
    mysqli_stmt_close($registryStmt);
    if (!$hasRegistry) {
        webmail_verify_fail('modules_registry row missing for webmail.');
    } else {
        webmail_verify_pass('modules_registry row present for webmail.');
    }
}

$sessionEmail = '';
$emailStmt = mysqli_prepare(
    $conn,
    'SELECT COALESCE(NULLIF(TRIM(work_email), ""), NULLIF(TRIM(personal_email), "")) AS em
     FROM employees WHERE id = ? AND company_id = ? LIMIT 1'
);
if ($emailStmt) {
    mysqli_stmt_bind_param($emailStmt, 'ii', $employeeId, $companyId);
    mysqli_stmt_execute($emailStmt);
    mysqli_stmt_bind_result($emailStmt, $sessionEmail);
    mysqli_stmt_fetch($emailStmt);
    mysqli_stmt_close($emailStmt);
}
$sessionEmail = trim((string)$sessionEmail);
if ($sessionEmail === '') {
    webmail_verify_fail('Seed employee 1 has no work/personal email for webmail scope tests.');
    exit(1);
}

$token = 'webmail-verify-' . bin2hex(random_bytes(4));
$peerEmail = 'peer-' . $token . '@example.com';
$subject = 'Webmail verify ' . $token;
$insertStmt = mysqli_prepare(
    $conn,
    'INSERT INTO emails (company_id, to_email, from_email, cc_email, subject, status, details, sent_at, active, is_archived, is_star, is_deleted, created_by)
     VALUES (?, ?, ?, "", ?, "sent", "<p>verify</p>", NOW(), 1, 0, 1, 0, ?)'
);
$testId = 0;
if (!$insertStmt) {
    webmail_verify_fail('Could not prepare disposable inbox/starred insert.');
} else {
    mysqli_stmt_bind_param($insertStmt, 'isssi', $companyId, $sessionEmail, $peerEmail, $subject, $employeeId);
    if (!mysqli_stmt_execute($insertStmt)) {
        webmail_verify_fail('Disposable email insert failed.');
    } else {
        $testId = (int)mysqli_insert_id($conn);
        webmail_verify_pass('Inserted disposable starred inbox row id=' . $testId . '.');
    }
    mysqli_stmt_close($insertStmt);
}

if ($testId > 0) {
    $filters = ['status' => '', 'starred' => '', 'archived' => '', 'date_from' => '', 'date_to' => '', 'search' => $token, 'sort' => 'sent_at', 'dir' => 'DESC'];
    $starredList = webmail_fetch_list($conn, 'starred', $companyId, $employeeId, $sessionEmail, $filters, 50, 1);
    if (!webmail_verify_list_contains_id($starredList, $testId)) {
        webmail_verify_fail('Starred folder did not return disposable row.');
    } else {
        webmail_verify_pass('Starred folder lists session-scoped starred row.');
    }

    $inboxList = webmail_fetch_list($conn, 'inbox', $companyId, $employeeId, $sessionEmail, $filters, 50, 1);
    if (!webmail_verify_list_contains_id($inboxList, $testId)) {
        webmail_verify_fail('Inbox folder did not return disposable row.');
    } else {
        webmail_verify_pass('Inbox folder lists recipient-scoped row.');
    }

    $sentToken = 'webmail-verify-sent-' . bin2hex(random_bytes(4));
    $sentSubject = 'Webmail verify sent ' . $sentToken;
    $sentInsert = mysqli_prepare(
        $conn,
        'INSERT INTO emails (company_id, to_email, from_email, cc_email, subject, status, details, sent_at, active, is_archived, is_star, is_deleted, created_by)
         VALUES (?, ?, ?, "", ?, "sent", "<p>sent verify</p>", NOW(), 1, 0, 0, 0, ?)'
    );
    $sentTestId = 0;
    if (!$sentInsert) {
        webmail_verify_fail('Could not prepare disposable sent-folder insert.');
    } else {
        mysqli_stmt_bind_param($sentInsert, 'isssi', $companyId, $peerEmail, $sessionEmail, $sentSubject, $employeeId);
        if (!mysqli_stmt_execute($sentInsert)) {
            webmail_verify_fail('Disposable sent-row insert failed.');
        } else {
            $sentTestId = (int)mysqli_insert_id($conn);
            webmail_verify_pass('Inserted disposable sent row id=' . $sentTestId . '.');
        }
        mysqli_stmt_close($sentInsert);
    }

    if ($sentTestId > 0) {
        $sentFilters = ['status' => '', 'starred' => '', 'archived' => '', 'date_from' => '', 'date_to' => '', 'search' => $sentToken, 'sort' => 'sent_at', 'dir' => 'DESC'];
        $sentList = webmail_fetch_list($conn, 'sent', $companyId, $employeeId, $sessionEmail, $sentFilters, 50, 1);
        if (!webmail_verify_list_contains_id($sentList, $sentTestId)) {
            webmail_verify_fail('Sent folder did not return session from_email row.');
        } else {
            webmail_verify_pass('Sent folder lists sender-scoped row.');
        }
        $inboxExcludesSent = webmail_fetch_list($conn, 'inbox', $companyId, $employeeId, $sessionEmail, $sentFilters, 50, 1);
        if (webmail_verify_list_contains_id($inboxExcludesSent, $sentTestId)) {
            webmail_verify_fail('Inbox folder must not list sent-only disposable row.');
        } else {
            webmail_verify_pass('Inbox excludes sent-only disposable row.');
        }
    }

    if (!webmail_toggle_star($conn, $testId, $companyId, $employeeId, $sessionEmail)) {
        webmail_verify_fail('toggle_star failed on disposable row.');
    } else {
        $starredAfter = webmail_fetch_list($conn, 'starred', $companyId, $employeeId, $sessionEmail, $filters, 50, 1);
        if (webmail_verify_list_contains_id($starredAfter, $testId)) {
            webmail_verify_fail('Row still listed in starred after unstar.');
        } else {
            webmail_verify_pass('Unstar removes row from starred folder list.');
        }
    }

    if (!webmail_toggle_archive($conn, $testId, $companyId, $employeeId, $sessionEmail, 1)) {
        webmail_verify_fail('archive toggle (archive) failed.');
    } else {
        $archivedList = webmail_fetch_list($conn, 'archived', $companyId, $employeeId, $sessionEmail, $filters, 50, 1);
        $inboxAfterArchive = webmail_fetch_list($conn, 'inbox', $companyId, $employeeId, $sessionEmail, $filters, 50, 1);
        if (!webmail_verify_list_contains_id($archivedList, $testId)) {
            webmail_verify_fail('Archived folder did not list row after archive.');
        } elseif (webmail_verify_list_contains_id($inboxAfterArchive, $testId)) {
            webmail_verify_fail('Inbox still lists row after archive.');
        } else {
            webmail_verify_pass('Archive moves row to archived folder and off inbox.');
        }
        if (!webmail_toggle_archive($conn, $testId, $companyId, $employeeId, $sessionEmail, 0)) {
            webmail_verify_fail('archive toggle (unarchive) failed.');
        } else {
            $inboxAfterUnarchive = webmail_fetch_list($conn, 'inbox', $companyId, $employeeId, $sessionEmail, $filters, 50, 1);
            if (!webmail_verify_list_contains_id($inboxAfterUnarchive, $testId)) {
                webmail_verify_fail('Inbox did not list row after unarchive.');
            } else {
                webmail_verify_pass('Unarchive restores row to inbox.');
            }
        }
    }

    if (!webmail_soft_delete($conn, $testId, $companyId, $employeeId, $sessionEmail)) {
        webmail_verify_fail('soft_delete failed.');
    } else {
        $trashList = webmail_fetch_list($conn, 'trash', $companyId, $employeeId, $sessionEmail, $filters, 50, 1);
        if (!webmail_verify_list_contains_id($trashList, $testId)) {
            webmail_verify_fail('Trash folder did not list soft-deleted row for session employee.');
        } else {
            webmail_verify_pass('Trash folder lists personal soft-deleted row.');
        }
        if (!webmail_restore($conn, $testId, $companyId, $employeeId)) {
            webmail_verify_fail('restore failed.');
        } else {
            webmail_verify_pass('Soft delete and restore succeeded.');
        }
    }

    webmail_soft_delete($conn, $testId, $companyId, $employeeId, $sessionEmail);
    if (!webmail_hard_delete($conn, $testId, $companyId, $employeeId, $sessionEmail)) {
        webmail_verify_fail('hard_delete from trash failed.');
    } else {
        webmail_verify_pass('Hard delete from trash succeeded.');
    }

    if ($sentTestId > 0) {
        $delSent = mysqli_prepare($conn, 'DELETE FROM emails WHERE id = ? AND company_id = ?');
        if ($delSent) {
            mysqli_stmt_bind_param($delSent, 'ii', $sentTestId, $companyId);
            mysqli_stmt_execute($delSent);
            mysqli_stmt_close($delSent);
        }
    }
}

if (!function_exists('webmail_folders') || !in_array('starred', webmail_folders(), true)) {
    webmail_verify_fail('webmail_folders() must include starred.');
} else {
    webmail_verify_pass('webmail_folders() includes starred.');
}

if ($failures > 0) {
    echo colorText('Verification finished with ' . $failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('All webmail checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
