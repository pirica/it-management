<?php
/**
 * Static audit: NO MIXED emoji+word on action controls and page headings.
 *
 * Why: Standard UI actions use emoji-only visible text; full phrases live in title/aria-label.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/itm_ui_action_labels.php';
require_once __DIR__ . '/lib/script_cli_output.php';
$nl = itm_script_output_nl();


$isCli = (PHP_SAPI === 'cli');
itm_script_output_begin('UI action emoji audit (NO MIXED)');

$scanExtensions = ['php', 'js', 'html'];
$excludePathFragments = [
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'coverage' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'qa-reports' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
];

$violations = [];

/**
 * @return array<int, string>
 */
function itm_ui_action_collect_files(string $root, array $extensions, array $excludeFragments): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $path = $fileInfo->getPathname();
        foreach ($excludeFragments as $fragment) {
            if (strpos($path, $fragment) !== false) {
                continue 2;
            }
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true)) {
            continue;
        }
        $files[] = $path;
    }

    sort($files);
    return $files;
}

/**
 * @param array<int, string> $lines
 */
function itm_ui_action_line_exempt(string $line): bool
{
    if (strpos($line, 'itm-ui-action-exempt:') !== false) {
        return true;
    }
    if (strpos($line, 'data-itm-bulk-cancel="1"') !== false && preg_match('/>\s*Cancel\s*</', $line)) {
        return true;
    }
    if (preg_match('/>\s*(Search|Select to Delete|Delete Selected|Clear Table)\s*</', $line)) {
        return true;
    }
    // Descriptive non-actions containing action words without standard emoji prefix.
    if (preg_match('/(View IP record|Reset View|Table View|Keep View|rack-drag-trash)/i', $line)) {
        return true;
    }
    return false;
}

$files = itm_ui_action_collect_files($root, $scanExtensions, $excludePathFragments);
$patterns = itm_ui_action_no_mixed_patterns();
$literals = itm_ui_action_known_literal_violations();

foreach ($files as $path) {
    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $relNorm = str_replace('\\', '/', $rel);
    if (in_array($relNorm, [
        'includes/itm_ui_action_labels.php',
        'scripts/check_ui_action_emoji.php',
        'scripts/apply_ui_action_emoji.php',
        'scripts/apply_pagination_emoji_labels.php',
    ], true)) {
        continue;
    }
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $lines = preg_split('/\R/u', $content) ?: [];

    foreach ($lines as $lineNum => $line) {
        $humanLine = $lineNum + 1;
        if (itm_ui_action_line_exempt($line)) {
            continue;
        }

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $line)) {
                $violations[] = "{$rel}:{$humanLine} [NO MIXED:{$label}] " . trim($line);
            }
        }

        foreach ($literals as $literal) {
            if (strpos($line, $literal) !== false) {
                $violations[] = "{$rel}:{$humanLine} [known literal] {$literal}";
            }
        }

        // Plain-text standalone action words on interactive tags (no leading emoji).
        if (preg_match('/<(a|button|input)[^>]*>\s*(Save|Cancel|View|Edit|Delete|Create|Back|Add|New|Previous|Next|Prev)\s*<\//i', $line, $m)) {
            $word = $m[2];
            if (strcasecmp($word, 'Cancel') === 0 && strpos($line, 'data-itm-bulk-cancel="1"') !== false) {
                continue;
            }
            if ($word === 'Search') {
                continue;
            }
            $violations[] = "{$rel}:{$humanLine} [plain action] >{$word}< without emoji";
        }
    }
}

// Header intentRules drift check.
$headerPath = $root . '/includes/header.php';
$headerContent = file_get_contents($headerPath);
if ($headerContent !== false) {
    if (strpos($headerContent, "emoji: '👀'") !== false) {
        $violations[] = 'includes/header.php [header drift] View rule still uses 👀';
    }
    if (strpos($headerContent, "emoji: '↩️'") !== false) {
        $violations[] = 'includes/header.php [header drift] Back rule still uses ↩️';
    }
    if (strpos($headerContent, "label: 'Cancel'") === false && strpos($headerContent, "/^cancel\$/i") === false) {
        $violations[] = 'includes/header.php [header drift] missing Cancel intent rule';
    }
}

$mixedCount = 0;
foreach ($violations as $v) {
    if (strpos($v, '[NO MIXED:') !== false || strpos($v, '[known literal]') !== false) {
        $mixedCount++;
    }
}

if (empty($violations)) {
    echo "PASS: 0 violations incl. mixed emoji+word" . $nl;
    exit(0);
}

echo 'FAIL: ' . count($violations) . " violation(s); NO MIXED + known literals: {$mixedCount}\n";
foreach ($violations as $msg) {
    echo "  - {$msg}" . $nl;
}
exit(1);

itm_script_output_end();
