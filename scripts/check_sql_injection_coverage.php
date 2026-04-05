<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$ignorePaths = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
];

// Potentially dangerous when present in SQL string construction.
$userInputPattern = '/\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES)|php:\/\/input|json_decode\s*\(\s*file_get_contents\s*\(\s*[\"\']php:\/\/input[\"\']\s*\)/i';

// Direct query execution calls that do not use bound parameters.
$directQueryPattern = '/\b(mysqli_query|itm_run_query)\s*\(/i';

// Common sanitization and safety indicators. Presence does not guarantee safety,
// but helps reduce obvious false positives in static checks.
$safetyPattern = '/mysqli_prepare\s*\(|mysqli_stmt_bind_param\s*\(|\(int\)\s*\(?\$|intval\s*\(|cr_escape_identifier\s*\(|so_escape_identifier\s*\(|itm_is_safe_identifier\s*\(|mysqli_real_escape_string\s*\(/i';

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$issues = [];
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
    foreach ($ignorePaths as $ignore) {
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
        continue;
    }

    $scanned++;

    if (preg_match($directQueryPattern, $source) !== 1 || preg_match($userInputPattern, $source) !== 1) {
        continue;
    }

    $lines = preg_split('/\R/', $source) ?: [];
    $lineCount = count($lines);

    foreach ($lines as $index => $line) {
        if (preg_match($directQueryPattern, $line) !== 1) {
            continue;
        }

        $lineNo = $index + 1;
        $start = max(0, $index - 15);
        $length = min($lineCount - $start, 31);
        $window = implode("\n", array_slice($lines, $start, $length));

        if (preg_match($userInputPattern, $window) !== 1) {
            continue;
        }

        if (preg_match($safetyPattern, $window) === 1) {
            continue;
        }

        // Specifically ignore itm_run_query in functions where it's essentially a wrapper.
        if (strpos($line, 'itm_run_query($conn, $statement)') !== false || strpos($line, 'itm_run_query($conn, $sql)') !== false) {
            continue;
        }

        $issues[] = [
            'path' => str_replace($root . DIRECTORY_SEPARATOR, '', $path),
            'line' => $lineNo,
            'reason' => 'Direct mysqli_query near user input without obvious parameter binding/sanitization in local context',
        ];
    }
}

if (empty($issues)) {
    echo "<br>";
    echo "SQL injection static check passed. Scanned {$scanned} PHP files and found no high-confidence direct-query findings.\n";
    exit(0);
}
echo"<br>";
echo "SQL injection static check found potential issues:\n";
foreach ($issues as $issue) {
    echo sprintf(" - %s:%d %s\n", $issue['path'], $issue['line'], $issue['reason']);
    echo"<br>";
}

exit(1);
