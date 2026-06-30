<?php
/**
 * Subprocess runner for PHPUnit tests that include module entry files.
 *
 * Why: Module index.php files declare procedural helpers at top level; a second
 * in-process include in the same PHPUnit run causes "Cannot redeclare" fatals.
 */
trait ItmModuleIsolatedTestTrait
{
    use ItmScriptCliTestTrait;

    /**
     * @param array<string,mixed> $sessionData
     * @param array<string,mixed> $postData
     * @param array<string,mixed> $getData
     * @param array<string,mixed> $serverData
     * @param array<string,mixed> $extraGlobals
     */
    protected function runIsolatedModule(
        string $scriptPath,
        array $sessionData = [],
        array $postData = [],
        array $getData = [],
        array $serverData = [],
        array $extraGlobals = []
    ): string {
        $scriptPathLiteral = var_export($scriptPath, true);
        $lines = [
            '<?php',
            "define('ITM_CLI_SCRIPT', true);",
            'session_start();',
        ];

        foreach ($sessionData as $key => $value) {
            $lines[] = '$_SESSION[' . var_export((string)$key, true) . '] = ' . var_export($value, true) . ';';
        }
        foreach ($postData as $key => $value) {
            $lines[] = '$_POST[' . var_export((string)$key, true) . '] = ' . var_export($value, true) . ';';
        }
        foreach ($getData as $key => $value) {
            $lines[] = '$_GET[' . var_export((string)$key, true) . '] = ' . var_export($value, true) . ';';
        }
        foreach ($serverData as $key => $value) {
            $lines[] = '$_SERVER[' . var_export((string)$key, true) . '] = ' . var_export($value, true) . ';';
        }
        foreach ($extraGlobals as $key => $value) {
            $safeKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key);
            if ($safeKey === '') {
                continue;
            }
            $lines[] = 'global $' . $safeKey . '; $' . $safeKey . ' = ' . var_export($value, true) . ';';
        }

        $lines[] = "chdir(dirname({$scriptPathLiteral}));";
        $lines[] = "if (!isset(\$_SERVER['REQUEST_METHOD'])) { \$_SERVER['REQUEST_METHOD'] = 'GET'; }";
        $lines[] = 'ob_start();';
        $lines[] = "include basename({$scriptPathLiteral});";
        $lines[] = 'echo ob_get_clean();';

        $tmpFile = tempnam(sys_get_temp_dir(), 'itm_module_isolated_');
        file_put_contents($tmpFile, implode("\n", $lines) . "\n");
        $result = $this->runPhpScriptFile($tmpFile);
        unlink($tmpFile);

        return (string)$result['output'];
    }
}
