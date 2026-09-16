<?php
/**
 * Point scaffold cr_table_columns / cr_fk_map / cr_fk_metadata at cached shared helpers.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 *
 * Usage:
 *   php scripts/apply_crud_schema_cached_helpers.php
 *   php scripts/apply_crud_schema_cached_helpers.php --apply
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="apply_crud_schema_cached_helpers.php">dry-run</a> / <a href="apply_crud_schema_cached_helpers.php?apply=1">apply=1</a>. CLI: <code>php scripts/apply_crud_schema_cached_helpers.php</code> then <code>php scripts/apply_crud_schema_cached_helpers.php --apply</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply CRUD schema cached helpers');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

/**
 * @return array{content:string,replaced:bool}
 */
function itm_apply_crud_schema_replace_function(string $content, string $functionName, string $newBody): array
{
    $needle = 'function ' . $functionName . '($conn, $table) {';
    $pos = strpos($content, $needle);
    if ($pos === false) {
        return ['content' => $content, 'replaced' => false];
    }

    if (strpos($content, 'return itm_crud_' . ($functionName === 'cr_table_columns' ? 'table_columns' : ($functionName === 'cr_fk_map' ? 'fk_map' : 'fk_metadata')), $pos) !== false) {
        return ['content' => $content, 'replaced' => false];
    }

    $bracePos = strpos($content, '{', $pos);
    if ($bracePos === false) {
        return ['content' => $content, 'replaced' => false];
    }

    $depth = 0;
    $len = strlen($content);
    for ($i = $bracePos; $i < $len; $i++) {
        $ch = $content[$i];
        if ($ch === '{') {
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth === 0) {
                $updated = substr($content, 0, $pos) . $newBody . substr($content, $i + 1);

                return ['content' => $updated, 'replaced' => true];
            }
        }
    }

    return ['content' => $content, 'replaced' => false];
}

$replacements = [
    'cr_table_columns' => "function cr_table_columns(\$conn, \$table) {\n    return itm_crud_table_columns(\$conn, \$table);\n}",
    'cr_fk_map' => "function cr_fk_map(\$conn, \$table) {\n    return itm_crud_fk_map(\$conn, \$table);\n}",
    'cr_fk_metadata' => "function cr_fk_metadata(\$conn, \$table) {\n    return itm_crud_fk_metadata(\$conn, \$table);\n}",
];

$paths = array_merge(
    glob($root . '/modules/*/*.php') ?: [],
    glob($root . '/modules/*/*/*.php') ?: []
);

$changed = [];
$unchanged = [];

foreach ($paths as $path) {
    $rel = itm_apply_script_rel_path($root, $path);
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $original = $content;
    $fileChanged = false;

    foreach ($replacements as $functionName => $newBody) {
        if (strpos($content, 'function ' . $functionName . '($conn, $table)') === false) {
            continue;
        }
        $result = itm_apply_crud_schema_replace_function($content, $functionName, $newBody);
        $content = $result['content'];
        if ($result['replaced']) {
            $fileChanged = true;
        }
    }

    if ($fileChanged && $content !== $original) {
        if ($apply) {
            file_put_contents($path, $content);
        }
        $changed[] = $rel;
    } elseif (strpos($original, 'function cr_table_columns($conn, $table)') !== false
        || strpos($original, 'function cr_fk_map($conn, $table)') !== false
        || strpos($original, 'function cr_fk_metadata($conn, $table)') !== false) {
        $unchanged[] = $rel;
    }
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' module file(s).' . $nl . $nl;
if ($changed !== []) {
    foreach ($changed as $rel) {
        echo '  - ' . $rel . $nl;
    }
}
if (!$apply && $unchanged !== []) {
    echo $nl . 'Already delegated or unmatched: ' . count($unchanged) . ' file(s).' . $nl;
}

exit(0);
