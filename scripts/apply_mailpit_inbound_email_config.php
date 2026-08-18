<?php
/**
 * Point default SMTP profiles at local Mailpit for inbound ticket polling.
 *
 * CLI: php scripts/apply_mailpit_inbound_email_config.php [--apply]
 * Browser: scripts/apply_mailpit_inbound_email_config.php?run=1 (dry-run) or ?run=1&apply=1 (Admin)
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Sets <code>imap_host</code> to <code>mailpit</code> and enables <code>inbound_ticket_enabled</code> on each tenant default SMTP profile. Outbound SMTP stays on <code>127.0.0.1:1025</code> (Mailpit). Inbound polling uses the Mailpit HTTP API at <a href="http://localhost/mailpit/" target="_blank" rel="noopener">http://localhost/mailpit/</a> — no PHP <code>imap</code> extension required locally.
<br><br>
CLI dry-run: <code>php scripts/apply_mailpit_inbound_email_config.php</code><br>
CLI apply: <code>php scripts/apply_mailpit_inbound_email_config.php --apply</code><br>
Browser apply (Admin): <code>?run=1&amp;apply=1</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply Mailpit Inbound Email Config', ['skip_db_tests' => false]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$conn = $boot['conn'];

if (!$conn instanceof mysqli) {
    echo colorText('[FAIL] No database connection.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$res = mysqli_query(
    $conn,
    'SELECT esc.id, esc.company_id, c.company, esc.imap_host, esc.inbound_ticket_enabled
     FROM email_smtp_configurations esc
     INNER JOIN companies c ON c.id = esc.company_id
     WHERE esc.is_default = 1 AND esc.active = 1
     ORDER BY esc.company_id ASC'
);
$rows = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }
    mysqli_free_result($res);
}

if ($rows === []) {
    echo colorText('[SKIP] No default SMTP profiles found.', 'warn') . $nl;
    itm_script_output_end();
    exit(0);
}

$wouldUpdate = 0;
foreach ($rows as $row) {
    $companyId = (int)($row['company_id'] ?? 0);
    $companyName = (string)($row['company'] ?? '');
    $currentHost = (string)($row['imap_host'] ?? '');
    $currentEnabled = (int)($row['inbound_ticket_enabled'] ?? 0);
    $needs = ($currentHost !== 'mailpit' || $currentEnabled !== 1);
    if (!$needs) {
        echo colorText('[OK] Company ' . $companyId . ' (' . $companyName . ') already uses Mailpit inbound.', 'pass') . $nl;
        continue;
    }
    $wouldUpdate++;
    $label = $apply ? 'Updated' : 'Would update';
    echo colorText('[' . $label . '] Company ' . $companyId . ' (' . $companyName . '): imap_host mailpit, inbound_ticket_enabled=1', $apply ? 'pass' : 'warn') . $nl;
    if ($apply) {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE email_smtp_configurations SET imap_host = ?, inbound_ticket_enabled = 1 WHERE id = ? AND company_id = ?'
        );
        if ($stmt) {
            $mailpit = 'mailpit';
            $id = (int)($row['id'] ?? 0);
            mysqli_stmt_bind_param($stmt, 'sii', $mailpit, $id, $companyId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

echo $nl;
if ($wouldUpdate === 0) {
    echo colorText('All default SMTP profiles already configured for Mailpit inbound.', 'pass') . $nl;
} elseif (!$apply) {
    echo colorText('Dry-run only — re-run with --apply or browser apply=1 to write.', 'warn') . $nl;
} else {
    echo colorText('Mailpit inbound configuration applied to ' . $wouldUpdate . ' profile(s).', 'pass') . $nl;
}

itm_script_output_end();
exit(0);
