<?php
/**
 * Static audit: list pagination uses emoji-only visible labels (⏮️ ◀️ ▶️ ⏭️) and word-only titles.
 *
 * Why: MBQA pagination step and AGENTS.md require ⏮️/◀️/▶️/⏭️ anchors with
 * title="First page" / "Previous page" / "Next page" / "Last page" — not plain Previous/Next
 * or mixed emoji+word visible text.
 *
 * Usage (repository root):
 *   php scripts/check_pagination_emoji.php
 */
declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

require_once $root . '/includes/itm_ui_action_labels.php';
require_once __DIR__ . '/lib/itm_ui_list_contract_checks.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Pagination emoji audit');
$nl = itm_script_output_nl();

$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';

$skipRelativeFiles = [
    'scripts/check_pagination_emoji.php',
    'scripts/apply_pagination_emoji_labels.php',
    'scripts/apply_pagination_first_last.php',
];

/**
 * @return bool
 */
function cpe_content_has_pagination_nav(string $content): bool
{
    if ($content === '') {
        return false;
    }

    if (strpos($content, 'title="Previous page"') !== false
        || strpos($content, "title='Previous page'") !== false
        || strpos($content, 'title="Next page"') !== false
        || strpos($content, "title='Next page'") !== false
        || strpos($content, 'title="First page"') !== false
        || strpos($content, 'title="Last page"') !== false
    ) {
        return true;
    }

    if (preg_match('/>\s*[◀️▶️⏮️⏭️]\s*<\/a>/u', $content) === 1) {
        return true;
    }

    return preg_match('/<a\b[^>]*btn-sm[^>]*>\s*(Previous|Next|Prev)\s*<\/a>/i', $content) === 1;
}

/**
 * @return array<int, string>
 */
function cpe_collect_audit_sources(string $root, string $modulesDir): array
{
    $sources = [];

    $items = scandir($modulesDir) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $modulePath = $modulesDir . DIRECTORY_SEPARATOR . $item;
        if (!is_dir($modulePath)) {
            continue;
        }

        $indexPath = $modulePath . DIRECTORY_SEPARATOR . 'index.php';
        if (is_file($indexPath)) {
            $merged = itm_ui_merge_thin_router_audit_content($modulePath);
            if (cpe_content_has_pagination_nav($merged)) {
                $sources['modules/' . $item . '/index.php'] = $merged;
            }
        }

        foreach (['list_all.php', 'view.php', 'delete.php'] as $basename) {
            $entryPath = $modulePath . DIRECTORY_SEPARATOR . $basename;
            if (!is_file($entryPath)) {
                continue;
            }
            $content = file_get_contents($entryPath);
            if ($content !== false && cpe_content_has_pagination_nav($content)) {
                $sources['modules/' . $item . '/' . $basename] = $content;
            }
        }

        $partialPath = $modulePath . '/includes/partials/render.php';
        if (is_file($partialPath)) {
            $content = file_get_contents($partialPath);
            if ($content !== false && cpe_content_has_pagination_nav($content)) {
                $sources['modules/' . $item . '/includes/partials/render.php'] = $content;
            }
        }

        $tabsDir = $modulePath . DIRECTORY_SEPARATOR . 'tabs';
        if (is_dir($tabsDir)) {
            foreach (glob($tabsDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $tabPath) {
                $content = file_get_contents($tabPath);
                if ($content === false || !cpe_content_has_pagination_nav($content)) {
                    continue;
                }
                $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $tabPath);
                $sources[str_replace('\\', '/', $rel)] = $content;
            }
        }
    }

    ksort($sources);

    return $sources;
}

/**
 * @return array<int, string>
 */
function cpe_line_mixed_violations(string $rel, int $lineNum, string $line): array
{
    if (strpos($line, 'itm-ui-action-exempt:') !== false) {
        return [];
    }

    $violations = [];
    $patterns = [
        'pagination_prev_mixed' => '/◀️\s*Previous/u',
        'pagination_next_mixed' => '/▶️\s*Next/u',
        'pagination_first_mixed' => '/⏮️\s*First/u',
        'pagination_last_mixed' => '/⏭️\s*Last/u',
    ];
    foreach ($patterns as $label => $pattern) {
        if (preg_match($pattern, $line)) {
            $violations[] = "{$rel}:{$lineNum} [NO MIXED:{$label}]";
        }
    }

    if (preg_match('/title="[◀️▶️⏮️⏭️]\s+(Previous|Next|First|Last)/u', $line)) {
        $violations[] = "{$rel}:{$lineNum} [mixed title] pagination title must be word-only";
    }

    if (preg_match('/<a\b[^>]*btn-sm[^>]*>\s*(Previous|Next|Prev)\s*<\/a>/i', $line)) {
        $violations[] = "{$rel}:{$lineNum} [plain pagination] use ◀️/▶️/⏮️/⏭️ visible labels";
    }

    return $violations;
}

/**
 * @return array<int, string>
 */
function cpe_header_pagination_intent_violations(string $headerContent): array
{
    $violations = [];
    if ($headerContent === '') {
        return $violations;
    }

    $required = [
        'First page' => '⏮️',
        'Previous page' => '◀️',
        'Next page' => '▶️',
        'Last page' => '⏭️',
    ];
    foreach ($required as $label => $emoji) {
        if (strpos($headerContent, "label: '{$label}'") === false
            && strpos($headerContent, 'label: "' . $label . '"') === false
        ) {
            $violations[] = "includes/header.php [header drift] missing intent rule label: {$label}";
        }
        if (strpos($headerContent, "emoji: '{$emoji}'") === false) {
            $violations[] = "includes/header.php [header drift] missing intent rule emoji: {$emoji}";
        }
    }

    return $violations;
}

$failures = [];
$passCount = 0;
$naCount = 0;

$sources = cpe_collect_audit_sources($root, $modulesDir);
foreach ($sources as $rel => $content) {
    if (in_array($rel, $skipRelativeFiles, true)) {
        continue;
    }

    $contract = itm_check_pagination_nav_titles($content, $rel);
    if ($contract['status'] === 'fail') {
        $failures[] = "{$rel} [contract] {$contract['details']}";
    } elseif ($contract['status'] === 'pass') {
        $passCount++;
        echo "[pass] {$rel} — {$contract['details']}{$nl}";
    } else {
        $naCount++;
    }

    $lines = preg_split('/\R/u', $content) ?: [];
    foreach ($lines as $lineNum => $line) {
        foreach (cpe_line_mixed_violations($rel, $lineNum + 1, $line) as $violation) {
            $failures[] = $violation;
        }
    }
}

$headerPath = $root . '/includes/header.php';
$headerContent = file_get_contents($headerPath);
if ($headerContent !== false) {
    foreach (cpe_header_pagination_intent_violations($headerContent) as $violation) {
        $failures[] = $violation;
    }
}

$failures = array_values(array_unique($failures));
sort($failures);

echo $nl;
echo 'Scanned ' . count($sources) . " pagination source(s); pass={$passCount}, n/a={$naCount}, fail=" . count($failures) . $nl;

if ($failures === []) {
    echo "PASS: pagination emoji contract satisfied across audited sources.{$nl}";
    itm_script_output_end();
    exit(0);
}

echo "FAIL: " . count($failures) . " pagination emoji violation(s):{$nl}";
foreach ($failures as $msg) {
    echo "  - {$msg}{$nl}";
}

itm_script_output_end();
exit(1);
