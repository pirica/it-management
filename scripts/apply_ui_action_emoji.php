<?php
/**
 * One-time/maintenance bulk replace for simple NO MIXED markup.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply UI action emoji (NO MIXED)');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$scanExtensions = ['php', 'js', 'html'];
$excludePathFragments = [
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'coverage' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'qa-reports' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
];

$skipRelative = [
    'scripts/check_ui_action_emoji.php',
    'scripts/apply_ui_action_emoji.php',
    'includes/itm_ui_action_labels.php',
];

/**
 * @return array<int, array{pattern:string,replacement:string,label:string}>
 */
function itm_ui_action_apply_rules(): array
{
    return [
        [
            'label' => 'save',
            'pattern' => '/(<(?:a|button|input)[^>]*)(>)\s*💾\s*Save(?:\s+Changes|\s+API\s+Key)?\s*(<\/(?:a|button)>|\/>\s*$)/iu',
            'replacement' => '$1 title="Save"$2💾$3',
        ],
        [
            'label' => 'back',
            'pattern' => '/(<(?:a|button)[^>]*)(>)\s*🔙\s*Back\s*(<\/(?:a|button)>)/iu',
            'replacement' => '$1 title="Back"$2🔙$3',
        ],
        [
            'label' => 'cancel',
            'pattern' => '/(<(?:a|button)[^>]*)(>)\s*🔙\s*Cancel\s*(<\/(?:a|button)>)/iu',
            'replacement' => '$1 title="Cancel"$2🔙$3',
        ],
        [
            'label' => 'edit',
            'pattern' => '/(<(?:a|button)[^>]*)(>)\s*✏️\s*Edit(?:\s+\w+)?\s*(<\/(?:a|button)>)/iu',
            'replacement' => '$1 title="Edit"$2✏️$3',
        ],
        [
            'label' => 'delete',
            'pattern' => '/(<(?:button)[^>]*)(>)\s*🗑️\s*Delete(?:\s+\w+)?\s*(<\/button>)/iu',
            'replacement' => '$1 title="Delete"$2🗑️$3',
        ],
    ];
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$rules = itm_ui_action_apply_rules();
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
    if ($content === false || strpos($content, '💾') === false
        && strpos($content, '🔙') === false
        && strpos($content, '✏️') === false
        && strpos($content, '🗑️') === false) {
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
    echo 'No simple mixed markup found to replace.' . $nl;
} else {
    echo $modeLabel . ': ' . count($changedFiles) . " file(s), {$totalReplacements} replacement(s)." . $nl . $nl;
    itm_apply_script_echo_list($modeLabel . ' files', $changedFiles);
}
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changedFiles), $nl, 'apply_ui_action_emoji.php');

itm_script_output_end();
exit(0);
