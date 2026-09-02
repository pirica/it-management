<?php
/**
 * Integration webhooks module — hide secrets, URL validation, secret rotation.
 */

if (!function_exists('iw_module_hidden_fields')) {
    function iw_module_hidden_fields()
    {
        return ['secret_encrypted'];
    }
}

if (!function_exists('iw_module_filter_columns')) {
    function iw_module_filter_columns(array $columns)
    {
        $hidden = iw_module_hidden_fields();
        return array_values(array_filter($columns, function ($col) use ($hidden) {
            return !in_array((string) ($col['Field'] ?? ''), $hidden, true);
        }));
    }
}

if (!function_exists('iw_module_apply_post_rules')) {
    function iw_module_apply_post_rules($crudAction, array &$data, array &$errors)
    {
        if (!function_exists('itm_webhook_queue_validate_url')) {
            require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
        }
        $urlCheck = itm_webhook_queue_validate_url((string) ($data['target_url'] ?? ''));
        if (empty($urlCheck['ok'])) {
            $errors[] = (string) ($urlCheck['error'] ?? 'Invalid webhook URL.');
        } else {
            $data['target_url'] = (string) ($urlCheck['url'] ?? $data['target_url']);
        }

        $types = trim((string) ($data['event_types'] ?? ''));
        if ($types === '') {
            $errors[] = 'Select at least one event type.';
        } else {
            $allowed = itm_webhook_queue_event_types();
            $parts = array_map('trim', explode(',', $types));
            $parts = array_values(array_filter($parts));
            foreach ($parts as $part) {
                if ($part !== '*' && !in_array($part, $allowed, true)) {
                    $errors[] = 'Unknown event type: ' . $part;
                }
            }
            $data['event_types'] = implode(',', $parts);
        }

        if ($crudAction === 'create') {
            $plain = itm_webhook_queue_generate_secret();
            $data['secret_encrypted'] = itm_webhook_queue_encrypt_secret($plain);
        } elseif (!empty($_POST['rotate_secret'])) {
            $plain = itm_webhook_queue_generate_secret();
            $data['secret_encrypted'] = itm_webhook_queue_encrypt_secret($plain);
        }
    }
}
