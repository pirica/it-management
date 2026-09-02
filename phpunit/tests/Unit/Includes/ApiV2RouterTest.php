<?php
use PHPUnit\Framework\TestCase;

class ApiV2RouterTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('itm_api_v2_route_registry')) {
            require_once __DIR__ . '/../../../../includes/itm_api_v2.php';
        }
        if (!function_exists('itm_api_v2_scope_catalog')) {
            require_once __DIR__ . '/../../../../includes/itm_api_v2_scopes.php';
        }
    }

    public function testScopeCatalogContainsMvpSlugs(): void
    {
        $catalog = itm_api_v2_scope_catalog();
        $this->assertArrayHasKey('tickets.read', $catalog);
        $this->assertArrayHasKey('tickets.write', $catalog);
        $this->assertArrayHasKey('equipment.read', $catalog);
        $this->assertArrayHasKey('equipment.write', $catalog);
    }

    public function testDefaultReadScopesOnly(): void
    {
        $defaults = itm_api_v2_default_read_scope_slugs();
        $this->assertSame(['tickets.read', 'equipment.read'], $defaults);
    }

    public function testMatchRouteListTickets(): void
    {
        $route = itm_api_v2_match_route([
            'method' => 'GET',
            'resource' => 'tickets',
            'id' => 0,
        ]);
        $this->assertIsArray($route);
        $this->assertSame('tickets.read', $route['scope'] ?? '');
        $this->assertSame('view', $route['rbac'] ?? '');
    }

    public function testMatchRoutePatchTicketById(): void
    {
        $route = itm_api_v2_match_route([
            'method' => 'PATCH',
            'resource' => 'tickets',
            'id' => 99,
        ]);
        $this->assertIsArray($route);
        $this->assertSame('tickets.write', $route['scope'] ?? '');
        $this->assertSame('edit', $route['rbac'] ?? '');
    }

    public function testMatchRouteEmptyPathProbeOnGet(): void
    {
        $route = itm_api_v2_match_route([
            'method' => 'GET',
            'resource' => '',
            'id' => 0,
        ]);
        $this->assertIsArray($route);
        $this->assertSame('probe', $route['resource'] ?? '');
    }

    public function testInvalidScopeSlugNormalizedToEmpty(): void
    {
        $this->assertSame('', itm_api_v2_normalize_scope_slug('not-a-real-scope'));
        $this->assertSame('tickets.read', itm_api_v2_normalize_scope_slug('TICKETS.READ'));
    }

    public function testRouteRegistryIncludesProbeAndTickets(): void
    {
        $resources = array_map(static function ($route) {
            return ($route['method'] ?? '') . ' ' . ($route['resource'] ?? '');
        }, itm_api_v2_route_registry());

        $this->assertContains('GET probe', $resources);
        $this->assertContains('GET tickets', $resources);
        $this->assertContains('POST tickets', $resources);
        $this->assertContains('GET equipment', $resources);
    }
}
