<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Zip-slip guard for explorer_extract_zip_safely() — mirrors scripts/verify_explorer_zip_leak.php intent.
 */
class ExplorerZipSlipTest extends TestCase
{
    /** @var string[] */
    private $tempDirs = [];

    protected function setUp(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is not available.');
        }

        require_once ROOT_PATH . 'includes/itm_explorer_paths.php';
    }

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
        $this->tempDirs = [];
    }

    public function testRejectsZipEntryOutsideDestination(): void
    {
        $destination = $this->makeTempDir('explorer-zip-safe-dest');
        $zipPath = $destination . '/evil.zip';
        $this->createZip($zipPath, ['../outside.txt' => 'pwned']);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);

        $this->assertFalse(explorer_extract_zip_safely($zip, $destination));
        $zip->close();

        $this->assertFileDoesNotExist(dirname($destination) . '/outside.txt');
    }

    public function testExtractsSafeRelativeEntry(): void
    {
        $destination = $this->makeTempDir('explorer-zip-safe-dest');
        $zipPath = $destination . '/safe.zip';
        $this->createZip($zipPath, ['notes/readme.txt' => 'hello']);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);

        $this->assertTrue(explorer_extract_zip_safely($zip, $destination));
        $zip->close();

        $this->assertFileExists($destination . '/notes/readme.txt');
        $this->assertSame('hello', file_get_contents($destination . '/notes/readme.txt'));
    }

    private function makeTempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(4));
        $this->assertTrue(mkdir($dir, 0755, true));
        $this->tempDirs[] = $dir;

        return $dir;
    }

    /**
     * @param array<string,string> $entries relative path => contents
     */
    private function createZip(string $zipPath, array $entries): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        foreach ($entries as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }
        $zip->close();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }
            @unlink($path);
        }

        @rmdir($dir);
    }
}
