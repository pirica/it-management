<?php

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for Explorer safe ZIP extraction.
 */
class ExplorerZipSlipTest extends TestCase
{
    public function testZipSlipEntryIsBlocked()
    {
        require_once ROOT_PATH . 'includes/itm_explorer_paths.php';

        // Use existing directory but different filename for ZIP
        $destination = __DIR__ . '/zip_destination';
        $zipPath = $destination . '/itm_malicious_' . uniqid() . '.zip';

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('../../../poc_zip_slip_explorer.txt', 'Zip Slip Success');
        $zip->close();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $extracted = explorer_extract_zip_safely($zip, $destination);
        $zip->close();

        $this->assertFalse($extracted);
        // The check still works because explorer_extract_zip_safely should block the traversal
        $this->assertFileDoesNotExist(dirname($destination, 3) . '/poc_zip_slip_explorer.txt');

        @unlink($zipPath);
    }
}
