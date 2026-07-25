<?php
/**
 * Replace inline new_button_position blocks with itm_resolve_new_button_position().
 *
 * Why: Settings default is left (itm_ui_config_defaults); modules duplicated left_right fallbacks.
 *
 * Browser + CLI. Default dry-run; writes with CLI --apply or browser ?apply=1 (Admin).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply new button position helper');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

/**
 * @return array{content:string,changed:bool,var:string}
 */
function itm_apply_new_button_position_helper_rewrite(string $content): array
{
    if (strpos($content, 'itm_resolve_new_button_position(') !== false
        && strpos($content, "?? 'left_right'") === false
        && strpos($content, '?? "left_right"') === false
    ) {
        return ['content' => $content, 'changed' => false, 'var' => '$ui_config'];
    }

    $replacements = [
        [
            'pattern' => '/\$newButtonPosition\s*=\s*\(string\)\s*\(\s*\$uiConfig\s*\[\s*[\'"]new_button_position[\'"]\s*\]\s*\?\?\s*[\'"]left_right[\'"]\s*\)\s*;\s*\n(?:if\s*\(!in_array\(\$newButtonPosition,\s*\[[^\]]+\],\s*true\)\)\s*\{\s*\$newButtonPosition\s*=\s*[\'"]left_right[\'"];\s*\}|if\s*\(!in_array\(\$newButtonPosition,\s*\[[^\]]+\],\s*true\)\)\s*\{\s*\n\s*\$newButtonPosition\s*=\s*[\'"]left_right[\'"];\s*\n\s*\})/',
            'var' => '$uiConfig',
        ],
        [
            'pattern' => '/\$newButtonPosition\s*=\s*\(string\)\s*\(\s*\(\s*\$ui_config\s*\?\?\s*\[\]\s*\)\s*\[\s*[\'"]new_button_position[\'"]\s*\]\s*\?\?\s*[\'"]left_right[\'"]\s*\)\s*;\s*\n(?:if\s*\(!in_array\(\$newButtonPosition,\s*\[[^\]]+\],\s*true\)\)\s*\{\s*\$newButtonPosition\s*=\s*[\'"]left_right[\'"];\s*\}|if\s*\(!in_array\(\$newButtonPosition,\s*\[[^\]]+\],\s*true\)\)\s*\{\s*\n\s*\$newButtonPosition\s*=\s*[\'"]left_right[\'"];\s*\n\s*\})/',
            'var' => '$ui_config',
        ],
        [
            'pattern' => '/\$newButtonPosition\s*=\s*\(string\)\s*\(\s*\$ui_config\s*\[\s*[\'"]new_button_position[\'"]\s*\]\s*\?\?\s*[\'"]left_right[\'"]\s*\)\s*;\s*\n(?:if\s*\(!in_array\(\$newButtonPosition,\s*\[[^\]]+\],\s*true\)\)\s*\{\s*\$newButtonPosition\s*=\s*[\'"]left_right[\'"];\s*\}|if\s*\(!in_array\(\$newButtonPosition,\s*\[[^\]]+\],\s*true\)\)\s*\{\s*\n\s*\$newButtonPosition\s*=\s*[\'"]left_right[\'"];\s*\n\s*\})/',
            'var' => '$ui_config',
        ],
        [
            'pattern' => '/\$newButtonPosition\s*=\s*\(string\)\s*\(\s*\$uiConfig\s*\[\s*[\'"]new_button_position[\'"]\s*\]\s*\?\?\s*[\'"]left_right[\'"]\s*\)\s*;/',
            'var' => '$uiConfig',
        ],
        [
            'pattern' => '/\$newButtonPosition\s*=\s*\(string\)\s*\(\s*\(\s*\$ui_config\s*\?\?\s*\[\]\s*\)\s*\[\s*[\'"]new_button_position[\'"]\s*\]\s*\?\?\s*[\'"]left_right[\'"]\s*\)\s*;/',
            'var' => '$ui_config',
        ],
        [
            'pattern' => '/\$newButtonPosition\s*=\s*\(string\)\s*\(\s*\$ui_config\s*\[\s*[\'"]new_button_position[\'"]\s*\]\s*\?\?\s*[\'"]left_right[\'"]\s*\)\s*;/',
            'var' => '$ui_config',
        ],
    ];

    foreach ($replacements as $replacement) {
        $updated = preg_replace(
            $replacement['pattern'],
            '$newButtonPosition = itm_resolve_new_button_position(' . $replacement['var'] . ');',
            $content,
            1,
            $count
        );
        if ($count > 0 && is_string($updated)) {
            return ['content' => $updated, 'changed' => true, 'var' => $replacement['var']];
        }
    }

    return ['content' => $content, 'changed' => false, 'var' => '$ui_config'];
}

$scanned = 0;
$changed = [];
$skipped = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . 'modules', FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root)));
    $content = (string) file_get_contents($path);
    if (strpos($content, 'new_button_position') === false) {
        continue;
    }

    $scanned++;
    $rewrite = itm_apply_new_button_position_helper_rewrite($content);
    if (!$rewrite['changed']) {
        $skipped++;
        continue;
    }

    if ($apply) {
        file_put_contents($path, $rewrite['content']);
    }
    $changed[] = $relative . ' (' . $rewrite['var'] . ')';
}

echo 'Apply new button position helper' . $nl;
echo ($apply ? 'Mode: APPLY' : 'Mode: dry-run (pass --apply or ?apply=1 to write)') . $nl;
echo 'Scanned: ' . $scanned . $nl;
echo 'Skipped (already canonical or no match): ' . $skipped . $nl;
echo 'Changed: ' . count($changed) . $nl;
foreach ($changed as $line) {
    echo '  - ' . $line . $nl;
}

if (!$apply && count($changed) > 0) {
    exit(2);
}

exit(0);
