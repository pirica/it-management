<?php
/**
 * Scrape scripts/scripts.php and verify catalog tag filter contract.
 *
 * CLI: php scripts/verify_scripts_catalog_filter.php
 * Browser: scripts/verify_scripts_catalog_filter.php (Admin)
 */
declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_script_catalog_tags.php';
require_once __DIR__ . '/lib/itm_script_catalog_documentation_files.php';

$nl = itm_check_script_begin_browser_admin('Scripts catalog filter verification');
$root = dirname(__DIR__);
$catalogPath = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'scripts.php';
$html = file_get_contents($catalogPath);
if (!is_string($html)) {
    echo 'FAIL: could not read scripts/scripts.php' . $nl;
    itm_script_output_end();
    exit(1);
}

$rows = itm_script_catalog_tags_parse_catalog_rows($html);
$failures = [];
$infoRows = [];
$markdownRows = [];
$missingDataTags = 0;
$missingTagsCell = 0;
$wrongTdCount = 0;

$jsonFileRows = 0;
$txtFileRows = 0;
$mdFileRows = 0;

foreach ($rows as $row) {
    $slug = $row['slug'];
    $full = $row['full'];

    if (!preg_match('/\bdata-tags=["\']([^"\']*)["\']/i', $full, $tagMatch)) {
        $missingDataTags++;
        $failures[] = $slug . ': missing data-tags on <tr>';
        continue;
    }

    if (strpos($full, 'scripts-tags-cell') === false) {
        $missingTagsCell++;
        $failures[] = $slug . ': missing scripts-tags-cell';
    }

    preg_match_all('/<td\b/i', $full, $tdMatches);
    $tdCount = count($tdMatches[0]);
    if ($tdCount !== 5) {
        $wrongTdCount++;
        $failures[] = $slug . ': expected 5 <td> cells, found ' . $tdCount;
    }

    $href = verify_catalog_row_href($full);
    if (verify_catalog_row_has_ext($href, '.json')) {
        $jsonFileRows++;
        $infoRows[] = $slug;
    }
    if (verify_catalog_row_has_ext($href, '.txt')) {
        $txtFileRows++;
        $infoRows[] = $slug;
    }
    if (verify_catalog_row_has_ext($href, '.md')) {
        $mdFileRows++;
        $markdownRows[] = $slug;
    }
}

function verify_catalog_row_href(string $rowHtml): string
{
    if (preg_match('/<td[^>]*>\s*<a[^>]+href=["\']([^"\']+)["\']/i', $rowHtml, $match)) {
        return strtolower(trim($match[1]));
    }

    return '';
}

function verify_catalog_row_has_ext(string $href, string $ext): bool
{
    $ext = strtolower($ext);

    return $ext !== '' && strlen($href) >= strlen($ext) && substr($href, -strlen($ext)) === $ext;
}

// Simulate JS filter for *.json / *.txt / *.md documentation files.
function verify_catalog_filter_matches(string $href, string $dataTags, string $rowText, string $query, string $activeTag): bool
{
    $tagsAttr = strtolower($dataTags);
    $query = strtolower(trim($query));
    $href = strtolower($href);

    if ($query === '.json' || $query === 'json' || $query === '*.json') {
        $textMatch = verify_catalog_row_has_ext($href, '.json');
    } elseif ($query === '.txt' || $query === 'txt' || $query === '*.txt') {
        $textMatch = verify_catalog_row_has_ext($href, '.txt');
    } elseif ($query === '.md' || $query === 'md' || $query === '*.md') {
        $textMatch = verify_catalog_row_has_ext($href, '.md');
    } else {
        $textMatch = $query === ''
            || stripos($rowText, $query) !== false
            || strpos($tagsAttr, $query) !== false;
    }

    if ($activeTag === '') {
        return $textMatch;
    }

    if ($activeTag === '*.json') {
        $tagMatch = verify_catalog_row_has_ext($href, '.json');
    } elseif ($activeTag === '*.txt') {
        $tagMatch = verify_catalog_row_has_ext($href, '.txt');
    } elseif ($activeTag === '*.md') {
        $tagMatch = verify_catalog_row_has_ext($href, '.md');
    } elseif ($activeTag === 'Info') {
        $tagMatch = verify_catalog_row_has_ext($href, '.json') || verify_catalog_row_has_ext($href, '.txt');
    } elseif ($activeTag === 'Markdown') {
        $tagMatch = verify_catalog_row_has_ext($href, '.md');
    } else {
        $tags = preg_split('/\s+/', trim($dataTags)) ?: [];
        $tagMatch = in_array($activeTag, $tags, true);
    }

    return $textMatch && $tagMatch;
}

$jsonSearchHits = 0;
$txtSearchHits = 0;
$mdSearchHits = 0;
$infoChipHits = 0;
$mdChipHits = 0;
foreach ($rows as $row) {
    if (!preg_match('/\bdata-tags=["\']([^"\']*)["\']/i', $row['full'], $tagMatch)) {
        continue;
    }
    $plain = html_entity_decode(strip_tags($row['full']), ENT_QUOTES, 'UTF-8');
    $href = verify_catalog_row_href($row['full']);
    if (verify_catalog_filter_matches($href, $tagMatch[1], $plain, '*.json', '')) {
        $jsonSearchHits++;
    }
    if (verify_catalog_filter_matches($href, $tagMatch[1], $plain, '*.txt', '')) {
        $txtSearchHits++;
    }
    if (verify_catalog_filter_matches($href, $tagMatch[1], $plain, '*.md', '')) {
        $mdSearchHits++;
    }
    if (verify_catalog_filter_matches($href, $tagMatch[1], $plain, '', 'Info')) {
        $infoChipHits++;
    }
    if (verify_catalog_filter_matches($href, $tagMatch[1], $plain, '', '*.md')) {
        $mdChipHits++;
    }
}

$cssNthChildBug = (bool) preg_match(
    '/\.scripts-catalog\s+td:nth-child\(3\)\s*\{[^}]*grid-area:\s*what/i',
    $html
);

echo 'Scripts catalog filter verification' . $nl;
echo str_repeat('=', 40) . $nl . $nl;
echo 'Catalog rows parsed: ' . count($rows) . $nl;
echo 'Documentation .json rows: ' . $jsonFileRows . $nl;
echo 'Documentation .txt rows: ' . $txtFileRows . $nl;
echo 'Documentation .md rows: ' . $mdFileRows . $nl;
echo 'Missing data-tags: ' . $missingDataTags . $nl;
echo 'Missing scripts-tags-cell: ' . $missingTagsCell . $nl;
echo 'Wrong <td> count (not 5): ' . $wrongTdCount . $nl;
echo 'Simulated search *.json hits: ' . $jsonSearchHits . $nl;
echo 'Simulated search *.txt hits: ' . $txtSearchHits . $nl;
echo 'Simulated search *.md hits: ' . $mdSearchHits . $nl;
echo 'Simulated Info chip hits: ' . $infoChipHits . $nl;
echo 'Simulated *.md chip hits: ' . $mdChipHits . $nl;
echo 'CSS nth-child(3)=what bug present: ' . ($cssNthChildBug ? 'YES' : 'no') . $nl . $nl;

$expectedDocs = itm_script_catalog_documentation_files_discover($root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR);
$expectedJson = 0;
$expectedTxt = 0;
$expectedMd = 0;
foreach ($expectedDocs as $docPath) {
    $ext = strtolower((string)pathinfo($docPath, PATHINFO_EXTENSION));
    if ($ext === 'json') {
        $expectedJson++;
    } elseif ($ext === 'txt') {
        $expectedTxt++;
    } elseif ($ext === 'md') {
        $expectedMd++;
    }
}

if ($jsonFileRows < 1 || $txtFileRows < 1 || $mdFileRows < 1) {
    $failures[] = 'Documentation section must catalog at least one .json, .txt, and .md file';
}
if ($jsonSearchHits !== $jsonFileRows || $txtSearchHits !== $txtFileRows) {
    $failures[] = 'Filter simulation: *.json/*.txt hits must match documentation file rows ('
        . $jsonSearchHits . '/' . $txtSearchHits . ' vs ' . $jsonFileRows . '/' . $txtFileRows . ')';
}
if ($infoChipHits !== $jsonFileRows + $txtFileRows) {
    $failures[] = 'Filter simulation: Info chip must match .json + .txt rows (' . $infoChipHits . ' vs ' . ($jsonFileRows + $txtFileRows) . ')';
}
if ($mdSearchHits !== $mdFileRows || $mdChipHits !== $mdFileRows) {
    $failures[] = 'Filter simulation: *.md search/chip must match .md rows ('
        . $mdSearchHits . '/' . $mdChipHits . ' vs ' . $mdFileRows . ')';
}
if ($jsonFileRows !== $expectedJson || $txtFileRows !== $expectedTxt || $mdFileRows !== $expectedMd) {
    $failures[] = 'Documentation catalog missing data files (catalog '
        . $jsonFileRows . '/' . $txtFileRows . '/' . $mdFileRows
        . ' vs disk ' . $expectedJson . '/' . $expectedTxt . '/' . $expectedMd . ') — run apply_script_catalog_documentation_files.php --apply';
}

if ($cssNthChildBug) {
    $failures[] = 'CSS maps td:nth-child(3) to "what" but column 3 is scripts-tags-cell (tags misaligned)';
}

if ($failures !== []) {
    echo 'FAILURES (' . count($failures) . '):' . $nl;
    foreach (array_slice($failures, 0, 25) as $failure) {
        echo '  - ' . $failure . $nl;
    }
    if (count($failures) > 25) {
        echo '  … and ' . (count($failures) - 25) . ' more' . $nl;
    }
    itm_script_output_end();
    exit(1);
}

echo 'PASS: catalog filter contract OK' . $nl;
itm_script_output_end();
exit(0);
