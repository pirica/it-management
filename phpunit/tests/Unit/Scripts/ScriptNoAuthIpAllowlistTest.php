<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/scripts/lib/itm_script_bootstrap.php';

class ScriptNoAuthIpAllowlistTest extends TestCase
{
    public function testLoopbackIpsAreAllowed(): void
    {
        $this->assertTrue(itm_script_client_ip_is_loopback('127.0.0.1'));
        $this->assertTrue(itm_script_client_ip_is_loopback('::1'));
        $this->assertTrue(itm_script_client_ip_is_loopback('::ffff:127.0.0.1'));
        $this->assertFalse(itm_script_client_ip_is_loopback('203.0.113.1'));
    }

    public function testExactAndCidrAllowlistMatching(): void
    {
        $this->assertTrue(itm_script_client_ip_matches_allowlist_entry('203.0.113.50', '203.0.113.50'));
        $this->assertFalse(itm_script_client_ip_matches_allowlist_entry('203.0.113.51', '203.0.113.50'));
        $this->assertTrue(itm_script_client_ip_matches_allowlist_entry('10.20.30.40', '10.20.30.0/24'));
        $this->assertFalse(itm_script_client_ip_matches_allowlist_entry('10.20.31.1', '10.20.30.0/24'));
    }

    public function testNoAuthAllowlistMatchesAnyEntry(): void
    {
        $allowed = ['203.0.113.10', '198.51.100.0/24'];
        $this->assertTrue(itm_script_client_ip_matches_no_auth_allowlist('203.0.113.10', $allowed));
        $this->assertTrue(itm_script_client_ip_matches_no_auth_allowlist('198.51.100.99', $allowed));
        $this->assertFalse(itm_script_client_ip_matches_no_auth_allowlist('192.0.2.1', $allowed));
    }

    public function testBuiltinHostAllowlistIncludesMyhomeAndLocalhost(): void
    {
        $hosts = itm_script_no_auth_allowed_hosts_resolved();
        $this->assertContains('localhost', $hosts);
        $this->assertContains('myhome.dynip.sapo.pt', $hosts);
    }

    public function testRequestHostMatchesBuiltinAllowlist(): void
    {
        $_SERVER['HTTP_HOST'] = 'myhome.dynip.sapo.pt';
        $this->assertTrue(itm_script_request_host_matches_no_auth_allowlist());

        $_SERVER['HTTP_HOST'] = 'localhost:8080';
        $this->assertTrue(itm_script_request_host_matches_no_auth_allowlist());

        $_SERVER['HTTP_HOST'] = 'evil.example.com';
        $this->assertFalse(itm_script_request_host_matches_no_auth_allowlist());
    }

    public function testBuiltinIpAllowlistIncludesLoopbackIpv4(): void
    {
        $ips = itm_script_no_auth_allowed_ips_resolved();
        $this->assertContains('127.0.0.1', $ips);
    }
}
