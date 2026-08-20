<?php
/**
 * API v2 JSON REST gateway (PATH_INFO routes on this file).
 *
 * Example: GET .../router.php/tickets with header X-API-Key.
 */
define('ITM_API_V2', true);

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/itm_api_v2.php';

itm_api_v2_dispatch($conn);
