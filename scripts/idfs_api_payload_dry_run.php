<?php
/**
 * Offline JSON payload validator for IDF Device API endpoints.
 * Why: Allows repeatable structure checks during development without requiring MySQL.
 *
 * Usage examples:
 *   php scripts/idfs_api_payload_dry_run.php --endpoint=port_update --file=/tmp/payload.json
 *   php scripts/idfs_api_payload_dry_run.php --endpoint=link_create --json='{"port_id_a":1,"port_id_b":2}'
 *   php scripts/idfs_api_payload_dry_run.php --samples
 */

declare(strict_types=1);

function itm_dry_fail(string $message, int $code = 1): void
{
    fwrite(STDERR, "ERROR: {$message}" . PHP_EOL);
    exit($code);
}

function itm_is_int_like($value): bool
{
    if (is_int($value)) {
        return true;
    }
    if (is_string($value) && $value !== '' && preg_match('/^-?\d+$/', $value)) {
        return true;
    }
    return false;
}

function itm_validate_required_int(array $payload, string $field, array &$errors, bool $mustBePositive = true): void
{
    if (!array_key_exists($field, $payload)) {
        $errors[] = "Missing required field: {$field}";
        return;
    }
    if (!itm_is_int_like($payload[$field])) {
        $errors[] = "Field {$field} must be an integer.";
        return;
    }
    if ($mustBePositive && (int)$payload[$field] <= 0) {
        $errors[] = "Field {$field} must be > 0.";
    }
}

function itm_validate_optional_int(array $payload, string $field, array &$errors): void
{
    if (!array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
        return;
    }
    if (!itm_is_int_like($payload[$field])) {
        $errors[] = "Field {$field} must be an integer when provided.";
    }
}

function itm_validate_optional_string(array $payload, string $field, array &$errors): void
{
    if (!array_key_exists($field, $payload) || $payload[$field] === null) {
        return;
    }
    if (!is_string($payload[$field]) && !is_numeric($payload[$field])) {
        $errors[] = "Field {$field} must be a string when provided.";
    }
}

function itm_validate_endpoint(string $endpoint, array $payload): array
{
    $errors = [];

    if (!array_key_exists('csrf_token', $payload) || trim((string)$payload['csrf_token']) === '') {
        $errors[] = 'Missing required field: csrf_token';
    }

    if ($endpoint === 'port_update') {
        itm_validate_required_int($payload, 'port_id', $errors, true);

        if (!array_key_exists('port_type_id', $payload) && !array_key_exists('port_type', $payload)) {
            $errors[] = 'Either port_type_id or port_type is required.';
        }

        itm_validate_optional_int($payload, 'status_id', $errors);
        itm_validate_optional_int($payload, 'vlan_id', $errors);
        itm_validate_optional_int($payload, 'speed_id', $errors);
        itm_validate_optional_int($payload, 'poe_id', $errors);
        itm_validate_optional_int($payload, 'cable_color_id', $errors);

        foreach (['label', 'connected_to', 'notes', 'port_type', 'status', 'vlan', 'speed', 'poe'] as $field) {
            itm_validate_optional_string($payload, $field, $errors);
        }
    } elseif ($endpoint === 'link_create') {
        itm_validate_required_int($payload, 'port_id_a', $errors, true);
        itm_validate_required_int($payload, 'port_id_b', $errors, true);
        itm_validate_optional_int($payload, 'equipment_id', $errors);
        itm_validate_optional_int($payload, 'switch_port_id', $errors);
        itm_validate_optional_int($payload, 'cable_color_id', $errors);
        itm_validate_optional_int($payload, 'status_id', $errors);

        foreach (['cable_label', 'notes', 'linked_equipment_port', 'linked_destination_port', 'status'] as $field) {
            itm_validate_optional_string($payload, $field, $errors);
        }

        $a = isset($payload['port_id_a']) && itm_is_int_like($payload['port_id_a']) ? (int)$payload['port_id_a'] : 0;
        $b = isset($payload['port_id_b']) && itm_is_int_like($payload['port_id_b']) ? (int)$payload['port_id_b'] : 0;
        if ($a > 0 && $b > 0 && $a === $b) {
            $errors[] = 'port_id_a and port_id_b cannot be the same value.';
        }

        $switchPortId = isset($payload['switch_port_id']) && itm_is_int_like($payload['switch_port_id']) ? (int)$payload['switch_port_id'] : 0;
        $equipmentId = isset($payload['equipment_id']) && itm_is_int_like($payload['equipment_id']) ? (int)$payload['equipment_id'] : 0;
        if ($switchPortId > 0 && $equipmentId <= 0) {
            $errors[] = 'equipment_id is required when switch_port_id is provided.';
        }
    } elseif ($endpoint === 'link_delete') {
        itm_validate_required_int($payload, 'link_id', $errors, true);
    } else {
        $errors[] = 'Unsupported endpoint. Allowed: port_update, link_create, link_delete.';
    }

    return $errors;
}

function itm_print_samples(): void
{
    $samples = [
        'port_update' => [
            'csrf_token' => 'dry-run-token',
            'port_id' => 17,
            'port_type_id' => 1,
            'status_id' => 2,
            'label' => 'Uplink to Core',
            'connected_to' => 'Pos 4 • SN-SRV-001 • Port 3',
            'vlan_id' => 10,
            'speed_id' => 3,
            'poe_id' => 1,
            'cable_color_id' => 5,
            'notes' => 'Dry run update sample'
        ],
        'link_create' => [
            'csrf_token' => 'dry-run-token',
            'port_id_a' => 17,
            'port_id_b' => 53,
            'equipment_id' => 4,
            'switch_port_id' => 88,
            'cable_color_id' => 5,
            'cable_label' => 'Patch C-17',
            'notes' => 'Dry run create sample',
            'status_id' => 2,
            'linked_destination_port' => '3'
        ],
        'link_delete' => [
            'csrf_token' => 'dry-run-token',
            'link_id' => 101
        ],
    ];

    foreach ($samples as $endpoint => $payload) {
        echo "=== {$endpoint} ===" . PHP_EOL;
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL;
    }
}

$opts = getopt('', ['endpoint:', 'file:', 'json:', 'samples']);
if (isset($opts['samples'])) {
    itm_print_samples();
    exit(0);
}

$endpoint = trim((string)($opts['endpoint'] ?? ''));
if ($endpoint === '') {
    itm_dry_fail('Please provide --endpoint=port_update|link_create|link_delete');
}

$raw = null;
if (isset($opts['json'])) {
    $raw = (string)$opts['json'];
} elseif (isset($opts['file'])) {
    $file = (string)$opts['file'];
    if (!is_file($file)) {
        itm_dry_fail('JSON file not found: ' . $file);
    }
    $raw = (string)file_get_contents($file);
}

if ($raw === null) {
    itm_dry_fail('Provide payload with --json or --file, or use --samples.');
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    itm_dry_fail('Invalid JSON payload.');
}

$errors = itm_validate_endpoint($endpoint, $payload);
if ($errors) {
    echo "INVALID payload for {$endpoint}" . PHP_EOL;
    foreach ($errors as $err) {
        echo " - {$err}" . PHP_EOL;
    }
    exit(2);
}

echo "VALID payload for {$endpoint}" . PHP_EOL;
exit(0);
