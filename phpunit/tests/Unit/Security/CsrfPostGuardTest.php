<?php

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;

/**
 * POST CSRF guard contract — complements Securityunittest token validation.
 */
class CsrfPostGuardTest extends TestCase
{
    /** @var array<string,mixed> */
    private $postBackup = [];

    /** @var array<string,mixed> */
    private $sessionBackup = [];

    protected function setUp(): void
    {
        $this->postBackup = $_POST;
        $this->sessionBackup = $_SESSION;
        $_POST = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->postBackup;
        $_SESSION = $this->sessionBackup;
    }

    public function testTryPostCsrfAcceptsMatchingSessionToken(): void
    {
        $_SESSION['csrf_token'] = 'session-token';
        $_POST['csrf_token'] = 'session-token';

        $this->assertTrue(itm_try_post_csrf());
    }

    public function testTryPostCsrfRejectsMissingToken(): void
    {
        $_SESSION['csrf_token'] = 'session-token';
        unset($_POST['csrf_token']);

        $this->assertFalse(itm_try_post_csrf());
    }

    public function testTryPostCsrfRejectsWrongToken(): void
    {
        $_SESSION['csrf_token'] = 'session-token';
        $_POST['csrf_token'] = 'wrong-token';

        $this->assertFalse(itm_try_post_csrf());
    }

    public function testTryPostCsrfAcceptsDoubleSubmitCookieWhenSessionEmpty(): void
    {
        unset($_SESSION['csrf_token']);
        $cookieName = itm_csrf_double_submit_cookie_name();
        $_COOKIE[$cookieName] = 'cookie-token';
        $_POST['csrf_token'] = 'cookie-token';

        $this->assertTrue(itm_try_post_csrf());
        $this->assertSame('cookie-token', $_SESSION['csrf_token']);

        unset($_COOKIE[$cookieName]);
    }

    public function testCsrfCookieParamsIncludePathAndSameSite(): void
    {
        $params = itm_csrf_cookie_params();

        $this->assertIsArray($params);
        $this->assertArrayHasKey('path', $params);
        $this->assertArrayHasKey('secure', $params);
        $this->assertArrayHasKey('samesite', $params);
        $this->assertSame('Lax', $params['samesite']);
    }
}
