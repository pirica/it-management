<?php
/**
 * Build markdown QA report from JSON output of module_browser_qa_runner.php.
 *
 * CLI: php scripts/module_browser_qa_build_report.php [--date=YYYY-MM-DD]
 * Browser: scripts/module_browser_qa_build_report.php (form) or ?run=1
 */
declare(strict_types=1);

$GLOBALS['mbqa_build_report_config'] = [
    'self_script' => 'module_browser_qa_build_report.php',
    'runner_script' => 'module_browser_qa_runner.php',
    'runner_label' => 'Run QA runner',
    'page_title' => 'Module browser QA — build report',
    'rerun_ui_click_smoke' => true,
    'md_runner_cli' => 'php scripts/module_browser_qa_runner.php',
    'md_runner_browser' => 'scripts/module_browser_qa_runner.php',
];

require_once __DIR__ . '/lib/mbqa_build_report_lib.php';
