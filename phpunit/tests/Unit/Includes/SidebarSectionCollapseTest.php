<?php

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class SidebarSectionCollapseTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../includes/ui_config.php';
    }

    public function testNormalizeSidebarCollapsedMapCoercesFlags(): void
    {
        $map = itm_normalize_sidebar_collapsed_map([
            'planning' => 1,
            'admin' => '0',
            'finance' => false,
            '' => 1,
        ]);

        $this->assertSame(1, $map['planning']);
        $this->assertSame(0, $map['admin']);
        $this->assertSame(0, $map['finance']);
        $this->assertArrayNotHasKey('', $map);
    }

    public function testFeatureEnabledDefaultsToOn(): void
    {
        $this->assertTrue(itm_sidebar_section_collapse_feature_enabled(null));
        $this->assertTrue(itm_sidebar_section_collapse_feature_enabled([]));
        $this->assertTrue(itm_sidebar_section_collapse_feature_enabled(['enable_sidebar_section_collapse' => 1]));
        $this->assertFalse(itm_sidebar_section_collapse_feature_enabled(['enable_sidebar_section_collapse' => 0]));
    }

    public function testValidSectionIdMatchesCatalog(): void
    {
        $this->assertFalse(itm_sidebar_is_valid_section_id(''));
        $this->assertFalse(itm_sidebar_is_valid_section_id('not-a-real-section'));
        $this->assertTrue(itm_sidebar_is_valid_section_id('planning'));
    }
}
