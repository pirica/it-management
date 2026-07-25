<?php
/**
 * Build markdown QA report from JSON output of module_browser_qa_runner.php.
 *
 * CLI: php scripts/module_browser_qa_build_report.php [--date=YYYY-MM-DD]
 * Browser: scripts/module_browser_qa_build_report.php (form) or ?run=1
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="module_browser_qa_build_report.php" target="_blank" rel="nofollow noreferrer">module_browser_qa_build_report.php</a> (form; <code>?run=1&amp;date=YYYY-MM-DD</code>)<br><code>php scripts/module_browser_qa_build_report.php</code><br><code>php scripts/module_browser_qa_build_report.php --date=2026-05-20</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
$GLOBALS['mbqa_build_report_config'] = [
    'self_script' => 'module_browser_qa_build_report.php',
    'runner_script' => 'module_browser_qa_runner.php',
    'runner_label' => 'Run QA runner',
    'page_title' => 'Module browser QA — build report',
    'rerun_ui_click_smoke' => true,
    'md_runner_cli' => 'php scripts/module_browser_qa_runner.php',
    'md_runner_browser' => 'scripts/module_browser_qa_runner.php',
];

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    require_once __DIR__ . '/lib/itm_script_browser_usage.php';
    itm_script_browser_usage_maybe_gate([]);
}

require_once __DIR__ . '/lib/mbqa_build_report_lib.php';
