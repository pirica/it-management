<?php
/**
 * Build OOXML .xlsx QA reports from module-browser-qa.json (ZipArchive, no Composer).
 */

require_once __DIR__ . '/mbqa_report_paths.php';
require_once __DIR__ . '/mbqa_step_display.php';

function mbqa_report_xlsx_basename(): string
{
    return 'module-browser-qa.xlsx';
}

function mbqa_report_xlsx_path(string $projectRoot): string
{
    return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . 'qa-reports' . DIRECTORY_SEPARATOR . mbqa_report_xlsx_basename();
}

function mbqar_xlsx_step_label(string $step): string
{
    static $labels = [
        'mysql' => 'database.sql seed rows',
        'error_log' => 'Error log',
        'list' => 'List page',
        'ui_check' => 'Table Actions UI',
        'clear' => 'Tenant clear',
        'sample_data' => 'Sample data',
        'add' => 'Bulk random rows',
        'pagination' => 'Pagination',
        'bulk_cancel' => 'Bulk Cancel UI',
        'bulk_delete' => 'Bulk delete',
        'search' => 'Search',
        'sort' => 'Sort links',
        'create' => 'Create form',
        'view' => 'View record',
        'edit' => 'Edit form',
        'list_all' => 'List all',
        'export_pdf' => 'Export PDF',
        'export_xls' => 'Export Excel (.xlsx)',
        'export_xlsx' => 'Export Excel (.xlsx)',
        'import_db' => 'Import Excel',
        'single_delete' => 'Single delete',
        'clear_table' => 'Clear table',
        'company_switch' => 'Company switch',
    ];

    return $labels[$step] ?? $step;
}

/**
 * @param array<int, mixed> $results Runner results[] from JSON.
 * @return array{summary: array<int, array<int, string|int>>, steps: array<int, array<int, string|int>>, failures: array<int, array<int, string|int>>}
 */
function mbqar_xlsx_workbook_rows(array $results, int $pass, int $fail, string $generatedAt = ''): array
{
    $summary = [
        ['Metric', 'Value'],
        ['Generated at', $generatedAt !== '' ? $generatedAt : date('Y-m-d H:i:s')],
        ['Steps pass', $pass],
        ['Steps fail', $fail],
        ['Module result rows', count($results)],
    ];

    $steps = [['Module', 'Company ID', 'Company Name', 'Tier', 'Step', 'Step Label', 'Status', 'Notes']];
    $failures = [['Module', 'Company ID', 'Company Name', 'Tier', 'Step', 'Step Label', 'Status', 'Notes']];

    foreach ($results as $row) {
        if (!is_array($row)) {
            continue;
        }
        $module = (string)($row['module'] ?? '');
        $companyId = (int)($row['company_id'] ?? 0);
        $companyName = (string)($row['company_name'] ?? '');
        $tier = (string)($row['tier'] ?? '');
        $stepRows = $row['steps'] ?? [];
        if (!is_array($stepRows)) {
            continue;
        }
        foreach ($stepRows as $step) {
            if (!is_array($step)) {
                continue;
            }
            $slug = (string)($step['step'] ?? '');
            $status = (string)($step['status'] ?? '');
            $note = (string)($step['notes'] ?? '');
            $line = [
                $module,
                $companyId,
                $companyName,
                $tier,
                $slug,
                mbqar_xlsx_step_label($slug),
                mbqa_step_human_result($status, $note),
                $note,
            ];
            $steps[] = $line;
            if ($status === 'Fail') {
                $failures[] = $line;
            }
        }
    }

    if (count($failures) === 1) {
        $failures[] = ['', '', '', '', '', '', '', 'No failures recorded.'];
    }

    return ['summary' => $summary, 'steps' => $steps, 'failures' => $failures];
}

function mbqar_xlsx_col_letter(int $index): string
{
    $letters = '';
    while ($index > 0) {
        $index--;
        $letters = chr(65 + ($index % 26)) . $letters;
        $index = (int) floor($index / 26);
    }

    return $letters !== '' ? $letters : 'A';
}

function mbqar_xlsx_escape_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * @param array<int, array<int, string|int|float>> $rows
 */
function mbqar_xlsx_sheet_xml(array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>';
    $rowNum = 1;
    foreach ($rows as $row) {
        $xml .= '<row r="' . $rowNum . '">';
        $colNum = 1;
        foreach ($row as $cell) {
            $ref = mbqar_xlsx_col_letter($colNum) . $rowNum;
            if (is_int($cell) || is_float($cell)) {
                $xml .= '<c r="' . $ref . '"><v>' . $cell . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>'
                    . mbqar_xlsx_escape_xml((string)$cell)
                    . '</t></is></c>';
            }
            $colNum++;
        }
        $xml .= '</row>';
        $rowNum++;
    }
    $xml .= '</sheetData></worksheet>';

    return $xml;
}

/**
 * @param array<string, array<int, array<int, string|int|float>>> $sheets Sheet name => rows (with header row).
 */
function mbqar_write_xlsx_file(string $path, array $sheets): bool
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $tmp = $path . '.tmp-' . getmypid();
    @unlink($tmp);

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }

    $sheetIndex = 1;
    $sheetEntries = '';
    $workbookRels = '';
    $contentOverrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';

    foreach ($sheets as $sheetName => $rows) {
        $safeName = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '_', (string)$sheetName) ?? 'Sheet';
        // ASCII sheet titles only; avoid mbstring dependency on Laragon/PHP 7.4 installs.
        $safeName = substr($safeName, 0, 31);
        if ($safeName === '') {
            $safeName = 'Sheet' . $sheetIndex;
        }
        $sheetPath = 'xl/worksheets/sheet' . $sheetIndex . '.xml';
        $zip->addFromString($sheetPath, mbqar_xlsx_sheet_xml($rows));
        $sheetEntries .= '<sheet name="' . mbqar_xlsx_escape_xml($safeName) . '" sheetId="' . $sheetIndex
            . '" r:id="rId' . $sheetIndex . '"/>';
        $workbookRels .= '<Relationship Id="rId' . $sheetIndex . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetIndex . '.xml"/>';
        $contentOverrides .= '<Override PartName="/' . $sheetPath . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $sheetIndex++;
    }

    $stylesRelId = $sheetIndex;
    $workbookRels .= '<Relationship Id="rId' . $stylesRelId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $contentOverrides .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

    $zip->addFromString(
        'xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $sheetEntries . '</sheets></workbook>'
    );
    $zip->addFromString(
        'xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $workbookRels
        . '</Relationships>'
    );
    $zip->addFromString(
        'xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'
    );
    $zip->addFromString(
        '[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . $contentOverrides
        . '</Types>'
    );
    $zip->addFromString(
        '_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>'
    );

    $zip->close();

    if (!is_file($tmp)) {
        return false;
    }

    if (is_file($path)) {
        @unlink($path);
    }

    return rename($tmp, $path);
}

/**
 * @param array<int, mixed> $results
 * @return array{ok:bool, path:string, error:string}
 */
function mbqar_build_runner_xlsx(
    string $projectRoot,
    array $results,
    int $pass,
    int $fail,
    string $generatedAt = '',
    ?string $outputPath = null
): array {
    if ($outputPath !== null && $outputPath !== '') {
        $path = $outputPath;
    } else {
        $paths = mbqa_report_paths_for_run($projectRoot, $generatedAt !== '' ? $generatedAt : null);
        $path = $paths['xlsx_path'];
    }
    $workbook = mbqar_xlsx_workbook_rows($results, $pass, $fail, $generatedAt);
    $ok = mbqar_write_xlsx_file($path, [
        'Summary' => $workbook['summary'],
        'All steps' => $workbook['steps'],
        'Failures' => $workbook['failures'],
    ]);

    if (!$ok) {
        $error = class_exists('ZipArchive') ? 'Could not write XLSX file' : 'ZipArchive extension is not available';

        return ['ok' => false, 'path' => $path, 'error' => $error];
    }

    return ['ok' => true, 'path' => $path, 'error' => ''];
}

function mbqar_echo_xlsx_vendor_script(): void
{
    echo '<script src="../js/vendor/xlsx.full.min.js"></script>';
}

/**
 * Shared SheetJS export (runner footer fetches JSON; report page may embed payload).
 */
function mbqar_echo_xlsx_client_bootstrap(): void
{
    echo <<<'JS'
<script>
(function () {
  function stepLabel(step) {
    var labels = {
      mysql: 'database.sql seed rows',
      error_log: 'Error log',
      list: 'List page',
      clear: 'Tenant clear',
      sample_data: 'Sample data',
      add: 'Bulk random rows',
      pagination: 'Pagination',
      bulk_cancel: 'Bulk Cancel UI',
      bulk_delete: 'Bulk delete',
      search: 'Search',
      sort: 'Sort links',
      create: 'Create form',
      view: 'View record',
      edit: 'Edit form',
      list_all: 'List all',
      export_pdf: 'Export PDF',
      export_xls: 'Export Excel (.xlsx)',
      export_xlsx: 'Export Excel (.xlsx)',
      import_db: 'Import Excel',
      single_delete: 'Single delete',
      clear_table: 'Clear table',
      company_switch: 'Company switch'
    };
    return labels[step] || step;
  }

  function flattenResults(payload) {
    var results = payload.results || payload;
    if (!Array.isArray(results)) { results = []; }
    var header = ['Module', 'Company ID', 'Company Name', 'Tier', 'Step', 'Step Label', 'Status', 'Notes'];
    var steps = [header.slice()];
    var failures = [header.slice()];
    var pass = 0;
    var fail = 0;
    results.forEach(function (row) {
      if (!row || !row.steps) { return; }
      row.steps.forEach(function (st) {
        var line = [
          row.module || '',
          row.company_id || 0,
          row.company_name || '',
          row.tier || '',
          st.step || '',
          stepLabel(st.step || ''),
          st.status || '',
          st.notes || ''
        ];
        steps.push(line);
        if (st.status === 'Pass') { pass++; }
        else if (st.status === 'Fail') { fail++; failures.push(line); }
      });
    });
    if (failures.length === 1) {
      failures.push(['', '', '', '', '', '', '', 'No failures recorded.']);
    }
    var summary = [
      ['Metric', 'Value'],
      ['Generated at', payload.generated_at || ''],
      ['Steps pass', pass],
      ['Steps fail', fail],
      ['Module result rows', results.length]
    ];
    return { summary: summary, steps: steps, failures: failures };
  }

  function writeWorkbook(payload) {
    if (!window.XLSX || !window.XLSX.utils) {
      window.alert('XLSX library did not load. Refresh the page and try again.');
      return;
    }
    var data = flattenResults(payload);
    var wb = window.XLSX.utils.book_new();
    window.XLSX.utils.book_append_sheet(wb, window.XLSX.utils.aoa_to_sheet(data.summary), 'Summary');
    window.XLSX.utils.book_append_sheet(wb, window.XLSX.utils.aoa_to_sheet(data.steps), 'All steps');
    window.XLSX.utils.book_append_sheet(wb, window.XLSX.utils.aoa_to_sheet(data.failures), 'Failures');
    window.XLSX.writeFile(wb, 'module-browser-qa.xlsx');
  }

  window.mbqaExportResultsFromPayload = writeWorkbook;

  window.mbqaExportResultsFromJsonUrl = function (jsonUrl) {
    fetch(jsonUrl, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (res) {
        if (!res.ok) { throw new Error('HTTP ' + res.status); }
        return res.json();
      })
      .then(writeWorkbook)
      .catch(function () {
        window.alert('Could not load QA JSON for export.');
      });
  };
})();
</script>
JS;
}

/**
 * Download link for qa-reports/module-browser-qa.xlsx on runner/report pages.
 */
function mbqar_render_xlsx_download_link(string $xlsxRelPath, bool $xlsxOk, string $xlsxError = ''): void
{
    if ($xlsxOk) {
        echo '<a href="' . htmlspecialchars($xlsxRelPath, ENT_QUOTES, 'UTF-8') . '">Download XLSX</a> · ';

        return;
    }
    if ($xlsxError !== '') {
        echo '<span style="color:#cf222e;font-size:0.85rem;">XLSX unavailable (' . htmlspecialchars($xlsxError, ENT_QUOTES, 'UTF-8') . ')</span> · ';
    }
}

/**
 * Browser/CLI link row for the report-built page.
 */
function mbqar_render_xlsx_export_ui(
    string $xlsxRelPath,
    bool $xlsxOk,
    string $xlsxError,
    array $reportPayload
): void {
    $xlsxEsc = htmlspecialchars($xlsxRelPath, ENT_QUOTES, 'UTF-8');
    if ($xlsxOk) {
        echo '<a href="' . $xlsxEsc . '">Download XLSX</a> · ';
    }
    echo '<button type="button" id="mbqar-export-xlsx-btn" style="padding:4px 10px;font-size:inherit;cursor:pointer;">Export results as XLSX</button>';
    if (!$xlsxOk && $xlsxError !== '') {
        echo ' <span style="color:#cf222e;font-size:0.85rem;">(' . htmlspecialchars($xlsxError, ENT_QUOTES, 'UTF-8') . ')</span>';
    }
    mbqar_echo_xlsx_vendor_script();
    mbqar_echo_xlsx_client_bootstrap();
    echo '<script id="mbqar-report-payload" type="application/json">';
    echo json_encode($reportPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo '</script>';
    echo '<script>(function(){var btn=document.getElementById("mbqar-export-xlsx-btn");var el=document.getElementById("mbqar-report-payload");if(!btn||!el||!window.mbqaExportResultsFromPayload){return;}btn.addEventListener("click",function(){try{window.mbqaExportResultsFromPayload(JSON.parse(el.textContent||"{}"));}catch(e){window.alert("Could not read report JSON.");}});})();</script>';
}
