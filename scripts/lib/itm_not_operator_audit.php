<?php
/**
 * Codacy-aligned static audit helpers for unary ! on variables.
 *
 * Why: Codacy flags "Operator ! prohibited; use === FALSE instead" when a
 * variable is negated with ! instead of a strict false comparison. Function
 * guards such as !is_array() are intentionally excluded.
 */

declare(strict_types=1);

if (!function_exists('itm_not_operator_line_exempt')) {
    function itm_not_operator_line_exempt(string $line): bool
    {
        if (strpos($line, 'itm-not-operator-exempt:') !== false) {
            return true;
        }

        $trimmed = ltrim($line);
        if ($trimmed === '' || strpos($trimmed, '//') === 0 || strpos($trimmed, '*') === 0 || strpos($trimmed, '#') === 0) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_not_operator_audit_line')) {
    /**
     * @return array<int, array{rule:string,message:string}>
     */
    function itm_not_operator_audit_line(string $line): array
    {
        if (itm_not_operator_line_exempt($line)) {
            return [];
        }

        // Unary ! on $variable (not !==, !=, or !function_name()).
        if (preg_match('/(?<![=!])\!(?!=)\s*\$[a-zA-Z_][a-zA-Z0-9_]*(?:\s*(?:->|\[|\)|,|;))?/u', $line) !== 1) {
            return [];
        }

        return [
            [
                'rule' => 'unary_not_on_variable',
                'message' => 'Unary ! on $variable — prefer strict === false (or === null) when that is the intent; use itm-not-operator-exempt: for intentional falsy checks',
            ],
        ];
    }
}

if (!function_exists('itm_not_operator_collect_violations')) {
    /**
     * @param array<int, string> $scanRelativeDirs
     * @return array<int, string>
     */
    function itm_not_operator_collect_violations(string $repoRoot, array $scanRelativeDirs = ['modules', 'includes', 'config']): array
    {
        $violations = [];
        $root = rtrim($repoRoot, '/\\');
        $skipDirNames = ['vendor', 'node_modules', 'coverage', 'qa-reports'];

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
                $pathNorm = str_replace('\\', '/', $path);
                $skip = false;
                foreach ($skipDirNames as $skipDir) {
                    if (strpos($pathNorm, '/' . $skipDir . '/') !== false) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue;
                }

                $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
                $rel = str_replace('\\', '/', $rel);
                $content = file_get_contents($path);
                if ($content === false) {
                    continue;
                }

                $lines = preg_split('/\R/u', $content) ?: [];
                foreach ($lines as $lineNum => $line) {
                    foreach (itm_not_operator_audit_line($line) as $hit) {
                        $violations[] = $rel . ':' . ($lineNum + 1) . ' [' . $hit['rule'] . '] ' . $hit['message'];
                    }
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
