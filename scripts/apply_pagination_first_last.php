<?php
/**
 * Add first/last page pagination controls (⏮️ / ⏭️) beside existing ◀️ / ▶️ links.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply pagination first/last page controls');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$scanExtensions = ['php'];
$excludePathFragments = [
    DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'qa-reports' . DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR,
];

$skipRelative = [
    'scripts/apply_pagination_first_last.php',
    'scripts/apply_pagination_emoji_labels.php',
];

/**
 * @return array<int, array{pattern:string,replacement:string,label:string}>
 */
function itm_pagination_first_last_apply_rules(): array
{
    return [
        [
            'label' => 'searchRaw_prev',
            'pattern' => '/<a class="btn btn-sm" href="\?search=<\?php echo urlencode\(\$searchRaw\); \?>&sort=<\?php echo urlencode\(\$sort\); \?>&dir=<\?php echo urlencode\(\$dir\); \?>&page=<\?php echo \$page - 1; \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=1" title="First page">⏮️</a>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'searchRaw_next',
            'pattern' => '/<a class="btn btn-sm" href="\?search=<\?php echo urlencode\(\$searchRaw\); \?>&sort=<\?php echo urlencode\(\$sort\); \?>&dir=<\?php echo urlencode\(\$dir\); \?>&page=<\?php echo \$page \+ 1; \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="Next page">▶️</a>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $totalPages; ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'search_prev',
            'pattern' => '/<a class="btn btn-sm" href="\?search=<\?php echo urlencode\(\$search\); \?>&sort=<\?php echo urlencode\(\$sort\); \?>&dir=<\?php echo urlencode\(\$dir\); \?>&page=<\?php echo \$page - 1; \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=1" title="First page">⏮️</a>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'search_next',
            'pattern' => '/<a class="btn btn-sm" href="\?search=<\?php echo urlencode\(\$search\); \?>&sort=<\?php echo urlencode\(\$sort\); \?>&dir=<\?php echo urlencode\(\$dir\); \?>&page=<\?php echo \$page \+ 1; \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="Next page">▶️</a>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $totalPages; ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'int_page_prev',
            'pattern' => '/<a class="btn btn-sm" href="\?search=<\?php echo urlencode\(\$searchRaw\); \?>&sort=<\?php echo urlencode\(\$sort\); \?>&dir=<\?php echo urlencode\(\$dir\); \?>&page=<\?php echo \(int\)\$page - 1; \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=1" title="First page">⏮️</a>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo (int)$page - 1; ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'int_page_next',
            'pattern' => '/<a class="btn btn-sm" href="\?search=<\?php echo urlencode\(\$searchRaw\); \?>&sort=<\?php echo urlencode\(\$sort\); \?>&dir=<\?php echo urlencode\(\$dir\); \?>&page=<\?php echo \(int\)\$page \+ 1; \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo (int)$page + 1; ?>" title="Next page">▶️</a>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $totalPages; ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'list_url_prev_idf',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(itm_idf_list_url\(\[\'page\' => \$page - 1\]\)\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize(itm_idf_list_url([\'page\' => 1])); ?>" title="First page">⏮️</a>
                                    <a class="btn btn-sm" href="<?php echo sanitize(itm_idf_list_url([\'page\' => $page - 1])); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'list_url_next_idf',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(itm_idf_list_url\(\[\'page\' => \$page \+ 1\]\)\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize(itm_idf_list_url([\'page\' => $page + 1])); ?>" title="Next page">▶️</a>
                                    <a class="btn btn-sm" href="<?php echo sanitize(itm_idf_list_url([\'page\' => $totalPages])); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'list_url_prev_inventory',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(itm_inventory_items_list_url\(\[\'page\' => \$page - 1\]\)\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize(itm_inventory_items_list_url([\'page\' => 1])); ?>" title="First page">⏮️</a>
                                <a class="btn btn-sm" href="<?php echo sanitize(itm_inventory_items_list_url([\'page\' => $page - 1])); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'list_url_next_inventory',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(itm_inventory_items_list_url\(\[\'page\' => \$page \+ 1\]\)\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize(itm_inventory_items_list_url([\'page\' => $page + 1])); ?>" title="Next page">▶️</a>
                                <a class="btn btn-sm" href="<?php echo sanitize(itm_inventory_items_list_url([\'page\' => $totalPages])); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'todo_http_build_prev',
            'pattern' => '/<a class="btn btn-sm" href="index\.php\?<\?php echo http_build_query\(\[\'filter\' => \$filter, \'search\' => \$searchRaw, \'sort\' => \$sort, \'dir\' => \$dir, \'page\' => \$page - 1\]\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="index.php?<?php echo http_build_query([\'filter\' => $filter, \'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => 1]); ?>" title="First page">⏮️</a>
                                        <a class="btn btn-sm" href="index.php?<?php echo http_build_query([\'filter\' => $filter, \'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $page - 1]); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'todo_http_build_next',
            'pattern' => '/<a class="btn btn-sm" href="index\.php\?<\?php echo http_build_query\(\[\'filter\' => \$filter, \'search\' => \$searchRaw, \'sort\' => \$sort, \'dir\' => \$dir, \'page\' => \$page \+ 1\]\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="index.php?<?php echo http_build_query([\'filter\' => $filter, \'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $page + 1]); ?>" title="Next page">▶️</a>
                                        <a class="btn btn-sm" href="index.php?<?php echo http_build_query([\'filter\' => $filter, \'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $totalPages]); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'notes_list_all_prev',
            'pattern' => '/<a class="btn btn-sm" href="list_all\.php\?<\?php echo http_build_query\(\[\'filter\' => \$filter, \'search\' => \$searchRaw, \'sort\' => \$sort, \'dir\' => \$dir, \'page\' => \$page - 1\] \+ \(\$filter === \'tag\' && isset\(\$_GET\[\'label\'\]\) \? \[\'label\' => \(string\)\$_GET\[\'label\'\]\] : \[\]\)\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="list_all.php?<?php echo http_build_query([\'filter\' => $filter, \'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => 1] + ($filter === \'tag\' && isset($_GET[\'label\']) ? [\'label\' => (string)$_GET[\'label\']] : [])); ?>" title="First page">⏮️</a>
                                            <a class="btn btn-sm" href="list_all.php?<?php echo http_build_query([\'filter\' => $filter, \'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $page - 1] + ($filter === \'tag\' && isset($_GET[\'label\']) ? [\'label\' => (string)$_GET[\'label\']] : [])); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'notes_list_all_next',
            'pattern' => '/<a class="btn btn-sm" href="list_all\.php\?<\?php echo http_build_query\(\[\'filter\' => \$filter, \'search\' => \$searchRaw, \'sort\' => \$sort, \'dir\' => \$dir, \'page\' => \$page \+ 1\] \+ \(\$filter === \'tag\' && isset\(\$_GET\[\'label\'\]\) \? \[\'label\' => \(string\)\$_GET\[\'label\'\]\] : \[\]\)\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="list_all.php?<?php echo http_build_query([\'filter\' => $filter, \'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $page + 1] + ($filter === \'tag\' && isset($_GET[\'label\']) ? [\'label\' => (string)$_GET[\'label\']] : [])); ?>" title="Next page">▶️</a>
                                            <a class="btn btn-sm" href="list_all.php?<?php echo http_build_query([\'filter\' => $filter, \'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $totalPages] + ($filter === \'tag\' && isset($_GET[\'label\']) ? [\'label\' => (string)$_GET[\'label\']] : [])); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'rack_planner_prev',
            'pattern' => '/<a href="\?page=<\?php echo \$page - 1; \?>&sort=<\?php echo \$sort; \?>&dir=<\?php echo \$dir; \?>&search=<\?php echo urlencode\(\$search\); \?>" class="btn btn-sm" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a href="?page=1&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm" title="First page">⏮️</a>
                                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'rack_planner_next',
            'pattern' => '/<a href="\?page=<\?php echo \$page \+ 1; \?>&sort=<\?php echo \$sort; \?>&dir=<\?php echo \$dir; \?>&search=<\?php echo urlencode\(\$search\); \?>" class="btn btn-sm" title="Next page">▶️<\/a>/u',
            'replacement' => '<a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm" title="Next page">▶️</a>
                                <a href="?page=<?php echo $totalPages; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'tickets_show_archived_prev',
            'pattern' => '/<a class="btn btn-sm" href="\?search=<\?php echo urlencode\(\$searchRaw\); \?>&show_archived=<\?php echo \$showArchived \? \'1\' : \'0\'; \?>&sort=<\?php echo urlencode\(\$sort\); \?>&dir=<\?php echo urlencode\(\$dir\); \?>&page=<\?php echo \(int\)\$page - 1; \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&show_archived=<?php echo $showArchived ? \'1\' : \'0\'; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=1" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&show_archived=<?php echo $showArchived ? \'1\' : \'0\'; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo (int)$page - 1; ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'tickets_show_archived_next',
            'pattern' => '/<a class="btn btn-sm" href="\?search=<\?php echo urlencode\(\$searchRaw\); \?>&show_archived=<\?php echo \$showArchived \? \'1\' : \'0\'; \?>&sort=<\?php echo urlencode\(\$sort\); \?>&dir=<\?php echo urlencode\(\$dir\); \?>&page=<\?php echo \(int\)\$page \+ 1; \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&show_archived=<?php echo $showArchived ? \'1\' : \'0\'; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo (int)$page + 1; ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&show_archived=<?php echo $showArchived ? \'1\' : \'0\'; ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $totalPages; ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'catalog_new_products_prev',
            'pattern' => '/<a class="btn btn-sm" href="\?search=<\?php echo urlencode\(\$searchRaw\); \?>&sort=<\?php echo urlencode\(\$sort\); \?>&dir=<\?php echo urlencode\(\$dir\); \?>&page=<\?php echo \$page - 1; \?><\?php echo \$catalogNewProductsQuery; \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=1<?php echo $catalogNewProductsQuery; ?>" title="First page">⏮️</a>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?><?php echo $catalogNewProductsQuery; ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'catalog_new_products_next',
            'pattern' => '/<a class="btn btn-sm" href="\?search=<\?php echo urlencode\(\$searchRaw\); \?>&sort=<\?php echo urlencode\(\$sort\); \?>&dir=<\?php echo urlencode\(\$dir\); \?>&page=<\?php echo \$page \+ 1; \?><\?php echo \$catalogNewProductsQuery; \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?><?php echo $catalogNewProductsQuery; ?>" title="Next page">▶️</a>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $totalPages; ?><?php echo $catalogNewProductsQuery; ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'ip_addresses_focused_prev',
            'pattern' => '/<a class="btn btn-sm" href="\?<\?php echo \$itmIpAddressFocusedList \? \$itmIpAddressListQuerySuffix : \(\'search=\' \. urlencode\(\$searchRaw\) \. \'&sort=\' \. urlencode\(\$sort\) \. \'&dir=\' \. urlencode\(\$dir\)\); \?>&page=<\?php echo \$page - 1; \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?<?php echo $itmIpAddressFocusedList ? $itmIpAddressListQuerySuffix : (\'search=\' . urlencode($searchRaw) . \'&sort=\' . urlencode($sort) . \'&dir=\' . urlencode($dir)); ?>&page=1" title="First page">⏮️</a>
                                <a class="btn btn-sm" href="?<?php echo $itmIpAddressFocusedList ? $itmIpAddressListQuerySuffix : (\'search=\' . urlencode($searchRaw) . \'&sort=\' . urlencode($sort) . \'&dir=\' . urlencode($dir)); ?>&page=<?php echo $page - 1; ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'ip_addresses_focused_next',
            'pattern' => '/<a class="btn btn-sm" href="\?<\?php echo \$itmIpAddressFocusedList \? \$itmIpAddressListQuerySuffix : \(\'search=\' \. urlencode\(\$searchRaw\) \. \'&sort=\' \. urlencode\(\$sort\) \. \'&dir=\' \. urlencode\(\$dir\)\); \?>&page=<\?php echo \$page \+ 1; \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?<?php echo $itmIpAddressFocusedList ? $itmIpAddressListQuerySuffix : (\'search=\' . urlencode($searchRaw) . \'&sort=\' . urlencode($sort) . \'&dir=\' . urlencode($dir)); ?>&page=<?php echo $page + 1; ?>" title="Next page">▶️</a>
                                <a class="btn btn-sm" href="?<?php echo $itmIpAddressFocusedList ? $itmIpAddressListQuerySuffix : (\'search=\' . urlencode($searchRaw) . \'&sort=\' . urlencode($sort) . \'&dir=\' . urlencode($dir)); ?>&page=<?php echo $totalPages; ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'pwd_build_list_prev',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(pwd_build_list_url\(array_merge\(\$pwdListQueryState, \[\'page\' => \$page - 1\]\)\)\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize(pwd_build_list_url(array_merge($pwdListQueryState, [\'page\' => 1]))); ?>" title="First page">⏮️</a>
                                            <a class="btn btn-sm" href="<?php echo sanitize(pwd_build_list_url(array_merge($pwdListQueryState, [\'page\' => $page - 1]))); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'pwd_build_list_next',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(pwd_build_list_url\(array_merge\(\$pwdListQueryState, \[\'page\' => \$page \+ 1\]\)\)\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize(pwd_build_list_url(array_merge($pwdListQueryState, [\'page\' => $page + 1]))); ?>" title="Next page">▶️</a>
                                            <a class="btn btn-sm" href="<?php echo sanitize(pwd_build_list_url(array_merge($pwdListQueryState, [\'page\' => $totalPages]))); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'sa_build_query_prev',
            'pattern' => '/<a class="btn btn-sm" href="\?<\?php echo sanitize\(sa_build_query\(\[\'search\' => \$searchRaw, \'sort\' => \$sort, \'dir\' => \$dir, \'page\' => \$page - 1\]\)\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?<?php echo sanitize(sa_build_query([\'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => 1])); ?>" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="?<?php echo sanitize(sa_build_query([\'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $page - 1])); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'sa_build_query_next',
            'pattern' => '/<a class="btn btn-sm" href="\?<\?php echo sanitize\(sa_build_query\(\[\'search\' => \$searchRaw, \'sort\' => \$sort, \'dir\' => \$dir, \'page\' => \$page \+ 1\]\)\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?<?php echo sanitize(sa_build_query([\'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $page + 1])); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="?<?php echo sanitize(sa_build_query([\'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $totalPages])); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'audit_logs_build_query_prev',
            'pattern' => '/<a class="btn btn-sm" href="\?<\?php echo sanitize\(itm_audit_logs_build_query\(array_merge\(\$listQueryBase, \[\'page\' => \$page - 1\]\)\)\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?<?php echo sanitize(itm_audit_logs_build_query(array_merge($listQueryBase, [\'page\' => 1]))); ?>" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="?<?php echo sanitize(itm_audit_logs_build_query(array_merge($listQueryBase, [\'page\' => $page - 1]))); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'audit_logs_build_query_next',
            'pattern' => '/<a class="btn btn-sm" href="\?<\?php echo sanitize\(itm_audit_logs_build_query\(array_merge\(\$listQueryBase, \[\'page\' => \$page \+ 1\]\)\)\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?<?php echo sanitize(itm_audit_logs_build_query(array_merge($listQueryBase, [\'page\' => $page + 1]))); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="?<?php echo sanitize(itm_audit_logs_build_query(array_merge($listQueryBase, [\'page\' => $totalPages]))); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'esa_module_build_query_prev',
            'pattern' => '/<a class="btn btn-sm" href="\?<\?php echo sanitize\(esa_module_build_query\(\[\'search\' => \$searchRaw, \'sort\' => \$sort, \'dir\' => \$dir, \'page\' => \$page - 1\]\)\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?<?php echo sanitize(esa_module_build_query([\'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => 1])); ?>" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="?<?php echo sanitize(esa_module_build_query([\'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $page - 1])); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'esa_module_build_query_next',
            'pattern' => '/<a class="btn btn-sm" href="\?<\?php echo sanitize\(esa_module_build_query\(\[\'search\' => \$searchRaw, \'sort\' => \$sort, \'dir\' => \$dir, \'page\' => \$page \+ 1\]\)\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="?<?php echo sanitize(esa_module_build_query([\'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $page + 1])); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="?<?php echo sanitize(esa_module_build_query([\'search\' => $searchRaw, \'sort\' => $sort, \'dir\' => $dir, \'page\' => $totalPages])); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'bkm_build_index_query_prev',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(bkm_build_index_query\(array_merge\(\$bkmListQueryState, \[\'page\' => \$page - 1\]\)\)\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize(bkm_build_index_query(array_merge($bkmListQueryState, [\'page\' => 1]))); ?>" title="First page">⏮️</a>
                                <a class="btn btn-sm" href="<?php echo sanitize(bkm_build_index_query(array_merge($bkmListQueryState, [\'page\' => $page - 1]))); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'bkm_build_index_query_next',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(bkm_build_index_query\(array_merge\(\$bkmListQueryState, \[\'page\' => \$page \+ 1\]\)\)\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize(bkm_build_index_query(array_merge($bkmListQueryState, [\'page\' => $page + 1]))); ?>" title="Next page">▶️</a>
                                <a class="btn btn-sm" href="<?php echo sanitize(bkm_build_index_query(array_merge($bkmListQueryState, [\'page\' => $totalPages]))); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'pc_build_list_prev',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(pc_build_list_url\(array_merge\(\$pcListQueryState, \[\'page\' => \$page - 1\]\)\)\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize(pc_build_list_url(array_merge($pcListQueryState, [\'page\' => 1]))); ?>" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="<?php echo sanitize(pc_build_list_url(array_merge($pcListQueryState, [\'page\' => $page - 1]))); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'pc_build_list_next',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(pc_build_list_url\(array_merge\(\$pcListQueryState, \[\'page\' => \$page \+ 1\]\)\)\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize(pc_build_list_url(array_merge($pcListQueryState, [\'page\' => $page + 1]))); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="<?php echo sanitize(pc_build_list_url(array_merge($pcListQueryState, [\'page\' => $totalPages]))); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'emails_send_logs_prev',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(\$emailsSendLogsPageUrl\(\[\'page\' => \$page - 1\]\)\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize($emailsSendLogsPageUrl([\'page\' => 1])); ?>" title="First page">⏮️</a>
                    <a class="btn btn-sm" href="<?php echo sanitize($emailsSendLogsPageUrl([\'page\' => $page - 1])); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'emails_send_logs_next',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo sanitize\(\$emailsSendLogsPageUrl\(\[\'page\' => \$page \+ 1\]\)\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo sanitize($emailsSendLogsPageUrl([\'page\' => $page + 1])); ?>" title="Next page">▶️</a>
                    <a class="btn btn-sm" href="<?php echo sanitize($emailsSendLogsPageUrl([\'page\' => $sendLogsTotalPages])); ?>" title="Last page">⏭️</a>',
        ],
        [
            'label' => 'ops_report_search_hits_prev',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo \$oprSearchHitsListUrl\(\$searchSort, \$searchDir, \$searchPage - 1\); \?>" title="Previous page">◀️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo $oprSearchHitsListUrl($searchSort, $searchDir, 1); ?>" title="First page">⏮️</a>
                                    <a class="btn btn-sm" href="<?php echo $oprSearchHitsListUrl($searchSort, $searchDir, $searchPage - 1); ?>" title="Previous page">◀️</a>',
        ],
        [
            'label' => 'ops_report_search_hits_next',
            'pattern' => '/<a class="btn btn-sm" href="<\?php echo \$oprSearchHitsListUrl\(\$searchSort, \$searchDir, \$searchPage \+ 1\); \?>" title="Next page">▶️<\/a>/u',
            'replacement' => '<a class="btn btn-sm" href="<?php echo $oprSearchHitsListUrl($searchSort, $searchDir, $searchPage + 1); ?>" title="Next page">▶️</a>
                                    <a class="btn btn-sm" href="<?php echo $oprSearchHitsListUrl($searchSort, $searchDir, $crossDateHitTotalPages); ?>" title="Last page">⏭️</a>',
        ],
    ];
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$rules = itm_pagination_first_last_apply_rules();
$changedFiles = [];
$totalReplacements = 0;

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }
    $path = $fileInfo->getPathname();
    foreach ($excludePathFragments as $fragment) {
        if (strpos($path, $fragment) !== false) {
            continue 2;
        }
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, $scanExtensions, true)) {
        continue;
    }

    $rel = itm_apply_script_rel_path($root, $path);
    if (in_array($rel, $skipRelative, true)) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false || strpos($content, 'title="Previous page"') === false) {
        continue;
    }
    if (strpos($content, 'title="First page"') !== false && strpos($content, 'title="Last page"') !== false) {
        continue;
    }
    if (strpos($rel, 'scripts/lib/') === 0 || strpos($rel, 'scripts/check_') === 0) {
        continue;
    }

    $original = $content;
    $fileHits = 0;
    foreach ($rules as $rule) {
        $content = preg_replace($rule['pattern'], $rule['replacement'], $content, -1, $count);
        if ($count > 0) {
            $fileHits += $count;
        }
    }

    if ($content === $original) {
        continue;
    }

    $totalReplacements += $fileHits;
    $changedFiles[] = $rel . ' (' . $fileHits . ' replacement(s))';
    echo ($apply ? '[apply] ' : '[dry-run] ') . "{$rel} ({$fileHits} replacement(s))\n";

    if ($apply) {
        file_put_contents($path, $content);
    }
}

$modeLabel = $apply ? 'Apply complete' : 'Would change';
echo $nl;
if ($changedFiles === []) {
    echo 'No pagination blocks missing first/last controls.' . $nl;
} else {
    echo $modeLabel . ': ' . count($changedFiles) . " file(s), {$totalReplacements} replacement(s)." . $nl . $nl;
    itm_apply_script_echo_list($modeLabel . ' files', $changedFiles);
}
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changedFiles), $nl, 'apply_pagination_first_last.php');

itm_script_output_end();
exit(0);
