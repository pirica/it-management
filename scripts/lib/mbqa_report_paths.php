<?php
/**
 * Fixed QA report filenames under qa-reports/ (runner overwrites JSON each run).
 */

function mbqa_report_json_basename(): string
{
    return 'module-browser-qa.json';
}

function mbqa_report_markdown_basename(): string
{
    return 'module-browser-qa.md';
}

function mbqa_report_json_path(string $projectRoot): string
{
    return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'qa-reports' . DIRECTORY_SEPARATOR . mbqa_report_json_basename();
}

function mbqa_report_markdown_path(string $projectRoot): string
{
    return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'qa-reports' . DIRECTORY_SEPARATOR . mbqa_report_markdown_basename();
}

/**
 * Legacy dated JSON from older runner versions (module-browser-qa-YYYY-MM-DD.json).
 */
function mbqa_report_legacy_json_path(string $projectRoot, string $dateYmd): string
{
    return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'qa-reports' . DIRECTORY_SEPARATOR
        . 'module-browser-qa-' . $dateYmd . '.json';
}

function mbqa_runner_script_v1(): string
{
    return 'module_browser_qa_runner.php';
}

function mbqa_runner_script_v2(): string
{
    return 'module_browser_qa_runnerV2.php';
}

/**
 * Which runner produced the JSON (V2 when run_options.runner_script says so).
 *
 * @param array<string, mixed> $payload
 */
function mbqa_runner_script_from_payload(array $payload): string
{
    $opts = $payload['run_options'] ?? null;
    if (!is_array($opts)) {
        return mbqa_runner_script_v1();
    }

    $script = basename(trim((string)($opts['runner_script'] ?? '')));
    if ($script === mbqa_runner_script_v2()) {
        return mbqa_runner_script_v2();
    }
    // Why: V2 JSON written before runner_script was added still carries ui_click_smoke.
    if (array_key_exists('ui_click_smoke', $opts)) {
        return mbqa_runner_script_v2();
    }

    return mbqa_runner_script_v1();
}

function mbqa_runner_form_label(string $runnerScript): string
{
    return $runnerScript === mbqa_runner_script_v2() ? 'Run QA runner V2' : 'Run QA runner';
}
