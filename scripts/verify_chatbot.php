<?php
/**
 * Chatbot and Knowledge Base regression checks.
 *
 * CLI: php scripts/verify_chatbot.php
 * Browser: scripts/verify_chatbot.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Chatbot Verification');

$nl = itm_script_output_nl();
$failures = 0;

function chat_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function chat_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

function chat_verify_audit_triggers(mysqli $conn, $table)
{
    $safeTable = mysqli_real_escape_string($conn, (string)$table);
    $res = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM information_schema.TRIGGERS
         WHERE TRIGGER_SCHEMA = DATABASE()
           AND EVENT_OBJECT_TABLE = '{$safeTable}'
           AND TRIGGER_NAME LIKE 'trg\\_%\\_audit\\_%'"
    );
    $count = $res ? (int)(mysqli_fetch_assoc($res)['c'] ?? 0) : 0;
    if ($count < 3) {
        chat_verify_fail("Missing audit triggers for {$table} (expected 3, found {$count})");
        return;
    }
    chat_verify_pass("Audit triggers present for {$table}");
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    chat_verify_fail('No database connection.');
    exit(1);
}

foreach (['knowledge_base', 'it_settings'] as $table) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    if (!$res || mysqli_num_rows($res) === 0) {
        chat_verify_fail('Missing table ' . $table . '.');
    } else {
        chat_verify_pass('Table ' . $table . ' exists.');
    }
}

$registryStmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? LIMIT 1');
$slug = 'knowledge_base';
if ($registryStmt) {
    mysqli_stmt_bind_param($registryStmt, 's', $slug);
    mysqli_stmt_execute($registryStmt);
    $hasRow = mysqli_stmt_fetch($registryStmt);
    mysqli_stmt_close($registryStmt);
    if (!$hasRow) {
        chat_verify_fail('modules_registry missing knowledge_base.');
    } else {
        chat_verify_pass('modules_registry has knowledge_base.');
    }
}

$chatApiPath = ROOT_PATH . 'modules/knowledge_base/chat_api.php';
$chatApiCode = is_file($chatApiPath) ? (string)file_get_contents($chatApiPath) : '';
if ($chatApiCode === '') {
    chat_verify_fail('modules/knowledge_base/chat_api.php missing.');
} else {
    if (strpos($chatApiCode, 'itm_api_enforce_rate_limit_or_exit') === false) {
        chat_verify_fail('chat_api.php missing itm_api_enforce_rate_limit_or_exit().');
    } else {
        chat_verify_pass('chat_api.php enforces API rate limits.');
    }
    if (strpos($chatApiCode, 'itm_validate_csrf_token') === false) {
        chat_verify_fail('chat_api.php missing CSRF validation.');
    } else {
        chat_verify_pass('chat_api.php validates CSRF tokens.');
    }
    if (!preg_match('/WHERE\s+company_id\s*=\s*\$company_id/i', $chatApiCode)
        && strpos($chatApiCode, 'WHERE company_id = $company_id') === false) {
        chat_verify_fail('chat_api.php knowledge_base queries must scope by company_id.');
    } else {
        chat_verify_pass('chat_api.php scopes knowledge_base by company_id.');
    }
    if (stripos($chatApiCode, 'it_settings') === false || stripos($chatApiCode, 'company_id') === false) {
        chat_verify_fail('chat_api.php must load it_settings per company for escalation/contact copy.');
    } else {
        chat_verify_pass('chat_api.php reads it_settings for contact/escalation responses.');
    }
}

$chatbotJsPath = ROOT_PATH . 'js/chatbot.js';
$chatbotJs = is_file($chatbotJsPath) ? (string)file_get_contents($chatbotJsPath) : '';
if ($chatbotJs === '') {
    chat_verify_fail('js/chatbot.js missing.');
} else {
    if (strpos($chatbotJs, 'function escapeHtml') === false) {
        chat_verify_fail('js/chatbot.js missing escapeHtml() helper.');
    } else {
        chat_verify_pass('js/chatbot.js defines escapeHtml().');
    }
    $addMessagePos = strpos($chatbotJs, 'function addMessage');
    $escapePos = strpos($chatbotJs, 'escapeHtml(text)');
    $innerHtmlPos = strpos($chatbotJs, 'innerHTML');
    if ($addMessagePos === false || $escapePos === false || $innerHtmlPos === false || $escapePos > $innerHtmlPos) {
        chat_verify_fail('js/chatbot.js must call escapeHtml() before assigning message innerHTML.');
    } else {
        chat_verify_pass('js/chatbot.js escapes bot/user text before innerHTML.');
    }
    if (strpos($chatbotJs, 'X-CSRF-Token') === false) {
        chat_verify_fail('js/chatbot.js must send X-CSRF-Token header to chat_api.php.');
    } else {
        chat_verify_pass('js/chatbot.js sends CSRF header on chat API fetch.');
    }
}

$headerPath = ROOT_PATH . 'includes/header.php';
$headerCode = is_file($headerPath) ? (string)file_get_contents($headerPath) : '';
if ($headerCode === '' || strpos($headerCode, 'enable_chatbot') === false) {
    chat_verify_fail('includes/header.php must gate chatbot assets on enable_chatbot.');
} else {
    chat_verify_pass('includes/header.php gates chatbot.css/js on enable_chatbot.');
}

$uiColRes = mysqli_query($conn, "SHOW COLUMNS FROM ui_configuration LIKE 'enable_chatbot'");
if (!$uiColRes || mysqli_num_rows($uiColRes) === 0) {
    chat_verify_fail('ui_configuration.enable_chatbot column missing.');
} else {
    chat_verify_pass('ui_configuration.enable_chatbot column exists.');
    $defaultRow = mysqli_fetch_assoc(mysqli_query($conn, "SHOW COLUMNS FROM ui_configuration LIKE 'enable_chatbot'"));
    $columnDefault = (string)($defaultRow['Default'] ?? '');
    if ($columnDefault !== '1') {
        chat_verify_fail('ui_configuration.enable_chatbot column default must be 1.');
    } else {
        chat_verify_pass('ui_configuration.enable_chatbot column default is 1.');
    }
}

$demoSeedPath = ROOT_PATH . 'scripts/lib/itm_demo_module_users_seed.php';
$demoSeedSource = is_file($demoSeedPath) ? (string)file_get_contents($demoSeedPath) : '';
if ($demoSeedSource === '' || strpos($demoSeedSource, "1, 1, 0, 0, '25'") !== false || strpos($demoSeedSource, '1, 1, 0, 0, \\\'25\\\'') !== false) {
    chat_verify_fail('Demo ui_configuration seed must set enable_chatbot = 1.');
} elseif ($demoSeedSource !== '') {
    chat_verify_pass('Demo ui_configuration seed sets enable_chatbot = 1.');
}

chat_verify_audit_triggers($conn, 'knowledge_base');

// Why: Prove tenant isolation — company 2 article must not match company 1 scoped search.
$company1 = 1;
$company2 = 2;
$marker = 'CHATBOT-VERIFY-' . bin2hex(random_bytes(4));
$title = $marker . ' Title';
$content = $marker . ' body';
$articleId = 0;
$insertStmt = mysqli_prepare(
    $conn,
    'INSERT INTO knowledge_base (company_id, employee_id, category, title, content, active) VALUES (?, 1, ?, ?, ?, 1)'
);
$category = 'Verify';
if ($insertStmt) {
    mysqli_stmt_bind_param($insertStmt, 'isss', $company2, $category, $title, $content);
    if (mysqli_stmt_execute($insertStmt)) {
        $articleId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($insertStmt);
}
if ($articleId <= 0) {
    chat_verify_fail('Unable to seed disposable knowledge_base row for tenant isolation test.');
} else {
    $search = '%' . $marker . '%';
    $countCompany1 = 0;
    $isolationStmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM knowledge_base WHERE company_id = ? AND active = 1 AND (title LIKE ? OR content LIKE ?)'
    );
    if ($isolationStmt) {
        mysqli_stmt_bind_param($isolationStmt, 'iss', $company1, $search, $search);
        mysqli_stmt_execute($isolationStmt);
        mysqli_stmt_bind_result($isolationStmt, $countCompany1);
        mysqli_stmt_fetch($isolationStmt);
        mysqli_stmt_close($isolationStmt);
    }
    $countCompany2 = 0;
    $isolationStmt2 = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM knowledge_base WHERE company_id = ? AND active = 1 AND (title LIKE ? OR content LIKE ?)'
    );
    if ($isolationStmt2) {
        mysqli_stmt_bind_param($isolationStmt2, 'iss', $company2, $search, $search);
        mysqli_stmt_execute($isolationStmt2);
        mysqli_stmt_bind_result($isolationStmt2, $countCompany2);
        mysqli_stmt_fetch($isolationStmt2);
        mysqli_stmt_close($isolationStmt2);
    }
    if ((int)$countCompany1 !== 0) {
        chat_verify_fail('Company 1 search leaked company 2 knowledge_base article.');
    } elseif ((int)$countCompany2 < 1) {
        chat_verify_fail('Company 2 search did not find seeded knowledge_base article.');
    } else {
        chat_verify_pass('knowledge_base search stays tenant-scoped by company_id.');
    }
    mysqli_query($conn, 'DELETE FROM knowledge_base WHERE id = ' . (int)$articleId);
}

if ($failures > 0) {
    echo colorText($failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('All chatbot checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
