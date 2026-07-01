<?php
/**
 * Explorer Preview Mode Test (Pure Logic)
 *
 * Why: JPG/PNG previews must route through file.php; text files stay on the open API path.
 * .htaccess blocking direct /files/ HTTP access is unrelated to preview classification.
 */

if (!function_exists('explorer_resolve_preview_mode_logic')) {
    function explorer_resolve_preview_mode_logic($filename) {
        $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return 'image';
        }
        if ($ext === 'pdf') {
            return 'pdf';
        }
        if (in_array($ext, ['txt', 'md', 'log', 'json', 'xml', 'csv', 'php', 'js', 'css', 'html', 'htm'], true)) {
            return 'text';
        }
        return 'unsupported';
    }
}

$test_cases = [
    ['image (3).jpg', 'image', 'JPEG photo'],
    ['photo.JPEG', 'image', 'JPEG uppercase extension'],
    ['logo.png', 'image', 'PNG image'],
    ['icon.gif', 'image', 'GIF image'],
    ['banner.webp', 'image', 'WebP image'],
    ['manual.pdf', 'pdf', 'PDF document'],
    ['notes.txt', 'text', 'Plain text'],
    ['readme.md', 'text', 'Markdown'],
    ['app.log', 'text', 'Log file'],
    ['data.json', 'text', 'JSON config'],
    ['page.html', 'text', 'HTML source preview'],
    ['archive.zip', 'unsupported', 'ZIP archive'],
    ['sheet.xlsx', 'unsupported', 'Spreadsheet'],
    ['.htaccess', 'unsupported', 'Managed deny_http placeholder'],
    ['index.html', 'text', 'HTML placeholder file'],
    ['image.jpg.bak', 'unsupported', 'Backup suffix must not match image'],
];

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Explorer Preview Mode Test');
$nl = itm_script_output_nl();

$failed = 0;
foreach ($test_cases as $tc) {
    list($filename, $expected, $label) = $tc;
    $result = explorer_resolve_preview_mode_logic($filename);
    if ($result === $expected) {
        echo itm_script_format_status_line("[PASS] $label ($filename): $result") . $nl;
    } else {
        echo itm_script_format_status_line("[FAIL] $label ($filename): expected $expected, got $result") . $nl;
        $failed++;
    }
}

if ($failed === 0) {
    echo $nl . colorText('All Explorer preview mode tests passed!', 'pass') . $nl;
    exit(0);
}

echo $nl . colorText("$failed Explorer preview mode test(s) failed!", 'fail') . $nl;
exit(1);
