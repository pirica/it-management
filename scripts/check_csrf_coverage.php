<?php
/**
 * CSRF Coverage Static Analysis Script
 * 
 * Scans all PHP files in the project to identify POST request handlers
 * that perform state-changing operations (INSERT, UPDATE, DELETE)
 * without an associated CSRF protection check.
 */

declare(strict_types=1);

// Initialize project root for scanning
$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

// Known CSRF protection function names used across the system
$csrfPatterns = [
    'itm_require_post_csrf',
    'itm_validate_csrf_token',
    'cr_require_valid_csrf_token',
    'so_require_valid_csrf_token',
    'idf_require_csrf',
];

// Regex patterns to identify POST handlers and state mutation code
$postHandlerPattern = '/if\s*\([^\n{};]*REQUEST_METHOD[^\n{};]*POST[^\n{};]*\)\s*\{/i';
$postSurfacePattern = '/REQUEST_METHOD\s*[\"\']?\]\s*={2,3}\s*[\"\']POST[\"\']|\$_POST\s*\[|php:\/\/input/i';
$stateMutationPattern = '/\b(INSERT|UPDATE|DELETE)\b|\b(mysqli_query|mysqli_prepare|mysqli_stmt_execute)\s*\(|\$_SESSION\s*\[[^\]]+\]\s*=|session_destroy\s*\(|setcookie\s*\(/i';

$pathIgnores = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
];

/**
 * Extracts a balanced code block starting from a given brace position
 */
function extractCodeBlock(string $source, int $bracePos): string
{
    $length = strlen($source);
    $depth = 0;
    $start = $bracePos;

    for ($i = $bracePos; $i < $length; $i++) {
        $ch = $source[$i];
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($source, $start, $i - $start + 1);
            }
        }
    }

    return substr($source, $start);
}

// Begin recursive directory scan
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$missing = [];
$scanned = 0;
$handlerScanned = 0;

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();
    // Only scan PHP files
    if (substr($path, -4) !== '.php') {
        continue;
    }

    // Apply ignore filters
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

    // Skip files that don't appear to handle POST requests
    if (preg_match($postSurfacePattern, $source) !== 1) {
        continue;
    }

    // Check if the file has a global CSRF guard
    $hasFileLevelGuard = false;
    foreach ($csrfPatterns as $pattern) {
        if (strpos($source, $pattern . '(') !== false) {
            $hasFileLevelGuard = true;
            break;
        }
    }

    // Analyze individual POST handler blocks
    if (preg_match_all($postHandlerPattern, $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
        foreach ($matches[0] as [$matchText, $offset]) {
            $handlerScanned++;
            $bracePos = strpos($source, '{', $offset + strlen($matchText) - 1);
            if ($bracePos === false) {
                continue;
            }
            $block = extractCodeBlock($source, $bracePos);

            // If the block doesn't change state, it doesn't strictly need CSRF protection
            if (preg_match($stateMutationPattern, $block) !== 1) {
                continue;
            }

            // Look for CSRF guard inside the specific code block
            $hasBlockGuard = false;
            foreach ($csrfPatterns as $pattern) {
                if (strpos($block, $pattern . '(') !== false) {
                    $hasBlockGuard = true;
                    break;
                }
            }

            // Report if neither block-level nor file-level guard found
            if (!$hasBlockGuard && !$hasFileLevelGuard) {
                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $missing[] = [$path, "POST state-changing handler without CSRF guard (line {$line})"];
            }
        }
        continue;
    }

    // Handle files that handle POST requests but not inside an if(POST) block
    if (preg_match($stateMutationPattern, $source) !== 1) {
        continue;
    }

    if (!$hasFileLevelGuard) {
        $missing[] = [$path, 'POST/mutation surface without CSRF guard reference'];
    }
}

// Final output of results
if (empty($missing)) {
    echo "CSRF coverage check passed. Scanned {$scanned} PHP files and {$handlerScanned} POST handlers with no uncovered state-changing POST handlers.\n";
    exit(0);
}

echo "CSRF coverage check found potential gaps:\n";
foreach ($missing as [$path, $reason]) {
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    echo " - {$relative}: {$reason}\n";
}
exit(1);
