<?php

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

/**
 * Explorer relative-path normalization and extension whitelist (no storage I/O).
 */
class ExplorerNormalizePathTest extends TestCase
{
    protected function setUp(): void
    {
        require_once ROOT_PATH . 'includes/itm_explorer_paths.php';
    }

    public function testEmptyPathReturnsEmptyString(): void
    {
        $this->assertSame('', explorer_normalize_relative_path(''));
        $this->assertSame('', explorer_normalize_relative_path('./'));
    }

    public function testBackslashAndDotSegmentsCollapse(): void
    {
        $this->assertSame('Private/Admin_1/file.txt', explorer_normalize_relative_path('.\\Private\\Admin_1\\./file.txt'));
        $this->assertSame('Common/docs/readme.pdf', explorer_normalize_relative_path('Common/./docs/readme.pdf'));
    }

    public function testParentSegmentReturnsNull(): void
    {
        $this->assertNull(explorer_normalize_relative_path('../secrets'));
        $this->assertNull(explorer_normalize_relative_path('Common/../../etc/passwd'));
    }

    /**
     * @dataProvider allowedExtensionProvider
     */
    public function testAllowedExtensionMatrix(string $filename, bool $expected): void
    {
        $this->assertSame($expected, itm_explorer_is_allowed_extension($filename));
    }

    public function allowedExtensionProvider(): array
    {
        return [
            ['photo.JPG', true],
            ['manual.pdf', true],
            ['notes.txt', true],
            ['archive.zip', true],
            ['script.php', false],
            ['.htaccess', false],
            ['payload.exe', false],
        ];
    }
}
