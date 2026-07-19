<?php

use PHPUnit\Framework\TestCase;

final class UiConfigurationReviewedTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('itm_ui_configuration_check_is_reviewed')) {
            require_once __DIR__ . '/../../../../scripts/lib/itm_ui_configuration_reviewed.php';
        }
    }

    public function testMatchesCheckLabelFromRegistry(): void
    {
        $registry = [
            'modules' => [
                'attempts' => [
                    'checks' => [
                        ['check' => '+ New Button', 'code' => 'ui_config_new_button'],
                    ],
                ],
            ],
        ];

        $this->assertTrue(itm_ui_configuration_check_is_reviewed('attempts', '+ New Button', $registry));
        $this->assertFalse(itm_ui_configuration_check_is_reviewed('attempts', 'Search', $registry));
    }

    public function testValidatesRegistryShape(): void
    {
        $result = itm_ui_configuration_validate_reviewed_registry(['modules' => []]);
        $this->assertTrue($result['ok']);
    }
}
