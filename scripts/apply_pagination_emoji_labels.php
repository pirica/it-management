<?php
/**
 * Bulk replace legacy list pagination labels (plain Previous/Next) with emoji-only
 * visible text and word-only title attributes per AGENTS.md pagination contract.
 *
 * Transforms legacy list pagination anchors: mixed emoji+word titles and plain
 * Previous/Next visible text become emoji-only ◀️/▶️ with title Previous page / Next page.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply pagination emoji labels (NO MIXED)');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$scanExtensions = ['php', 'html'];
$excludePathFragments = [
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'coverage' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'qa-reports' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
];

$skipRelative = [
    'scripts/check_ui_action_emoji.php',
    'scripts/apply_pagination_emoji_labels.php',
    'scripts/apply_ui_action_emoji.php',
    'includes/itm_ui_action_labels.php',
    'AGENTS.md',
    'scripts/SCRIPTS.md',
];

/**
 * @return array<int, array{pattern:string,replacement:string,label:string}>
 */
function itm_pagination_emoji_apply_rules(): array
{
    return [
        [
            'label' => 'previous_double_quote',
            'pattern' => '/title="◀️\s*Previous"([^>]*>)\s*Previous\s*<\/a>/iu',
            'replacement' => 'title="Previous page"$1◀️</a>',
        ],
        [
            'label' => 'previous_single_quote',
            'pattern' => "/title='◀️\s*Previous'([^>]*>)\s*Previous\s*<\/a>/iu",
            'replacement' => "title='Previous page'$1◀️</a>",
        ],
        [
            'label' => 'next_double_quote',
            'pattern' => '/title="▶️\s*Next"([^>]*>)\s*Next\s*<\/a>/iu',
            'replacement' => 'title="Next page"$1▶️</a>',
        ],
        [
            'label' => 'next_single_quote',
            'pattern' => "/title='▶️\s*Next'([^>]*>)\s*Next\s*<\/a>/iu",
            'replacement' => "title='Next page'$1▶️</a>",
        ],
        [
            'label' => 'prev_double_quote',
            'pattern' => '/title="◀️\s*Previous"([^>]*>)\s*Prev\s*<\/a>/iu',
            'replacement' => 'title="Previous page"$1◀️</a>',
        ],
        [
            'label' => 'plain_previous_no_title',
            'pattern' => '/(<a\b(?![^>]*\btitle=)[^>]*class="[^"]*btn-sm[^"]*"[^>]*href="[^"]*page=\d+[^"]*"[^>]*)(>)\s*Previous\s*(<\/a>)/iu',
            'replacement' => '$1 title="Previous page"$2◀️$3',
        ],
        [
            'label' => 'plain_next_no_title',
            'pattern' => '/(<a\b(?![^>]*\btitle=)[^>]*class="[^"]*btn-sm[^"]*"[^>]*href="[^"]*page=\d+[^"]*"[^>]*)(>)\s*Next\s*(<\/a>)/iu',
            'replacement' => '$1 title="Next page"$2▶️$3',
        ],
    ];
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$rules = itm_pagination_emoji_apply_rules();
$changedFiles = [];
$totalReplacements = 0;

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }
    $path = $fileInfo->getPathname();
    foreach ($excludePathFragments as $fragment) {
        if (strpos($path, $fragment) !== false) {
            continue 2;
        }
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, $scanExtensions, true)) {
        continue;
    }

    $rel = itm_apply_script_rel_path($root, $path);
    if (in_array($rel, $skipRelative, true)) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    if (stripos($content, 'Previous') === false && stripos($content, 'Next') === false
        && stripos($content, '◀️') === false && stripos($content, '▶️') === false) {
        continue;
    }

    $original = $content;
    $fileHits = 0;
    foreach ($rules as $rule) {
        $content = preg_replace($rule['pattern'], $rule['replacement'], $content, -1, $count);
        if ($count > 0) {
            $fileHits += $count;
        }
    }

    if ($content === $original) {
        continue;
    }

    $totalReplacements += $fileHits;
    $changedFiles[] = $rel . ' (' . $fileHits . ' replacement(s))';
    echo ($apply ? '[apply] ' : '[dry-run] ') . "{$rel} ({$fileHits} replacement(s))\n";

    if ($apply) {
        file_put_contents($path, $content);
    }
}

$modeLabel = $apply ? 'Apply complete' : 'Would change';
echo $nl;
if ($changedFiles === []) {
    echo 'No legacy pagination Previous/Next markup found to replace.' . $nl;
} else {
    echo $modeLabel . ': ' . count($changedFiles) . " file(s), {$totalReplacements} replacement(s)." . $nl . $nl;
    itm_apply_script_echo_list($modeLabel . ' files', $changedFiles);
}
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changedFiles), $nl, 'apply_pagination_emoji_labels.php');

itm_script_output_end();
exit(0);
