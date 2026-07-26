<?php
/**
 * Cursor project rules (.cursor/rules/*.mdc) — read-only browser viewer (Admin).
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Open <code>scripts/rules.php</code> in the browser (Admin only). Lists <code>.cursor/rules/*.mdc</code> and shows file contents. CLI: <code>php scripts/rules.php [--json]</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$rootDir = dirname(__DIR__);
require_once __DIR__ . '/lib/itm_cursor_rules.php';

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    $asJson = false;
    if (isset($argv) && is_array($argv)) {
        foreach ($argv as $arg) {
            if ($arg === '--json') {
                $asJson = true;
            }
        }
    }
    $list = itm_cursor_rules_list_basenames();
    if ($asJson) {
        echo json_encode(['rules_dir' => '.cursor/rules', 'files' => $list], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    }
    echo "Cursor rules (.cursor/rules)\n";
    if (!$list) {
        echo "No .mdc files found.\n";
        exit(0);
    }
    foreach ($list as $name) {
        echo "  - {$name}\n";
    }
    exit(0);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

$viewFile = isset($_GET['f']) ? (string)$_GET['f'] : '';
$resolvedPath = $viewFile !== '' ? itm_cursor_rules_resolve_file($viewFile) : null;
$ruleFiles = itm_cursor_rules_list_basenames();
$generatedAt = gmdate('Y-m-d H:i:s') . ' UTC';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT Management — Cursor Rules</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body { padding: 0; margin: 0; background-color: var(--bg-secondary, #f6f8fa); }
        .scripts-wrap { max-width: 1400px; width: 95%; margin: 0 auto; padding: 24px 20px 48px; min-height: calc(100vh - 60px); }
        .scripts-card { background: var(--bg-primary, #fff); border: 1px solid var(--border, #d0d7de); border-radius: 8px; margin-bottom: 20px; padding: 18px 20px; }
        .scripts-muted { color: var(--text-secondary, #57606a); margin: 0 0 12px; line-height: 1.5; }
        .rules-list { list-style: none; margin: 0; padding: 0; }
        .rules-list li { margin: 0 0 8px; }
        .rules-link { font-weight: 600; color: #0969da; text-decoration: none; }
        .rules-link:hover { text-decoration: underline; }
        .rules-link.is-active { color: var(--text-primary, #24292f); }
        .rules-pre {
            background: var(--bg-secondary, #f6f8fa);
            border: 1px solid var(--border, #d0d7de);
            border-radius: 6px;
            padding: 14px 18px;
            margin-top: 12px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.88rem;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<?php itm_script_browser_main_menu_echo('rules'); ?>
<div class="scripts-wrap">
    <?php itm_script_browser_nav_echo(); ?>

    <div class="scripts-card">
        <h1 style="margin: 0 0 8px;">Cursor Rules</h1>
        <p class="scripts-muted">
            Project rules for Cursor live under <code>.cursor/rules/</code> (typically <code>*.mdc</code>).
            This page is read-only; edit files in the repository or in Cursor Settings → Rules.
        </p>
        <p class="scripts-muted" style="margin-bottom: 0; font-size: 0.85rem;">
            Files: <strong><?= count($ruleFiles); ?></strong> · Generated at: <strong><?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8'); ?></strong>
        </p>
    </div>

    <div class="scripts-card">
        <?php if (!$ruleFiles): ?>
            <p class="scripts-muted">No <code>*.mdc</code> files found in <code>.cursor/rules/</code>.</p>
        <?php else: ?>
            <ul class="rules-list">
                <?php foreach ($ruleFiles as $basename): ?>
                    <?php
                    $isActive = $resolvedPath !== null && basename($resolvedPath) === $basename;
                    $href = 'rules.php?f=' . rawurlencode($basename);
                    ?>
                    <li>
                        <a class="rules-link<?= $isActive ? ' is-active' : ''; ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($basename, ENT_QUOTES, 'UTF-8'); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($viewFile !== '' && $resolvedPath === null): ?>
            <p class="scripts-muted" style="margin-top:16px;color:var(--danger,#cf222e);">Rule file not found or invalid name.</p>
        <?php elseif ($resolvedPath !== null): ?>
            <?php
            $contents = file_get_contents($resolvedPath);
            if ($contents === false) {
                $contents = '';
            }
            ?>
            <h2 style="margin:20px 0 8px;font-size:1.1rem;"><?= htmlspecialchars(basename($resolvedPath), ENT_QUOTES, 'UTF-8'); ?></h2>
            <pre class="rules-pre"><?= htmlspecialchars($contents, ENT_QUOTES, 'UTF-8'); ?></pre>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
