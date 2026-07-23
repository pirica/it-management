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

foreach ($rows as $row) {
    $slug = $row['slug'];
    $full = $row['full'];
    $attrs = $row['attrs'];

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

    $dataTags = preg_split('/\s+/', trim($tagMatch[1])) ?: [];
    if (in_array('Info', $dataTags, true)) {
        $infoRows[] = $slug;
    }
    if (in_array('Markdown', $dataTags, true)) {
        $markdownRows[] = $slug;
    }
}

// Simulate JS filter for *.json / *.txt / Info chip.
function verify_catalog_filter_matches(string $dataTags, string $rowText, string $query, string $activeTag): bool
{
    $tagsAttr = strtolower($dataTags);
    $query = strtolower(trim($query));

    if ($query === '.json' || $query === 'json' || $query === '*.json') {
        $textMatch = strpos($tagsAttr, 'info') !== false;
    } elseif ($query === '.txt' || $query === 'txt' || $query === '*.txt') {
        $textMatch = strpos($tagsAttr, 'info') !== false;
    } elseif ($query === '.md' || $query === 'md' || $query === '*.md') {
        $textMatch = strpos($tagsAttr, 'markdown') !== false;
    } else {
        $textMatch = $query === ''
            || stripos($rowText, $query) !== false
            || strpos($tagsAttr, $query) !== false;
    }

    if ($activeTag === '') {
        return $textMatch;
    }

    $tags = preg_split('/\s+/', trim($dataTags)) ?: [];
    $tagMatch = in_array($activeTag, $tags, true);
    if ($activeTag === '*.json' || $activeTag === '*.txt') {
        $tagMatch = in_array('Info', $tags, true);
    }
    if ($activeTag === '*.md') {
        $tagMatch = in_array('Markdown', $tags, true);
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
    if (verify_catalog_filter_matches($tagMatch[1], $plain, '*.json', '')) {
        $jsonSearchHits++;
    }
    if (verify_catalog_filter_matches($tagMatch[1], $plain, '*.txt', '')) {
        $txtSearchHits++;
    }
    if (verify_catalog_filter_matches($tagMatch[1], $plain, '*.md', '')) {
        $mdSearchHits++;
    }
    if (verify_catalog_filter_matches($tagMatch[1], $plain, '', 'Info')) {
        $infoChipHits++;
    }
    if (verify_catalog_filter_matches($tagMatch[1], $plain, '', '*.md')) {
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
echo 'Rows with Info tag: ' . count($infoRows) . $nl;
echo 'Rows with Markdown tag: ' . count($markdownRows) . $nl;
echo 'Missing data-tags: ' . $missingDataTags . $nl;
echo 'Missing scripts-tags-cell: ' . $missingTagsCell . $nl;
echo 'Wrong <td> count (not 5): ' . $wrongTdCount . $nl;
echo 'Simulated search *.json hits: ' . $jsonSearchHits . $nl;
echo 'Simulated search *.txt hits: ' . $txtSearchHits . $nl;
echo 'Simulated search *.md hits: ' . $mdSearchHits . $nl;
echo 'Simulated Info chip hits: ' . $infoChipHits . $nl;
echo 'Simulated *.md chip hits: ' . $mdChipHits . $nl;
echo 'CSS nth-child(3)=what bug present: ' . ($cssNthChildBug ? 'YES' : 'no') . $nl . $nl;

if ($jsonSearchHits < 1 || $infoChipHits < 1) {
    $failures[] = 'Filter simulation: expected at least 1 *.json search hit and 1 Info chip hit';
}
if ($jsonSearchHits !== count($infoRows) || $txtSearchHits !== count($infoRows)) {
    $failures[] = 'Filter simulation: *.json/*.txt search hits must equal Info row count ('
        . $jsonSearchHits . '/' . $txtSearchHits . ' vs ' . count($infoRows) . ')';
}
if ($mdSearchHits < 1 || $mdChipHits < 1) {
    $failures[] = 'Filter simulation: expected at least 1 *.md search hit and 1 *.md chip hit (Markdown tag)';
}
if ($mdSearchHits !== count($markdownRows) || $mdChipHits !== count($markdownRows)) {
    $failures[] = 'Filter simulation: *.md search/chip hits (' . $mdSearchHits . '/' . $mdChipHits . ') must match Markdown row count (' . count($markdownRows) . ')';
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
