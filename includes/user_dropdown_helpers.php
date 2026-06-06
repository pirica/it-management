<?php
/**
 * Shared user dropdown helpers for *_by fields on tenant-scoped forms.
 *
 * Why: users.company_id is the home tenant; user_companies grants access to other companies,
 * so company-scoped selects must list both without losing persisted created_by selections.
 */

if (!function_exists('itm_user_build_display_label')) {
    function itm_user_build_display_label(array $row): string
    {
        $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string)($row['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        return 'User #' . (int)($row['id'] ?? 0);
    }
}

if (!function_exists('itm_user_label_by_id_for_company')) {
    function itm_user_label_by_id_for_company(mysqli $conn, int $companyId, $rawId): string
    {
        if ($rawId === null || $rawId === '') {
            return '';
        }

        $id = (int)$rawId;
        if ($id <= 0) {
            return '';
        }

        if ($companyId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT u.username, u.first_name, u.last_name
                 FROM users u
                 WHERE u.id = ?
                   AND (
                        u.company_id = ?
                        OR EXISTS (
                            SELECT 1
                            FROM user_companies uc
                            WHERE uc.user_id = u.id
                              AND uc.company_id = ?
                              AND COALESCE(uc.active, 1) = 1
                        )
                   )
                 LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iii', $id, $companyId, $companyId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = ($res) ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if (is_array($row)) {
                    return itm_user_build_display_label($row);
                }
            }
        }

        $fallback = mysqli_query(
            $conn,
            'SELECT username, first_name, last_name FROM users WHERE id=' . $id . ' LIMIT 1'
        );
        $fallbackRow = ($fallback) ? mysqli_fetch_assoc($fallback) : null;
        if (is_array($fallbackRow)) {
            return itm_user_build_display_label($fallbackRow);
        }

        return '';
    }
}

if (!function_exists('itm_user_options_for_company')) {
    /**
     * @return array<int, array{id:int,label:string}>
     */
    function itm_user_options_for_company(mysqli $conn, int $companyId): array
    {
        $options = [];

        if ($companyId > 0) {
            $sql = 'SELECT DISTINCT u.id, u.username, u.first_name, u.last_name
                    FROM users u
                    LEFT JOIN user_companies uc
                      ON uc.user_id = u.id
                     AND uc.company_id = ' . (int)$companyId . '
                     AND COALESCE(uc.active, 1) = 1
                    WHERE COALESCE(u.active, 1) = 1
                      AND (u.company_id = ' . (int)$companyId . ' OR uc.user_id IS NOT NULL)
                    ORDER BY u.first_name ASC, u.last_name ASC, u.username ASC';
        } else {
            $sql = 'SELECT id, username, first_name, last_name
                    FROM users
                    WHERE COALESCE(active, 1) = 1
                    ORDER BY first_name ASC, last_name ASC, username ASC';
        }

        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $options[] = [
                'id' => (int)($row['id'] ?? 0),
                'label' => itm_user_build_display_label($row),
            ];
        }

        return $options;
    }
}

if (!function_exists('itm_user_append_selected_option')) {
    /**
     * @param array<int, array<string, mixed>> $options
     * @return array<int, array<string, mixed>>
     */
    function itm_user_append_selected_option(mysqli $conn, int $companyId, array $options, $selectedValue): array
    {
        $selectedId = (int)$selectedValue;
        if ($selectedId <= 0) {
            return $options;
        }

        foreach ($options as $option) {
            if ((int)($option['id'] ?? 0) === $selectedId) {
                return $options;
            }
        }

        $label = itm_user_label_by_id_for_company($conn, $companyId, $selectedId);
        if ($label !== '') {
            $options[] = ['id' => $selectedId, 'label' => $label];
        }

        return $options;
    }
}
