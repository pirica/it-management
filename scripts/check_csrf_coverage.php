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

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('CSRF coverage check');

// Known CSRF protection function names used across the system
$csrfPatterns = [
    'itm_require_post_csrf',
    'itm_validate_csrf_token',
    'cr_require_valid_csrf_token',
    'so_require_valid_csrf_token',
    'itm_validate_csrf_token',
    'idf_require_csrf',
];

// Regex patterns to identify POST handlers and state mutation code
$postHandlerPattern = '/if\s*\([^\n{};]*REQUEST_METHOD[^\n{};]*POST[^\n{};]*\)\s*\{/i';
$postSurfacePattern = '/REQUEST_METHOD\s*[\"\']?\]\s*={2,3}\s*[\"\']POST[\"\']|\$_POST\s*\[|php:\/\/input/i';
$stateMutationPattern = '/\b(INSERT|UPDATE|DELETE)\b|\b(mysqli_query|mysqli_prepare|mysqli_stmt_execute)\s*\(|\$_SESSION\s*\[[^\]]+\]\s*=|session_destroy\s*\(|setcookie\s*\(/i';

$pathIgnores = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR,
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

/**
 * Why: CLI output should name skipped paths relative to the project root.
 */
function csrf_coverage_relative_path(string $root, string $path): string
{
    $prefix = $root . DIRECTORY_SEPARATOR;
    if (strpos($path, $prefix) === 0) {
        return str_replace('\\', '/', substr($path, strlen($prefix)));
    }

    return str_replace('\\', '/', $path);
}

// Begin recursive directory scan
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$missing = [];
$skipped = [];
$scanned = 0;
$handlerScanned = 0;
$handlersSkippedNoMutation = 0;

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
        foreach ($matches[0] as $match) {
            $matchText = $match[0];
            $offset = (int) $match[1];
            $handlerScanned++;
            $bracePos = strpos($source, '{', $offset + strlen($matchText) - 1);
            if ($bracePos === false) {
                continue;
            }
            $block = extractCodeBlock($source, $bracePos);

            // If the block doesn't change state, it doesn't strictly need CSRF protection
            if (preg_match($stateMutationPattern, $block) !== 1) {
                $handlersSkippedNoMutation++;
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

    // Why: includes/*.php hook libraries (e.g. ipam_crud_hooks) mutate via helpers; callers enforce CSRF.
    $relativePath = csrf_coverage_relative_path($root, $path);
    if (strpos($relativePath, 'includes/') === 0) {
        $skipped[] = [
            $relativePath,
            'includes/ hook library: no direct if (REQUEST_METHOD ... POST) entrypoint; '
            . 'uses $_POST/mutations inside functions only — CSRF must be enforced by the requiring module before calling these helpers',
        ];
        continue;
    }

    // Why: scripts/test_ajax.php and test_edit.php are CLI-only test scripts.
    if ($relativePath === 'scripts/test_ajax.php' || $relativePath === 'scripts/test_edit.php') {
        $skipped[] = [
            $relativePath,
            'CLI-only test script that mocks session/POST; not a web-accessible endpoint',
        ];
        continue;
    }

    // Why: scripts/repro_vulnerabilities.php is a reproduction/test script; $_POST is mocked for internal API calls.
    if ($relativePath === 'scripts/repro_vulnerabilities.php') {
        $skipped[] = [
            $relativePath,
            'CLI/Browser reproduction script: mocks $_POST for internal API inclusions; not a standard CRUD endpoint',
        ];
        continue;
    }

    // Why: scripts/repro_auth_bypass_v3.php mocks $_POST in isolated subprocesses for auth-bypass PoC only.
    if ($relativePath === 'scripts/repro_auth_bypass_v3.php') {
        $skipped[] = [
            $relativePath,
            'CLI/Browser reproduction script: mocks $_POST for internal module inclusions; not a standard CRUD endpoint',
        ];
        continue;
    }

    // Why: scripts/explorer_human_test.php is a CLI integration test; $_POST is mocked for internal API calls.
    if ($relativePath === 'scripts/explorer_human_test.php') {
        $skipped[] = [
            $relativePath,
            'CLI integration test: mocks $_POST for internal API inclusions; not a web-accessible POST endpoint',
        ];
        continue;
    }

    // Why: module_browser_qa_* scripts are CLI/browser QA tools; $_POST is option parsing only and
    // $_SESSION['company_id'] simulates tenant context for HTTP checks — not application CRUD endpoints.
    if (preg_match('#^scripts/module_browser_qa_.+\\.php$#', $relativePath) === 1) {
        $skipped[] = [
            $relativePath,
            'QA runner/report script: maintenance tool, not a module POST handler; tenant session is set for automated HTTP QA only',
        ];
        continue;
    }

    // Why: scripts/lib/mbqa_*.php are shared QA report/runner libraries (browser form $_POST only).
    if (preg_match('#^scripts/lib/mbqa_.+\\.php$#', $relativePath) === 1) {
        $skipped[] = [
            $relativePath,
            'QA shared library: browser form option parsing only; no module CRUD POST handlers',
        ];
        continue;
    }

    if (!$hasFileLevelGuard) {
        $missing[] = [$path, 'POST/mutation surface without CSRF guard reference'];
    }
}

// Final output of results
if (empty($missing)) {
    echo "CSRF coverage check passed. Scanned {$scanned} PHP files and {$handlerScanned} POST handlers with no uncovered state-changing POST handlers." . (php_sapi_name() === "cli" ? "\n" : "<br><br>");
    if ($handlersSkippedNoMutation > 0) {
        echo "POST handlers skipped (read-only / no state mutation in block): {$handlersSkippedNoMutation}" . (php_sapi_name() === "cli" ? "\n" : "<br><br>");
    }
    if (!empty($skipped)) {
        echo "\nSkipped (trusted — not direct POST endpoints):" . (php_sapi_name() === "cli" ? "\n" : "<br><br>");
        foreach ($skipped as $entry) {
            echo " - {$entry[0]}: {$entry[1]}" . (php_sapi_name() === "cli" ? "\n" : "<br><br>");
        }
    }
    exit(0);
}

echo "CSRF coverage check found potential gaps:" . (php_sapi_name() === "cli" ? "\n" : "<br><br>");
foreach ($missing as $entry) {
    $path = $entry[0];
    $reason = $entry[1];
    $relative = csrf_coverage_relative_path($root, $path);
    echo " - {$relative}: {$reason}" . (php_sapi_name() === "cli" ? "\n" : "<br><br>");
}
if (!empty($skipped)) {
    echo "\nSkipped (trusted — not direct POST endpoints):" . (php_sapi_name() === "cli" ? "\n" : "<br><br>");
    foreach ($skipped as $entry) {
        echo " - {$entry[0]}: {$entry[1]}" . (php_sapi_name() === "cli" ? "\n" : "<br><br>");
    }
}
exit(1);
