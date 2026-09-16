<?php

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class UiConfigFaviconTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../includes/ui_config.php';
    }

    public function testCanonicalFaviconRelativePathUsesCompanyId(): void
    {
        $this->assertSame('', itm_ui_config_canonical_favicon_relative_path(0));
        $this->assertSame('images/favicons/company_1.ico', itm_ui_config_canonical_favicon_relative_path(1));
        $this->assertSame('images/favicons/company_5.ico', itm_ui_config_canonical_favicon_relative_path(5));
    }

    public function testResolveFaviconRelativePathFallsBackToCanonicalFileOnDisk(): void
    {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 4) . DIRECTORY_SEPARATOR);
        }

        $canonical = itm_ui_config_canonical_favicon_relative_path(1);
        $absolute = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $canonical);
        if (!is_file($absolute)) {
            $this->markTestSkipped('Seed favicon file missing: ' . $canonical);
        }

        $resolved = itm_ui_config_resolve_favicon_relative_path(['favicon_path' => ''], 1);
        $this->assertSame($canonical, $resolved);
    }

    public function testFaviconUrlUsesCanonicalFallbackWhenDbPathEmpty(): void
    {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 4) . DIRECTORY_SEPARATOR);
        }
        if (!defined('BASE_URL')) {
            define('BASE_URL', 'http://localhost/it-management/');
        }

        $canonical = itm_ui_config_canonical_favicon_relative_path(1);
        $absolute = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $canonical);
        if (!is_file($absolute)) {
            $this->markTestSkipped('Seed favicon file missing: ' . $canonical);
        }

        $url = itm_ui_config_favicon_url(['favicon_path' => ''], 1);
        $this->assertStringContainsString($canonical, $url);
        $this->assertStringStartsWith((string) BASE_URL, $url);
    }
}
