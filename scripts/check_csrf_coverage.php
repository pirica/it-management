#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$csrfPatterns = [
    'itm_require_post_csrf',
    'itm_validate_csrf_token',
    'cr_require_valid_csrf_token',
    'so_require_valid_csrf_token',
    'idf_require_csrf',
];

$pathIgnores = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$missing = [];
$scanned = 0;

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    if (substr($path, -4) !== '.php') {
        continue;
    }

    $skip = false;
    foreach ($pathIgnores as $ignore) {
        if (strpos($path, $ignore) !== false) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    $source = file_get_contents($path);
    if ($source === false) {
        $missing[] = [$path, 'Unreadable file'];
        continue;
    }

    $scanned++;
    $hasPostSurface = preg_match('/REQUEST_METHOD\s*\]\s*={2,3}\s*[\"\']POST[\"\']|\$_POST\s*\[|php:\/\/input/i', $source) === 1;
    if (!$hasPostSurface) {
        continue;
    }

    $hasDbMutationSurface = preg_match('/\b(INSERT|UPDATE|DELETE)\b|\b(mysqli_query|mysqli_prepare)\s*\(/i', $source) === 1;
    if (!$hasDbMutationSurface) {
        continue;
    }

    $hasCsrfGuard = false;
    foreach ($csrfPatterns as $pattern) {
        if (strpos($source, $pattern) !== false) {
            $hasCsrfGuard = true;
            break;
        }
    }

    if (!$hasCsrfGuard) {
        $missing[] = [$path, 'POST/mutation surface without CSRF guard reference'];
    }
}

if (empty($missing)) {
    echo "CSRF coverage check passed. Scanned {$scanned} PHP files with no uncovered POST mutation handlers.\n";
    exit(0);
}

echo "CSRF coverage check found potential gaps:\n";
foreach ($missing as [$path, $reason]) {
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    echo " - {$relative}: {$reason}\n";
}
exit(1);
