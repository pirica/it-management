<?php
/**
 * Room Types FK helpers — upgrade target shows booking_rooms_types.code (not name).
 */
require_once ROOT_PATH . 'includes/fk_dropdown_helpers.php';

if (!function_exists('brt_fk_label_column_for_field')) {
    function brt_fk_label_column_for_field($fieldName, mysqli $conn, string $refTable): string
    {
        $available = itm_fk_table_column_names($conn, $refTable);
        if ($fieldName === 'upgrade_to_room_type_id' && in_array('code', $available, true)) {
            return 'code';
        }

        return itm_fk_label_column_for_table($available);
    }
}

if (!function_exists('brt_fk_label_by_id')) {
    function brt_fk_label_by_id(mysqli $conn, array $fk, int $companyId, int $rawId, string $fieldName = ''): string
    {
        $id = (int) $rawId;
        if ($id <= 0) {
            return '';
        }

        $refTable = (string) ($fk['REFERENCED_TABLE_NAME'] ?? '');
        $refColumn = (string) ($fk['REFERENCED_COLUMN_NAME'] ?? 'id');
        if ($refTable === '' || !itm_is_safe_identifier($refTable) || !itm_is_safe_identifier($refColumn)) {
            return '';
        }

        $labelCol = brt_fk_label_column_for_field($fieldName, $conn, $refTable);
        if (!itm_is_safe_identifier($labelCol)) {
            return '';
        }

        if ($companyId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT `' . $labelCol . '` AS label FROM `' . $refTable . '` WHERE `' . $refColumn . '`=? AND company_id=? LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = ($res) ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if (is_array($row) && isset($row['label'])) {
                    return (string) $row['label'];
                }
            }
        }

        $fallback = mysqli_query(
            $conn,
            'SELECT `' . $labelCol . '` AS label FROM `' . $refTable . '` WHERE `' . $refColumn . '`=' . $id . ' LIMIT 1'
        );
        $fallbackRow = ($fallback) ? mysqli_fetch_assoc($fallback) : null;
        if (is_array($fallbackRow) && isset($fallbackRow['label'])) {
            return (string) $fallbackRow['label'];
        }

        return '';
    }
}

if (!function_exists('brt_fk_options')) {
    function brt_fk_options(mysqli $conn, array $fk, int $companyId, string $fieldName = ''): array
    {
        $table = $fk['REFERENCED_TABLE_NAME'];
        $col = $fk['REFERENCED_COLUMN_NAME'];
        if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($col)) {
            return [];
        }

        $labelCol = brt_fk_label_column_for_field($fieldName, $conn, (string) $table);
        $available = itm_fk_table_column_names($conn, (string) $table);

        $where = '';
        if (in_array('company_id', $available, true) && $companyId > 0) {
            $where = ' WHERE company_id=' . (int) $companyId;
        }

        $sql = 'SELECT `' . $col . '` AS id, `' . $labelCol . '` AS label FROM `' . $table . '`' . $where . ' ORDER BY label';
        $rows = [];
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('brt_form_columns_for_field_order')) {
    /**
     * @param array<int,array<string,mixed>> $fieldColumns
     * @param array<int,string> $fieldOrder
     * @return array<int,array<string,mixed>>
     */
    function brt_form_columns_for_field_order(array $fieldColumns, array $fieldOrder): array
    {
        $byField = [];
        foreach ($fieldColumns as $col) {
            $field = (string) ($col['Field'] ?? '');
            if ($field !== '') {
                $byField[$field] = $col;
            }
        }
        $ordered = [];
        foreach ($fieldOrder as $fieldName) {
            if (isset($byField[$fieldName])) {
                $ordered[] = $byField[$fieldName];
            }
        }
        return $ordered;
    }
}

if (!function_exists('brt_portal_rule_form_field_order')) {
    /**
     * Occupancy portal rules (subset of edit sections; kept for regression probes).
     *
     * @return array<int,string>
     */
    function brt_portal_rule_form_field_order(): array
    {
        return [
            'max_adults',
            'max_children',
            'max_babies',
            'min_adults',
            'child_max_age',
            'included_adults_per_room',
            'adults_only',
            'extra_bed_allowed',
            'max_extra_beds',
            'crib_included',
            'allow_mixed_types_in_group',
            'max_rooms_per_booking',
        ];
    }
}

if (!function_exists('brt_portal_rule_form_columns')) {
    /**
     * @param array<int,array<string,mixed>> $fieldColumns
     * @return array<int,array<string,mixed>>
     */
    function brt_portal_rule_form_columns(array $fieldColumns): array
    {
        return brt_form_columns_for_field_order($fieldColumns, brt_portal_rule_form_field_order());
    }
}

if (!function_exists('brt_edit_form_sections')) {
    /**
     * Create/edit cards for fields hidden from the list grid (view parity minus company_id).
     *
     * @return array<string,array{intro?:string,fields:array<int,string>}>
     */
    function brt_edit_form_sections(): array
    {
        return [
            'Room details' => [
                'fields' => [
                    'description',
                    'bed_summary',
                    'room_size_sqm',
                    'filter_tags',
                    'details_bullets',
                ],
            ],
            'Portal rules' => [
                'intro' => 'Occupancy and booking limits enforced on the guest portal (<code>max_total_guests</code> and <code>portal_bookable</code> stay on the main form above).',
                'fields' => brt_portal_rule_form_field_order(),
            ],
            'Stay rules' => [
                'fields' => [
                    'min_stay_nights',
                    'max_stay_nights',
                    'min_advance_booking_days',
                    'max_advance_booking_days',
                    'closed_to_arrival_days',
                    'closed_to_departure_days',
                ],
            ],
            'Guest policies' => [
                'fields' => [
                    'requires_approval',
                    'smoking_allowed',
                    'accessible_room',
                ],
            ],
            'Pets' => [
                'intro' => 'When <code>pets_allowed</code> is on, Step 3 special requests show pet controls on the booking portal.',
                'fields' => [
                    'pets_allowed',
                    'pet_max_weight_kg',
                    'pet_non_refundable_fee',
                    'portal_pet_daily_fee',
                ],
            ],
            'Portal pricing overrides' => [
                'intro' => 'Leave blank to inherit hotel defaults from Portal Rate Plans.',
                'fields' => [
                    'portal_extra_adult_supplement_percent',
                    'portal_child_nightly_supplement',
                    'portal_baby_nightly_supplement',
                    'portal_included_children_free',
                    'portal_single_occupancy_discount_percent',
                ],
            ],
            'Room upgrade offer' => [
                'fields' => [
                    'upgrade_to_room_type_id',
                    'upgrade_price_per_night',
                    'upgrade_pitch',
                ],
            ],
        ];
    }
}

if (!function_exists('brt_edit_form_section_columns')) {
    /**
     * @param array<int,array<string,mixed>> $fieldColumns
     * @return array<string,array{intro?:string,columns:array<int,array<string,mixed>>}>
     */
    function brt_edit_form_section_columns(array $fieldColumns): array
    {
        $sections = [];
        foreach (brt_edit_form_sections() as $title => $meta) {
            $columns = brt_form_columns_for_field_order($fieldColumns, (array) ($meta['fields'] ?? []));
            if ($columns === []) {
                continue;
            }
            $sections[$title] = [
                'intro' => isset($meta['intro']) ? (string) $meta['intro'] : '',
                'columns' => $columns,
            ];
        }
        return $sections;
    }
}
