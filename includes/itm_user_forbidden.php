<?php
/**
 * Shared forbidden / permission-denied responses for browser and CLI callers.
 *
 * Why: Plain-text "Forbidden: …" on POST confused users; browser flows redirect with
 * a session flash and modal, fetch clients receive JSON, CLI probes keep plain text.
 */

if (!function_exists('itm_forbidden_user_message')) {
    function itm_forbidden_user_message($message)
    {
        $text = trim((string)$message);
        if ($text === '') {
            return 'You do not have permission to perform this action.';
        }

        if (preg_match('/^Forbidden:\s*(.+)$/i', $text, $matches)) {
            $text = trim((string)($matches[1] ?? ''));
        }

        if ($text === '') {
            return 'You do not have permission to perform this action.';
        }

        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            $first = mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8');
            $text = $first . mb_substr($text, 1, null, 'UTF-8');
        } else {
            $text = ucfirst($text);
        }

        return $text;
    }
}

if (!function_exists('itm_request_expects_json_response')) {
    function itm_request_expects_json_response()
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        if ($accept !== '' && strpos($accept, 'application/json') !== false) {
            return true;
        }

        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        if (trim((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) !== '') {
            return true;
        }

        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        if ($contentType !== '' && strpos($contentType, 'application/json') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_exit_forbidden')) {
    /**
     * @param string $message Canonical message (may include "Forbidden:" prefix).
     */
    function itm_exit_forbidden($message)
    {
        $canonical = trim((string)$message);
        if ($canonical === '') {
            $canonical = 'Forbidden: You do not have permission to perform this action.';
        }

        $display = itm_forbidden_user_message($canonical);

        if (!headers_sent()) {
            http_response_code(403);
        }

        if (PHP_SAPI === 'cli') {
            echo $canonical;
            exit;
        }

        if (itm_request_expects_json_response()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            if (function_exists('itm_api_json_response')) {
                itm_api_json_response(['ok' => false, 'error' => $display], 403);
            }
            echo json_encode(
                ['ok' => false, 'error' => $display],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            exit;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['itm_forbidden_message'] = $display;
        }

        $redirect = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        if ($redirect === '' || stripos($redirect, 'login.php') !== false) {
            $redirect = (defined('BASE_URL') ? BASE_URL : '/') . 'dashboard.php';
        }

        if (!headers_sent()) {
            header('Location: ' . $redirect);
        }
        exit;
    }
}
