<?php
/**
 * Replace scaffold native date/datetime-local inputs with UK dd/mmm/yyyy widgets.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Patches flattened scaffold modules under <code>modules/</code> to use <code>itm_render_uk_date_input()</code> and <code>itm_render_uk_datetime_input()</code> instead of native <code>type="date"</code> / <code>datetime-local</code> on create/edit forms.<br>
Also inserts <code>itm_crud_coerce_post_date_value()</code> in POST handlers so UK text parses to MySQL <code>Y-m-d</code> / <code>Y-m-d H:i:s</code>.<br>
Skips audit-exempt modules (<code>reports</code>, <code>ops_report</code>, <code>settings</code>, <code>backup_tape_log</code>, <code>birthdays</code>, <code>resignations</code>, <code>calendar</code>, <code>explorer</code>, <code>hotel*</code>).<br>
CLI: <code>php scripts/apply_crud_uk_date_inputs.php</code> then <code>php scripts/apply_crud_uk_date_inputs.php --apply</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_module_date_format_display_audit.php';

$boot = itm_apply_script_bootstrap('Apply CRUD UK date inputs');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/\\');

$formPatterns = [
    [
        'old' => <<<'ITM_FORM_OLD'
                            <?php elseif ($isDateTime): ?>
                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
                            <?php elseif ($isDate): ?>
                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
ITM_FORM_OLD
        ,
        'new' => <<<'ITM_FORM_NEW'
                            <?php elseif ($isDateTime): ?>
                                <?php itm_render_uk_datetime_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
                            <?php elseif ($isDate): ?>
                                <?php itm_render_uk_date_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
ITM_FORM_NEW
    ],
    [
        'old' => <<<'ITM_FORM_OLD'
        <?php elseif ($isDateTime): ?>
            <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr((string) $displayVal, 0, 16))); ?>">
        <?php elseif ($isDate): ?>
            <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr((string) $displayVal, 0, 10)); ?>">
ITM_FORM_OLD
        ,
        'new' => <<<'ITM_FORM_NEW'
        <?php elseif ($isDateTime): ?>
            <?php itm_render_uk_datetime_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
        <?php elseif ($isDate): ?>
            <?php itm_render_uk_date_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
ITM_FORM_NEW
    ],
    [
        'old' => <<<'ITM_FORM_OLD'
                            <?php elseif ($isDateTime): ?>
                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
    <?php elseif ($isDate): ?>
                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
ITM_FORM_OLD
        ,
        'new' => <<<'ITM_FORM_NEW'
                            <?php elseif ($isDateTime): ?>
                                <?php itm_render_uk_datetime_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
                            <?php elseif ($isDate): ?>
                                <?php itm_render_uk_date_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
ITM_FORM_NEW
    ],
    [
        'old' => <<<'ITM_FORM_OLD'
                            <?php elseif ($isDateTime): ?>
                                <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
                            <?php elseif ($isDate): ?>
                                <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
ITM_FORM_OLD
        ,
        'new' => <<<'ITM_FORM_NEW'
                            <?php elseif ($isDateTime): ?>
                                <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                                <?php itm_render_uk_datetime_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
                            <?php elseif ($isDate): ?>
                                <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                                <?php itm_render_uk_date_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
ITM_FORM_NEW
    ],
    [
        'old' => <<<'ITM_FORM_OLD'
                            <?php elseif ($isDate): ?>
                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>" placeholder="dd/mmm/yyyy">
ITM_FORM_OLD
        ,
        'new' => <<<'ITM_FORM_NEW'
                            <?php elseif ($isDate): ?>
                                <?php itm_render_uk_date_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
ITM_FORM_NEW
    ],
    [
        'old' => <<<'ITM_FORM_OLD'
                                            <?php elseif ($isDateTime): ?>
                                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
                                            <?php elseif ($isDate): ?>
                                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
ITM_FORM_OLD
        ,
        'new' => <<<'ITM_FORM_NEW'
                                            <?php elseif ($isDateTime): ?>
                                                <?php itm_render_uk_datetime_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
                                            <?php elseif ($isDate): ?>
                                                <?php itm_render_uk_date_input((string) $name, itm_crud_dom_input_id($name, 'dt'), (string) $displayVal); ?>
ITM_FORM_NEW
    ],
];

$postPostSuperglobal = '_' . 'POST';
$postAnchor = "        // Generic value processing and numeric validation\n        \$value = \${$postPostSuperglobal}[\$name] ?? null;";
$postReplacement = <<<ITM_POST_PATCH
        // Generic value processing and numeric validation
        \$value = \${$postPostSuperglobal}[\$name] ?? null;
        if (\$value !== '' && \$value !== null && function_exists('itm_crud_coerce_post_date_value')) {
            \$colTypeRaw = (string) (\$col['Type'] ?? '');
            if (function_exists('itm_crud_column_type_is_datetime') && itm_crud_column_type_is_datetime(strtolower(\$colTypeRaw))) {
                \$coercedDate = itm_crud_coerce_post_date_value(\$value, \$colTypeRaw);
                if (\$coercedDate === false) {
                    \$errors[] = cr_humanize_field(\$name) . ' must be a valid date (dd/mmm/yyyy).';
                    \$data[\$name] = (string) \$value;
                    continue;
                }
                if (\$coercedDate === null) {
                    \$value = null;
                } else {
                    \$value = \$coercedDate;
                }
            }
        }
ITM_POST_PATCH;

$changed = [];
$skipped = [];
$moduleDirs = glob($root . '/modules/*', GLOB_ONLYDIR) ?: [];

foreach ($moduleDirs as $moduleDir) {
    $slug = basename($moduleDir);
    if (itm_module_date_format_display_audit_is_exempt_module($slug)) {
        $skipped[] = 'modules/' . $slug . ' (audit exempt)';
        continue;
    }

    $paths = array_merge(
        glob($moduleDir . '/*.php') ?: [],
        glob($moduleDir . '/includes/*.php') ?: []
    );

    $includesDir = $moduleDir . '/includes';
    if (is_dir($includesDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($includesDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
                $paths[] = $fileInfo->getPathname();
            }
        }
    }
    $paths = array_values(array_unique($paths));

    foreach ($paths as $path) {
        $rel = itm_apply_script_rel_path($root, $path);
        $content = file_get_contents($path);
        if ($content === false) {
            $skipped[] = $rel . ' (unreadable)';
            continue;
        }

        $original = $content;
        $fileChanged = false;

        if (strpos($content, $postAnchor) !== false
            && strpos($content, 'itm_crud_coerce_post_date_value') === false) {
            $content = str_replace($postAnchor, $postReplacement, $content);
            $fileChanged = true;
        }

        foreach ($formPatterns as $pattern) {
            if (strpos($content, $pattern['old']) !== false) {
                $content = str_replace($pattern['old'], $pattern['new'], $content);
                $fileChanged = true;
            }
        }

        if ($fileChanged && $content !== $original) {
            if ($apply) {
                file_put_contents($path, $content);
            }
            $changed[] = $rel;
        }
    }
}

$bespokePatches = [
    'modules/audit_logs/index.php' => [
        '<input type="date" name="date_from" value="<?php echo sanitize($dateFrom); ?>">' =>
        '<?php itm_render_uk_date_input(\'date_from\', \'audit-date-from\', (string) $dateFrom); ?>',
        '<input type="date" name="date_to" value="<?php echo sanitize($dateTo); ?>">' =>
        '<?php itm_render_uk_date_input(\'date_to\', \'audit-date-to\', (string) $dateTo); ?>',
    ],
    'modules/equipment/view.php' => [
        '<input type="date" name="disposal_date" value="<?php echo sanitize($equipmentDisposalDateDefault); ?>">' =>
        '<?php itm_render_uk_date_input(\'disposal_date\', \'equipment-disposal-date\', (string) $equipmentDisposalDateDefault); ?>',
    ],
    'modules/employees/includes/profile_birthday_fields.php' => [
        '<input type="date" name="birthday" value="<?= sanitize((string)($form[\'birthday\'] ?? \'\')) ?>">' =>
        '<?php itm_render_uk_date_input(\'birthday\', \'employee-birthday\', (string) ($form[\'birthday\'] ?? \'\')); ?>',
    ],
    'modules/employees/includes/profile_request_fields.php' => [
        '<input type="date" name="request_date" value="<?= sanitize((string)($form[\'request_date\'] ?? \'\')) ?>">' =>
        '<?php itm_render_uk_date_input(\'request_date\', \'employee-request-date\', (string) ($form[\'request_date\'] ?? \'\')); ?>',
    ],
    'modules/employees/includes/profile_start_date_field.php' => [
        '<input type="date" name="start_date" value="<?= sanitize((string)($form[\'start_date\'] ?? \'\')) ?>">' =>
        '<?php itm_render_uk_date_input(\'start_date\', \'employee-start-date\', (string) ($form[\'start_date\'] ?? \'\')); ?>',
    ],
    'modules/employees/includes/profile_termination_date_field.php' => [
        '<input type="date" name="termination_date" value="<?= sanitize((string)($form[\'termination_date\'] ?? \'\')) ?>">' =>
        '<?php itm_render_uk_date_input(\'termination_date\', \'employee-termination-date\', (string) ($form[\'termination_date\'] ?? \'\')); ?>',
    ],
    'modules/inventory_items/create.php' => [
        '<input type="date" name="storage_date" value="<?php echo sanitize((string)($data[\'storage_date\'] ?? \'\')); ?>">' =>
        '<?php itm_render_uk_date_input(\'storage_date\', \'inventory-storage-date\', (string) ($data[\'storage_date\'] ?? \'\')); ?>',
    ],
    'modules/myactivity/index.php' => [
        '<input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo sanitize($dateFrom); ?>">' =>
        '<?php itm_render_uk_date_input(\'date_from\', \'date_from\', (string) $dateFrom, [\'class\' => \'form-control\']); ?>',
        '<input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo sanitize($dateTo); ?>">' =>
        '<?php itm_render_uk_date_input(\'date_to\', \'date_to\', (string) $dateTo, [\'class\' => \'form-control\']); ?>',
    ],
    'modules/expenses/index.php' => [
        '<input type="date" id="expenseDateFrom" name="date_from" value="<?php echo sanitize($expenseDateFrom); ?>" data-itm-saved-report-filter="1">' =>
        '<?php itm_render_uk_date_input(\'date_from\', \'expenseDateFrom\', (string) $expenseDateFrom, [\'saved_report_filter\' => true]); ?>',
        '<input type="date" id="expenseDateTo" name="date_to" value="<?php echo sanitize($expenseDateTo); ?>" data-itm-saved-report-filter="1">' =>
        '<?php itm_render_uk_date_input(\'date_to\', \'expenseDateTo\', (string) $expenseDateTo, [\'saved_report_filter\' => true]); ?>',
    ],
    'modules/private_contacts/edit_form.php' => [
        '<input type="date" name="birthday" class="form-control" value="<?php echo $contact[\'birthday\'] ?? \'\'; ?>">' =>
        '<?php itm_render_uk_date_input(\'birthday\', \'pc-birthday\', (string) ($contact[\'birthday\'] ?? \'\'), [\'class\' => \'form-control\']); ?>',
        '<input type="date" name="event1_value" class="form-control" value="<?php echo $contact[\'event1_value\'] ?? \'\'; ?>">' =>
        '<?php itm_render_uk_date_input(\'event1_value\', \'pc-event1-value\', (string) ($contact[\'event1_value\'] ?? \'\'), [\'class\' => \'form-control\']); ?>',
    ],
    'modules/tickets/create.php' => [
        '<input type="datetime-local" name="created_at" value="<?php echo sanitize((string)($data[\'created_at\'] ?? \'\')); ?>">' =>
        '<?php itm_render_uk_datetime_input(\'created_at\', \'ticket-created-at\', (string) ($data[\'created_at\'] ?? \'\')); ?>',
        '<input type="date" name="due_date" value="<?php echo sanitize((string)($data[\'due_date\'] ?? \'\')); ?>">' =>
        '<?php itm_render_uk_date_input(\'due_date\', \'ticket-due-date\', (string) ($data[\'due_date\'] ?? \'\')); ?>',
    ],
    'modules/todo/index.php' => [
        '<input type="datetime-local" name="due_date" value="<?php echo isset($data["due_date"]) ? str_replace(" ", "T", substr($data["due_date"], 0, 16)) : ""; ?>">' =>
        '<?php itm_render_uk_datetime_input(\'due_date\', \'todo-due-date\', (string) ($data[\'due_date\'] ?? \'\')); ?>',
        '<input type="datetime-local" name="reminder_at" value="<?php echo isset($data["reminder_at"]) ? str_replace(" ", "T", substr($data["reminder_at"], 0, 16)) : ""; ?>">' =>
        '<?php itm_render_uk_datetime_input(\'reminder_at\', \'todo-reminder-at\', (string) ($data[\'reminder_at\'] ?? \'\')); ?>',
    ],
    'modules/notes/index.php' => [
        '<input type="datetime-local" name="reminder_at" id="reminder_at_input" value="<?php echo isset($data["reminder_at"]) ? str_replace(" ", "T", substr($data["reminder_at"], 0, 16)) : ""; ?>">' =>
        '<?php itm_render_uk_datetime_input(\'reminder_at\', \'reminder_at_input\', (string) ($data[\'reminder_at\'] ?? \'\')); ?>',
    ],
    'modules/visitors_access_log/index.php' => [
        '<input type="datetime-local" name="date_time_in" class="form-control" value="<?= !empty($data[\'date_time_in\']) ? date(\'Y-m-d\\TH:i\', strtotime($data[\'date_time_in\'])) : \'\' ?>">' =>
        '<?php itm_render_uk_datetime_input(\'date_time_in\', \'val-date-time-in\', (string) ($data[\'date_time_in\'] ?? \'\'), [\'class\' => \'form-control\']); ?>',
        '<input type="datetime-local" name="date_time_out" class="form-control" value="<?= !empty($data[\'date_time_out\']) ? date(\'Y-m-d\\TH:i\', strtotime($data[\'date_time_out\'])) : \'\' ?>">' =>
        '<?php itm_render_uk_datetime_input(\'date_time_out\', \'val-date-time-out\', (string) ($data[\'date_time_out\'] ?? \'\'), [\'class\' => \'form-control\']); ?>',
    ],
    'modules/equipment/create.php' => [
        '<div class="form-group"><label>Warranty Expiry</label><input type="date" name="warranty_expiry" value="<?php echo sanitize($data[\'warranty_expiry\']); ?>"></div>' =>
        '<div class="form-group"><label>Warranty Expiry</label><?php itm_render_uk_date_input(\'warranty_expiry\', \'equipment-warranty-expiry\', (string) ($data[\'warranty_expiry\'] ?? \'\')); ?></div>',
        '<div class="form-group"><label>Certificate Expiry</label><input type="date" name="certificate_expiry" value="<?php echo sanitize($data[\'certificate_expiry\']); ?>"></div>' =>
        '<div class="form-group"><label>Certificate Expiry</label><?php itm_render_uk_date_input(\'certificate_expiry\', \'equipment-certificate-expiry\', (string) ($data[\'certificate_expiry\'] ?? \'\')); ?></div>',
        '<div class="form-group"><label>Purchase Date</label><input type="date" name="purchase_date" value="<?php echo sanitize($data[\'purchase_date\']); ?>"></div>' =>
        '<div class="form-group"><label>Purchase Date</label><?php itm_render_uk_date_input(\'purchase_date\', \'equipment-purchase-date\', (string) ($data[\'purchase_date\'] ?? \'\')); ?></div>',
        '<div class="form-group"><label>Depreciation start</label><input type="date" name="depreciation_start_date" value="<?php echo sanitize($data[\'depreciation_start_date\'] ?? \'\'); ?>"></div>' =>
        '<div class="form-group"><label>Depreciation start</label><?php itm_render_uk_date_input(\'depreciation_start_date\', \'equipment-depreciation-start\', (string) ($data[\'depreciation_start_date\'] ?? \'\')); ?></div>',
        '<div class="form-group"><label>Disposal date</label><input type="date" name="disposal_date" value="<?php echo sanitize($data[\'disposal_date\'] ?? \'\'); ?>"></div>' =>
        '<div class="form-group"><label>Disposal date</label><?php itm_render_uk_date_input(\'disposal_date\', \'equipment-disposal-date-create\', (string) ($data[\'disposal_date\'] ?? \'\')); ?></div>',
        '<div class="form-group"><label>Workstation OS Installed On</label><input type="date" name="workstation_os_installed_on" value="<?php echo sanitize($data[\'workstation_os_installed_on\'] ?? \'\'); ?>"></div>' =>
        '<div class="form-group"><label>Workstation OS Installed On</label><?php itm_render_uk_date_input(\'workstation_os_installed_on\', \'equipment-ws-os-installed\', (string) ($data[\'workstation_os_installed_on\'] ?? \'\')); ?></div>',
    ],
];

foreach ($bespokePatches as $rel => $replacements) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        $skipped[] = $rel . ' (missing bespoke target)';
        continue;
    }
    $content = file_get_contents($path);
    if ($content === false) {
        $skipped[] = $rel . ' (unreadable bespoke)';
        continue;
    }
    $original = $content;
    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            $content = str_replace($old, $new, $content);
        }
    }
    if ($content !== $original) {
        if ($apply) {
            file_put_contents($path, $content);
        }
        $changed[] = $rel . ' (bespoke)';
    }
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' file(s) with UK date/datetime form widgets.' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' files', $changed);
itm_apply_script_echo_list('Skipped', $skipped);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_crud_uk_date_inputs.php');

itm_script_output_end();
exit(0);
