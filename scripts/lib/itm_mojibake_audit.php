<?php
/**
 * Shared UTF-8 / mojibake static audit helpers for repository source scans.
 *
 * Why: UTF-8 emoji and punctuation saved or re-opened as Latin-1/Windows-1252 leaves
 * stable garbage substrings (âœ…, ðŸ'¾, Ã©) that render wrong in the browser.
 */

if (!function_exists('itm_mojibake_default_scan_roots')) {
    /**
     * @return string[] Relative paths from repository root.
     */
    function itm_mojibake_default_scan_roots(): array
    {
        return ['modules', 'includes', 'scripts', 'js', 'css', 'config'];
    }
}

if (!function_exists('itm_mojibake_exclude_path_fragments')) {
    /**
     * @return string[]
     */
    function itm_mojibake_exclude_path_fragments(): array
    {
        return [
            DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'coverage' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'qa-reports' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR,
        ];
    }
}

if (!function_exists('itm_mojibake_default_extensions')) {
    /**
     * @return string[]
     */
    function itm_mojibake_default_extensions(): array
    {
        return ['php', 'js', 'html', 'css', 'md', 'sql'];
    }
}

if (!function_exists('itm_mojibake_skip_relative_files')) {
    /**
     * Files that intentionally document mojibake examples.
     *
     * @return string[]
     */
    function itm_mojibake_skip_relative_files(): array
    {
        return [
            'scripts/lib/itm_mojibake_audit.php',
            'scripts/verify_source_utf8_mojibake.php',
            'AGENTS.md',
        ];
    }
}

if (!function_exists('itm_mojibake_known_signatures')) {
    /**
     * Literal mojibake substrings → intended UTF-8 (for reports and optional repair).
     *
     * @return array<int, array{needle:string,label:string,fix:string}>
     */
    function itm_mojibake_known_signatures(): array
    {
        return [
            ['needle' => 'âœ…', 'label' => 'check_mark', 'fix' => '✅'],
            ['needle' => 'âŒ', 'label' => 'cross_mark', 'fix' => '❌'],
            ['needle' => "âœ\xC2\x8Fï¸\xC2\x8F", 'label' => 'pencil_emoji', 'fix' => '✏️'],
            ['needle' => 'âž•', 'label' => 'plus_emoji', 'fix' => '➕'],
            ['needle' => 'ðŸ”Ž', 'label' => 'view_emoji', 'fix' => '🔎'],
            ['needle' => 'ðŸ’¾', 'label' => 'save_emoji', 'fix' => '💾'],
            ['needle' => 'ðŸ”™', 'label' => 'back_emoji', 'fix' => '🔙'],
            ['needle' => 'ðŸ—‘ï¸', 'label' => 'delete_emoji', 'fix' => '🗑️'],
            ['needle' => "🗑️\xC2\x8F", 'label' => 'delete_emoji_trailing', 'fix' => '🗑️'],
            ['needle' => 'â—€ï¸', 'label' => 'previous_arrow', 'fix' => '◀️'],
            ['needle' => 'â–¶ï¸', 'label' => 'next_arrow', 'fix' => '▶️'],
            ['needle' => 'â€”', 'label' => 'em_dash', 'fix' => '—'],
            ['needle' => 'â€¦', 'label' => 'ellipsis', 'fix' => '…'],
            ['needle' => 'Ã©', 'label' => 'e_acute', 'fix' => 'é'],
            ['needle' => 'ðŸ§©', 'label' => 'puzzle_emoji', 'fix' => '🧩'],
        ];
    }
}

if (!function_exists('itm_mojibake_collect_files')) {
    /**
     * @param string[] $roots Relative repo roots
     * @param string[] $extensions
     * @param string[] $excludeFragments
     * @return string[] Absolute file paths
     */
    function itm_mojibake_collect_files(string $repoRoot, array $roots, array $extensions, array $excludeFragments): array
    {
        $files = [];
        $repoRoot = rtrim($repoRoot, '/\\') . DIRECTORY_SEPARATOR;

        foreach ($roots as $relativeRoot) {
            $base = $repoRoot . str_replace('/', DIRECTORY_SEPARATOR, $relativeRoot);
            if (!is_dir($base)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }
                $path = $fileInfo->getPathname();
                foreach ($excludeFragments as $fragment) {
                    if (strpos($path, $fragment) !== false) {
                        continue 2;
                    }
                }
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensions, true)) {
                    continue;
                }
                $files[] = $path;
            }
        }

        sort($files, SORT_STRING);
        return $files;
    }
}

if (!function_exists('itm_mojibake_scan_file')) {
    /**
     * @return array<int, array{file:string,line:int,code:string,detail:string}>
     */
    function itm_mojibake_scan_file(string $absolutePath, string $repoRoot): array
    {
        $violations = [];
        $rel = str_replace(rtrim($repoRoot, '/\\') . DIRECTORY_SEPARATOR, '', $absolutePath);
        $relNorm = str_replace('\\', '/', $rel);

        if (in_array($relNorm, itm_mojibake_skip_relative_files(), true)) {
            return [];
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return [[
                'file' => $relNorm,
                'line' => 0,
                'code' => 'unreadable',
                'detail' => 'unable to read file',
            ]];
        }

        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $violations[] = [
                'file' => $relNorm,
                'line' => 1,
                'code' => 'utf8_bom',
                'detail' => 'UTF-8 BOM present (forbidden in tracked source except qa-reports artifacts)',
            ];
        }

        if (function_exists('mb_check_encoding') && !mb_check_encoding($content, 'UTF-8')) {
            $violations[] = [
                'file' => $relNorm,
                'line' => 0,
                'code' => 'invalid_utf8',
                'detail' => 'file is not valid UTF-8',
            ];
        }

        $lines = preg_split('/\R/u', $content) ?: [];
        $signatures = itm_mojibake_known_signatures();

        foreach ($lines as $lineNum => $line) {
            $humanLine = $lineNum + 1;
            foreach ($signatures as $signature) {
                $needle = (string)$signature['needle'];
                if ($needle !== '' && strpos($line, $needle) !== false) {
                    $violations[] = [
                        'file' => $relNorm,
                        'line' => $humanLine,
                        'code' => 'mojibake:' . (string)$signature['label'],
                        'detail' => 'found ' . $needle . ' (expected ' . (string)$signature['fix'] . ')',
                    ];
                }
            }

            // Generic Latin-1 misread of UTF-8 emoji/punctuation (ðŸ / â€ / Ã + continuation).
            if (preg_match('/(?:ðŸ[\x80-\xBF]{2,}|â€[\x80-\xBF]|Ã[\x80-\xBF]{1,2})/u', $line, $m)) {
                $already = false;
                foreach ($signatures as $signature) {
                    if (strpos($line, (string)$signature['needle']) !== false) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $violations[] = [
                        'file' => $relNorm,
                        'line' => $humanLine,
                        'code' => 'mojibake:generic',
                        'detail' => 'suspect sequence ' . $m[0],
                    ];
                }
            }
        }

        return $violations;
    }
}

if (!function_exists('itm_mojibake_scan_repository')) {
    /**
     * @return array{files_scanned:int,violations:array<int, array{file:string,line:int,code:string,detail:string}>}
     */
    function itm_mojibake_scan_repository(string $repoRoot, ?array $roots = null, ?array $extensions = null): array
    {
        $roots = $roots ?? itm_mojibake_default_scan_roots();
        $extensions = $extensions ?? itm_mojibake_default_extensions();
        $files = itm_mojibake_collect_files(
            $repoRoot,
            $roots,
            $extensions,
            itm_mojibake_exclude_path_fragments()
        );

        $violations = [];
        foreach ($files as $path) {
            $violations = array_merge($violations, itm_mojibake_scan_file($path, $repoRoot));
        }

        return [
            'files_scanned' => count($files),
            'violations' => $violations,
        ];
    }
}
