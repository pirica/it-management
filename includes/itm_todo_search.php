<?php
/**
 * Todo list search helpers.
 *
 * Why: Search must match category, department, and assignee labels shown in the task UI,
 * not only title/description. CSV *_id columns use FIND_IN_SET + EXISTS.
 */

if (!function_exists('itm_todo_build_search_clause')) {
    /**
     * @param string $searchRaw Trimmed search text from the request.
     * @return array{sql:string,types:string,params:array<int,string>}
     */
    function itm_todo_build_search_clause($searchRaw)
    {
        $searchRaw = trim((string)$searchRaw);
        if ($searchRaw === '') {
            return ['sql' => '', 'types' => '', 'params' => []];
        }

        $searchTerm = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_'))
            ? $searchRaw
            : '%' . $searchRaw . '%';

        $parts = [
            't.title LIKE ?',
            't.description LIKE ?',
            "EXISTS (SELECT 1 FROM todo_categories tc WHERE FIND_IN_SET(tc.id, t.category_id) AND tc.name LIKE ?)",
            "EXISTS (SELECT 1 FROM departments d WHERE FIND_IN_SET(d.id, t.department_id) AND (d.name LIKE ? OR d.code LIKE ?))",
            "EXISTS (
                SELECT 1 FROM employees ae
                WHERE FIND_IN_SET(ae.id, t.assigned_to_employee_id)
                  AND (
                    ae.username LIKE ?
                    OR COALESCE(ae.display_name, '') LIKE ?
                    OR COALESCE(ae.first_name, '') LIKE ?
                    OR COALESCE(ae.last_name, '') LIKE ?
                    OR CONCAT(COALESCE(ae.first_name, ''), ' ', COALESCE(ae.last_name, '')) LIKE ?
                  )
            )",
        ];

        return [
            'sql' => ' AND (' . implode(' OR ', $parts) . ')',
            'types' => 'ssssssssss',
            'params' => array_fill(0, 10, $searchTerm),
        ];
    }
}
