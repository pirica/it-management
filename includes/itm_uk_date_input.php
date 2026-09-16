<?php
/**
 * UK dd/mmm/yyyy text field + native calendar picker (hidden type="date").
 */

if (!function_exists('itm_render_uk_date_input')) {
    /**
     * @param array{required?:bool,class?:string,min?:string,placeholder?:string,saved_report_filter?:bool} $options
     */
    function itm_render_uk_date_input($name, $id, $rawValue, array $options = [])
    {
        $name = trim((string) $name);
        $id = trim((string) $id);
        if ($name === '' || $id === '') {
            return;
        }
        $required = !empty($options['required']);
        $class = trim((string) ($options['class'] ?? 'form-control'));
        if ($class === '') {
            $class = 'form-control';
        }
        $minIso = trim((string) ($options['min'] ?? ''));
        $placeholder = trim((string) ($options['placeholder'] ?? 'dd/mmm/yyyy'));
        $savedReportFilter = !empty($options['saved_report_filter']);
        $iso = function_exists('itm_date_input_iso_value') ? itm_date_input_iso_value($rawValue) : '';
        $display = function_exists('itm_format_date_display') ? itm_format_date_display($rawValue) : '';
        $nativeId = $id . '_native';
        ?>
<div class="itm-uk-date-field">
<input type="text" name="<?php echo sanitize($name); ?>" id="<?php echo sanitize($id); ?>" class="<?php echo sanitize($class); ?> itm-uk-date-text" value="<?php echo sanitize($display); ?>" placeholder="<?php echo sanitize($placeholder); ?>" autocomplete="off" inputmode="numeric"<?php echo $required ? ' required' : ''; ?><?php echo $savedReportFilter ? ' data-itm-saved-report-filter="1"' : ''; ?>>
<input type="date" id="<?php echo sanitize($nativeId); ?>" class="itm-uk-date-native" value="<?php echo sanitize(substr($iso, 0, 10)); ?>"<?php echo $minIso !== '' ? ' min="' . sanitize($minIso) . '"' : ''; ?> aria-hidden="true" tabindex="-1">
<button type="button" class="btn btn-sm itm-uk-date-open" data-itm-uk-date-for="<?php echo sanitize($id); ?>" title="Pick date">📅</button>
</div>
        <?php
    }
}

if (!function_exists('itm_render_uk_datetime_input')) {
    /**
     * @param array{required?:bool,class?:string,placeholder?:string} $options
     */
    function itm_render_uk_datetime_input($name, $id, $rawValue, array $options = [])
    {
        $name = trim((string) $name);
        $id = trim((string) $id);
        if ($name === '' || $id === '') {
            return;
        }
        $required = !empty($options['required']);
        $class = trim((string) ($options['class'] ?? 'form-control'));
        if ($class === '') {
            $class = 'form-control';
        }
        $placeholder = trim((string) ($options['placeholder'] ?? 'dd/mmm/yyyy HH:mm'));
        $iso = function_exists('itm_datetime_input_local_value') ? itm_datetime_input_local_value($rawValue) : '';
        $display = function_exists('itm_format_datetime_display') ? itm_format_datetime_display($rawValue) : '';
        $nativeId = $id . '_native';
        ?>
<div class="itm-uk-date-field itm-uk-datetime-field">
<input type="text" name="<?php echo sanitize($name); ?>" id="<?php echo sanitize($id); ?>" class="<?php echo sanitize($class); ?> itm-uk-date-text itm-uk-datetime-text" value="<?php echo sanitize($display); ?>" placeholder="<?php echo sanitize($placeholder); ?>" autocomplete="off" inputmode="numeric"<?php echo $required ? ' required' : ''; ?>>
<input type="datetime-local" id="<?php echo sanitize($nativeId); ?>" class="itm-uk-date-native itm-uk-datetime-native" value="<?php echo sanitize($iso); ?>" aria-hidden="true" tabindex="-1">
<button type="button" class="btn btn-sm itm-uk-date-open itm-uk-datetime-open" data-itm-uk-date-for="<?php echo sanitize($id); ?>" title="Pick date and time">📅</button>
</div>
        <?php
    }
}
