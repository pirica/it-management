<?php
/**
 * Ops Report cross-date search list helpers (sort + pagination for All dates results).
 *
 * Mirrors module logic in modules/ops_report/index.php — keep in sync when search list changes.
 */

if (!function_exists('opr_search_cross_date_rows_from_hits')) {
    function opr_search_cross_date_rows_from_hits(array $hits) {
        $rows = [];
        foreach ($hits as $reportDate => $sections) {
            $rows[] = [
                'report_date' => (string)$reportDate,
                'sections' => $sections,
                'sections_label' => implode(', ', $sections),
            ];
        }
        return $rows;
    }
}

if (!function_exists('opr_search_cross_date_sort_rows')) {
    function opr_search_cross_date_sort_rows(array $rows, $sort, $dir) {
        $sort = in_array($sort, ['report_date', 'sections'], true) ? $sort : 'report_date';
        $dir = strtoupper((string)$dir) === 'ASC' ? 'ASC' : 'DESC';

        usort($rows, static function (array $a, array $b) use ($sort, $dir) {
            if ($sort === 'report_date') {
                $cmp = strcmp($a['report_date'], $b['report_date']);
            } else {
                $cmp = strcasecmp($a['sections_label'], $b['sections_label']);
            }
            if ($cmp === 0) {
                $cmp = strcmp($a['report_date'], $b['report_date']);
            }
            return $dir === 'ASC' ? $cmp : -$cmp;
        });

        return $rows;
    }
}

if (!function_exists('opr_search_cross_date_paginate_rows')) {
    function opr_search_cross_date_paginate_rows(array $rows, $page, $perPage) {
        $total = count($rows);
        $perPage = max(1, (int)$perPage);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min((int)$page, $totalPages));
        $offset = ($page - 1) * $perPage;

        return [
            'rows' => array_slice($rows, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'offset' => $offset,
        ];
    }
}
