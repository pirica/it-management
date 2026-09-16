<?php
/**
 * Static gate: scaffold cr_table_columns / cr_fk_map / cr_fk_metadata must delegate to cached helpers.
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

$root = dirname(__DIR__);
$failures = [];

/**
 * @return string|null
 */
function itm_check_crud_schema_extract_function_body(string $content, string $functionName): ?string
{
    $needle = 'function ' . $functionName . '($conn, $table) {';
    $pos = strpos($content, $needle);
    if ($pos === false) {
        return null;
    }

    $bracePos = strpos($content, '{', $pos);
    if ($bracePos === false) {
        return null;
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
                return substr($content, $pos, $i + 1 - $pos);
            }
        }
    }

    return null;
}

$patterns = [
    'cr_table_columns' => [
        'delegate' => 'return itm_crud_table_columns($conn, $table);',
        'forbidden' => ['DESCRIBE', 'SHOW COLUMNS'],
    ],
    'cr_fk_map' => [
        'delegate' => 'return itm_crud_fk_map($conn, $table);',
        'forbidden' => ['information_schema.KEY_COLUMN_USAGE', 'DESCRIBE', 'SHOW COLUMNS'],
    ],
    'cr_fk_metadata' => [
        'delegate' => 'return itm_crud_fk_metadata($conn, $table);',
        'forbidden' => ['DESCRIBE', 'SHOW COLUMNS'],
    ],
];

$paths = array_merge(
    glob($root . '/modules/*/*.php') ?: [],
    glob($root . '/modules/*/*/*.php') ?: []
);

foreach ($paths as $path) {
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));

    foreach ($patterns as $functionName => $rules) {
        $body = itm_check_crud_schema_extract_function_body($content, $functionName);
        if ($body === null) {
            continue;
        }
        if (strpos($body, $rules['delegate']) === false) {
            $failures[] = $rel . ' :: ' . $functionName . '() must delegate to cached helper';
        }
        foreach ($rules['forbidden'] as $token) {
            if (stripos($body, $token) !== false) {
                $failures[] = $rel . ' :: ' . $functionName . '() still contains ' . $token;
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "CRUD schema cached helper check failed:\n");
    foreach ($failures as $line) {
        fwrite(STDERR, '  - ' . $line . "\n");
    }
    exit(1);
}

echo "PASS: scaffold schema helpers delegate to itm_crud_* cached helpers.\n";
exit(0);
