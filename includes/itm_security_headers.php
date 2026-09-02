<?php
/**
 * Central HTTP security headers for browser-facing responses.
 *
 * Why: Legacy UI relies on inline scripts and a few CDN assets; CSP is pragmatic
 * (unsafe-inline + jsdelivr) until modules adopt nonces. HSTS is sent only on HTTPS.
 */

if (!function_exists('itm_request_is_https_from_server')) {
    /**
     * HTTPS detection from a $_SERVER-style array (testable without web SAPI).
     *
     * @param array<string, mixed> $server
     */
    function itm_request_is_https_from_server(array $server): bool
    {
        $httpsFlag = strtolower((string)($server['HTTPS'] ?? ''));
        if ($httpsFlag === 'on' || $httpsFlag === '1') {
            return true;
        }

        if (isset($server['SERVER_PORT']) && (int)$server['SERVER_PORT'] === 443) {
            return true;
        }

        $forwardedProtoRaw = (string)($server['HTTP_X_FORWARDED_PROTO'] ?? '');
        $forwardedProtoParts = array_map('trim', explode(',', strtolower($forwardedProtoRaw)));
        $forwardedProto = $forwardedProtoParts[0] ?? '';
        if ($forwardedProto === 'https') {
            return true;
        }

        $forwardedSsl = strtolower((string)($server['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($forwardedSsl === 'on') {
            return true;
        }

        $requestScheme = strtolower((string)($server['REQUEST_SCHEME'] ?? ''));
        return $requestScheme === 'https';
    }
}

if (!function_exists('itm_request_is_https')) {
    /**
     * Whether the active web request is served over TLS (direct or trusted proxy hint).
     */
    function itm_request_is_https(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        return itm_request_is_https_from_server($_SERVER);
    }
}

if (!function_exists('itm_build_content_security_policy')) {
    /**
     * CSP compatible with inline module UI and known CDN script/style hosts.
     */
    function itm_build_content_security_policy(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://cdn.jsdelivr.net",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }
}

if (!function_exists('itm_send_security_headers')) {
    /**
     * Emit standard browser hardening headers once per request (no-op under CLI).
     */
    function itm_send_security_headers(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        static $sent = false;
        if ($sent) {
            return;
        }
        $sent = true;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: ' . itm_build_content_security_policy());

        if (itm_request_is_https()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}
