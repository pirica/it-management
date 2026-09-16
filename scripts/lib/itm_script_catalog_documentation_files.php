<?php
/**
 * Auto-catalog scripts/data/* and scripts/*.md under Documentation in scripts/scripts.php.
 */

if (!function_exists('itm_script_catalog_documentation_files_discover')) {
    /**
     * @return array<int, string> relative paths under scripts/ (e.g. data/foo.json, SCRIPTS.md)
     */
    function itm_script_catalog_documentation_files_discover(string $scriptsRoot): array
    {
        $scriptsRoot = rtrim(str_replace('\\', '/', $scriptsRoot), '/') . '/';
        $paths = [];

        foreach (['md'] as $ext) {
            foreach (glob($scriptsRoot . '*.' . $ext) ?: [] as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $paths[basename($file)] = basename($file);
            }
        }

        $dataDir = $scriptsRoot . 'data/';
        if (is_dir($dataDir)) {
            foreach (['json', 'txt', 'md'] as $ext) {
                foreach (glob($dataDir . '*.' . $ext) ?: [] as $file) {
                    if (!is_file($file)) {
                        continue;
                    }
                    $rel = 'data/' . basename($file);
                    $paths[$rel] = $rel;
                }
            }
        }

        $list = array_values($paths);
        sort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }
}

if (!function_exists('itm_script_catalog_documentation_files_kind')) {
    function itm_script_catalog_documentation_files_kind(string $relativePath): string
    {
        $ext = strtolower((string)pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($ext === 'md') {
            return 'markdown';
        }

        return 'info';
    }
}

if (!function_exists('itm_script_catalog_documentation_files_blurb')) {
    function itm_script_catalog_documentation_files_blurb(string $relativePath): string
    {
        $blurbs = [
            'SCRIPTS.md' => 'Development standards for the scripts directory (catalog, newlines, security, retention).',
            'SCRIPTS_TEST_MATRIX.md' => 'Full catalog verification matrix: tiers 0–5, runner coverage map, Tier 5 exclusion list, destroy→document→fresh db/ split bundle protocol.',
            'AGENT_NOTES.md' => 'Scripts folder agent notes: catalog layout, pitfalls, and maintenance contracts.',
            'data/AGENT_NOTES.md' => 'scripts/data static files: allowlists, baselines, reviewed exception manifests, matrix logs.',
            'data/script_catalog_tags.json' => 'Computed catalog tag manifest for scripts/scripts.php rows (apply/check drift gate).',
            'data/scripts_errors.txt' => 'Latest safe scripts matrix run report (Passed / Skipped / Failures lists).',
            'data/scripts-matrix-destroy-log.md' => 'Append-only destroy→fresh-clone log for blanket scripts/* verification.',
        ];

        if (isset($blurbs[$relativePath])) {
            return $blurbs[$relativePath];
        }

        $name = pathinfo($relativePath, PATHINFO_FILENAME);
        $name = str_replace(['_', '-'], ' ', (string)$name);

        return 'Static scripts data file: <code>' . htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8') . '</code>.';
    }
}

if (!function_exists('itm_script_catalog_documentation_files_render_row')) {
    function itm_script_catalog_documentation_files_render_row(string $relativePath): string
    {
        $kind = itm_script_catalog_documentation_files_kind($relativePath);
        $tags = $kind === 'markdown' ? ['Markdown'] : ['Info'];
        $dataTags = htmlspecialchars(implode(' ', $tags), ENT_QUOTES, 'UTF-8');
        $href = htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8');
        $blurb = itm_script_catalog_documentation_files_blurb($relativePath);

        if ($kind === 'markdown') {
            $access = '<span class="scripts-access-badges"><span class="scripts-badge scripts-badge-md">Markdown</span></span>';
            $tagBadges = '<span class="scripts-badge scripts-badge-tag" data-tag-kind="markdown">Markdown</span>';
        } else {
            $access = '<span class="scripts-access-badges"><span class="scripts-badge scripts-badge-info">Info</span></span>';
            $tagBadges = '<span class="scripts-badge scripts-badge-tag" data-tag-kind="info">Info</span>';
        }

        $how = 'Open <code>scripts/' . $href . '</code> in the repository or IDE.';

        return '                <tr data-tags="' . $dataTags . '" data-catalog-doc-file="1">'
            . "\n"
            . '                    <td><a href="' . $href . '" target="_blank" rel="nofollow noreferrer">' . $label . '</a></td>'
            . "\n"
            . '                    <td class="scripts-access-cell">' . $access . '</td>'
            . "\n"
            . '                    <td class="scripts-tags-cell"><span class="scripts-tag-badges">' . $tagBadges . '</span></td>'
            . "\n"
            . '                    <td>' . $blurb . '</td>'
            . "\n"
            . '                    <td>' . $how . '</td>'
            . "\n"
            . '                </tr>';
    }
}

if (!function_exists('itm_script_catalog_documentation_files_patch_catalog')) {
    /**
     * @return array{html: string, added: array<int, string>, skipped: array<int, string>}
     */
    function itm_script_catalog_documentation_files_patch_catalog(string $catalogHtml, string $scriptsRoot): array
    {
        $begin = '<!-- ITM_CATALOG_DATA_DOCS_BEGIN -->';
        $end = '<!-- ITM_CATALOG_DATA_DOCS_END -->';
        $discover = itm_script_catalog_documentation_files_discover($scriptsRoot);
        $added = [];
        $skipped = [];

        $existingHrefs = [];
        if (preg_match_all('/<tr\b[^>]*>.*?<\/tr>/is', $catalogHtml, $rowMatches)) {
            foreach ($rowMatches[0] as $rowHtml) {
                if (!preg_match('/href=["\']([^"\']+)["\']/i', $rowHtml, $hrefMatch)) {
                    continue;
                }
                $href = str_replace('\\', '/', trim($hrefMatch[1]));
                if ($href !== '' && !preg_match('#^https?://#i', $href)) {
                    $existingHrefs[strtolower($href)] = true;
                }
            }
        }

        $rows = [];
        foreach ($discover as $relativePath) {
            $key = strtolower($relativePath);
            if (isset($existingHrefs[$key])) {
                $skipped[] = $relativePath;
                continue;
            }
            $rows[] = itm_script_catalog_documentation_files_render_row($relativePath);
            $added[] = $relativePath;
        }

        $block = $begin . "\n" . implode("\n", $rows) . "\n                " . $end;

        if (strpos($catalogHtml, $begin) !== false && strpos($catalogHtml, $end) !== false) {
            $patched = preg_replace(
                '/' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '/s',
                $block,
                $catalogHtml,
                1
            );
            if (!is_string($patched)) {
                $patched = $catalogHtml;
            }
        } else {
            $docsStart = strpos($catalogHtml, '<div class="scripts-card" id="docs">');
            $docsEnd = strpos($catalogHtml, '<div class="scripts-card" id="browser">');
            if ($docsStart === false || $docsEnd === false || $docsEnd <= $docsStart) {
                $patched = $catalogHtml;
            } else {
                $docsSection = substr($catalogHtml, $docsStart, $docsEnd - $docsStart);
                $tbodyClose = strrpos($docsSection, '</tbody>');
                if ($tbodyClose === false) {
                    $patched = $catalogHtml;
                } else {
                    $insertAt = $docsStart + $tbodyClose;
                    $patched = substr($catalogHtml, 0, $insertAt)
                        . "\n" . $block . "\n            "
                        . substr($catalogHtml, $insertAt);
                }
            }
        }

        return [
            'html' => $patched,
            'added' => $added,
            'skipped' => $skipped,
        ];
    }
}
