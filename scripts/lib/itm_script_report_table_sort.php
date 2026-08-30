<?php
/**
 * Shared sortable report-table helpers for script browser HTML and CLI output.
 */

if (!function_exists('itm_script_report_table_sort_base_columns')) {
    /**
     * @return array<string, string>
     */
    function itm_script_report_table_sort_base_columns(): array
    {
        return [
            'table' => 'table',
            'slug' => 'slug',
            'sample_data' => 'sample_data',
        ];
    }
}

if (!function_exists('itm_script_report_table_sort_company_columns')) {
    /**
     * @return array<string, string>
     */
    function itm_script_report_table_sort_company_columns(): array
    {
        return array_merge(itm_script_report_table_sort_base_columns(), [
            'tenant' => 'tenant_status',
            'tenant_rows' => 'tenant_rows',
        ]);
    }
}

if (!function_exists('itm_script_report_table_sort_resolve')) {
    /**
     * @param array<string, string> $allowedColumns
     * @return array{sort:string,dir:string}
     */
    function itm_script_report_table_sort_resolve(bool $isCli, array $allowedColumns): array
    {
        $sort = '';
        $dir = 'asc';
        $allowedKeys = array_keys($allowedColumns);

        if ($isCli) {
            foreach ($GLOBALS['argv'] ?? [] as $arg) {
                if (preg_match('/^--sort=(.+)$/', (string) $arg, $match)) {
                    $sort = strtolower(trim((string) ($match[1] ?? '')));
                }
                if (preg_match('/^--dir=(asc|desc)$/i', (string) $arg, $match)) {
                    $dir = strtolower((string) ($match[1] ?? 'asc'));
                }
            }
        } else {
            if (isset($_GET['sort'])) {
                $sort = strtolower(trim((string) $_GET['sort']));
            }
            if (isset($_GET['dir'])) {
                $candidate = strtolower(trim((string) $_GET['dir']));
                if ($candidate === 'asc' || $candidate === 'desc') {
                    $dir = $candidate;
                }
            }
        }

        if ($sort !== '' && !in_array($sort, $allowedKeys, true)) {
            $sort = '';
        }

        if ($dir !== 'asc' && $dir !== 'desc') {
            $dir = 'asc';
        }

        return [
            'sort' => $sort,
            'dir' => $dir,
        ];
    }
}

if (!function_exists('itm_script_report_table_sort_value')) {
    /**
     * @param array<string, mixed> $row
     */
    function itm_script_report_table_sort_value(array $row, string $sortKey): string
    {
        if ($sortKey === 'tenant_rows') {
            $value = $row['tenant_rows'] ?? null;
            if ($value === null) {
                return '-1';
            }

            return (string) (int) $value;
        }

        if ($sortKey === 'slug' || $sortKey === 'table' || $sortKey === 'sample_data' || $sortKey === 'tenant') {
            $field = $sortKey === 'tenant' ? 'tenant_status' : $sortKey;
            $text = trim((string) ($row[$field] ?? ''));

            return $text === '' ? '~' : $text;
        }

        return '';
    }
}

if (!function_exists('itm_script_report_table_sort_is_numeric_column')) {
    function itm_script_report_table_sort_is_numeric_column(string $sortKey): bool
    {
        return $sortKey === 'tenant_rows';
    }
}

if (!function_exists('itm_script_report_table_sort_apply')) {
    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string> $allowedColumns
     * @return array<int, array<string, mixed>>
     */
    function itm_script_report_table_sort_apply(array $rows, string $sort, string $dir, array $allowedColumns): array
    {
        if ($sort === '' || !isset($allowedColumns[$sort])) {
            return $rows;
        }

        $mult = $dir === 'desc' ? -1 : 1;
        $numeric = itm_script_report_table_sort_is_numeric_column($sort);

        usort($rows, static function (array $left, array $right) use ($sort, $mult, $numeric): int {
            $leftValue = itm_script_report_table_sort_value($left, $sort);
            $rightValue = itm_script_report_table_sort_value($right, $sort);

            if ($numeric) {
                $cmp = ((int) $leftValue <=> (int) $rightValue);
            } else {
                $cmp = strcasecmp($leftValue, $rightValue);
            }

            if ($cmp === 0) {
                return strcasecmp(
                    itm_script_report_table_sort_value($left, 'table'),
                    itm_script_report_table_sort_value($right, 'table')
                );
            }

            return $mult * $cmp;
        });

        return $rows;
    }
}

if (!function_exists('itm_script_report_table_sort_styles')) {
    function itm_script_report_table_sort_styles(): string
    {
        return <<<'CSS'
        .itm-script-sortable-table th.itm-sortable { cursor: pointer; user-select: none; position: relative; padding-right: 22px; }
        .itm-script-sortable-table th.itm-sortable::after { content: '↕'; position: absolute; right: 8px; color: #888; font-size: 11px; }
        .itm-script-sortable-table th.itm-sortable.itm-sorted-asc::after { content: '▲'; color: #333; }
        .itm-script-sortable-table th.itm-sortable.itm-sorted-desc::after { content: '▼'; color: #333; }
        CSS;
    }
}

if (!function_exists('itm_script_report_table_sort_th')) {
    function itm_script_report_table_sort_th(
        string $label,
        int $colIndex,
        string $sortType = 'text',
        bool $sortable = true
    ): string {
        $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        if (!$sortable) {
            return '<th scope="col">' . $labelEsc . '</th>';
        }

        $typeEsc = htmlspecialchars($sortType, ENT_QUOTES, 'UTF-8');

        return '<th scope="col" class="itm-sortable" data-col-index="' . (int) $colIndex
            . '" data-sort-type="' . $typeEsc
            . '" data-sort-dir="none" title="Sort by ' . $labelEsc . '">' . $labelEsc . '</th>';
    }
}

if (!function_exists('itm_script_report_table_sort_footer_js')) {
    function itm_script_report_table_sort_footer_js(): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        echo <<<'JS'
<script>
(function () {
    function cellSortValue(cell, sortType) {
        if (!cell) {
            return sortType === 'number' ? -1 : '';
        }
        var text = (cell.textContent || '').replace(/\s+/g, ' ').trim();
        if (sortType === 'number') {
            if (text === '' || text === '—' || text === '-') {
                return -1;
            }
            var parsed = parseInt(text, 10);
            return isNaN(parsed) ? -1 : parsed;
        }
        return text.toLowerCase();
    }

    function renumberRows(tbody) {
        var rows = tbody.querySelectorAll('tr');
        for (var i = 0; i < rows.length; i++) {
            var indexCell = rows[i].cells[0];
            if (indexCell) {
                indexCell.textContent = String(i + 1);
            }
        }
    }

    function sortTable(table, header) {
        var tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }
        var colIndex = parseInt(header.getAttribute('data-col-index') || '0', 10);
        var sortType = header.getAttribute('data-sort-type') || 'text';
        var currentDirection = header.getAttribute('data-sort-dir') || 'none';
        var nextDirection = currentDirection === 'asc' ? 'desc' : 'asc';

        table.querySelectorAll('th.itm-sortable').forEach(function (th) {
            th.classList.remove('itm-sorted-asc', 'itm-sorted-desc');
            th.setAttribute('data-sort-dir', 'none');
        });
        header.classList.add(nextDirection === 'asc' ? 'itm-sorted-asc' : 'itm-sorted-desc');
        header.setAttribute('data-sort-dir', nextDirection);

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.sort(function (leftRow, rightRow) {
            var leftValue = cellSortValue(leftRow.cells[colIndex], sortType);
            var rightValue = cellSortValue(rightRow.cells[colIndex], sortType);
            var cmp = 0;
            if (sortType === 'number') {
                cmp = leftValue - rightValue;
            } else if (leftValue < rightValue) {
                cmp = -1;
            } else if (leftValue > rightValue) {
                cmp = 1;
            }
            if (cmp === 0) {
                var leftTable = cellSortValue(leftRow.cells[1], 'text');
                var rightTable = cellSortValue(rightRow.cells[1], 'text');
                if (leftTable < rightTable) {
                    cmp = -1;
                } else if (leftTable > rightTable) {
                    cmp = 1;
                }
            }
            return nextDirection === 'asc' ? cmp : -cmp;
        });

        rows.forEach(function (row) {
            tbody.appendChild(row);
        });
        renumberRows(tbody);
    }

    document.querySelectorAll('table.itm-script-sortable-table').forEach(function (table) {
        table.querySelectorAll('th.itm-sortable').forEach(function (header) {
            header.addEventListener('click', function () {
                sortTable(table, header);
            });
        });
    });
})();
</script>
JS;
    }
}
