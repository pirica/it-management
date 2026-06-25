<?php
/**
 * Static guard: forbidden cross-module delegates to standard CRUD template.
 *
 * Why: standard CRUD modules must use local materialized copies.
 * Modules should use itm_materialize_standard_crud_module_files() instead of cross-module requires.
 *
 * CLI:  php scripts/check_standard_crud_delegate_requires.php
 * Browser: scripts/check_standard_crud_delegate_requires.php
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Standard CRUD delegate require check');

$modulesRoot = $root . DIRECTORY_SEPARATOR . 'modules';
$manufacturersDir = $modulesRoot . DIRECTORY_SEPARATOR . 'manufacturers';

if (!is_dir($modulesRoot)) {
    fwrite(STDERR, "modules/ directory not found.\n");
    exit(2);
}

/**
 * @return string Relative path with forward slashes.
 */
function mdr_relative_path(string $root, string $path): string
{
    $prefix = $root . DIRECTORY_SEPARATOR;
    if (strpos($path, $prefix) === 0) {
        return str_replace('\\', '/', substr($path, strlen($prefix)));
    }

    return str_replace('\\', '/', $path);
}

/**
 * Detects require/require_once of a specific template module from another module folder.
 */
function mdr_line_has_forbidden_delegate(string $line): bool
{
    // The manufacturers module is the filesystem source for the standard CRUD pattern.
    if (stripos($line, 'manufacturers') === false) {
        return false;
    }

    $patterns = [
        '/require(?:_once)?\s+__DIR__\s*\.\s*[\'"]\/\.\.\/manufacturers\//i',
        '/require(?:_once)?\s+__DIR__\s*\.\s*[\'"]\.\.\/manufacturers\//i',
        '/require(?:_once)?\s+[\'"]\.\.\/manufacturers\//i',
        '/require(?:_once)?\s+[\'"]\/\.\.\/manufacturers\//i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $line) === 1) {
            return true;
        }
    }

    return false;
}

$violations = [];
$scannedFiles = 0;
$isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
$nl = $isCli ? "\n" : '';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($modulesRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    if (strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    // Why: manufacturers/ is the only module allowed to implement this CRUD tree.
    if (strpos($path, $manufacturersDir . DIRECTORY_SEPARATOR) === 0) {
        continue;
    }

    $scannedFiles++;
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        $violations[] = [
            'path' => mdr_relative_path($root, $path),
            'line' => 0,
            'text' => 'Could not read file',
        ];
        continue;
    }

    foreach ($lines as $lineNumber => $lineText) {
        if (!mdr_line_has_forbidden_delegate($lineText)) {
            continue;
        }

        $violations[] = [
            'path' => mdr_relative_path($root, $path),
            'line' => $lineNumber + 1,
            'text' => trim($lineText),
        ];
    }
}

if ($violations === []) {
    echo "Standard CRUD delegate require check passed. Scanned {$scannedFiles} PHP file(s) under modules/ (excluding modules/manufacturers/).{$nl}";
    exit(0);
}

echo "Forbidden cross-module delegate(s) found (modules must be self-contained):{$nl}{$nl}";
foreach ($violations as $violation) {
    echo "{$violation['path']}:{$violation['line']}: {$violation['text']}{$nl}";
}

echo "{$nl}Fix: materialized standard CRUD modules must be self-contained — do not use cross-module requires for core logic.{$nl}";
exit(1);
