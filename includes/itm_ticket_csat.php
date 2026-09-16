<?php
if (!function_exists('itm_ticket_csat_secret')) {
    function itm_ticket_csat_secret() {
        $env = getenv('ITM_TICKET_CSAT_SECRET');
        if ($env !== false && $env !== '') return (string)$env;
        return hash('sha256', 'itm-ticket-csat:' . (defined('DB_PASS') ? (string)DB_PASS : ''), true);
    }
}
if (!function_exists('itm_ticket_csat_b64url_encode')) {
    function itm_ticket_csat_b64url_encode($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
}
if (!function_exists('itm_ticket_csat_b64url_decode')) {
    function itm_ticket_csat_b64url_decode($d) {
        $d = strtr((string)$d, '-_', '+/'); $p = strlen($d) % 4; if ($p) $d .= str_repeat('=', 4 - $p);
        return base64_decode($d, true);
    }
}
if (!function_exists('itm_ticket_csat_build_token')) {
    function itm_ticket_csat_build_token($companyId, $ticketId) {
        $companyId = (int)$companyId; $ticketId = (int)$ticketId;
        if ($companyId <= 0 || $ticketId <= 0) return '';
        $payload = json_encode(['company_id' => $companyId, 'ticket_id' => $ticketId, 'exp' => time() + 86400 * 30], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pb = itm_ticket_csat_b64url_encode($payload);
        return $pb . '.' . itm_ticket_csat_b64url_encode(hash_hmac('sha256', $pb, itm_ticket_csat_secret(), true));
    }
}
if (!function_exists('itm_ticket_csat_verify_token')) {
    function itm_ticket_csat_verify_token($token) {
        $token = trim((string)$token);
        if ($token === '' || strpos($token, '.') === false) return null;
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;
        $exp = itm_ticket_csat_b64url_encode(hash_hmac('sha256', $parts[0], itm_ticket_csat_secret(), true));
        if (!hash_equals($exp, $parts[1])) return null;
        $json = itm_ticket_csat_b64url_decode($parts[0]);
        if ($json === false || $json === '') return null;
        $p = json_decode($json, true);
        if (!is_array($p)) return null;
        if ((int)($p['exp'] ?? 0) > 0 && (int)$p['exp'] < time()) return null;
        $cid = (int)($p['company_id'] ?? 0); $tid = (int)($p['ticket_id'] ?? 0);
        return ($cid > 0 && $tid > 0) ? ['company_id' => $cid, 'ticket_id' => $tid] : null;
    }
}
if (!function_exists('itm_ticket_csat_build_public_url')) {
    function itm_ticket_csat_build_public_url($companyId, $ticketId) {
        $t = itm_ticket_csat_build_token($companyId, $ticketId);
        if ($t === '') return '';
        return rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/ticket-csat.php?token=' . rawurlencode($t);
    }
}
if (!function_exists('itm_ticket_csat_submit')) {
    function itm_ticket_csat_submit($conn, $companyId, $ticketId, $score, $comment = '') {
        if (!$conn instanceof mysqli) return false;
        $companyId = (int)$companyId; $ticketId = (int)$ticketId; $score = (int)$score;
        if ($companyId <= 0 || $ticketId <= 0 || $score < 1 || $score > 5) return false;
        $comment = trim((string)$comment); if (strlen($comment) > 2000) $comment = substr($comment, 0, 2000);
        $stmt = mysqli_prepare($conn, 'UPDATE tickets SET csat_score = ?, csat_comment = ?, csat_submitted_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND csat_submitted_at IS NULL LIMIT 1');
        if (!$stmt) return false;
        mysqli_stmt_bind_param($stmt, 'isii', $score, $comment, $ticketId, $companyId);
        $ok = mysqli_stmt_execute($stmt); $n = mysqli_stmt_affected_rows($stmt); mysqli_stmt_close($stmt);
        if ($ok && $n > 0 && function_exists('itm_ticket_activity_log')) {
            itm_ticket_activity_log($conn, $companyId, $ticketId, null, 'csat_submitted', ['score' => $score]);
        }
        return $ok && $n > 0;
    }
}
