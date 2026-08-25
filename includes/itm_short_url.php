<?php
/**
 * Short URL — validation, public redirect, click analytics, QR integration.
 */

function itm_short_url_generate_code($length = 8)
{
    $length = max(6, min(32, (int) $length));
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max = strlen($chars) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

function itm_short_url_generate_access_token()
{
    return bin2hex(random_bytes(32));
}

function itm_short_url_default_public_base_prefix()
{
    return rtrim((string) BASE_URL, '/') . '/go.php?c=';
}

function itm_short_url_public_css_href()
{
    return rtrim((string) BASE_URL, '/') . '/css/styles.css';
}

function itm_short_url_render_public_page($statusCode, $title, $message)
{
    $statusCode = (int) $statusCode;
    if ($statusCode > 0) {
        http_response_code($statusCode);
    }
    $pageTitle = htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8');
    $body = htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8');
    $css = htmlspecialchars(itm_short_url_public_css_href(), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . $pageTitle . '</title><link rel="stylesheet" href="' . $css . '"></head><body>';
    echo '<p style="margin:40px;text-align:center;font-size:1.1rem;">' . $body . '</p></body></html>';
}

function itm_short_url_normalize_public_base_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }
    if (strlen($url) > 512) {
        return false;
    }
    if (!preg_match('#^https?://#i', $url)) {
        return false;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return false;
    }
    return $url;
}

function itm_short_url_parse_public_base_url_input(array $input)
{
    $raw = trim((string) ($input['public_base_url'] ?? ''));
    if ($raw === '') {
        return ['ok' => true, 'value' => null, 'error' => ''];
    }
    $normalized = itm_short_url_normalize_public_base_url($raw);
    if ($normalized === false) {
        return [
            'ok' => false,
            'value' => null,
            'error' => 'Public base URL must be a valid http:// or https:// URL (max 512 characters), ending before the short code (for example …/go.php?c=).',
        ];
    }
    return ['ok' => true, 'value' => $normalized, 'error' => ''];
}

function itm_short_url_resolve_public_base_prefix($conn, $companyId)
{
    $companyId = (int) $companyId;
    if (!($conn instanceof mysqli) || $companyId <= 0) {
        return itm_short_url_default_public_base_prefix();
    }
    $settings = itm_short_url_load_settings($conn, $companyId);
    $custom = trim((string) ($settings['public_base_url'] ?? ''));
    if ($custom !== '') {
        return $custom;
    }
    return itm_short_url_default_public_base_prefix();
}

function itm_short_url_build_public_url($shortCode, $conn = null, $companyId = 0)
{
    $code = trim((string) $shortCode);
    if ($code === '') {
        return '';
    }
    $prefix = ($conn instanceof mysqli && (int) $companyId > 0)
        ? itm_short_url_resolve_public_base_prefix($conn, $companyId)
        : itm_short_url_default_public_base_prefix();
    return $prefix . rawurlencode($code);
}

function itm_short_url_normalize_destination($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return $url;
}

function itm_short_url_default_settings()
{
    return [
        'default_expiry_days' => null,
        'custom_code_min_length' => 4,
        'require_https_destination' => 0,
        'analytics_enabled' => 1,
        'allow_password_protect' => 1,
        'public_base_url' => null,
    ];
}

function itm_short_url_load_settings($conn, $companyId)
{
    $companyId = (int) $companyId;
    if ($companyId <= 0) {
        return itm_short_url_default_settings();
    }
    $sql = 'SELECT * FROM short_url_settings WHERE company_id = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return itm_short_url_default_settings();
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return itm_short_url_default_settings();
    }
    return array_merge(itm_short_url_default_settings(), $row);
}

function itm_short_url_save_settings($conn, $companyId, $employeeId, array $input)
{
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    if ($companyId <= 0) {
        return false;
    }
    $defaultExpiry = trim((string) ($input['default_expiry_days'] ?? ''));
    $defaultExpiryDays = $defaultExpiry === '' ? null : max(1, (int) $defaultExpiry);
    $minLen = max(4, min(12, (int) ($input['custom_code_min_length'] ?? 4)));
    $requireHttps = !empty($input['require_https_destination']) ? 1 : 0;
    $analytics = !empty($input['analytics_enabled']) ? 1 : 0;
    $allowPassword = !empty($input['allow_password_protect']) ? 1 : 0;
    $baseParse = itm_short_url_parse_public_base_url_input($input);
    if (empty($baseParse['ok'])) {
        return false;
    }
    $publicBaseUrl = $baseParse['value'];

    $existing = itm_short_url_load_settings($conn, $companyId);
    $existingId = (int) ($existing['id'] ?? 0);

    if ($existingId > 0) {
        $sql = 'UPDATE short_url_settings SET default_expiry_days = ?, custom_code_min_length = ?, require_https_destination = ?, analytics_enabled = ?, allow_password_protect = ?, public_base_url = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiiiisiii', $defaultExpiryDays, $minLen, $requireHttps, $analytics, $allowPassword, $publicBaseUrl, $employeeId, $existingId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }

    $sql = 'INSERT INTO short_url_settings (company_id, default_expiry_days, custom_code_min_length, require_https_destination, analytics_enabled, allow_password_protect, public_base_url, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iiiiissii', $companyId, $defaultExpiryDays, $minLen, $requireHttps, $analytics, $allowPassword, $publicBaseUrl, $employeeId, $employeeId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function itm_short_url_is_valid_custom_code($code, $minLength = 4)
{
    $code = trim((string) $code);
    $minLength = max(4, min(12, (int) $minLength));
    if (strlen($code) < $minLength || strlen($code) > 64) {
        return false;
    }
    return (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $code);
}

function itm_short_url_code_exists($conn, $companyId, $code, $excludeId = 0)
{
    $companyId = (int) $companyId;
    $excludeId = (int) $excludeId;
    $code = trim((string) $code);
    if ($companyId <= 0 || $code === '') {
        return false;
    }
    $sql = 'SELECT id FROM short_urls WHERE company_id = ? AND short_code = ? AND deleted_at IS NULL';
    if ($excludeId > 0) {
        $sql .= ' AND id <> ?';
    }
    $sql .= ' LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    if ($excludeId > 0) {
        mysqli_stmt_bind_param($stmt, 'isi', $companyId, $code, $excludeId);
    } else {
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $code);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $exists = $res && mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return (bool) $exists;
}

function itm_short_url_title_from_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return 'Short link';
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (is_string($host) && $host !== '') {
        return $host;
    }
    return substr($url, 0, 200);
}

function itm_short_url_validate_save($conn, $companyId, $employeeId, array $input, $existingId = 0)
{
    $errors = [];
    $settings = itm_short_url_load_settings($conn, $companyId);
    $destination = itm_short_url_normalize_destination($input['destination_url'] ?? '');
    if ($destination === '' || !filter_var($destination, FILTER_VALIDATE_URL)) {
        $errors[] = 'A valid destination URL is required.';
    } elseif (!empty($settings['require_https_destination']) && stripos($destination, 'https://') !== 0) {
        $errors[] = 'Destination URL must use HTTPS for this company.';
    }

    $minLen = (int) ($settings['custom_code_min_length'] ?? 4);
    $shortCode = trim((string) ($input['short_code'] ?? ''));
    if ($shortCode === '') {
        $shortCode = itm_short_url_generate_code(8);
        $attempts = 0;
        while (itm_short_url_code_exists($conn, $companyId, $shortCode, $existingId) && $attempts < 10) {
            $shortCode = itm_short_url_generate_code(8);
            $attempts++;
        }
    } elseif (!itm_short_url_is_valid_custom_code($shortCode, $minLen)) {
        $errors[] = 'Custom code must be ' . $minLen . '–64 characters (letters, numbers, underscore, hyphen).';
    } elseif (itm_short_url_code_exists($conn, $companyId, $shortCode, $existingId)) {
        $errors[] = 'That short code is already in use for this company.';
    }

    $password = (string) ($input['password'] ?? '');
    $clearPassword = !empty($input['clear_password']);
    if ($password !== '' && empty($settings['allow_password_protect'])) {
        $errors[] = 'Password-protected links are disabled for this company.';
    }

    $expiresAt = null;
    $expiresInput = trim((string) ($input['expires_at'] ?? ''));
    if ($expiresInput !== '') {
        if (function_exists('itm_parse_date_input')) {
            $parsed = itm_parse_date_input($expiresInput);
            if ($parsed === null || $parsed === '') {
                $errors[] = 'Expiration date is invalid.';
            } else {
                $expiresAt = $parsed . ' 23:59:59';
            }
        } else {
            $expiresAt = $expiresInput . ' 23:59:59';
        }
    } elseif ($existingId <= 0 && !empty($settings['default_expiry_days'])) {
        $days = (int) $settings['default_expiry_days'];
        if ($days > 0) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        }
    }

    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        $title = itm_short_url_title_from_url($destination);
    }

    return [
        'errors' => $errors,
        'destination_url' => $destination,
        'short_code' => $shortCode,
        'title' => $title,
        'expires_at' => $expiresAt,
        'password' => $password,
        'clear_password' => $clearPassword,
    ];
}

function itm_short_url_fetch_by_id($conn, $companyId, $employeeId, $id)
{
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $id = (int) $id;
    if ($companyId <= 0 || $employeeId <= 0 || $id <= 0) {
        return null;
    }
    $sql = 'SELECT * FROM short_urls WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'iii', $id, $companyId, $employeeId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function itm_short_url_fetch_by_code($conn, $shortCode)
{
    $shortCode = trim((string) $shortCode);
    if ($shortCode === '' || strlen($shortCode) > 64) {
        return null;
    }
    $sql = 'SELECT * FROM short_urls WHERE short_code = ? AND deleted_at IS NULL AND active = 1 LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $shortCode);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function itm_short_url_fetch_by_token($conn, $token)
{
    $token = trim((string) $token);
    if ($token === '' || strlen($token) > 64) {
        return null;
    }
    $sql = 'SELECT * FROM short_urls WHERE access_token = ? AND deleted_at IS NULL AND active = 1 LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function itm_short_url_is_expired(array $row)
{
    $expiresAt = trim((string) ($row['expires_at'] ?? ''));
    if ($expiresAt === '') {
        return false;
    }
    return strtotime($expiresAt) < time();
}

function itm_short_url_rate_limit_dir()
{
    return rtrim((string) ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'files'
        . DIRECTORY_SEPARATOR . 'rate_limits' . DIRECTORY_SEPARATOR . 'short_url_go';
}

function itm_short_url_rate_limit_check($recordAttempt = false, $maxAttempts = 120, $windowSeconds = 900)
{
    if (!function_exists('itm_qr_share_join_rate_limit_prune_events')) {
        return ['ok' => true];
    }
    $maxAttempts = max(1, (int) $maxAttempts);
    $windowSeconds = max(60, (int) $windowSeconds);
    $ip = function_exists('itm_get_client_ip_address')
        ? trim((string) itm_get_client_ip_address())
        : trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        $ip = 'unknown';
    }
    $dir = itm_short_url_rate_limit_dir();
    if (function_exists('itm_ensure_upload_directory')) {
        itm_ensure_upload_directory($dir, 'deny_all');
    } elseif (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $path = $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    $now = time();
    $events = [];
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['events']) && is_array($decoded['events'])) {
            $events = itm_qr_share_join_rate_limit_prune_events($decoded['events'], $now, $windowSeconds);
        }
    }
    if (count($events) >= $maxAttempts) {
        return ['ok' => false, 'error' => 'Too many requests. Please wait and try again.'];
    }
    if ($recordAttempt) {
        $events[] = $now;
        @file_put_contents(
            $path,
            json_encode(['events' => $events], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
    return ['ok' => true];
}

function itm_short_url_record_click($conn, array $row)
{
    $shortId = (int) ($row['id'] ?? 0);
    $companyId = (int) ($row['company_id'] ?? 0);
    if ($shortId <= 0 || $companyId <= 0) {
        return false;
    }
    $settings = itm_short_url_load_settings($conn, $companyId);
    if (empty($settings['analytics_enabled'])) {
        return true;
    }
    $ip = function_exists('itm_get_client_ip_address')
        ? trim((string) itm_get_client_ip_address())
        : trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $ipHash = $ip !== '' ? hash('sha256', $ip) : null;
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $referrer = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 512);
    $sql = 'INSERT INTO short_url_clicks (short_url_id, company_id, ip_hash, user_agent, referrer) VALUES (?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iisss', $shortId, $companyId, $ipHash, $ua, $referrer);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $upd = mysqli_prepare($conn, 'UPDATE short_urls SET click_count = click_count + 1 WHERE id = ? AND company_id = ?');
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'ii', $shortId, $companyId);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
    return true;
}

function itm_short_url_password_session_key($shortCode)
{
    return 'short_url_unlock_' . hash('sha256', (string) $shortCode);
}

function itm_short_url_password_verified($shortCode)
{
    $key = itm_short_url_password_session_key($shortCode);
    return !empty($_SESSION[$key]);
}

function itm_short_url_set_password_verified($shortCode)
{
    $_SESSION[itm_short_url_password_session_key($shortCode)] = 1;
}

function itm_short_url_render_password_gate(array $row, $error = '')
{
    $code = htmlspecialchars((string) ($row['short_code'] ?? ''), ENT_QUOTES, 'UTF-8');
    $err = $error !== '' ? '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>' : '';
    $css = htmlspecialchars(itm_short_url_public_css_href(), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Password required</title><link rel="stylesheet" href="' . $css . '"></head><body>';
    echo '<div class="card" style="max-width:420px;margin:48px auto;">';
    echo '<h1 title="Password required">🔒</h1><p>This link is password protected.</p>' . $err;
    echo '<form method="post"><input type="hidden" name="short_url_password_gate" value="1">';
    echo '<div class="form-group"><label>Password</label><input type="password" name="link_password" class="form-control" required autocomplete="current-password"></div>';
    echo '<button type="submit" class="btn btn-primary" title="Continue">➡️</button></form></div></body></html>';
}

function itm_short_url_insert_row($conn, $companyId, $employeeId, array $validated, $passwordHash = null)
{
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $accessToken = itm_short_url_generate_access_token();
    $sql = 'INSERT INTO short_urls (company_id, employee_id, title, destination_url, short_code, access_token, password_hash, expires_at, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    $title = (string) $validated['title'];
    $dest = (string) $validated['destination_url'];
    $code = (string) $validated['short_code'];
    $expiresAt = $validated['expires_at'];
    mysqli_stmt_bind_param($stmt, 'iissssssii', $companyId, $employeeId, $title, $dest, $code, $accessToken, $passwordHash, $expiresAt, $employeeId, $employeeId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $newId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $newId;
}

function itm_short_url_create_from_destination($conn, $companyId, $employeeId, $destinationUrl, array $opts = [])
{
    $input = [
        'destination_url' => $destinationUrl,
        'title' => $opts['title'] ?? '',
        'short_code' => $opts['short_code'] ?? '',
        'expires_at' => $opts['expires_at'] ?? '',
        'password' => $opts['password'] ?? '',
    ];
    $validated = itm_short_url_validate_save($conn, $companyId, $employeeId, $input, 0);
    if (!empty($validated['errors'])) {
        return ['ok' => false, 'errors' => $validated['errors']];
    }
    $passwordHash = null;
    if ((string) ($validated['password'] ?? '') !== '') {
        $passwordHash = password_hash((string) $validated['password'], PASSWORD_DEFAULT);
    }
    $newId = itm_short_url_insert_row($conn, $companyId, $employeeId, $validated, $passwordHash);
    if ($newId <= 0) {
        return ['ok' => false, 'errors' => ['Could not create short URL.']];
    }
    $row = itm_short_url_fetch_by_id($conn, $companyId, $employeeId, $newId);
    return ['ok' => true, 'id' => $newId, 'row' => $row, 'public_url' => itm_short_url_build_public_url($validated['short_code'], $conn, $companyId)];
}

function itm_short_url_create_linked_qr($conn, array $shortRow)
{
    if (!function_exists('itm_qr_generator_generate_access_token')) {
        require_once ROOT_PATH . 'includes/itm_qr_generator.php';
    }
    $shortId = (int) ($shortRow['id'] ?? 0);
    $companyId = (int) ($shortRow['company_id'] ?? 0);
    $employeeId = (int) ($shortRow['employee_id'] ?? 0);
    if ($shortId <= 0 || $companyId <= 0 || $employeeId <= 0) {
        return ['ok' => false, 'error' => 'Invalid short URL row.'];
    }
    if (!empty($shortRow['qr_code_id'])) {
        return ['ok' => true, 'qr_code_id' => (int) $shortRow['qr_code_id']];
    }
    $publicUrl = itm_short_url_build_public_url((string) ($shortRow['short_code'] ?? ''), $conn, $companyId);
    $title = trim((string) ($shortRow['title'] ?? 'Short URL QR'));
    if ($title === '') {
        $title = 'Short URL QR';
    }
    $typeSlug = 'website';
    $encodingMode = 'dynamic';
    $payload = ['url' => $publicUrl];
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $designJson = json_encode(itm_qr_generator_default_design(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $accessToken = itm_qr_generator_generate_access_token();
    $sql = 'INSERT INTO qr_codes (company_id, employee_id, title, type_slug, encoding_mode, payload_json, encoded_payload, access_token, design_json, short_url_id, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'QR insert prepare failed.'];
    }
    mysqli_stmt_bind_param($stmt, 'iissssssiii', $companyId, $employeeId, $title, $typeSlug, $encodingMode, $payloadJson, $accessToken, $designJson, $shortId, $employeeId, $employeeId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ['ok' => false, 'error' => 'QR insert failed.'];
    }
    $qrId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    $upd = mysqli_prepare($conn, 'UPDATE short_urls SET qr_code_id = ?, updated_by = ? WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL');
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'iiiii', $qrId, $employeeId, $shortId, $companyId, $employeeId);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
    return ['ok' => true, 'qr_code_id' => $qrId];
}

/**
 * Linked QR scan total for a short URL (forward FK qr_code_id or qr_codes.short_url_id back-link).
 */
function itm_short_url_fetch_linked_qr_scan_count($conn, $companyId, $employeeId, $shortUrlId)
{
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $shortUrlId = (int) $shortUrlId;
    if (!($conn instanceof mysqli) || $companyId <= 0 || $employeeId <= 0 || $shortUrlId <= 0) {
        return 0;
    }
    $sql = 'SELECT COALESCE(qfk.scan_count, qbk.scan_count, 0) AS scan_count
        FROM short_urls su
        LEFT JOIN qr_codes qfk ON qfk.id = su.qr_code_id AND qfk.company_id = su.company_id AND qfk.deleted_at IS NULL
        LEFT JOIN qr_codes qbk ON qbk.short_url_id = su.id AND qbk.company_id = su.company_id AND qbk.employee_id = su.employee_id AND qbk.deleted_at IS NULL
        WHERE su.id = ? AND su.company_id = ? AND su.employee_id = ? AND su.deleted_at IS NULL
        LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'iii', $shortUrlId, $companyId, $employeeId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int) ($row['scan_count'] ?? 0);
}

function itm_short_url_fetch_clicks($conn, $shortId, $companyId, $limit = 50)
{
    $shortId = (int) $shortId;
    $companyId = (int) $companyId;
    $limit = max(1, min(200, (int) $limit));
    $rows = [];
    $sql = 'SELECT clicked_at, ip_hash, user_agent, referrer FROM short_url_clicks WHERE short_url_id = ? AND company_id = ? ORDER BY clicked_at DESC LIMIT ?';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $rows;
    }
    mysqli_stmt_bind_param($stmt, 'iii', $shortId, $companyId, $limit);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($r = mysqli_fetch_assoc($res))) {
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}
