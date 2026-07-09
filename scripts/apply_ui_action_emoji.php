<?php
/**
 * One-time/maintenance bulk replace for simple NO MIXED markup.
 *
 * Why: Safe mechanical fixes for >💾 Save< style controls; PHP ternaries and JS templates stay manual.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once __DIR__ . '/lib/script_cli_output.php';
$nl = itm_script_output_nl();


$apply = in_array('--apply', $argv ?? [], true);
$dryRun = !$apply;

itm_script_output_begin('Apply UI action emoji (NO MIXED)');

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
$changedFiles = 0;
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

    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    if (in_array(str_replace('\\', '/', $rel), $skipRelative, true)) {
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

    $changedFiles++;
    $totalReplacements += $fileHits;
    echo ($dryRun ? '[dry-run] ' : '[apply] ') . "{$rel} ({$fileHits} replacement(s))\n";

    if (!$dryRun) {
        file_put_contents($path, $content);
    }
}

if ($changedFiles === 0) {
    echo "No simple mixed markup found to replace." . $nl;
    exit(0);
}

echo ($dryRun ? 'Dry-run complete' : 'Apply complete') . ": {$changedFiles} file(s), {$totalReplacements} replacement(s).\n";
if ($dryRun) {
    echo "Re-run with --apply to write changes." . $nl;
}
exit(0);

itm_script_output_end();
