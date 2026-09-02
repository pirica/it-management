<?php
/**
 * Build OpenAPI 3.0 document for API v2 from the route registry.
 */

require_once __DIR__ . '/itm_api_v2.php';

if (!function_exists('itm_api_v2_openapi_base_url')) {
    function itm_api_v2_openapi_base_url()
    {
        if (defined('BASE_URL')) {
            return rtrim((string)BASE_URL, '/') . '/modules/api_v2/router.php';
        }

        return 'http://localhost/it-management/modules/api_v2/router.php';
    }
}

if (!function_exists('itm_api_v2_openapi_scope_enum')) {
    function itm_api_v2_openapi_scope_enum()
    {
        return array_keys(itm_api_v2_scope_catalog());
    }
}

if (!function_exists('itm_api_v2_openapi_build_document')) {
    /**
     * @return array<string,mixed>
     */
    function itm_api_v2_openapi_build_document()
    {
        $paths = [];
        $scopeEnum = itm_api_v2_openapi_scope_enum();

        foreach (itm_api_v2_route_registry() as $route) {
            $resource = (string)($route['resource'] ?? '');
            $method = strtolower((string)($route['method'] ?? 'get'));
            $requiresId = !empty($route['id']);
            $pathKey = '/' . $resource . ($requiresId ? '/{id}' : '');

            if (!isset($paths[$pathKey])) {
                $paths[$pathKey] = [];
            }

            $operation = [
                'summary' => strtoupper($method) . ' ' . $resource . ($requiresId ? ' by id' : ''),
                'tags' => [$resource === 'probe' ? 'Meta' : ucfirst($resource)],
                'security' => [['ApiKeyAuth' => []]],
                'responses' => [
                    '200' => [
                        'description' => 'Success envelope `{ ok: true, data: … }`',
                    ],
                    '401' => ['description' => 'Missing or invalid API key'],
                    '403' => ['description' => 'Missing scope or RBAC permission'],
                    '404' => ['description' => 'Resource not found'],
                    '429' => ['description' => 'Rate limit exceeded'],
                ],
            ];

            $scope = (string)($route['scope'] ?? '');
            if ($scope !== '') {
                $operation['description'] = 'Requires scope `' . $scope . '`.';
            }

            if ($requiresId) {
                $operation['parameters'] = [[
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'integer'],
                ]];
            }

            if ($resource !== 'probe' && $method === 'get' && !$requiresId) {
                $operation['parameters'] = [
                    [
                        'name' => 'search',
                        'in' => 'query',
                        'required' => false,
                        'schema' => ['type' => 'string'],
                    ],
                    [
                        'name' => 'limit',
                        'in' => 'query',
                        'required' => false,
                        'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    ],
                ];
            }

            if (in_array($method, ['post', 'patch'], true)) {
                $operation['requestBody'] = [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ];
            }

            $paths[$pathKey][$method] = $operation;
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'IT Management API v2',
                'version' => '2.0.0',
                'description' => 'Partner JSON REST surface. Concatenate `servers[0].url` + path (PATH_INFO suffix), e.g. `.../router.php/tickets`. Paid tier + X-API-Key required. Scopes: ' . implode(', ', $scopeEnum) . '.',
            ],
            'servers' => [
                ['url' => itm_api_v2_openapi_base_url()],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                    ],
                ],
                'schemas' => [
                    'SuccessEnvelope' => [
                        'type' => 'object',
                        'properties' => [
                            'ok' => ['type' => 'boolean', 'example' => true],
                            'data' => ['type' => 'object'],
                        ],
                    ],
                    'ErrorEnvelope' => [
                        'type' => 'object',
                        'properties' => [
                            'ok' => ['type' => 'boolean', 'example' => false],
                            'error' => ['type' => 'string'],
                            'code' => ['type' => 'integer'],
                        ],
                    ],
                    'ApiScope' => [
                        'type' => 'string',
                        'enum' => $scopeEnum,
                    ],
                ],
            ],
            'paths' => $paths,
        ];
    }
}
