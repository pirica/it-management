<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Consolidated script catalog smoke tests (replaces per-script file-exists *.unittest.php stubs).
 */
class ScriptCatalogSmokeTest extends TestCase
{
    use ItmScriptCliTestTrait;

    /**
     * @return array<int, array{0:string}>
     */
    public static function trackedScriptFileProvider(): array
    {
        $paths = [];
        foreach (glob(ROOT_PATH . 'scripts/*.php') ?: [] as $file) {
            $base = basename($file);
            if (strpos($base, '_tmp_') === 0) {
                continue;
            }
            $paths[] = ['scripts/' . $base];
        }
        foreach (glob(ROOT_PATH . 'scripts/lib/*.php') ?: [] as $file) {
            $paths[] = ['scripts/lib/' . basename($file)];
        }

        sort($paths);

        return $paths;
    }

    /**
     * Read-only audit / listing scripts safe to invoke via CLI subprocess.
     *
     * @return array<string, array{0:string, 1:int[]}>
     */
    public static function readOnlyCliScriptProvider(): array
    {
        $allowed = [
            'scripts/verify_database_schema.php' => [0, 1],
            'scripts/identify_modules.php' => [0],
            'scripts/crud_tables.php' => [0],
            'scripts/count_args.php' => [0],
            'scripts/list_phone_columns.php' => [0],
            'scripts/list_modules_not_on_sidebar.php' => [0],
            'scripts/list_active_and_checkboxes.php' => [0],
            'scripts/analyze_database_health.php' => [0, 1],
            'scripts/compare_database_sql_modules.php' => [0, 1],
            'scripts/check_database_sql_company_name_uniques.php' => [0, 1],
            'scripts/check_multi_tenant_leaks.php' => [0, 1, 255],
            'scripts/check_delimiters.php' => [0, 1, 2],
            'scripts/check_duplicates.php' => [0, 1, 2],
            'scripts/check_equipment_clear_table_delete.php' => [0, 1, 2],
            'scripts/check_index_table_compliance.php' => [0, 1, 2],
            'scripts/check_phones.php' => [0, 1, 2],
            'scripts/check_sql_errors.php' => [0, 1, 2],
            'scripts/check_employees_clear_table_transaction.php' => [0, 1, 2],
            'scripts/detect_fk_dropdown_ui_risk.php' => [0, 1, 2],
            'scripts/db_field_active.php' => [0, 1],
            'scripts/verify_sql.php' => [0, 1],
            'scripts/verify_api_coverage.php' => [0, 1],
            'scripts/DBdesign.php' => [0, 1],
        ];

        $cases = [];
        foreach ($allowed as $path => $exits) {
            $cases[$path] = [$path, $exits];
        }

        return $cases;
    }

    public function testScriptsCatalogIndexExists(): void
    {
        $this->assertFileExists(ROOT_PATH . 'scripts/scripts.php');
    }

    /**
     * @dataProvider trackedScriptFileProvider
     */
    public function testTrackedScriptFileExists(string $relativePath): void
    {
        $this->assertFileExists(ROOT_PATH . ltrim($relativePath, '/'));
    }

    /**
     * @dataProvider readOnlyCliScriptProvider
     * @param int[] $allowedExitCodes
     */
    public function testReadOnlyCliScriptCompletes(string $relativePath, array $allowedExitCodes): void
    {
        $result = $this->runRepoScript($relativePath);
        $this->assertContains(
            $result['exit'],
            $allowedExitCodes,
            $relativePath . " exit {$result['exit']}: " . substr($result['output'], 0, 500)
        );
        $this->assertNotSame('', trim($result['output']), $relativePath . ' should emit CLI output');
    }

    public function testHealthScriptIsDocumentedShellNotPhp(): void
    {
        $path = ROOT_PATH . 'scripts/health.php';
        $this->assertFileExists($path);
        $head = (string)file_get_contents($path, false, null, 0, 40);
        $this->assertStringStartsWith('echo', $head, 'health.php is a shell bootstrap, not a PHP entry script');
    }
}
