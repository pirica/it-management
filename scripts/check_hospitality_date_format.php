<?php
/**
 * Static gate: hospitality stay dates in modules/hotel* and booking/ must use d/M/Y helpers.
 */
define('ITM_CLI_SCRIPT', true);
$repoRoot = dirname(__DIR__);
$fail = 0;

function hdc_fail($msg) {
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}

function hdc_pass($msg) {
    echo "[PASS] {$msg}\n";
}

$hospitalityRoots = [$repoRoot . '/booking'];
$modulesDir = $repoRoot . '/modules';
if (is_dir($modulesDir)) {
    foreach (scandir($modulesDir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (strpos($entry, 'hotel') === 0 || strpos($entry, 'booking_') === 0) {
            $path = $modulesDir . '/' . $entry;
            if (is_dir($path)) {
                $hospitalityRoots[] = $path;
            }
        }
    }
}

$stayFieldPattern = '/\b(check_in|check_out|from_date|through_date|start_date|end_date)\b/';
$badPatterns = [
    'label_format_example' => '/\(31\/Aug\/2026\)/',
    'placeholder_hospitality' => '/placeholder=["\']31\/Aug\/2026["\']/',
    'placeholder_ddmm' => '/placeholder=["\']dd\/mm\/yyyy["\']/',
];

$scanned = 0;
foreach ($hospitalityRoots as $root) {
    if (!is_dir($root)) {
        hdc_fail('missing directory ' . str_replace($repoRoot . '/', '', $root));
        continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $path = $fileInfo->getPathname();
        $ext = strtolower($fileInfo->getExtension());
        if (!in_array($ext, ['php', 'js'], true)) {
            continue;
        }
        if (strpos($path, DIRECTORY_SEPARATOR . 'AGENT_NOTES.md') !== false) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($path, strlen($repoRoot) + 1));
        $content = file_get_contents($path);
        if ($content === false) {
            hdc_fail('unreadable ' . $rel);
            continue;
        }
        $scanned++;

        foreach ($badPatterns as $label => $pattern) {
            if (preg_match($pattern, $content)) {
                hdc_fail($rel . ' — ' . $label);
            }
        }

        if ($ext === 'php' && preg_match($stayFieldPattern, $content)) {
            if (preg_match('/itm_format_date_display\s*\(/', $content)
                && preg_match('/\b(check_in|check_out|from_date|through_date)\b/', $content)) {
                hdc_fail($rel . ' — uses itm_format_date_display on hospitality stay fields');
            }
        }
    }
}

if ($scanned > 0) {
    hdc_pass('scanned ' . $scanned . ' hospitality PHP/JS files');
} else {
    hdc_fail('no hospitality files scanned');
}

require $repoRoot . '/includes/itm_date_format.php';

if (itm_format_hotel_date_display('2026-08-31') === '31/Aug/2026'
    && itm_format_hotel_date_display('2026-10-01') === '01/Oct/2026') {
    hdc_pass('itm_format_hotel_date_display d/M/Y contract');
} else {
    hdc_fail('itm_format_hotel_date_display contract broken');
}

if (itm_format_cell_scalar_display('from_date', '2026-10-01') === '01/Oct/2026'
    && itm_format_cell_scalar_display('start_date', '2026-10-01', 'hotel_booking_room_type_blocks') === '01/Oct/2026'
    && itm_format_cell_scalar_display('start_date', '2026-10-01', 'events') === itm_format_date_display('2026-10-01')) {
    hdc_pass('itm_format_cell_scalar_display hospitality routing');
} else {
    hdc_fail('itm_format_cell_scalar_display hospitality routing');
}

exit($fail > 0 ? 1 : 0);
