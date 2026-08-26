<?php
/**
 * UK dd/mmm/yyyy text field + native calendar picker (hidden type="date").
 */

if (!function_exists('itm_render_uk_date_input')) {
    /**
     * @param array{required?:bool,class?:string,min?:string,placeholder?:string} $options
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
        $iso = function_exists('itm_date_input_iso_value') ? itm_date_input_iso_value($rawValue) : '';
        $display = function_exists('itm_format_date_display') ? itm_format_date_display($rawValue) : '';
        $nativeId = $id . '_native';
        ?>
<div class="itm-uk-date-field">
<input type="text" name="<?php echo sanitize($name); ?>" id="<?php echo sanitize($id); ?>" class="<?php echo sanitize($class); ?> itm-uk-date-text" value="<?php echo sanitize($display); ?>" placeholder="<?php echo sanitize($placeholder); ?>" autocomplete="off" inputmode="numeric"<?php echo $required ? ' required' : ''; ?>>
<input type="date" id="<?php echo sanitize($nativeId); ?>" class="itm-uk-date-native" value="<?php echo sanitize(substr($iso, 0, 10)); ?>"<?php echo $minIso !== '' ? ' min="' . sanitize($minIso) . '"' : ''; ?> aria-hidden="true" tabindex="-1">
<button type="button" class="btn btn-sm itm-uk-date-open" data-itm-uk-date-for="<?php echo sanitize($id); ?>" title="Pick date">📅</button>
</div>
        <?php
    }
}
