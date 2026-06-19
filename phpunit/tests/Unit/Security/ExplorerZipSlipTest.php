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

        $destination = sys_get_temp_dir() . '/itm_zip_slip_' . uniqid();
        mkdir($destination, 0777, true);

        $zipPath = $destination . '/malicious.zip';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('../../../poc_zip_slip_explorer.txt', 'Zip Slip Success');
        $zip->close();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $extracted = explorer_extract_zip_safely($zip, $destination);
        $zip->close();

        $this->assertFalse($extracted);
        $this->assertFileDoesNotExist(dirname($destination) . '/poc_zip_slip_explorer.txt');

        @unlink($zipPath);
        @rmdir($destination);
    }
}
