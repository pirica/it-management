<?php
$content = file_get_contents('database.sql');
if (preg_match('/CREATE TRIGGER `trg_employees_audit_insert`.*?JSON_OBJECT\((.+?)\)/s', $content, $matches)) {
    $argsPart = $matches[1];
    
    // Improved argument splitting to handle nested functions if any (though unlikely here)
    $args = [];
    $current = '';
    $depth = 0;
    $inString = false;
    for ($i = 0; $i < strlen($argsPart); $i++) {
        $char = $argsPart[$i];
        if ($char === "'" && ($i === 0 || $argsPart[$i-1] !== "\\")) $inString = !$inString;
        if (!$inString) {
            if ($char === '(') $depth++;
            if ($char === ')') $depth--;
            if ($char === ',' && $depth === 0) {
                $args[] = trim($current);
                $current = '';
                continue;
            }
        }
        $current .= $char;
    }
    $args[] = trim($current);

    echo "Total arguments: " . count($args) . "\n";
    foreach ($args as $i => $arg) {
        echo ($i+1) . ": " . $arg . "\n";
    }
}
