<?php

/**
 * Load a single function from a production PHP file without eval().
 *
 * Why: Module index/api files are not safe to require wholesale in PHPUnit; extracting
 * one function into a temp file keeps tests focused and avoids eval().
 */
trait ItmExtractFunctionTestTrait
{
    protected function requireExtractedFunction(string $sourceFile, string $functionName, string $pattern): void
    {
        if (function_exists($functionName)) {
            return;
        }

        if (!is_file($sourceFile)) {
            return;
        }

        $content = file_get_contents($sourceFile);
        if ($content === false || !preg_match($pattern, $content, $matches)) {
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'itm_fn_');
        if ($tmp === false) {
            return;
        }

        file_put_contents($tmp, "<?php\n" . $matches[0]);
        require_once $tmp;
        @unlink($tmp);
    }
}
