<?php
/**
 * Offline dry-run validator for IDF device API payloads.
 *
 * Why: Allow repeated payload checks for Create Cable Link / Edit Port / Unlink
 * even when MySQL is offline, so JSON structures can be validated before runtime.
 *
 * Usage:
 *   php scripts/idfs_api_payload_dry_run.php /path/to/payloads.json
 *   cat payloads.json | php scripts/idfs_api_payload_dry_run.php --stdin
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This validator only runs in CLI mode.\n");
    exit(1);
}

function itm_dry_run_fail(array &$errors, string $message): void {
    $errors[] = $message;
}

function itm_dry_run_has_string_key(array $arr, string $key): bool {
    return array_key_exists($key, $arr) && is_string($arr[$key]);
}

function itm_dry_run_has_int_like_key(array $arr, string $key): bool {
    if (!array_key_exists($key, $arr)) {
        return false;
    }
    $value = $arr[$key];
    return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', trim($value)));
}

function itm_dry_run_has_nullable_int_like_key(array $arr, string $key): bool {
    if (!array_key_exists($key, $arr) || $arr[$key] === null || $arr[$key] === '') {
        return true;
    }
    return itm_dry_run_has_int_like_key($arr, $key);
}

function itm_dry_run_validate_port_update(array $payload, array &$errors): void {
    $requiredInt = ['port_id', 'port_type_id', 'status_id'];
    foreach ($requiredInt as $field) {
        if (!itm_dry_run_has_int_like_key($payload, $field)) {
            itm_dry_run_fail($errors, "port_update.$field must be an integer (or integer-like string).");
        }
    }

    $requiredString = ['label', 'connected_to', 'notes'];
    foreach ($requiredString as $field) {
        if (!itm_dry_run_has_string_key($payload, $field)) {
            itm_dry_run_fail($errors, "port_update.$field must be a string.");
        }
    }

    $nullableInt = ['vlan_id', 'speed_id', 'poe_id', 'cable_color_id'];
    foreach ($nullableInt as $field) {
        if (!itm_dry_run_has_nullable_int_like_key($payload, $field)) {
            itm_dry_run_fail($errors, "port_update.$field must be null/empty or an integer.");
        }
    }
}

function itm_dry_run_validate_link_create(array $payload, array &$errors): void {
    $requiredInt = ['port_id_a', 'port_id_b', 'status_id'];
    foreach ($requiredInt as $field) {
        if (!itm_dry_run_has_int_like_key($payload, $field)) {
            itm_dry_run_fail($errors, "link_create.$field must be an integer (or integer-like string).");
        }
    }

    $requiredString = ['cable_label', 'notes', 'linked_equipment_port', 'linked_destination_port'];
    foreach ($requiredString as $field) {
        if (!itm_dry_run_has_string_key($payload, $field)) {
            itm_dry_run_fail($errors, "link_create.$field must be a string.");
        }
    }

    $nullableInt = ['equipment_id', 'switch_port_id', 'cable_color_id'];
    foreach ($nullableInt as $field) {
        if (!itm_dry_run_has_nullable_int_like_key($payload, $field)) {
            itm_dry_run_fail($errors, "link_create.$field must be null/empty or an integer.");
        }
    }

    if (itm_dry_run_has_int_like_key($payload, 'port_id_a') && itm_dry_run_has_int_like_key($payload, 'port_id_b')) {
        if ((int)$payload['port_id_a'] === (int)$payload['port_id_b']) {
            itm_dry_run_fail($errors, 'link_create.port_id_a and link_create.port_id_b must be different.');
        }
    }
}

function itm_dry_run_validate_link_delete(array $payload, array &$errors): void {
    if (!itm_dry_run_has_int_like_key($payload, 'link_id')) {
        itm_dry_run_fail($errors, 'link_delete.link_id must be an integer (or integer-like string).');
    }
}

$sourceArg = $argv[1] ?? '';
$raw = '';

if ($sourceArg === '--stdin') {
    $raw = (string)stream_get_contents(STDIN);
} elseif ($sourceArg !== '') {
    if (!is_file($sourceArg)) {
        fwrite(STDERR, "Payload file not found: {$sourceArg}\n");
        exit(1);
    }
    $raw = (string)file_get_contents($sourceArg);
} else {
    fwrite(STDERR, "Usage: php scripts/idfs_api_payload_dry_run.php <payloads.json|--stdin>\n");
    exit(1);
}

$json = json_decode($raw, true);
if (!is_array($json)) {
    fwrite(STDERR, "Invalid JSON payload.\n");
    exit(1);
}

$errors = [];
if (!itm_dry_run_has_string_key($json, 'csrf_token') || trim((string)$json['csrf_token']) === '') {
    itm_dry_run_fail($errors, 'csrf_token must be a non-empty string.');
}

$sections = ['port_update', 'link_create', 'link_delete'];
foreach ($sections as $section) {
    if (!isset($json[$section]) || !is_array($json[$section])) {
        itm_dry_run_fail($errors, "{$section} must exist and be an object.");
    }
}

if (isset($json['port_update']) && is_array($json['port_update'])) {
    itm_dry_run_validate_port_update($json['port_update'], $errors);
}
if (isset($json['link_create']) && is_array($json['link_create'])) {
    itm_dry_run_validate_link_create($json['link_create'], $errors);
}
if (isset($json['link_delete']) && is_array($json['link_delete'])) {
    itm_dry_run_validate_link_delete($json['link_delete'], $errors);
}

if (!empty($errors)) {
    fwrite(STDERR, "Dry-run validation failed:\n");
    foreach ($errors as $err) {
        fwrite(STDERR, " - {$err}\n");
    }
    exit(1);
}

echo "Dry-run validation passed for port_update, link_create, and link_delete payloads.\n";
exit(0);
