<?php
/**
 * Shared UTF-8 emoji corruption detection for sidebar / equipment-type seeds.
 *
 * Why: PowerShell SQL pipes without UTF-8 replace multibyte emoji with ASCII '?',
 * which tier2 must catch without relying on a live MySQL connection.
 */

if (!function_exists('itm_utf8_is_question_mark_corruption')) {
    function itm_utf8_is_question_mark_corruption(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return (bool)preg_match('/^\?+(\s|$)/', $value) || (bool)preg_match('/^\?+$/', $value);
    }
}

if (!function_exists('itm_utf8_sidebar_label_emoji_corrupt')) {
    /**
     * True when a sidebar label's leading token is only question marks (broken emoji).
     */
    function itm_utf8_sidebar_label_emoji_corrupt(string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return false;
        }
        $parts = preg_split('/\s+/', $label, 2);
        if (!is_array($parts) || !isset($parts[0])) {
            return false;
        }

        return itm_utf8_is_question_mark_corruption((string)$parts[0]);
    }
}

if (!function_exists('itm_utf8_parse_equipment_type_seed_rows')) {
    /**
     * @return array<int,array{name:string,emoji:string|null}>
     */
    function itm_utf8_parse_equipment_type_seed_rows(string $sqlPath): array
    {
        if (!is_file($sqlPath)) {
            return [];
        }

        $content = (string)file_get_contents($sqlPath);
        if ($content === '') {
            return [];
        }

        $rows = [];
        $pattern = '/INSERT\s+INTO\s+`equipment_types`\s*\([^)]+\)\s*VALUES\s*\(\s*\'1\'\s*,\s*\'\d+\'\s*,\s*\'([^\']*)\'\s*,\s*\'[^\']*\'\s*,\s*(NULL|\'([^\']*)\')\s*,/iu';
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = trim((string)($match[1] ?? ''));
                if ($name === '') {
                    continue;
                }
                $emoji = null;
                if (($match[2] ?? '') !== 'NULL') {
                    $emoji = (string)($match[3] ?? '');
                }
                $rows[] = ['name' => $name, 'emoji' => $emoji];
            }
        }

        return $rows;
    }
}

if (!function_exists('itm_utf8_audit_equipment_sidebar_sources')) {
    /**
     * Static audit: canonical is_* labels, base sidebar, and db/02_data.sql seeds.
     *
     * @return array<int,string>
     */
    function itm_utf8_audit_equipment_sidebar_sources(string $root): array
    {
        $root = rtrim($root, '/\\');
        $failures = [];

        if (!function_exists('itm_canonical_equipment_is_module_names')) {
            require_once $root . '/scripts/lib/equipment_type_modules.php';
        }
        if (!function_exists('itm_sidebar_module_default_label')) {
            require_once $root . '/includes/ui_config.php';
        }

        foreach (itm_canonical_equipment_is_module_names() as $moduleSlug) {
            $label = itm_sidebar_module_default_label($moduleSlug);
            if ($label === null) {
                $failures[] = 'Missing itm_sidebar_module_default_label() for ' . $moduleSlug;
                continue;
            }
            if (itm_utf8_sidebar_label_emoji_corrupt($label)) {
                $failures[] = 'Corrupt sidebar default label for ' . $moduleSlug . ': ' . $label;
            }
        }

        foreach (itm_sidebar_base_structure() as $section) {
            foreach (($section['items'] ?? []) as $item) {
                $itemId = (string)($item['id'] ?? '');
                if (strpos($itemId, 'is_') !== 0) {
                    continue;
                }
                $label = (string)($item['label'] ?? '');
                if (itm_utf8_sidebar_label_emoji_corrupt($label)) {
                    $failures[] = 'Corrupt base sidebar label for ' . $itemId . ': ' . $label;
                }
            }
        }

        $seedPath = $root . '/db/02_data.sql';
        $seedRows = itm_utf8_parse_equipment_type_seed_rows($seedPath);
        if ($seedRows === []) {
            $failures[] = 'Could not parse equipment_types seeds from db/02_data.sql';
        }

        $seedByName = [];
        foreach ($seedRows as $row) {
            $seedByName[strtolower(trim((string)$row['name']))] = $row;
        }

        foreach (itm_canonical_equipment_type_names() as $typeName) {
            $key = strtolower(trim($typeName));
            if (!isset($seedByName[$key])) {
                $failures[] = 'db/02_data.sql missing equipment_types seed for ' . $typeName;
                continue;
            }
            $seedEmoji = $seedByName[$key]['emoji'];
            if ($seedEmoji !== null && itm_utf8_is_question_mark_corruption($seedEmoji)) {
                $failures[] = 'db/02_data.sql corrupt field_edit_emoji for ' . $typeName . ': ' . $seedEmoji;
                continue;
            }
            $canonical = itm_equipment_type_resolve_field_edit_emoji($typeName, '');
            if ($canonical === '') {
                continue;
            }
            if ($seedEmoji === null) {
                if ($typeName !== 'Other') {
                    $failures[] = 'db/02_data.sql NULL field_edit_emoji for ' . $typeName . ' (expected ' . $canonical . ')';
                }
                continue;
            }
            if ($seedEmoji !== $canonical) {
                $failures[] = 'db/02_data.sql field_edit_emoji mismatch for ' . $typeName
                    . ': seed=' . $seedEmoji . ' canonical=' . $canonical;
            }
        }

        $sqlContent = (string)file_get_contents($seedPath);
        if (preg_match('/INSERT INTO `ui_configuration`[^;]*\'\?+ IT Controls\'/i', $sqlContent)) {
            $failures[] = 'db/02_data.sql ui_configuration.app_name seed looks question-mark corrupted';
        }

        return $failures;
    }
}
