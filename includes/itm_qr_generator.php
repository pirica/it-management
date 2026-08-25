<?php
/**
 * QR Generator — type catalog, payload encoding, public resolver helpers.
 * Separate from itm_qr_share.php (temporary share_sessions).
 */

function itm_qr_generator_type_catalog()
{
    return [
        'website' => ['label' => 'Website', 'emoji' => '🔗', 'dynamic_default' => true],
        'pdf' => ['label' => 'PDF', 'emoji' => '📄', 'dynamic_default' => true, 'dynamic_only' => true],
        'images' => ['label' => 'Images', 'emoji' => '🖼️', 'dynamic_default' => true, 'dynamic_only' => true],
        'video' => ['label' => 'Video', 'emoji' => '🎬', 'dynamic_default' => true, 'dynamic_only' => true],
        'wifi' => ['label' => 'WiFi', 'emoji' => '📶', 'dynamic_default' => false, 'static_only' => true],
        'menu' => ['label' => 'Menu', 'emoji' => '🍽️', 'dynamic_default' => true, 'dynamic_only' => true],
        'business' => ['label' => 'Business', 'emoji' => '🏢', 'dynamic_default' => true, 'dynamic_only' => true],
        'vcard' => ['label' => 'vCard', 'emoji' => '👤', 'dynamic_default' => true],
        'mp3' => ['label' => 'Audio', 'emoji' => '🎵', 'dynamic_default' => true, 'dynamic_only' => true],
        'apps' => ['label' => 'Apps', 'emoji' => '📱', 'dynamic_default' => true, 'dynamic_only' => true],
        'list_of_links' => ['label' => 'List of Links', 'emoji' => '📋', 'dynamic_default' => true, 'dynamic_only' => true],
        'coupon' => ['label' => 'Coupon', 'emoji' => '🎟️', 'dynamic_default' => true, 'dynamic_only' => true],
        'facebook' => ['label' => 'Facebook', 'emoji' => '📘', 'dynamic_default' => true],
        'instagram' => ['label' => 'Instagram', 'emoji' => '📷', 'dynamic_default' => true],
        'social' => ['label' => 'Social Media', 'emoji' => '🌐', 'dynamic_default' => true],
        'whatsapp' => ['label' => 'WhatsApp', 'emoji' => '💬', 'dynamic_default' => false, 'static_only' => true],
        'email' => ['label' => 'Email', 'emoji' => '📧', 'dynamic_default' => true],
        'phone' => ['label' => 'Phone', 'emoji' => '📞', 'dynamic_default' => true],
        'sms' => ['label' => 'SMS', 'emoji' => '💬', 'dynamic_default' => false, 'static_only' => true],
        'text' => ['label' => 'Plain Text', 'emoji' => '📝', 'dynamic_default' => false, 'static_only' => true],
    ];
}

function itm_qr_generator_is_valid_type_slug($typeSlug)
{
    $typeSlug = trim((string) $typeSlug);
    return $typeSlug !== '' && isset(itm_qr_generator_type_catalog()[$typeSlug]);
}

function itm_qr_generator_generate_access_token()
{
    return bin2hex(random_bytes(32));
}

function itm_qr_generator_build_public_url($accessToken)
{
    $token = trim((string) $accessToken);
    if ($token === '') {
        return '';
    }
  return rtrim((string) BASE_URL, '/') . '/modules/qr/r.php?t=' . rawurlencode($token);
}

function itm_qr_generator_upload_relative_dir($companyId, $employeeId)
{
    return 'Common/qr/' . (int) $employeeId;
}

function itm_qr_generator_ensure_upload_dir($companyId, $employeeId)
{
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    if ($companyId <= 0 || $employeeId <= 0) {
        return false;
    }
    if (!function_exists('itm_files_storage_root') || !function_exists('itm_ensure_files_storage_directory')) {
        return false;
    }
    $abs = rtrim(itm_files_storage_root(), '/\\') . DIRECTORY_SEPARATOR . $companyId
        . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, itm_qr_generator_upload_relative_dir($companyId, $employeeId));
    return itm_ensure_files_storage_directory($abs);
}

function itm_qr_generator_normalize_payload($typeSlug, array $input)
{
    $typeSlug = trim((string) $typeSlug);
    $out = [];
    switch ($typeSlug) {
        case 'website':
            $out['url'] = trim((string) ($input['url'] ?? ''));
            break;
        case 'wifi':
            $out['ssid'] = trim((string) ($input['ssid'] ?? ''));
            $out['password'] = (string) ($input['password'] ?? '');
            $out['encryption'] = trim((string) ($input['encryption'] ?? 'WPA'));
            $out['hidden'] = !empty($input['hidden']) ? 1 : 0;
            break;
        case 'vcard':
            $out['first_name'] = trim((string) ($input['first_name'] ?? ''));
            $out['last_name'] = trim((string) ($input['last_name'] ?? ''));
            $out['organization'] = trim((string) ($input['organization'] ?? ''));
            $out['title'] = trim((string) ($input['title'] ?? ''));
            $out['phone'] = trim((string) ($input['phone'] ?? ''));
            $out['email'] = trim((string) ($input['email'] ?? ''));
            $out['website'] = trim((string) ($input['website'] ?? ''));
            $out['address'] = trim((string) ($input['address'] ?? ''));
            break;
        case 'email':
            $out['to'] = trim((string) ($input['to'] ?? ''));
            $out['subject'] = trim((string) ($input['subject'] ?? ''));
            $out['body'] = (string) ($input['body'] ?? '');
            break;
        case 'phone':
            $out['number'] = trim((string) ($input['number'] ?? ''));
            break;
        case 'sms':
            $out['number'] = trim((string) ($input['number'] ?? ''));
            $out['message'] = (string) ($input['message'] ?? '');
            break;
        case 'whatsapp':
            $out['number'] = trim((string) ($input['number'] ?? ''));
            $out['message'] = (string) ($input['message'] ?? '');
            break;
        case 'facebook':
            $out['url'] = trim((string) ($input['url'] ?? ''));
            break;
        case 'instagram':
            $out['url'] = trim((string) ($input['url'] ?? ''));
            break;
        case 'social':
            $out['links'] = is_array($input['links'] ?? null) ? $input['links'] : [];
            break;
        case 'text':
            $out['text'] = (string) ($input['text'] ?? '');
            break;
        case 'pdf':
            $out['file_path'] = trim((string) ($input['file_path'] ?? ''));
            $out['title'] = trim((string) ($input['title'] ?? ''));
            break;
        case 'images':
            $out['files'] = is_array($input['files'] ?? null) ? $input['files'] : [];
            $out['title'] = trim((string) ($input['title'] ?? ''));
            break;
        case 'video':
            $out['file_path'] = trim((string) ($input['file_path'] ?? ''));
            $out['url'] = trim((string) ($input['url'] ?? ''));
            $out['title'] = trim((string) ($input['title'] ?? ''));
            break;
        case 'mp3':
            $out['file_path'] = trim((string) ($input['file_path'] ?? ''));
            $out['title'] = trim((string) ($input['title'] ?? ''));
            break;
        case 'apps':
            $out['ios_url'] = trim((string) ($input['ios_url'] ?? ''));
            $out['android_url'] = trim((string) ($input['android_url'] ?? ''));
            $out['title'] = trim((string) ($input['title'] ?? ''));
            break;
        case 'list_of_links':
            $out['title'] = trim((string) ($input['title'] ?? ''));
            $out['links'] = is_array($input['links'] ?? null) ? $input['links'] : [];
            break;
        case 'menu':
            $out['title'] = trim((string) ($input['title'] ?? ''));
            $out['sections'] = is_array($input['sections'] ?? null) ? $input['sections'] : [];
            break;
        case 'business':
            $out['name'] = trim((string) ($input['name'] ?? ''));
            $out['description'] = (string) ($input['description'] ?? '');
            $out['phone'] = trim((string) ($input['phone'] ?? ''));
            $out['email'] = trim((string) ($input['email'] ?? ''));
            $out['website'] = trim((string) ($input['website'] ?? ''));
            $out['address'] = trim((string) ($input['address'] ?? ''));
            break;
        case 'coupon':
            $out['code'] = trim((string) ($input['code'] ?? ''));
            $out['title'] = trim((string) ($input['title'] ?? ''));
            $out['description'] = (string) ($input['description'] ?? '');
            $out['expires'] = trim((string) ($input['expires'] ?? ''));
            break;
        default:
            break;
    }
    return $out;
}

function itm_qr_generator_escape_vcard_value($value)
{
    $value = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', ''], (string) $value);
    return $value;
}

function itm_qr_generator_build_static_payload($typeSlug, array $payload)
{
    $typeSlug = trim((string) $typeSlug);
    switch ($typeSlug) {
        case 'website':
            $url = trim((string) ($payload['url'] ?? ''));
            if ($url === '') {
                return '';
            }
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            return $url;
        case 'wifi':
            $ssid = trim((string) ($payload['ssid'] ?? ''));
            if ($ssid === '') {
                return '';
            }
            $enc = strtoupper(trim((string) ($payload['encryption'] ?? 'WPA')));
            if ($enc === 'NOPASS' || $enc === 'NONE') {
                $enc = 'nopass';
            }
            $pass = (string) ($payload['password'] ?? '');
            $hidden = !empty($payload['hidden']) ? 'true' : 'false';
            return 'WIFI:T:' . $enc . ';S:' . $ssid . ';P:' . $pass . ';H:' . $hidden . ';;';
        case 'vcard':
            $lines = ['BEGIN:VCARD', 'VERSION:3.0'];
            $fn = trim((string) ($payload['first_name'] ?? '') . ' ' . (string) ($payload['last_name'] ?? ''));
            if ($fn !== '') {
                $lines[] = 'FN:' . itm_qr_generator_escape_vcard_value($fn);
            }
            if (($payload['first_name'] ?? '') !== '' || ($payload['last_name'] ?? '') !== '') {
                $lines[] = 'N:' . itm_qr_generator_escape_vcard_value((string) ($payload['last_name'] ?? ''))
                    . ';' . itm_qr_generator_escape_vcard_value((string) ($payload['first_name'] ?? '')) . ';;;';
            }
            if (($payload['organization'] ?? '') !== '') {
                $lines[] = 'ORG:' . itm_qr_generator_escape_vcard_value($payload['organization']);
            }
            if (($payload['title'] ?? '') !== '') {
                $lines[] = 'TITLE:' . itm_qr_generator_escape_vcard_value($payload['title']);
            }
            if (($payload['phone'] ?? '') !== '') {
                $lines[] = 'TEL:' . itm_qr_generator_escape_vcard_value($payload['phone']);
            }
            if (($payload['email'] ?? '') !== '') {
                $lines[] = 'EMAIL:' . itm_qr_generator_escape_vcard_value($payload['email']);
            }
            if (($payload['website'] ?? '') !== '') {
                $lines[] = 'URL:' . itm_qr_generator_escape_vcard_value($payload['website']);
            }
            if (($payload['address'] ?? '') !== '') {
                $lines[] = 'ADR:;;' . itm_qr_generator_escape_vcard_value($payload['address']) . ';;;;';
            }
            $lines[] = 'END:VCARD';
            return implode("\n", $lines);
        case 'email':
            $to = trim((string) ($payload['to'] ?? ''));
            if ($to === '') {
                return '';
            }
            $q = [];
            if (($payload['subject'] ?? '') !== '') {
                $q[] = 'subject=' . rawurlencode((string) $payload['subject']);
            }
            if (($payload['body'] ?? '') !== '') {
                $q[] = 'body=' . rawurlencode((string) $payload['body']);
            }
            return 'mailto:' . $to . ($q ? '?' . implode('&', $q) : '');
        case 'phone':
            $n = preg_replace('/[^\d+]/', '', (string) ($payload['number'] ?? ''));
            return $n !== '' ? 'tel:' . $n : '';
        case 'sms':
            $n = preg_replace('/[^\d+]/', '', (string) ($payload['number'] ?? ''));
            if ($n === '') {
                return '';
            }
            $msg = (string) ($payload['message'] ?? '');
            return 'sms:' . $n . ($msg !== '' ? '?body=' . rawurlencode($msg) : '');
        case 'whatsapp':
            $n = preg_replace('/\D/', '', (string) ($payload['number'] ?? ''));
            if ($n === '') {
                return '';
            }
            $msg = (string) ($payload['message'] ?? '');
            return 'https://wa.me/' . $n . ($msg !== '' ? '?text=' . rawurlencode($msg) : '');
        case 'facebook':
        case 'instagram':
            $url = trim((string) ($payload['url'] ?? ''));
            if ($url === '') {
                return '';
            }
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            return $url;
        case 'text':
            return (string) ($payload['text'] ?? '');
        default:
            return '';
    }
}

function itm_qr_generator_type_requires_dynamic($typeSlug)
{
    $catalog = itm_qr_generator_type_catalog();
    $typeSlug = trim((string) $typeSlug);
    if (!isset($catalog[$typeSlug])) {
        return true;
    }
    return !empty($catalog[$typeSlug]['dynamic_only']);
}

function itm_qr_generator_validate_save(array $data)
{
    $errors = [];
    $typeSlug = trim((string) ($data['type_slug'] ?? ''));
    if (!itm_qr_generator_is_valid_type_slug($typeSlug)) {
        $errors[] = 'Invalid QR type.';
        return $errors;
    }
    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    $mode = trim((string) ($data['encoding_mode'] ?? 'dynamic'));
    if ($mode !== 'static' && $mode !== 'dynamic') {
        $errors[] = 'Invalid encoding mode.';
    }
    if (itm_qr_generator_type_requires_dynamic($typeSlug)) {
        $mode = 'dynamic';
    }
    $catalog = itm_qr_generator_type_catalog();
    if (!empty($catalog[$typeSlug]['static_only'])) {
        $mode = 'static';
    }
    $payload = itm_qr_generator_normalize_payload($typeSlug, is_array($data['payload'] ?? null) ? $data['payload'] : []);
    if ($mode === 'static') {
        $encoded = itm_qr_generator_build_static_payload($typeSlug, $payload);
        if ($encoded === '') {
            $errors[] = 'Content is required for static QR encoding.';
        }
    } else {
        switch ($typeSlug) {
            case 'website':
                if (trim((string) ($payload['url'] ?? '')) === '') {
                    $errors[] = 'URL is required.';
                }
                break;
            case 'pdf':
            case 'mp3':
                if (trim((string) ($payload['file_path'] ?? '')) === '') {
                    $errors[] = 'File is required.';
                }
                break;
            case 'images':
                if (empty($payload['files'])) {
                    $errors[] = 'At least one image is required.';
                }
                break;
            case 'wifi':
                if (trim((string) ($payload['ssid'] ?? '')) === '') {
                    $errors[] = 'WiFi network name is required.';
                }
                break;
            default:
                break;
        }
    }
    return $errors;
}

function itm_qr_generator_decode_json_field($json)
{
    if ($json === null || $json === '') {
        return [];
    }
    $decoded = json_decode((string) $json, true);
    return is_array($decoded) ? $decoded : [];
}

function itm_qr_generator_default_design()
{
    return [
        'size' => 256,
        'colorDark' => '#000000',
        'colorLight' => '#ffffff',
        'correctLevel' => 'H',
        'logo_path' => '',
    ];
}

function itm_qr_generator_normalize_design(array $input)
{
    $defaults = itm_qr_generator_default_design();
    $size = (int) ($input['size'] ?? $defaults['size']);
    if ($size < 128) {
        $size = 128;
    }
    if ($size > 1024) {
        $size = 1024;
    }
    $dark = trim((string) ($input['colorDark'] ?? $defaults['colorDark']));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $dark)) {
        $dark = $defaults['colorDark'];
    }
    $light = trim((string) ($input['colorLight'] ?? $defaults['colorLight']));
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $light)) {
        $light = $defaults['colorLight'];
    }
    $level = strtoupper(trim((string) ($input['correctLevel'] ?? $defaults['correctLevel'])));
    if (!in_array($level, ['L', 'M', 'Q', 'H'], true)) {
        $level = $defaults['correctLevel'];
    }
    return [
        'size' => $size,
        'colorDark' => $dark,
        'colorLight' => $light,
        'correctLevel' => $level,
        'logo_path' => trim((string) ($input['logo_path'] ?? '')),
    ];
}

function itm_qr_generator_resolve_qr_text(array $row)
{
    $mode = (string) ($row['encoding_mode'] ?? 'dynamic');
    if ($mode === 'static') {
        return trim((string) ($row['encoded_payload'] ?? ''));
    }
    $token = trim((string) ($row['access_token'] ?? ''));
    return itm_qr_generator_build_public_url($token);
}

function itm_qr_generator_fetch_by_id($conn, $companyId, $employeeId, $id)
{
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $id = (int) $id;
    if ($companyId <= 0 || $employeeId <= 0 || $id <= 0) {
        return null;
    }
    $sql = 'SELECT * FROM qr_codes WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL LIMIT 1';
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

function itm_qr_generator_fetch_by_token($conn, $token)
{
    $token = trim((string) $token);
    if ($token === '' || strlen($token) > 64) {
        return null;
    }
    $sql = 'SELECT * FROM qr_codes WHERE access_token = ? AND deleted_at IS NULL AND active = 1 LIMIT 1';
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

function itm_qr_generator_rate_limit_dir()
{
    return rtrim((string) ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'files'
        . DIRECTORY_SEPARATOR . 'rate_limits' . DIRECTORY_SEPARATOR . 'qr_generator_resolve';
}

function itm_qr_generator_rate_limit_check($recordAttempt = false, $maxAttempts = 60, $windowSeconds = 900)
{
    if (function_exists('itm_qr_share_join_rate_limit_prune_events')) {
        $maxAttempts = max(1, (int) $maxAttempts);
        $windowSeconds = max(60, (int) $windowSeconds);
        $ip = function_exists('itm_get_client_ip_address')
            ? trim((string) itm_get_client_ip_address())
            : trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '') {
            $ip = 'unknown';
        }
        $dir = itm_qr_generator_rate_limit_dir();
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
    return ['ok' => true];
}

function itm_qr_generator_record_scan($conn, array $qrRow)
{
    $qrId = (int) ($qrRow['id'] ?? 0);
    $companyId = (int) ($qrRow['company_id'] ?? 0);
    if ($qrId <= 0 || $companyId <= 0) {
        return false;
    }
    $ip = function_exists('itm_get_client_ip_address')
        ? trim((string) itm_get_client_ip_address())
        : trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $ipHash = $ip !== '' ? hash('sha256', $ip) : null;
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $sql = 'INSERT INTO qr_code_scans (qr_code_id, company_id, ip_hash, user_agent) VALUES (?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iiss', $qrId, $companyId, $ipHash, $ua);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if ($ok) {
        $upd = mysqli_prepare($conn, 'UPDATE qr_codes SET scan_count = scan_count + 1 WHERE id = ? AND company_id = ?');
        if ($upd) {
            mysqli_stmt_bind_param($upd, 'ii', $qrId, $companyId);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }
    }
    return $ok;
}

function itm_qr_generator_asset_serve_url($companyId, $relativePath, $qrToken = '')
{
    $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        return '';
    }
    if ($qrToken !== '') {
        return rtrim((string) BASE_URL, '/') . '/modules/qr/asset.php?t=' . rawurlencode($qrToken) . '&p=' . rawurlencode($relativePath);
    }
    if (function_exists('itm_files_serve_url')) {
        return itm_files_serve_url($relativePath, '../../modules/explorer/file.php');
    }
    return '';
}

function itm_qr_generator_type_label($typeSlug)
{
    $catalog = itm_qr_generator_type_catalog();
    $typeSlug = trim((string) $typeSlug);
    if (!isset($catalog[$typeSlug])) {
        return $typeSlug;
    }
    return (string) $catalog[$typeSlug]['label'];
}

/**
 * @return array<int, array{id:int, name:string, design:array}>
 */
function itm_qr_generator_list_design_templates($conn, $companyId, $employeeId)
{
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    if (!($conn instanceof mysqli) || $companyId <= 0 || $employeeId <= 0) {
        return [];
    }
    $sql = 'SELECT id, name, design_json FROM qr_design_templates WHERE company_id = ? AND employee_id = ? AND deleted_at IS NULL ORDER BY name ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $employeeId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($res && ($r = mysqli_fetch_assoc($res))) {
        $rows[] = [
            'id' => (int) ($r['id'] ?? 0),
            'name' => (string) ($r['name'] ?? ''),
            'design' => itm_qr_generator_normalize_design(itm_qr_generator_decode_json_field($r['design_json'] ?? '')),
        ];
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * @return array<int, array{id:int, name:string, design:array}>
 */
function itm_qr_generator_design_templates_for_api($conn, $companyId, $employeeId)
{
    $out = [];
    foreach (itm_qr_generator_list_design_templates($conn, $companyId, $employeeId) as $row) {
        $out[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'design' => $row['design'],
        ];
    }
    return $out;
}

/**
 * @return array{ok:bool, error:string, id:int}
 */
function itm_qr_generator_save_design_template($conn, $companyId, $employeeId, $name, array $designRaw, $actorId)
{
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $actorId = (int) $actorId;
    $name = trim((string) $name);
    if (!($conn instanceof mysqli) || $companyId <= 0 || $employeeId <= 0) {
        return ['ok' => false, 'error' => 'Session required.', 'id' => 0];
    }
    if ($name === '' || strlen($name) > 120) {
        return ['ok' => false, 'error' => 'Template name is required (max 120 characters).', 'id' => 0];
    }
    $design = itm_qr_generator_normalize_design($designRaw);
    $designJson = json_encode($design, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $findSql = 'SELECT id FROM qr_design_templates WHERE company_id = ? AND employee_id = ? AND name = ? AND deleted_at IS NULL LIMIT 1';
    $findStmt = mysqli_prepare($conn, $findSql);
    if (!$findStmt) {
        return ['ok' => false, 'error' => 'Database error.', 'id' => 0];
    }
    mysqli_stmt_bind_param($findStmt, 'iis', $companyId, $employeeId, $name);
    mysqli_stmt_execute($findStmt);
    $findRes = mysqli_stmt_get_result($findStmt);
    $existing = $findRes ? mysqli_fetch_assoc($findRes) : null;
    mysqli_stmt_close($findStmt);

    if ($existing) {
        $id = (int) ($existing['id'] ?? 0);
        $updSql = 'UPDATE qr_design_templates SET design_json = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL';
        $updStmt = mysqli_prepare($conn, $updSql);
        if (!$updStmt) {
            return ['ok' => false, 'error' => 'Database error.', 'id' => 0];
        }
        mysqli_stmt_bind_param($updStmt, 'siiii', $designJson, $actorId, $id, $companyId, $employeeId);
        $ok = mysqli_stmt_execute($updStmt);
        mysqli_stmt_close($updStmt);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not update template.', 'id' => 0];
        }
        return ['ok' => true, 'error' => '', 'id' => $id];
    }

    $insSql = 'INSERT INTO qr_design_templates (company_id, employee_id, name, design_json, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)';
    $insStmt = mysqli_prepare($conn, $insSql);
    if (!$insStmt) {
        return ['ok' => false, 'error' => 'Database error.', 'id' => 0];
    }
    mysqli_stmt_bind_param($insStmt, 'iissii', $companyId, $employeeId, $name, $designJson, $actorId, $actorId);
    $ok = mysqli_stmt_execute($insStmt);
    $newId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($insStmt);
    if (!$ok || $newId <= 0) {
        return ['ok' => false, 'error' => 'Could not save template.', 'id' => 0];
    }
    return ['ok' => true, 'error' => '', 'id' => $newId];
}

/**
 * @return array{ok:bool, error:string}
 */
function itm_qr_generator_delete_design_template($conn, $companyId, $employeeId, $id, $actorId)
{
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $id = (int) $id;
    $actorId = (int) $actorId;
    if (!($conn instanceof mysqli) || $companyId <= 0 || $employeeId <= 0 || $id <= 0) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }
    if (function_exists('itm_crud_build_soft_delete_sql')) {
        $sql = itm_crud_build_soft_delete_sql('qr_design_templates', 'WHERE id = ? AND company_id = ? AND employee_id = ?', $actorId);
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Database error.'];
        }
        mysqli_stmt_bind_param($stmt, 'iii', $id, $companyId, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok ? ['ok' => true, 'error' => ''] : ['ok' => false, 'error' => 'Template not found.'];
    }
    $sql = 'UPDATE qr_design_templates SET active = 0, deleted_by = ?, deleted_at = NOW() WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Database error.'];
    }
    mysqli_stmt_bind_param($stmt, 'iiii', $actorId, $id, $companyId, $employeeId);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok || $affected < 1) {
        return ['ok' => false, 'error' => 'Template not found.'];
    }
    return ['ok' => true, 'error' => ''];
}
