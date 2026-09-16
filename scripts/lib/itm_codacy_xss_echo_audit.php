<?php
/**
 * Codacy-aligned static audit helpers for risky user-input echo patterns in module PHP.
 *
 * Why: Codacy Static Code Analysis flags short-echo search output and
 * echo sanitize(http_build_query(...)) inside href attributes as XSS even when
 * sanitize() wraps the value.
 */

declare(strict_types=1);

if (!function_exists('itm_codacy_xss_echo_line_exempt')) {
    function itm_codacy_xss_echo_line_exempt(string $line): bool
    {
        if (strpos($line, 'itm-codacy-xss-exempt:') !== false) {
            return true;
        }

        // Canonical browser title pattern — not a user-input search echo.
        if (preg_match('/<title>\s*<\?=\s*sanitize\s*\(\s*\$crud_title\s*\)/i', $line) === 1) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_codacy_xss_echo_audit_line')) {
    /**
     * @return array<int, array{rule:string,message:string}>
     */
    function itm_codacy_xss_echo_audit_line(string $line): array
    {
        if (itm_codacy_xss_echo_line_exempt($line)) {
            return [];
        }

        $violations = [];

        if (preg_match('/<\?=/', $line) === 1
            && preg_match('/sanitize\s*\(\s*\$(search(Raw)?)\b/i', $line) === 1
            && preg_match('/(value\s*=|href\s*=|<strong)/i', $line) === 1
        ) {
            $violations[] = [
                'rule' => 'short_echo_search_attr',
                'message' => 'Use <?php echo sanitize($search…); ?> instead of <?= in value/href/<strong> (Codacy XSS)',
            ];
        }

        if (preg_match('/href\s*=/i', $line) === 1
            && preg_match('/echo\s+sanitize\s*\(\s*http_build_query\s*\(/i', $line) === 1
        ) {
            $violations[] = [
                'rule' => 'echo_sanitize_http_build_query_href',
                'message' => 'Pre-assign href with htmlspecialchars(..., ENT_QUOTES, UTF-8) after http_build_query()',
            ];
        }

        return $violations;
    }
}

if (!function_exists('itm_codacy_xss_echo_collect_violations')) {
    /**
     * @param array<int, string> $scanRelativeDirs
     * @return array<int, string>
     */
    function itm_codacy_xss_echo_collect_violations(string $repoRoot, array $scanRelativeDirs = ['modules']): array
    {
        $violations = [];
        $root = rtrim($repoRoot, '/\\');

        foreach ($scanRelativeDirs as $relativeDir) {
            $base = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
            if (!is_dir($base)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
                    continue;
                }

                $path = $fileInfo->getPathname();
                $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
                $rel = str_replace('\\', '/', $rel);
                $content = file_get_contents($path);
                if ($content === false) {
                    continue;
                }

                $lines = preg_split('/\R/u', $content) ?: [];
                foreach ($lines as $lineNum => $line) {
                    foreach (itm_codacy_xss_echo_audit_line($line) as $hit) {
                        $violations[] = $rel . ':' . ($lineNum + 1) . ' [' . $hit['rule'] . '] ' . $hit['message'];
                    }
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
