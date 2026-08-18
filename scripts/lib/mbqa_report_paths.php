<?php
/**
 * QA report paths under qa-reports/ (timestamped JSON/XLSX per runner run).
 */

require_once __DIR__ . '/utf8_file.php';

function mbqa_report_dir(string $projectRoot): string
{
    return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'qa-reports';
}

function mbqa_report_markdown_basename(): string
{
    return 'module-browser-qa.md';
}

function mbqa_report_markdown_path(string $projectRoot): string
{
    return mbqa_report_dir($projectRoot) . DIRECTORY_SEPARATOR . mbqa_report_markdown_basename();
}

/**
 * Filesystem slug from generated_at or now: 2026-05-22-19-26-09
 */
function mbqa_report_timestamp_slug(?string $generatedAt = null): string
{
    if ($generatedAt !== null && trim($generatedAt) !== '') {
        $ts = strtotime(trim($generatedAt));
        if ($ts !== false) {
            return date('Y-m-d-H-i-s', $ts);
        }
    }

    return date('Y-m-d-H-i-s');
}

function mbqa_report_json_basename_for_slug(string $slug): string
{
    return 'module-browser-qa-' . $slug . '.json';
}

function mbqa_report_xlsx_basename_for_slug(string $slug): string
{
    return 'module-browser-qa-' . $slug . '.xlsx';
}

/**
 * @return array{slug:string,json_basename:string,xlsx_basename:string,json_path:string,xlsx_path:string}
 */
function mbqa_report_paths_for_run(string $projectRoot, ?string $generatedAt = null): array
{
    $slug = mbqa_report_timestamp_slug($generatedAt);
    $jsonBasename = mbqa_report_json_basename_for_slug($slug);
    $xlsxBasename = mbqa_report_xlsx_basename_for_slug($slug);
    $dir = mbqa_report_dir($projectRoot);

    return [
        'slug' => $slug,
        'json_basename' => $jsonBasename,
        'xlsx_basename' => $xlsxBasename,
        'json_path' => $dir . DIRECTORY_SEPARATOR . $jsonBasename,
        'xlsx_path' => $dir . DIRECTORY_SEPARATOR . $xlsxBasename,
    ];
}

/**
 * @return array<int, string>
 */
function mbqa_report_list_json_paths(string $projectRoot): array
{
    $dir = mbqa_report_dir($projectRoot);
    if (!is_dir($dir)) {
        return [];
    }

    $paths = [];
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!preg_match('/^module-browser-qa-.+\.json$/', $item)) {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_file($path)) {
            $paths[] = $path;
        }
    }

    return $paths;
}

function mbqa_report_find_latest_json_path(string $projectRoot): string
{
    $bestPath = '';
    $bestMtime = 0;
    foreach (mbqa_report_list_json_paths($projectRoot) as $path) {
        $mtime = (int)@filemtime($path);
        if ($mtime >= $bestMtime) {
            $bestMtime = $mtime;
            $bestPath = $path;
        }
    }

    return $bestPath;
}

function mbqa_report_find_json_path_by_run_id(string $projectRoot, string $runId): string
{
    $runId = trim($runId);
    if ($runId === '') {
        return '';
    }

    $bestPath = '';
    $bestMtime = 0;
    foreach (mbqa_report_list_json_paths($projectRoot) as $path) {
        $payload = json_decode(itm_read_utf8_text_file($path), true);
        if (!is_array($payload) || (string)($payload['run_id'] ?? '') !== $runId) {
            continue;
        }
        $mtime = (int)@filemtime($path);
        if ($mtime >= $bestMtime) {
            $bestMtime = $mtime;
            $bestPath = $path;
        }
    }

    return $bestPath;
}

/**
 * @return array{slug:string,json_basename:string,xlsx_basename:string,xlsx_path:string}|null
 */
function mbqa_report_files_from_json_path(string $jsonPath): ?array
{
    if (!is_file($jsonPath)) {
        return null;
    }

    $payload = json_decode(itm_read_utf8_text_file($jsonPath), true);
    if (is_array($payload) && is_array($payload['report_files'] ?? null)) {
        $files = $payload['report_files'];
        $slug = (string)($files['slug'] ?? '');
        $jsonBasename = (string)($files['json'] ?? '');
        $xlsxBasename = (string)($files['xlsx'] ?? '');
        if ($slug !== '' && $jsonBasename !== '' && $xlsxBasename !== '') {
            $dir = dirname($jsonPath);

            return [
                'slug' => $slug,
                'json_basename' => $jsonBasename,
                'xlsx_basename' => $xlsxBasename,
                'xlsx_path' => $dir . DIRECTORY_SEPARATOR . $xlsxBasename,
            ];
        }
    }

    $basename = basename($jsonPath);
    if (preg_match('/^module-browser-qa-(.+)\.json$/', $basename, $match)) {
        $slug = (string)$match[1];
        $xlsxBasename = mbqa_report_xlsx_basename_for_slug($slug);

        return [
            'slug' => $slug,
            'json_basename' => $basename,
            'xlsx_basename' => $xlsxBasename,
            'xlsx_path' => dirname($jsonPath) . DIRECTORY_SEPARATOR . $xlsxBasename,
        ];
    }

    return null;
}

/** @deprecated Use mbqa_report_find_latest_json_path() or mbqa_report_paths_for_run(). */
function mbqa_report_json_basename(): string
{
    return 'module-browser-qa.json';
}

/** @deprecated Use mbqa_report_find_latest_json_path(). */
function mbqa_report_json_path(string $projectRoot): string
{
    $latest = mbqa_report_find_latest_json_path($projectRoot);
    if ($latest !== '') {
        return $latest;
    }

    return mbqa_report_dir($projectRoot) . DIRECTORY_SEPARATOR . mbqa_report_json_basename();
}

/**
 * Legacy dated JSON from older runner versions (module-browser-qa-YYYY-MM-DD.json).
 */
function mbqa_report_legacy_json_path(string $projectRoot, string $dateYmd): string
{
    return mbqa_report_dir($projectRoot) . DIRECTORY_SEPARATOR . 'module-browser-qa-' . $dateYmd . '.json';
}
