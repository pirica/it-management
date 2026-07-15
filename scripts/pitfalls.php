<?php
/**
 * Common Pitfalls Extractor
 *
 * Why: Scan the whole repository for AGENT_NOTES.md (not only modules/).
 * Backfill missing note files under modules/ from templates/AGENT_NOTES.md.
 * Extract and display pitfalls documented under '## 10. Common Pitfalls'.
 *
 * This script supports both browser and CLI execution.
 * Browser access requires administrator privileges.
 */

declare(strict_types=1);

$root_dir = dirname(__DIR__);
$modules_dir = $root_dir . DIRECTORY_SEPARATOR . 'modules';
$template_path = $root_dir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'AGENT_NOTES.md';

if (!is_file($template_path)) {
    die('Template file not found at: ' . htmlspecialchars($template_path, ENT_QUOTES, 'UTF-8'));
}

/**
 * Directories / path segments to skip while walking the repo.
 *
 * Why: Avoid .git, vendor trees, generated coverage, and QA dumps — not agent notes.
 *
 * @return array<int, string>
 */
function itm_pitfalls_skip_dir_names(): array
{
    return [
        '.git',
        'vendor',
        'node_modules',
        'coverage',
        'qa-reports',
        'files',
        'backups',
        'tickets_photos',
        'floor_plans',
        'images',
    ];
}

/**
 * Recursively find all folders under a directory (legacy helper for modules/ backfill).
 *
 * @return array<int, string>
 */
function itm_get_all_subfolders(string $dir): array
{
    $folders = [];
    if (!is_dir($dir)) {
        return [];
    }

    $skip = itm_pitfalls_skip_dir_names();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item->isDir()) {
            continue;
        }
        $path = $item->getRealPath();
        if ($path === false) {
            continue;
        }
        $basename = $item->getFilename();
        if (in_array($basename, $skip, true)) {
            continue;
        }
        $folders[] = $path;
    }

    $folders = array_unique($folders);
    sort($folders);
    return $folders;
}

/**
 * Find every AGENT_NOTES.md under the repository root (UTF-8 paths).
 *
 * Why: Pitfalls live in config/, includes/, scripts/, phpunit/, root, .github/,
 * and modules/ — not only under modules/.
 *
 * Skips templates/AGENT_NOTES.md (outline only) and pruned runtime/generated trees.
 *
 * @return array<int, array{path: string, display: string, folder: string}>
 */
function itm_find_all_agent_notes(string $root_dir): array
{
    $root_real = realpath($root_dir);
    if ($root_real === false) {
        return [];
    }

    $skip_names = itm_pitfalls_skip_dir_names();
    $template_notes = $root_real . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'AGENT_NOTES.md';
    $found = [];

    $dir_iterator = new RecursiveDirectoryIterator(
        $root_real,
        FilesystemIterator::SKIP_DOTS
    );
    $filter = new RecursiveCallbackFilterIterator(
        $dir_iterator,
        static function ($current, $key, $iterator) use ($skip_names) {
            $name = $current->getFilename();
            if ($current->isDir() && in_array($name, $skip_names, true)) {
                return false;
            }
            // Allow .github (AGENT_NOTES) but skip other hidden dirs except that.
            if ($current->isDir() && isset($name[0]) && $name[0] === '.' && $name !== '.github') {
                return false;
            }
            return true;
        }
    );
    $iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

    foreach ($iterator as $item) {
        if (!$item->isFile() || $item->getFilename() !== 'AGENT_NOTES.md') {
            continue;
        }
        $path = $item->getRealPath();
        if ($path === false) {
            continue;
        }
        if ($path === $template_notes) {
            continue;
        }
        $folder = dirname($path);
        $rel = substr($path, strlen($root_real) + 1);
        $rel = str_replace('\\', '/', $rel);
        $display = ($rel === 'AGENT_NOTES.md') ? '(repo root)' : dirname($rel);
        if ($display === '.') {
            $display = '(repo root)';
        }
        $found[] = [
            'path' => $path,
            'display' => $display,
            'folder' => $folder,
        ];
    }

    usort($found, static function ($a, $b) {
        return strcasecmp($a['display'], $b['display']);
    });

    return $found;
}

/**
 * Extract the Common Pitfalls section from AGENT_NOTES.md
 */
function itm_extract_pitfalls(string $notes_path): string
{
    if (!is_file($notes_path)) {
        return 'No pitfalls documented';
    }

    $content = file_get_contents($notes_path);
    if ($content === false) {
        return 'No pitfalls documented';
    }

    // Check if it's a completely unmodified templates/AGENT_NOTES.md file
    $is_copied_template = (strpos($content, '## Authoring checklist') !== false && strpos($content, '# AGENT_NOTES.md - <Human Name>') !== false);
    if ($is_copied_template) {
        return 'No pitfalls documented';
    }

    $lines = preg_split('/\r\n|\n|\r/', $content);
    if (!is_array($lines)) {
        return 'No pitfalls documented';
    }

    $capturing = false;
    $pitfalls_lines = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Section starts with "## 10. Common Pitfalls"
        if (preg_match('/^##\s+10\.\s+Common\s+Pitfalls/i', $trimmed)) {
            $capturing = true;
            continue;
        }

        if ($capturing) {
            // Section ends on the next major heading (starting with # or ## but not ###)
            if (preg_match('/^##?[^#]/', $trimmed)) {
                break;
            }
            $pitfalls_lines[] = $line;
        }
    }

    if (empty($pitfalls_lines)) {
        return 'No pitfalls documented';
    }

    $pitfalls_text = trim(implode("\n", $pitfalls_lines));
    if ($pitfalls_text === '') {
        return 'No pitfalls documented';
    }

    // Why: Reviewed empty §10 may explicitly record confirmation so agents do not re-open gaps.
    if (preg_match('/^\[Confirmed\]\s*No pitfalls documented\.?\s*$/iu', $pitfalls_text)) {
        return '[Confirmed] No pitfalls documented';
    }

    // Check if the pitfalls text is exactly the original outline placeholder from templates/AGENT_NOTES.md
    $cleaned_text = str_replace(["\r", "\n", "\t", " "], '', $pitfalls_text);
    $template_placeholder = "Mistakesagentsmustavoid.VerifyFKdeletebehaviourin`database.sql`:|ChildFK|Pitfalltext||----------|----------------||`ONDELETESETNULL`|ChildFKsnulloutautomatically—nomanualdetach||`ONDELETECASCADE`|Parentdeleteremoveschildren||NoCASCADE/noSETNULL|DetachorclearchildFKsforactive`company_id`**before**parentdelete|Otherexamples:-Donotdeleterowsstillreferencedwhenschemablocksdelete.-Donotcopygeneric“detachfirst”textwithoutchecking`information_schema`/`database.sql`.-Bespokeorsensitivemodules:changeonlywhenexplicitlyrequested.-Document**knowngaps**(missing`employee_id`filter,unguardededitURLs)ratherthanidealbehaviour.";

    if ($cleaned_text === $template_placeholder || strpos($pitfalls_text, 'Mistakes agents must avoid') !== false && strlen($cleaned_text) < 1000) {
        // If it contains ONLY the template outline table/text and nothing else, treat as empty
        $remaining = str_replace([$template_placeholder, 'Mistakes agents must avoid', 'Verify FK delete behaviour', 'Other examples:'], '', $pitfalls_text);
        if (!preg_match('/[a-zA-Z0-9]/', $remaining)) {
            return 'No pitfalls documented';
        }
    }

    return $pitfalls_text;
}

/**
 * Whether a notes entry matches -module= / path filter (exact path, basename, or modules/<slug>).
 *
 * Why: Avoid substring false positives (`config` must not match `ui_configuration`).
 */
function itm_pitfalls_matches_filter(array $entry, ?string $module_filter): bool
{
    if ($module_filter === null || $module_filter === '') {
        return true;
    }
    $filter = strtolower(str_replace('\\', '/', trim($module_filter, "/ \t")));
    $display = strtolower(str_replace('\\', '/', $entry['display']));
    $basename = strtolower(basename($entry['folder']));
    if ($display === $filter || $basename === $filter) {
        return true;
    }
    // Prefix path match: scripts matches scripts and scripts/lib
    if ($filter !== '' && strpos($display, $filter . '/') === 0) {
        return true;
    }
    // Allow modules/<slug> and bare <slug> for module folders
    if ($display === 'modules/' . $filter || strpos($display, 'modules/' . $filter . '/') === 0) {
        return true;
    }
    if (strpos($filter, 'modules/') === 0) {
        return $display === $filter || strpos($display, $filter . '/') === 0;
    }
    return false;
}

/**
 * Browser href for a notes folder relative to scripts/.
 */
function itm_pitfalls_browser_href(string $root_dir, string $folder): string
{
    $root_real = realpath($root_dir);
    $folder_real = realpath($folder);
    if ($root_real === false || $folder_real === false) {
        return '../';
    }
    $rel = substr($folder_real, strlen($root_real) + 1);
    $rel = str_replace('\\', '/', $rel);
    if ($rel === false || $rel === '') {
        return '../index.php';
    }
    if (is_file($folder_real . DIRECTORY_SEPARATOR . 'index.php')) {
        return '../' . $rel . '/index.php';
    }
    if (is_file($folder_real . DIRECTORY_SEPARATOR . 'AGENT_NOTES.md')) {
        return '../' . $rel . '/AGENT_NOTES.md';
    }
    return '../' . $rel . '/';
}

// 1. Backfill missing AGENT_NOTES.md under modules/ only (not whole-repo — avoids filling upload trees)
$module_folders = itm_get_all_subfolders($modules_dir);
$created_count = 0;

foreach ($module_folders as $folder) {
    $notes_file = $folder . DIRECTORY_SEPARATOR . 'AGENT_NOTES.md';
    if (!is_file($notes_file)) {
        if (copy($template_path, $notes_file)) {
            $created_count++;
        }
    }
}

// 2. Discover all AGENT_NOTES.md repo-wide for extraction
$notes_entries = itm_find_all_agent_notes($root_dir);

// 2. Handle CLI Execution Mode
if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    $is_json = false;
    $module_filter = null;

    if (isset($argv) && is_array($argv)) {
        foreach ($argv as $arg) {
            if ($arg === '--json') {
                $is_json = true;
            } elseif (preg_match('/^-module=(.+)$/', $arg, $matches)) {
                $module_filter = trim($matches[1]);
            } elseif (preg_match('/^--module=(.+)$/', $arg, $matches)) {
                $module_filter = trim($matches[1]);
            } elseif (preg_match('/^module=(.+)$/', $arg, $matches)) {
                $module_filter = trim($matches[1]);
            }
        }
    }

    $results = [];
    foreach ($notes_entries as $entry) {
        if (!itm_pitfalls_matches_filter($entry, $module_filter)) {
            continue;
        }

        $pitfalls = itm_extract_pitfalls($entry['path']);

        $results[] = [
            'module' => $entry['display'],
            'path' => str_replace('\\', '/', substr($entry['path'], strlen(realpath($root_dir)) + 1)),
            'pitfalls' => $pitfalls
        ];
    }

    if ($is_json) {
        echo json_encode([
            'scanned_count' => count($notes_entries),
            'created_count' => $created_count,
            'results' => $results
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "Scan complete. Found " . count($notes_entries) . " AGENT_NOTES.md files repo-wide. Created " . $created_count . " missing modules/ AGENT_NOTES.md files.\n\n";
        if (empty($results)) {
            echo "No matching AGENT_NOTES.md entries found.\n";
        } else {
            foreach ($results as $res) {
                echo "Path: " . $res['module'] . "\n";
                echo "Pitfalls:\n";
                echo $res['pitfalls'] . "\n";
                echo str_repeat("-", 40) . "\n\n";
            }
        }
    }
    exit(0);
}

// 3. Handle Browser Execution Mode
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';

if (!itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

$generated_at = gmdate('Y-m-d H:i:s') . ' UTC';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT Management — Common Pitfalls</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body { padding: 0; margin: 0; background-color: var(--bg-secondary, #f6f8fa); }
        .scripts-wrap { max-width: 1400px; width: 95%; margin: 0 auto; padding: 24px 20px 48px; min-height: calc(100vh - 60px); }
        .scripts-card { background: var(--bg-primary, #fff); border: 1px solid var(--border, #d0d7de); border-radius: 8px; margin-bottom: 20px; padding: 18px 20px; }
        .scripts-muted { color: var(--text-secondary, #57606a); margin: 0 0 12px; line-height: 1.5; }
        .pitfalls-container { margin-top: 20px; }
        .pitfall-item { margin-bottom: 24px; padding-bottom: 12px; }
        .pitfall-link { font-weight: 600; font-size: 1.05rem; color: #0969da; text-decoration: none; }
        .pitfall-link:hover { text-decoration: underline; }
        .pitfalls-text-box {
            background: var(--bg-secondary, #f6f8fa);
            border: 1px solid var(--border, #d0d7de);
            border-radius: 6px;
            padding: 14px 18px;
            margin-top: 10px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text-primary, #24292f);
            overflow-x: auto;
        }
        .pitfalls-text-box table {
            border-collapse: collapse;
            width: 100%;
            margin: 12px 0;
            font-size: 0.9rem;
        }
        .pitfalls-text-box th, .pitfalls-text-box td {
            border: 1px solid var(--border, #d0d7de);
            padding: 8px 12px;
            text-align: left;
        }
        .pitfalls-text-box th {
            background-color: rgba(27, 31, 36, 0.02);
        }
        .pitfalls-text-box ul, .pitfalls-text-box ol {
            margin: 8px 0;
            padding-left: 20px;
        }
        .pitfalls-empty {
            color: var(--text-secondary, #57606a);
            font-style: italic;
        }
    </style>
</head>
<body>
<nav class="scripts-top-nav" aria-label="Scripts directory sections" style="position: sticky; top: 0; z-index: 100; margin: 0 0 16px; padding: 10px 20px; background: var(--bg-primary, #fff); border-bottom: 1px solid var(--border, #d0d7de); box-shadow: 0 1px 3px rgba(27, 31, 36, 0.08); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
    <div style="max-width: 1400px; width: 95%; margin: 0 auto; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <a href="scripts.php" style="font-weight: 700; color: var(--text-primary, #24292f); text-decoration: none; font-size: 1.1rem;">Scripts</a>
            <span style="color: var(--border, #d0d7de);">|</span>
            <a href="pitfalls.php" style="font-weight: 600; color: #0969da; text-decoration: none; font-size: 0.95rem;">Common Pitfalls</a>
        </div>
        <a href="../index.php" style="color: #0969da; text-decoration: none; font-size: 0.9rem;">← Home</a>
    </div>
</nav>

<div class="scripts-wrap">
    <?php itm_script_browser_nav_echo(); ?>

    <div class="scripts-card">
        <h1 style="margin: 0 0 8px;">Common Pitfalls Directory</h1>
        <p class="scripts-muted">
            Aggregated pitfalls and developer traps extracted from every <code>AGENT_NOTES.md</code> in the repository (modules, config, includes, scripts, phpunit, css, js, root, <code>.github</code>, and other in-scope folders). Missing notes under <code>modules/</code> are backfilled from the template.
        </p>
        <p class="scripts-muted" style="margin-bottom: 0; font-size: 0.85rem;">
            Total AGENT_NOTES.md scanned: <strong><?= count($notes_entries); ?></strong> |
            Newly initialized modules/ AGENT_NOTES: <strong><?= $created_count; ?></strong> |
            Generated at: <strong><?= htmlspecialchars($generated_at, ENT_QUOTES, 'UTF-8'); ?></strong>
        </p>
    </div>

    <div class="scripts-card pitfalls-container">
        <?php
        foreach ($notes_entries as $entry) {
            $display_name = $entry['display'];
            $href = itm_pitfalls_browser_href($root_dir, $entry['folder']);
            $pitfalls = itm_extract_pitfalls($entry['path']);

            $is_empty = ($pitfalls === 'No pitfalls documented');
            $is_confirmed = ($pitfalls === '[Confirmed] No pitfalls documented');
            ?>
            <div class="pitfall-item">
                <p style="margin: 0; font-weight: 500;">
                    <a class="pitfall-link" target="_blank" rel="noopener noreferrer" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?></a> - Pitfalls:
                </p>
                <div class="pitfalls-text-box <?= $is_empty ? 'pitfalls-empty' : ''; ?>">
                    <?php if ($is_empty): ?>
                        No pitfalls documented
                    <?php elseif ($is_confirmed): ?>
                        [Confirmed] No pitfalls documented
                    <?php else: ?>
                        <?php
                        // To preserve formatting nicely, we output HTML-escaped text.
                        // We also support rendering basic Markdown lists, tables, and bold tags, or we can use safe nl2br.
                        // Let's escape and do nl2br to preserve exact text line breaks and formatting, which is extremely robust.
                        echo nl2br(htmlspecialchars($pitfalls, ENT_QUOTES, 'UTF-8'));
                        ?>
                    <?php endif; ?>
                </div>
            </div>
            <!-- After each file documented add hr and br -->
            <hr><br>
            <?php
        }
        ?>
    </div>
</div>
</body>
</html>
