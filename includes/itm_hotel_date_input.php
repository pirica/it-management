<?php
/**
 * Hospitality date text field + native calendar picker (31/Aug/2026).
 */

if (!function_exists('itm_render_hotel_date_input')) {
    /**
     * @param array{required?:bool,class?:string,min?:string} $options
     */
    function itm_render_hotel_date_input($name, $id, $rawValue, array $options = [])
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
        $iso = itm_parse_date_input($rawValue) ?? '';
        $display = itm_format_hotel_date_display($rawValue);
        $nativeId = $id . '_native';
        ?>
<div class="hb-hotel-date-field">
<input type="text" name="<?php echo sanitize($name); ?>" id="<?php echo sanitize($id); ?>" class="<?php echo sanitize($class); ?> hb-hotel-date-text" value="<?php echo sanitize($display); ?>" autocomplete="off"<?php echo $required ? ' required' : ''; ?>>
<input type="date" id="<?php echo sanitize($nativeId); ?>" class="hb-hotel-date-native" value="<?php echo sanitize(substr($iso, 0, 10)); ?>"<?php echo $minIso !== '' ? ' min="' . sanitize($minIso) . '"' : ''; ?> aria-hidden="true" tabindex="-1">
<button type="button" class="btn btn-sm hb-hotel-date-open" data-hb-hotel-date-for="<?php echo sanitize($id); ?>" title="Pick date">📅</button>
</div>
        <?php
    }
}
