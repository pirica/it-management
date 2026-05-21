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
