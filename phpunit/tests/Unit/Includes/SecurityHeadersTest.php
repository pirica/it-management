<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function testBuildContentSecurityPolicyIncludesRequiredDirectives(): void
    {
        require_once dirname(__DIR__, 4) . '/includes/itm_security_headers.php';

        $csp = itm_build_content_security_policy();

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("'unsafe-inline'", $csp);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function testRequestIsHttpsHonoursForwardedProto(): void
    {
        require_once dirname(__DIR__, 4) . '/includes/itm_security_headers.php';

        $this->assertTrue(itm_request_is_https_from_server([
            'HTTPS' => 'off',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]));
    }

    public function testSessionCookieSecureForcesWhenAppUrlIsHttps(): void
    {
        require_once dirname(__DIR__, 4) . '/includes/itm_security_headers.php';

        $this->assertTrue(itm_session_cookie_secure_from_config(
            'https://itm.example.com/app/',
            null,
            false
        ));
    }

    public function testSessionCookieSecureFalseOnPlainHttpWithoutAppUrl(): void
    {
        require_once dirname(__DIR__, 4) . '/includes/itm_security_headers.php';

        $this->assertFalse(itm_session_cookie_secure_from_config('', null, false));
        $this->assertTrue(itm_session_cookie_secure_from_config('', null, true));
    }
}
