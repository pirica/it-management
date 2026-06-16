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
        if ($ext === 'zip') {
            return 'zip';
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
    ['archive.zip', 'zip', 'ZIP archive'],
    ['sheet.xlsx', 'unsupported', 'Spreadsheet'],
    ['.htaccess', 'unsupported', 'Managed deny_http placeholder'],
    ['index.html', 'text', 'HTML placeholder file'],
    ['image.jpg.bak', 'unsupported', 'Backup suffix must not match image'],
];

$failed = 0;
foreach ($test_cases as $tc) {
    list($filename, $expected, $label) = $tc;
    $result = explorer_resolve_preview_mode_logic($filename);
    if ($result === $expected) {
        echo "[PASS] $label ($filename): $result\n";
    } else {
        echo "[FAIL] $label ($filename): expected $expected, got $result\n";
        $failed++;
    }
}

if ($failed > 0) {
    echo "\n$failed Explorer preview mode test(s) failed!\n";
    exit(1);
}

if (!class_exists('ZipArchive')) {
    echo "\nAll Explorer preview mode tests passed!\n";
    echo "Skipped ZIP listing test: ZipArchive extension unavailable.\n";
    exit(0);
}

$tmpZip = sys_get_temp_dir() . '/itm_explorer_preview_' . uniqid('', true) . '.zip';
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "[FAIL] Could not create temporary ZIP for listing test\n";
    exit(1);
}
$zip->addFromString('readme.txt', 'hello');
$zip->addEmptyDir('docs/');
$zip->addFromString('docs/guide.md', '# Guide');
$zip->close();

$readZip = new ZipArchive();
if ($readZip->open($tmpZip) !== true) {
    echo "[FAIL] Could not reopen temporary ZIP for listing test\n";
    @unlink($tmpZip);
    exit(1);
}
$entryNames = [];
for ($i = 0; $i < $readZip->numFiles; $i++) {
    $stat = $readZip->statIndex($i);
    if (is_array($stat) && !empty($stat['name'])) {
        $entryNames[] = str_replace('\\', '/', (string)$stat['name']);
    }
}
$readZip->close();
@unlink($tmpZip);

if (count($entryNames) < 2) {
    echo "[FAIL] ZIP listing smoke test: expected at least 2 archive members\n";
    exit(1);
}
if (!in_array('readme.txt', $entryNames, true)) {
    echo "[FAIL] ZIP listing smoke test: missing readme.txt entry\n";
    exit(1);
}
echo "[PASS] ZIP listing smoke test reads archive members without extraction\n";

echo "\nAll Explorer preview tests passed!\n";
exit(0);
