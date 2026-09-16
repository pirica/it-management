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
            'scripts/fix_source_utf8_mojibake.php',
            'scripts/apply_utf8_mojibake_fix.php',
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

if (!function_exists('itm_mojibake_repair_content')) {
    /**
     * Replace known mojibake literals with intended UTF-8 and strip a leading BOM.
     *
     * @return array{content:string,replacement_count:int,stripped_bom:bool}
     */
    function itm_mojibake_repair_content(string $content): array
    {
        $replacementCount = 0;
        $strippedBom = false;

        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
            $strippedBom = true;
            $replacementCount++;
        }

        foreach (itm_mojibake_known_signatures() as $signature) {
            $needle = (string)$signature['needle'];
            if ($needle === '') {
                continue;
            }
            $count = 0;
            $content = str_replace($needle, (string)$signature['fix'], $content, $count);
            if ($count > 0) {
                $replacementCount += $count;
            }
        }

        return [
            'content' => $content,
            'replacement_count' => $replacementCount,
            'stripped_bom' => $strippedBom,
        ];
    }
}

if (!function_exists('itm_mojibake_normalize_repo_relative_path')) {
    function itm_mojibake_normalize_repo_relative_path(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }
}

if (!function_exists('itm_mojibake_collect_repair_candidates')) {
    /**
     * @return array<int, array{file:string,replacement_count:int,violation_count:int}>
     */
    function itm_mojibake_collect_repair_candidates(string $repoRoot, ?array $roots = null): array
    {
        $scan = itm_mojibake_scan_repository($repoRoot, $roots);
        $violations = is_array($scan['violations'] ?? null) ? $scan['violations'] : [];
        $violationsByFile = [];
        foreach ($violations as $row) {
            $file = itm_mojibake_normalize_repo_relative_path((string)($row['file'] ?? ''));
            if ($file === '') {
                continue;
            }
            $violationsByFile[$file] = ($violationsByFile[$file] ?? 0) + 1;
        }

        $files = itm_mojibake_collect_files(
            $repoRoot,
            $roots ?? itm_mojibake_default_scan_roots(),
            itm_mojibake_default_extensions(),
            itm_mojibake_exclude_path_fragments()
        );

        $candidates = [];
        foreach ($files as $absolutePath) {
            $rel = itm_mojibake_normalize_repo_relative_path(
                str_replace(rtrim($repoRoot, '/\\') . DIRECTORY_SEPARATOR, '', $absolutePath)
            );
            if ($rel === '' || in_array($rel, itm_mojibake_skip_relative_files(), true)) {
                continue;
            }

            $original = file_get_contents($absolutePath);
            if ($original === false) {
                continue;
            }

            $repaired = itm_mojibake_repair_content($original);
            $replacementCount = (int)($repaired['replacement_count'] ?? 0);
            if ($repaired['content'] === $original) {
                continue;
            }

            $candidates[] = [
                'file' => $rel,
                'replacement_count' => $replacementCount,
                'violation_count' => (int)($violationsByFile[$rel] ?? 0),
            ];
        }

        usort($candidates, static function (array $a, array $b): int {
            $cmp = ($b['replacement_count'] ?? 0) <=> ($a['replacement_count'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string)($a['file'] ?? ''), (string)($b['file'] ?? ''));
        });

        return $candidates;
    }
}

if (!function_exists('itm_mojibake_repair_repo_files')) {
    /**
     * @param string[] $relativeFiles Repo-relative paths
     * @return array{changed:array<int,string>,skipped:array<int,string>,preview:array<int,string>}
     */
    function itm_mojibake_repair_repo_files(string $repoRoot, array $relativeFiles, bool $apply): array
    {
        $repoRoot = rtrim($repoRoot, '/\\') . DIRECTORY_SEPARATOR;
        $changed = [];
        $skipped = [];
        $preview = [];

        foreach ($relativeFiles as $relativeFile) {
            $rel = itm_mojibake_normalize_repo_relative_path($relativeFile);
            if ($rel === '' || in_array($rel, itm_mojibake_skip_relative_files(), true)) {
                $skipped[] = $rel . ' (exempt)';
                continue;
            }

            $absolutePath = $repoRoot . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($absolutePath)) {
                $skipped[] = $rel . ' (missing)';
                continue;
            }

            $original = file_get_contents($absolutePath);
            if ($original === false) {
                $skipped[] = $rel . ' (unreadable)';
                continue;
            }

            $repaired = itm_mojibake_repair_content($original);
            $hits = (int)($repaired['replacement_count'] ?? 0);
            if ($repaired['content'] === $original) {
                continue;
            }

            $label = $rel . ' (' . $hits . ' replacement(s))';
            if ($apply) {
                if (file_put_contents($absolutePath, $repaired['content']) === false) {
                    $skipped[] = $rel . ' (write failed)';
                    continue;
                }
                $changed[] = $label;
            } else {
                $preview[] = $label;
            }
        }

        return [
            'changed' => $changed,
            'skipped' => $skipped,
            'preview' => $preview,
        ];
    }
}
