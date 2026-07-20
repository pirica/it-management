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

    public function testPrefixWildcardRegistryKeyMatchesEquipmentFacades(): void
    {
        $registry = [
            'modules' => [
                'is_*' => [
                    'checks' => [
                        ['check' => 'Search', 'code' => 'ui_config_search'],
                        ['check' => '+ New Button', 'code' => 'ui_config_new_button'],
                    ],
                ],
            ],
        ];

        $this->assertTrue(itm_ui_configuration_check_is_reviewed('is_switch', 'Search', $registry));
        $this->assertTrue(itm_ui_configuration_check_is_reviewed('is_server', '+ New Button', $registry));
        $this->assertFalse(itm_ui_configuration_check_is_reviewed('equipment', 'Search', $registry));
        $this->assertFalse(itm_ui_configuration_check_is_reviewed('is_switch', 'Back & Save (create.php)', $registry));
    }

    public function testReviewedRegistryKeyMatcher(): void
    {
        $this->assertTrue(itm_ui_configuration_reviewed_registry_key_matches_module('is_switch', 'is_switch'));
        $this->assertTrue(itm_ui_configuration_reviewed_registry_key_matches_module('is_*', 'is_switch'));
        $this->assertFalse(itm_ui_configuration_reviewed_registry_key_matches_module('is_*', 'equipment'));
    }

    public function testValidatesRegistryShape(): void
    {
        $result = itm_ui_configuration_validate_reviewed_registry(['modules' => []]);
        $this->assertTrue($result['ok']);
    }
}
