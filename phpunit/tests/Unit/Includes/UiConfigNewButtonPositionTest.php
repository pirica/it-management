<?php

namespace Tests\Unit\Includes;

use PHPUnit\Framework\TestCase;

class UiConfigNewButtonPositionTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../includes/ui_config.php';
    }

    public function testDefaultsToLeftWhenConfigMissing(): void
    {
        $this->assertSame('left', itm_resolve_new_button_position(null));
        $this->assertSame('left', itm_resolve_new_button_position([]));
    }

    public function testReturnsConfiguredValueWhenValid(): void
    {
        $this->assertSame('right', itm_resolve_new_button_position(['new_button_position' => 'right']));
        $this->assertSame('left_right', itm_resolve_new_button_position(['new_button_position' => 'left_right']));
    }

    public function testInvalidValueFallsBackToLeft(): void
    {
        $this->assertSame('left', itm_resolve_new_button_position(['new_button_position' => 'bogus']));
        $this->assertSame('left', itm_resolve_new_button_position(['new_button_position' => '']));
    }
}
