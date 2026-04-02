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

$postHandlerPattern = '/if\s*\([^\)]*REQUEST_METHOD\s*[\"\']?\]\s*={2,3}\s*[\"\']POST[\"\'][^\)]*\)\s*\{/i';
$postSurfacePattern = '/REQUEST_METHOD\s*[\"\']?\]\s*={2,3}\s*[\"\']POST[\"\']|\$_POST\s*\[|php:\/\/input/i';

$stateMutationPattern = '/\b(INSERT|UPDATE|DELETE)\b|\b(mysqli_query|mysqli_prepare|mysqli_stmt_execute)\s*\(|\$_SESSION\s*\[[^\]]+\]\s*=|session_destroy\s*\(|setcookie\s*\(/i';

$pathIgnores = [
    DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
];

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

    if (preg_match($postSurfacePattern, $source) !== 1) {
        continue;
    }

    $hasFileLevelGuard = false;
    foreach ($csrfPatterns as $pattern) {
        if (strpos($source, $pattern . '(') !== false) {
            $hasFileLevelGuard = true;
            break;
        }
    }

    if (preg_match_all($postHandlerPattern, $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
        foreach ($matches[0] as [$matchText, $offset]) {
            $handlerScanned++;
            $bracePos = strpos($source, '{', $offset + strlen($matchText) - 1);
            if ($bracePos === false) {
                continue;
            }
            $block = extractCodeBlock($source, $bracePos);

            if (preg_match($stateMutationPattern, $block) !== 1) {
                continue;
            }

            $hasBlockGuard = false;
            foreach ($csrfPatterns as $pattern) {
                if (strpos($block, $pattern . '(') !== false) {
                    $hasBlockGuard = true;
                    break;
                }
            }

            if (!$hasBlockGuard && !$hasFileLevelGuard) {
                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $missing[] = [$path, "POST state-changing handler without CSRF guard (line {$line})"];
            }
        }
        continue;
    }

    if (preg_match($stateMutationPattern, $source) !== 1) {
        continue;
    }

    if (!$hasFileLevelGuard) {
        $missing[] = [$path, 'POST/mutation surface without CSRF guard reference'];
    }
}

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
