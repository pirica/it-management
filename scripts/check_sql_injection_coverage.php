<?php
/**
 * SQL Injection Coverage Static Analysis Script
 * 
 * Scans PHP files to detect direct database query calls (mysqli_query, itm_run_query)
 * that occur in close proximity to user-controlled input ($_GET, $_POST, etc.)
 * without obvious signs of parameter binding or sanitization.
 */

declare(strict_types=1);

// Resolve project root
$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$ignorePaths = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
];

// Patterns to identify user-controlled data sources
$userInputPattern = '/\$_(GET|POST|REQUEST|COOKIE|SERVER|FILES)|php:\/\/input|json_decode\s*\(\s*file_get_contents\s*\(\s*[\"\']php:\/\/input[\"\']\s*\)/i';

// Patterns to identify direct, un-parameterized query execution
$directQueryPattern = '/\b(mysqli_query|itm_run_query)\s*\(/i';

// Indicators of safe coding practices (binding or sanitization)
// Presence of these within the analysis window reduces confidence of a vulnerability
$safetyPattern = '/mysqli_prepare\s*\(|mysqli_stmt_bind_param\s*\(|\(int\)\s*\(?\$|intval\s*\(|cr_escape_identifier\s*\(|so_escape_identifier\s*\(|itm_is_safe_identifier\s*\(|mysqli_real_escape_string\s*\(/i';

// Recursively iterate through the project directory
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

    // Apply ignore filters
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

    // Initial coarse-grained filter: skip files that don't have both queries and user input
    if (preg_match($directQueryPattern, $source) !== 1 || preg_match($userInputPattern, $source) !== 1) {
        continue;
    }

    $lines = preg_split('/\R/', $source) ?: [];
    $lineCount = count($lines);

    // Perform line-by-line proximity analysis
    foreach ($lines as $index => $line) {
        if (preg_match($directQueryPattern, $line) !== 1) {
            continue;
        }

        $lineNo = $index + 1;
        // Check a 31-line window around the query call
        $start = max(0, $index - 15);
        $length = min($lineCount - $start, 31);
        $window = implode("\n", array_slice($lines, $start, $length));

        // If user input is found in the window...
        if (preg_match($userInputPattern, $window) !== 1) {
            continue;
        }

        // ...and no safety measures are detected in the same window...
        if (preg_match($safetyPattern, $window) === 1) {
            continue;
        }

        // Specifically ignore safe wrapper functions
        if (strpos($line, 'itm_run_query($conn, $statement)') !== false || strpos($line, 'itm_run_query($conn, $sql)') !== false) {
            continue;
        }

        // Record a potential vulnerability finding
        $issues[] = [
            'path' => str_replace($root . DIRECTORY_SEPARATOR, '', $path),
            'line' => $lineNo,
            'reason' => 'Direct mysqli_query near user input without obvious parameter binding/sanitization in local context',
        ];
    }
}

// Reporting
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
