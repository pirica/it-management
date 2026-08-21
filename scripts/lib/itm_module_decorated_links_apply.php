<?php
/**
 * Apply itm-plain-link / sort-header inherit styling to decorated module anchors.
 */

if (!function_exists('itm_module_decorated_links_apply_line_is_exempt')) {
    function itm_module_decorated_links_apply_line_is_exempt($line)
    {
        $line = (string)$line;
        if (strpos($line, 'itm-decorated-link-exempt') !== false) {
            return true;
        }
        if (preg_match('/<DT>\s*<A\s+HREF/i', $line)) {
            return true;
        }
        if (strpos($line, 'itm-plain-link') !== false) {
            return true;
        }
        if (preg_match('/text-decoration\s*:\s*none/i', $line) && preg_match('/color\s*:\s*inherit/i', $line)) {
            return true;
        }
        if (preg_match('/\bclass\s*=\s*["\'][^"\']*\bbtn\b/i', $line)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('itm_module_decorated_links_apply_line_wants_sort_style')) {
    function itm_module_decorated_links_apply_line_wants_sort_style($line)
    {
        $line = (string)$line;
        if (preg_match('/bdays_sort_url|bgr_sort_url|resign_sort_url|hb_planning_sort_href|rackPlannerSortQueryBase|ssDbSortUrl/i', $line)) {
            return true;
        }
        if (preg_match('/oprSearchHitsListUrl|itm_schema_migrations_build_query/i', $line) && preg_match('/sort/i', $line)) {
            return true;
        }
        if (preg_match('/<th\b/i', $line) && preg_match('/<a\s/i', $line) && preg_match('/sort|Sort|&dir=|\?dir=|_dir/i', $line)) {
            return true;
        }
        if (preg_match('/\bsort\s*=>|\[\'sort\'|"sort"|&sort=|\?sort=/i', $line)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('itm_module_decorated_links_apply_patch_anchor_opening')) {
    function itm_module_decorated_links_apply_patch_anchor_opening($attrs, $useSortStyle)
    {
        $attrs = (string)$attrs;
        if (preg_match('/text-decoration\s*:\s*none/i', $attrs) && preg_match('/color\s*:\s*inherit/i', $attrs)) {
            return $attrs;
        }
        if (strpos($attrs, 'itm-plain-link') !== false) {
            return $attrs;
        }

        if ($useSortStyle) {
            if (preg_match('/\bstyle\s*=\s*"/i', $attrs)) {
                return preg_replace('/\bstyle\s*=\s*"/i', 'style="text-decoration:none;color:inherit;', $attrs, 1);
            }
            if (preg_match("/\bstyle\s*=\s*'/i", $attrs)) {
                return preg_replace("/\bstyle\s*=\s*'/i", "style='text-decoration:none;color:inherit;", $attrs, 1);
            }
            return ' style="text-decoration:none;color:inherit;"' . $attrs;
        }

        if (preg_match('/class\s*=\s*"\s*\.\s*\$/', $attrs) || preg_match("/class\s*=\s*'\s*\.\s*\$/", $attrs)) {
            return preg_replace("/(\bclass\s*=\s*['\"])\s*\.\s*(\$[a-zA-Z_][\w]*)\s*\.\s*(['\"])/", '$1 . $2 . \' itm-plain-link\' . $3', $attrs, 1)
                ?: preg_replace('/(\bclass\s*=\s*["\'])([^"\']*)(["\'])/', '$1$2 itm-plain-link$3', $attrs, 1);
        }
        if (preg_match('/\bclass\s*=\s*"([^"]*)"/i', $attrs, $m)) {
            $merged = trim($m[1] . ' itm-plain-link');
            return preg_replace('/\bclass\s*=\s*"[^"]*"/i', 'class="' . $merged . '"', $attrs, 1);
        }
        if (preg_match("/\bclass\s*=\s*'([^']*)'/i", $attrs, $m)) {
            $merged = trim($m[1] . ' itm-plain-link');
            return preg_replace("/\bclass\s*=\s*'[^']*'/i", "class='" . $merged . "'", $attrs, 1);
        }
        return ' class="itm-plain-link"' . $attrs;
    }
}

if (!function_exists('itm_module_decorated_links_apply_fix_line')) {
    function itm_module_decorated_links_apply_fix_line($line)
    {
        $line = (string)$line;
        if (itm_module_decorated_links_apply_line_is_exempt($line)) {
            return $line;
        }
        if (!preg_match('/<a\s/i', $line)) {
            return $line;
        }

        $useSortStyle = itm_module_decorated_links_apply_line_wants_sort_style($line);
        $patched = preg_replace_callback(
            '/<a(\s[^>]*?)>/i',
            static function (array $m) use ($useSortStyle): string {
                return '<a' . itm_module_decorated_links_apply_patch_anchor_opening($m[1], $useSortStyle) . '>';
            },
            $line
        );
        return is_string($patched) ? $patched : $line;
    }
}

if (!function_exists('itm_module_decorated_links_apply_fix_return_strings')) {
    function itm_module_decorated_links_apply_fix_return_strings($content)
    {
        $patterns = [
            "/return\s+'<a\s+href=/i" => "return '<a class=\"itm-plain-link\" href=",
            '/return\s+"<a\s+href=/i' => 'return "<a class=\"itm-plain-link\" href=',
            "/return\s+'<a\s+href\s*=/i" => "return '<a class=\"itm-plain-link\" href=",
        ];
        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        return $content;
    }
}

if (!function_exists('itm_module_decorated_links_apply_file')) {
    /**
     * @param array<int, int> $lineNumbers 1-based lines to patch (0 = whole-file string pass only)
     * @return array{changed:bool,lines:array<int,string>}
     */
    function itm_module_decorated_links_apply_file($filePath, array $lineNumbers, $write)
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return ['changed' => false, 'lines' => []];
        }
        $original = $content;
        $content = itm_module_decorated_links_apply_fix_return_strings($content);

        $lines = preg_split('/\R/', $content);
        if (!is_array($lines)) {
            return ['changed' => false, 'lines' => []];
        }

        $touched = [];
        $uniqueLines = array_values(array_unique(array_filter(array_map('intval', $lineNumbers), static function ($n) {
            return $n > 0;
        })));
        foreach ($uniqueLines as $lineNo) {
            $idx = $lineNo - 1;
            if (!isset($lines[$idx])) {
                continue;
            }
            $fixed = itm_module_decorated_links_apply_fix_line($lines[$idx]);
            if ($fixed !== $lines[$idx]) {
                $lines[$idx] = $fixed;
                $touched[] = $lineNo;
            }
        }

        $newContent = implode("\n", $lines);
        if (strpos($original, "\r\n") !== false) {
            $newContent = str_replace("\n", "\r\n", $newContent);
        }

        $changed = $newContent !== $original;
        if ($changed && $write) {
            file_put_contents($filePath, $newContent);
        }
        return ['changed' => $changed, 'lines' => $touched];
    }
}

if (!function_exists('itm_module_decorated_links_apply_all')) {
    /**
     * @return array{changed_files:array<int,string>,skipped:array<int,string>}
     */
    function itm_module_decorated_links_apply_all($repoRoot, $write, $moduleSlug = '')
    {
        require_once __DIR__ . '/itm_module_decorated_links_report.php';
        $rows = itm_module_decorated_links_collect_report($repoRoot, [
            'module_slug' => $moduleSlug,
        ]);

        $linesByFile = [];
        foreach ($rows as $row) {
            $rel = (string)($row['rel_file'] ?? '');
            $line = (int)($row['line'] ?? 0);
            if ($rel === '') {
                continue;
            }
            if (!isset($linesByFile[$rel])) {
                $linesByFile[$rel] = [];
            }
            if ($line > 0) {
                $linesByFile[$rel][] = $line;
            } else {
                $linesByFile[$rel][] = 0;
            }
        }

        $repoRoot = rtrim((string)$repoRoot, '/\\');
        $changedFiles = [];
        foreach ($linesByFile as $relFile => $lineNumbers) {
            $abs = $repoRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relFile);
            if (!is_file($abs)) {
                continue;
            }
            $result = itm_module_decorated_links_apply_file($abs, $lineNumbers, $write);
            if ($result['changed']) {
                $changedFiles[$relFile] = $result['lines'];
            }
        }

        ksort($changedFiles);
        return ['changed_files' => $changedFiles, 'skipped' => []];
    }
}
