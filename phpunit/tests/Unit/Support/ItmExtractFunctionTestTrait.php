<?php

/**
 * Load a single function from a production PHP file without eval().
 *
 * Why: Module index/api files are not safe to require wholesale in PHPUnit; extracting
 * one function into a temp file keeps tests focused and avoids eval().
 */
trait ItmExtractFunctionTestTrait
{
    /**
     * @param string|null $pattern Optional legacy regex; omit to extract by brace-balanced function name.
     */
    protected function requireExtractedFunction(string $sourceFile, string $functionName, ?string $pattern = null): void
    {
        if (function_exists($functionName)) {
            return;
        }

        if (!is_file($sourceFile)) {
            return;
        }

        $content = file_get_contents($sourceFile);
        if ($content === false) {
            return;
        }

        $functionSource = null;
        if ($pattern !== null && $pattern !== '') {
            if (preg_match($pattern, $content, $matches)) {
                $functionSource = $matches[0];
            }
        } else {
            $functionSource = $this->itmExtractFunctionSource($content, $functionName);
        }

        if ($functionSource === null || $functionSource === '') {
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'itm_fn_');
        if ($tmp === false) {
            return;
        }

        file_put_contents($tmp, "<?php\n" . $functionSource);
        require_once $tmp;
        @unlink($tmp);
    }

    /**
     * Why: Regex cannot reliably stop at nested braces (function_exists wrappers, inner if blocks).
     */
    protected function itmExtractFunctionSource(string $content, string $functionName): ?string
    {
        $needle = 'function ' . $functionName;
        $pos = strpos($content, $needle);
        if ($pos === false) {
            return null;
        }

        $bracePos = strpos($content, '{', $pos);
        if ($bracePos === false) {
            return null;
        }

        $depth = 0;
        $length = strlen($content);
        for ($i = $bracePos; $i < $length; $i++) {
            $char = $content[$i];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $pos, $i - $pos + 1);
                }
            }
        }

        return null;
    }
}
