<?php
/**
 * Build markdown QA report from JSON output of module_browser_qa_runnerV2.php.
 *
 * CLI: php scripts/module_browser_qa_build_reportV2.php [--date=YYYY-MM-DD]
 * Browser: scripts/module_browser_qa_build_reportV2.php (form) or ?run=1
 *
 * V1 runner uses scripts/module_browser_qa_build_report.php — do not cross-link.
 */
declare(strict_types=1);

$GLOBALS['mbqa_build_report_config'] = [
    'self_script' => 'module_browser_qa_build_reportV2.php',
    'runner_script' => 'module_browser_qa_runnerV2.php',
    'runner_label' => 'Run QA runner V2',
    'page_title' => 'Module browser QA V2 — build report',
    'rerun_ui_click_smoke' => true,
    'md_runner_cli' => 'php scripts/module_browser_qa_runnerV2.php',
    'md_runner_browser' => 'scripts/module_browser_qa_runnerV2.php',
];

require_once __DIR__ . '/lib/mbqa_build_report_lib.php';
