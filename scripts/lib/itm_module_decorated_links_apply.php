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
        if (preg_match('/(\$\w+\s*\.=\s*"|echo\s+")[^;]*<a\s/i', $line)) {
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

        // PHP echo inside class="..." — never use [^"]*; prepend token after opening quote only.
        if (preg_match('/\bclass\s*=\s*"<\?php/i', $attrs)) {
            return preg_replace('/\bclass\s*=\s*"/i', 'class="itm-plain-link ', $attrs, 1);
        }
        if (preg_match("/\bclass\s*=\s*'<\?php/i", $attrs)) {
            return preg_replace("/\bclass\s*=\s*'/i", "class='itm-plain-link ", $attrs, 1);
        }

        if (preg_match('/class\s*=\s*"\s*\.\s*\$/', $attrs) || preg_match("/class\s*=\s*'\s*\.\s*\$/", $attrs)) {
            $patched = preg_replace("/(\bclass\s*=\s*['\"])\s*\.\s*(\$[a-zA-Z_][\w]*)\s*\.\s*(['\"])/", '$1 . $2 . \' itm-plain-link\' . $3', $attrs, 1);
            if (is_string($patched) && $patched !== $attrs) {
                return $patched;
            }
        }

        if (preg_match('/\bclass\s*=\s*"([a-zA-Z0-9_\- ]*)"/i', $attrs, $m)) {
            $merged = trim($m[1] . ' itm-plain-link');
            return preg_replace('/\bclass\s*=\s*"[a-zA-Z0-9_\- ]*"/i', 'class="' . $merged . '"', $attrs, 1);
        }
        if (preg_match("/\bclass\s*=\s*'([a-zA-Z0-9_\- ]*)'/i", $attrs, $m)) {
            $merged = trim($m[1] . ' itm-plain-link');
            return preg_replace("/\bclass\s*=\s*'[a-zA-Z0-9_\- ]*'/i", "class='" . $merged . "'", $attrs, 1);
        }

        if (preg_match('/\bhref\s*=/i', $attrs)) {
            return preg_replace('/\bhref\s*=/i', 'class="itm-plain-link" href=', $attrs, 1);
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

        // Why: naive [^>]* anchor parsing breaks on PHP closing tags and ternary > inside attributes.
        if ($useSortStyle) {
            if (preg_match('/\bstyle\s*=\s*"[^"]*text-decoration\s*:\s*none/i', $line)) {
                return $line;
            }
            if (preg_match('/<a\s([^>]*)\bstyle\s*=\s*"/i', $line)) {
                return preg_replace('/\bstyle\s*=\s*"/i', 'style="text-decoration:none;color:inherit;', $line, 1);
            }
            return preg_replace('/<a\s+/i', '<a style="text-decoration:none;color:inherit;" ', $line, 1);
        }

        if (strpos($line, 'itm-plain-link') === false) {
            if (preg_match('/\bclass\s*=\s*"<\?php/i', $line)) {
                $line = preg_replace('/\bclass\s*=\s*"/i', 'class="itm-plain-link ', $line, 1);
            } elseif (preg_match('/\bclass\s*=\s*"\s*\.\s*\$/', $line)) {
                $line = preg_replace("/(\bclass\s*=\s*['\"])\s*\.\s*(\$[a-zA-Z_][\w]*)\s*\.\s*(['\"])/", '$1 . $2 . \' itm-plain-link\' . $3', $line, 1);
            } elseif (preg_match('/\bclass\s*=\s*"[a-zA-Z0-9_\- ]*"/i', $line)) {
                $line = preg_replace('/\bclass\s*=\s*"/i', 'class="itm-plain-link ', $line, 1);
            } else {
                $line = preg_replace('/<a\s+/i', '<a class="itm-plain-link" ', $line, 1);
            }
        }

        // Repair duplicate class attributes from older apply runs.
        $line = preg_replace(
            '/<a class="itm-plain-link" href="([^"]*)" class="/i',
            '<a href="$1" class="itm-plain-link ',
            $line
        );

        return $line;
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

if (!function_exists('itm_module_decorated_links_apply_sweep_content')) {
  function itm_module_decorated_links_apply_sweep_content($content)
    {
        require_once __DIR__ . '/itm_module_decorated_links_report.php';
        $result = '';
        $offset = 0;
        $length = strlen($content);
        while ($offset < $length) {
            $nextLf = strpos($content, "\n", $offset);
            if ($nextLf === false) {
                $line = substr($content, $offset);
                $break = '';
            } elseif ($nextLf > $offset && $content[$nextLf - 1] === "\r") {
                $line = substr($content, $offset, $nextLf - 1 - $offset);
                $break = "\r\n";
            } else {
                $line = substr($content, $offset, $nextLf - $offset);
                $break = "\n";
            }

            if (itm_module_decorated_links_line_is_decorated_anchor($line)) {
                $line = itm_module_decorated_links_apply_fix_line($line);
            }

            $result .= $line . $break;
            $offset = $nextLf === false ? $length : $nextLf + 1;
        }
        return $result;
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
        $content = itm_module_decorated_links_apply_sweep_content($content);

        $uniqueLines = array_values(array_unique(array_filter(array_map('intval', $lineNumbers), static function ($n) {
            return $n > 0;
        })));
        if ($uniqueLines === []) {
            $changed = $content !== $original;
            if ($changed && $write) {
                file_put_contents($filePath, $content);
            }
            return ['changed' => $changed, 'lines' => []];
        }

        $want = array_fill_keys($uniqueLines, true);
        $touched = [];
        $result = '';
        $offset = 0;
        $length = strlen($content);
        $lineNo = 1;

        while ($offset < $length) {
            $nextLf = strpos($content, "\n", $offset);
            if ($nextLf === false) {
                $line = substr($content, $offset);
                $break = '';
            } elseif ($nextLf > $offset && $content[$nextLf - 1] === "\r") {
                $line = substr($content, $offset, $nextLf - 1 - $offset);
                $break = "\r\n";
            } else {
                $line = substr($content, $offset, $nextLf - $offset);
                $break = "\n";
            }

            if (isset($want[$lineNo])) {
                $fixed = itm_module_decorated_links_apply_fix_line($line);
                if ($fixed !== $line) {
                    $line = $fixed;
                    $touched[] = $lineNo;
                }
            }

            $result .= $line . $break;
            $offset = $nextLf === false ? $length : $nextLf + 1;
            $lineNo++;
        }

        $changed = $result !== $original;
        if ($changed && $write) {
            file_put_contents($filePath, $result);
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
