<?php
/**
 * Shared subprocess runner for PHPUnit tests that exercise scripts/*.php via CLI.
 *
 * Why: Audit scripts exit() on completion; subprocess + 2>&1 matches SecurityFixesTest and works on Windows.
 */
trait ItmScriptCliTestTrait
{
    /**
     * @return array{exit:int, output:string}
     */
    protected function runPhpScriptFile(string $scriptFile): array
    {
        $phpBin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        if (strpos($phpBin, 'php-cgi') !== false) {
            $phpBin = str_replace('php-cgi', 'php', $phpBin);
        }

        $command = escapeshellarg($phpBin) . ' -d error_reporting=0 ' . escapeshellarg($scriptFile) . ' 2>&1';
        $lines = [];
        $exitCode = 0;
        exec($command, $lines, $exitCode);

        return [
            'exit' => (int)$exitCode,
            'output' => implode("\n", $lines),
        ];
    }

    /**
     * @return array{exit:int, output:string}
     */
    protected function runRepoScript(string $relativePathFromRoot): array
    {
        return $this->runPhpScriptFile(ROOT_PATH . ltrim($relativePathFromRoot, '/'));
    }
}
