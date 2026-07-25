<?php
/**
 * Regression: finance module attachments (storage path, validation, DB table).
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_finance_attachments.php</code>. Run when changing <code>includes/itm_finance_attachments.php</code> or finance attachment UI on invoices, bills, expenses, customers, bank_accounts.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
require_once dirname(__DIR__) . '/config/config.php';

$failures = 0;

function fa_report(string $label, bool $ok): void
{
    global $failures;
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$tables = itm_finance_attachment_supported_parent_tables();
fa_report('supported parent tables count', count($tables) === 5);
fa_report('max bytes is 5MB', itm_finance_attachment_max_bytes() === 5 * 1024 * 1024);

$ext = itm_finance_attachment_allowed_extensions();
fa_report('pdf allowed', in_array('pdf', $ext, true));
fa_report('txt allowed', in_array('txt', $ext, true));

$key = itm_finance_attachment_folder_key_for_row('invoices', ['id' => 1, 'document_number' => 'INV/2026-001']);
fa_report('invoice folder key sanitizes', $key === 'INV_2026-001');

$dir = itm_finance_attachment_directory_for_key(1, 'bills', 'BILL-100');
$dirNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);
$tail = '1' . DIRECTORY_SEPARATOR . 'bills' . DIRECTORY_SEPARATOR . 'BILL-100' . DIRECTORY_SEPARATOR;
fa_report('storage path under finance/{company}/{module}/{document}', strpos($dirNorm, 'finance' . DIRECTORY_SEPARATOR . $tail) !== false);

$res = mysqli_query($conn, "SHOW TABLES LIKE 'finance_attachments'");
fa_report('finance_attachments table exists', $res && mysqli_num_rows($res) === 1);

foreach (['modules/invoices/attachment.php', 'includes/itm_finance_attachments.php'] as $rel) {
    fa_report($rel . ' exists', is_file(ROOT_PATH . $rel));
}

exit($failures > 0 ? 1 : 0);
