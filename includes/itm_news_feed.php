<?php
/**
 * Multi-source news/RSS feed cache, fetch, and refresh helpers (no cron).
 */

if (!defined('NEWS_NVD_API_URL')) {
    define('NEWS_NVD_API_URL', 'https://services.nvd.nist.gov/rest/json/cves/2.0');
}
if (!defined('NEWS_RESULTS_PER_PAGE')) {
    define('NEWS_RESULTS_PER_PAGE', 50);
}
if (!defined('NEWS_CACHE_DURATION')) {
    define('NEWS_CACHE_DURATION', 86400);
}
if (!defined('NEWS_LOCK_TIMEOUT')) {
    define('NEWS_LOCK_TIMEOUT', 300);
}
if (!defined('NEWS_NVD_LOOKBACK_DAYS')) {
    define('NEWS_NVD_LOOKBACK_DAYS', 120);
}

require_once __DIR__ . '/itm_news_feed_ms_support_products.php';

if (!function_exists('news_microsoft_support_atom_url')) {
    function news_microsoft_support_atom_url($guid)
    {
        $guid = strtolower(trim((string)$guid));

        return 'https://support.microsoft.com/en-us/feed/atom/' . $guid;
    }
}

if (!function_exists('news_microsoft_support_feed_catalog_entries')) {
    /**
     * Atom feeds from Microsoft Support RSS feed picker (one entry per product).
     *
     * @return array<string,array<string,mixed>>
     */
    function news_microsoft_support_feed_catalog_entries()
    {
        if (!function_exists('news_microsoft_support_feed_products')) {
            return [];
        }

        $siteLink = 'https://support.microsoft.com/en-us/rss-feed-picker';
        $entries = [];
        foreach (news_microsoft_support_feed_products() as $product) {
            $id = (string)($product['id'] ?? '');
            $label = (string)($product['label'] ?? '');
            $guid = (string)($product['guid'] ?? '');
            if ($id === '' || $guid === '') {
                continue;
            }

            $entries[$id] = [
                'id' => $id,
                'label' => $label,
                'emoji' => '📦',
                'type' => 'rss',
                'url' => news_microsoft_support_atom_url($guid),
                'site_link' => $siteLink,
                'description' => $label . ' — Microsoft Support KB and update articles (feed picker)',
                'show_cvss' => false,
                'title_column' => 'Title',
            ];
        }

        return $entries;
    }
}

if (!function_exists('news_feed_source_catalog')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function news_feed_source_catalog()
    {
        $baseCatalog = [
            'nvd_cve' => [
                'id' => 'nvd_cve',
                'label' => 'CVE (NVD)',
                'emoji' => '🛡️',
                'type' => 'nvd',
                'url' => NEWS_NVD_API_URL,
                'site_link' => 'https://nvd.nist.gov/',
                'description' => 'Latest CVEs from the National Vulnerability Database',
                'show_cvss' => true,
                'title_column' => 'CVE ID',
            ],
            'ms_commandline' => [
                'id' => 'ms_commandline',
                'label' => 'Microsoft Command Line',
                'emoji' => '💻',
                'type' => 'rss',
                'url' => 'https://devblogs.microsoft.com/commandline/feed/',
                'site_link' => 'https://devblogs.microsoft.com/commandline/',
                'description' => 'Windows command line and developer tools blog',
                'show_cvss' => false,
                'title_column' => 'Title',
            ],
            'ms_windows_blog' => [
                'id' => 'ms_windows_blog',
                'label' => 'Windows Blog',
                'emoji' => '🪟',
                'type' => 'rss',
                'url' => 'https://blogs.windows.com/feed/',
                'site_link' => 'https://blogs.windows.com/',
                'description' => 'Official Windows blog feed',
                'show_cvss' => false,
                'title_column' => 'Title',
            ],
            'ms_powershell' => [
                'id' => 'ms_powershell',
                'label' => 'PowerShell Blog',
                'emoji' => '⚡',
                'type' => 'rss',
                'url' => 'https://devblogs.microsoft.com/powershell/feed/',
                'site_link' => 'https://devblogs.microsoft.com/powershell/',
                'description' => 'PowerShell team blog and release notes',
                'show_cvss' => false,
                'title_column' => 'Title',
            ],
            'ms_msrc_security' => [
                'id' => 'ms_msrc_security',
                'label' => 'MSRC Security Updates',
                'emoji' => '🔒',
                'type' => 'rss',
                'url' => 'https://api.msrc.microsoft.com/update-guide/rss',
                'site_link' => 'https://msrc.microsoft.com/update-guide',
                'description' => 'Security Update Guide — new CVEs, advisories, and Patch Tuesday revisions',
                'show_cvss' => false,
                'title_column' => 'Title',
            ],
        ];

        return array_merge($baseCatalog, news_microsoft_support_feed_catalog_entries());
    }
}

if (!function_exists('news_resolve_feed_source')) {
    function news_resolve_feed_source($sourceId)
    {
        $sourceId = strtolower(trim((string)$sourceId));
        $catalog = news_feed_source_catalog();
        if ($sourceId === '' || !isset($catalog[$sourceId])) {
            return $catalog['nvd_cve'];
        }

        return $catalog[$sourceId];
    }
}

if (!function_exists('news_cache_dir')) {
    function news_cache_dir()
    {
        if (defined('ROOT_PATH')) {
            return rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'news' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
        }

        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'news' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('news_source_cache_basename')) {
    function news_source_cache_basename($sourceId)
    {
        $source = news_resolve_feed_source($sourceId);

        return (string)$source['id'];
    }
}

if (!function_exists('news_cache_xml_file')) {
    function news_cache_xml_file($sourceId)
    {
        return news_cache_dir() . news_source_cache_basename($sourceId) . '-feed.xml';
    }
}

if (!function_exists('news_cache_json_file')) {
    function news_cache_json_file($sourceId)
    {
        return news_cache_dir() . news_source_cache_basename($sourceId) . '-feed.json';
    }
}

if (!function_exists('news_lock_file')) {
    function news_lock_file($sourceId)
    {
        return news_cache_dir() . news_source_cache_basename($sourceId) . '.lock';
    }
}

if (!function_exists('news_feed_user_agent')) {
    function news_feed_user_agent()
    {
        $base = defined('BASE_URL') ? (string)BASE_URL : '';
        if ($base === '' && isset($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/';
        }

        return 'IT-Management-News-Feed/1.0 (' . $base . ')';
    }
}

if (!function_exists('news_resolve_php_binary')) {
    function news_resolve_php_binary()
    {
        if (defined('PHP_BINARY') && PHP_BINARY !== '' && stripos((string)PHP_BINARY, 'php') !== false) {
            return (string)PHP_BINARY;
        }

        $laragonPhp = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
        if (is_file($laragonPhp)) {
            return $laragonPhp;
        }

        return 'php';
    }
}

if (!function_exists('news_ensure_cache_dir')) {
    function news_ensure_cache_dir()
    {
        $dir = news_cache_dir();
        if (function_exists('itm_ensure_upload_directory')) {
            return itm_ensure_upload_directory($dir, 'upload');
        }

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        return is_dir($dir) && is_writable($dir);
    }
}

if (!function_exists('news_log_info')) {
    function news_log_info($message)
    {
        error_log('[News feed] INFO: ' . (string)$message);
    }
}

if (!function_exists('news_log_error')) {
    function news_log_error($message)
    {
        error_log('[News feed] ERROR: ' . (string)$message);
    }
}

if (!function_exists('news_fetch_http_body')) {
    function news_fetch_http_body($url, array $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, (string)$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, news_feed_user_agent());
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('HTTP request returned code: ' . $httpCode);
        }

        return (string)$response;
    }
}

if (!function_exists('news_resolve_nvd_api_key')) {
    function news_resolve_nvd_api_key()
    {
        foreach (['NVD_API_KEY', 'ITM_NVD_API_KEY'] as $envName) {
            $value = getenv($envName);
            if ($value !== false && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        return '';
    }
}

if (!function_exists('news_build_nvd_api_query_params')) {
    /**
     * Why: NVD returns 1990s CVEs at startIndex=0 without a date window; API allows max 120-day ranges.
     *
     * @return array<string,string|int>
     */
    function news_build_nvd_api_query_params($limit, $lookbackDays = NEWS_NVD_LOOKBACK_DAYS)
    {
        $lookbackDays = max(1, min(120, (int)$lookbackDays));
        $end = gmdate('Y-m-d\TH:i:s.000\Z');
        $start = gmdate('Y-m-d\TH:i:s.000\Z', strtotime('-' . $lookbackDays . ' days'));

        return [
            'resultsPerPage' => max(1, (int)$limit),
            'startIndex' => 0,
            'pubStartDate' => $start,
            'pubEndDate' => $end,
        ];
    }
}

if (!function_exists('news_fetch_nvd_from_api')) {
    function news_fetch_nvd_from_api($url, $limit)
    {
        $headers = ['Accept: application/json'];
        $apiKey = news_resolve_nvd_api_key();
        if ($apiKey !== '') {
            $headers[] = 'apiKey: ' . $apiKey;
        }

        $query = http_build_query(
            news_build_nvd_api_query_params($limit),
            '',
            '&',
            PHP_QUERY_RFC3986
        );
        $body = news_fetch_http_body(rtrim((string)$url, '?') . '?' . $query, $headers);
        $data = json_decode($body, true);
        if (!isset($data['vulnerabilities']) || !is_array($data['vulnerabilities'])) {
            throw new Exception('Invalid response format from NVD API');
        }

        return $data['vulnerabilities'];
    }
}

if (!function_exists('news_item_published_timestamp')) {
    function news_item_published_timestamp(array $item)
    {
        $candidates = [
            $item['published'] ?? '',
            $item['last_modified'] ?? '',
        ];

        foreach ($candidates as $raw) {
            $raw = trim((string)$raw);
            if ($raw === '') {
                continue;
            }

            $timestamp = strtotime($raw);
            if ($timestamp !== false) {
                return (int)$timestamp;
            }
        }

        return 0;
    }
}

if (!function_exists('news_sort_items_newest_first')) {
    function news_sort_items_newest_first(array $items)
    {
        usort($items, function (array $left, array $right) {
            $rightTs = news_item_published_timestamp($right);
            $leftTs = news_item_published_timestamp($left);

            if ($rightTs === $leftTs) {
                return strcmp((string)($right['title'] ?? ''), (string)($left['title'] ?? ''));
            }

            return $rightTs <=> $leftTs;
        });

        return $items;
    }
}

if (!function_exists('news_strip_html_excerpt')) {
    function news_strip_html_excerpt($html, $maxLength = 220)
    {
        $text = html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text));
        if ($text === null) {
            $text = '';
        }
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength - 3) . '...';
        }

        return $text;
    }
}

if (!function_exists('news_parse_rss_items')) {
    function news_parse_rss_items($xmlString, $limit = NEWS_RESULTS_PER_PAGE)
    {
        $items = [];
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string)$xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            throw new Exception('Unable to parse RSS/Atom XML');
        }

        $rssItems = [];
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $rssItems[] = $item;
            }
        } elseif (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $rssItems[] = $entry;
            }
        }

        foreach ($rssItems as $item) {
            $title = trim((string)($item->title ?? ''));
            $link = trim((string)($item->link ?? ''));
            if ($link === '' && isset($item->link['href'])) {
                $link = trim((string)$item->link['href']);
            }
            if ($title === '') {
                continue;
            }

            $description = news_strip_html_excerpt((string)($item->description ?? $item->summary ?? $item->content ?? ''), 500);
            $published = trim((string)($item->pubDate ?? $item->published ?? $item->updated ?? ''));
            $guid = trim((string)($item->guid ?? $title));
            if ($link === '') {
                $link = $guid;
            }

            $items[] = [
                'id' => $title,
                'title' => $title,
                'link' => $link,
                'description' => $description,
                'published' => $published,
                'last_modified' => trim((string)($item->updated ?? '')),
                'base_score' => null,
                'severity' => '',
                'cvss_label' => '',
            ];
        }

        $items = news_sort_items_newest_first($items);
        if ((int)$limit > 0 && count($items) > (int)$limit) {
            $items = array_slice($items, 0, (int)$limit);
        }

        return $items;
    }
}

if (!function_exists('news_extract_cvss')) {
    function news_extract_cvss(array $cve)
    {
        $result = [
            'base_score' => null,
            'severity' => '',
            'vector' => '',
            'label' => '',
        ];

        if (isset($cve['metrics']['cvssMetricV31'][0]['cvssData'])) {
            $data = $cve['metrics']['cvssMetricV31'][0]['cvssData'];
            $result['base_score'] = $data['baseScore'] ?? null;
            $result['severity'] = (string)($data['baseSeverity'] ?? '');
            $result['vector'] = (string)($data['vectorString'] ?? '');
        } elseif (isset($cve['metrics']['cvssMetricV30'][0]['cvssData'])) {
            $data = $cve['metrics']['cvssMetricV30'][0]['cvssData'];
            $result['base_score'] = $data['baseScore'] ?? null;
            $result['severity'] = (string)($data['baseSeverity'] ?? '');
            $result['vector'] = (string)($data['vectorString'] ?? '');
        } elseif (isset($cve['metrics']['cvssMetricV2'][0]['cvssData'])) {
            $data = $cve['metrics']['cvssMetricV2'][0]['cvssData'];
            $result['base_score'] = $data['baseScore'] ?? null;
            $result['vector'] = (string)($data['vectorString'] ?? '');
        }

        if ($result['base_score'] !== null && $result['severity'] !== '') {
            $result['label'] = $result['base_score'] . ' (' . $result['severity'] . ')';
            if ($result['vector'] !== '') {
                $result['label'] .= ' - Vector: ' . $result['vector'];
            }
        } elseif ($result['base_score'] !== null) {
            $result['label'] = (string)$result['base_score'];
            if ($result['vector'] !== '') {
                $result['label'] .= ' (Vector: ' . $result['vector'] . ')';
            }
        }

        return $result;
    }
}

if (!function_exists('news_normalize_nvd_items')) {
    function news_normalize_nvd_items(array $cves)
    {
        $items = [];

        foreach ($cves as $cveData) {
            if (!isset($cveData['cve']) || !is_array($cveData['cve'])) {
                continue;
            }

            $cve = $cveData['cve'];
            $cveId = (string)($cve['id'] ?? '');
            if ($cveId === '') {
                continue;
            }

            $description = '';
            if (isset($cve['descriptions']) && is_array($cve['descriptions'])) {
                foreach ($cve['descriptions'] as $descRow) {
                    if (isset($descRow['lang']) && strtolower((string)$descRow['lang']) === 'en' && !empty($descRow['value'])) {
                        $description = (string)$descRow['value'];
                        break;
                    }
                }
                if ($description === '' && isset($cve['descriptions'][0]['value'])) {
                    $description = (string)$cve['descriptions'][0]['value'];
                }
            }

            $cvss = news_extract_cvss($cve);

            $items[] = [
                'id' => $cveId,
                'title' => $cveId,
                'link' => 'https://nvd.nist.gov/vuln/detail/' . rawurlencode($cveId),
                'description' => $description,
                'published' => (string)($cve['published'] ?? ''),
                'last_modified' => (string)($cve['lastModified'] ?? ''),
                'base_score' => $cvss['base_score'],
                'severity' => $cvss['severity'],
                'cvss_label' => $cvss['label'],
            ];
        }

        return news_sort_items_newest_first($items);
    }
}

if (!function_exists('news_generate_nvd_rss_feed')) {
    function news_generate_nvd_rss_feed(array $cves, array $source, $selfUrl = '')
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $rss = $xml->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $rss->setAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $xml->appendChild($rss);

        $channel = $xml->createElement('channel');
        $rss->appendChild($channel);

        $channel->appendChild($xml->createElement('title', (string)$source['label']));
        $channel->appendChild($xml->createElement('link', (string)$source['site_link']));
        $channel->appendChild($xml->createElement('description', (string)$source['description']));
        $channel->appendChild($xml->createElement('language', 'en-us'));
        $channel->appendChild($xml->createElement('lastBuildDate', date('r')));
        $channel->appendChild($xml->createElement('generator', 'IT Management News Feed'));

        if ($selfUrl === '') {
            $selfUrl = news_current_request_url();
        }

        $selfLink = $xml->createElement('atom:link');
        $selfLink->setAttribute('href', $selfUrl);
        $selfLink->setAttribute('rel', 'self');
        $selfLink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($selfLink);

        $sortedCves = $cves;
        usort($sortedCves, function (array $left, array $right) {
            $leftPublished = (string)($left['cve']['published'] ?? $left['cve']['lastModified'] ?? '');
            $rightPublished = (string)($right['cve']['published'] ?? $right['cve']['lastModified'] ?? '');
            $leftTs = $leftPublished !== '' ? strtotime($leftPublished) : 0;
            $rightTs = $rightPublished !== '' ? strtotime($rightPublished) : 0;
            if ($leftTs === false) {
                $leftTs = 0;
            }
            if ($rightTs === false) {
                $rightTs = 0;
            }

            return (int)$rightTs <=> (int)$leftTs;
        });

        foreach ($sortedCves as $cveData) {
            if (!isset($cveData['cve']) || !is_array($cveData['cve'])) {
                continue;
            }

            $cve = $cveData['cve'];
            $cveId = (string)($cve['id'] ?? '');
            if ($cveId === '') {
                continue;
            }

            $item = $xml->createElement('item');
            $item->appendChild($xml->createElement('title', $cveId));
            $item->appendChild($xml->createElement('link', 'https://nvd.nist.gov/vuln/detail/' . $cveId));

            $description = '';
            if (isset($cve['descriptions'][0]['value'])) {
                $description = htmlspecialchars((string)$cve['descriptions'][0]['value'], ENT_QUOTES, 'UTF-8');
            }

            $cvss = news_extract_cvss($cve);
            if ($cvss['label'] !== '') {
                $description .= PHP_EOL . PHP_EOL . 'CVSS Score: ' . $cvss['label'];
            }

            $item->appendChild($xml->createElement('description', $description));

            if (!empty($cve['published'])) {
                $item->appendChild($xml->createElement('pubDate', date('r', strtotime((string)$cve['published']))));
            }

            $guid = $xml->createElement('guid', $cveId);
            $guid->setAttribute('isPermaLink', 'false');
            $item->appendChild($guid);
            $channel->appendChild($item);
        }

        return $xml;
    }
}

if (!function_exists('news_current_request_url')) {
    function news_current_request_url()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');

        return $protocol . '://' . $host . $uri;
    }
}

if (!function_exists('news_feed_self_url')) {
    function news_feed_self_url($sourceId)
    {
        $sourceId = news_source_cache_basename($sourceId);
        if (defined('BASE_URL')) {
            return rtrim((string)BASE_URL, '/') . '/modules/news/feed.php?source=' . rawurlencode($sourceId);
        }

        return news_current_request_url();
    }
}

if (!function_exists('news_cache_is_valid')) {
    function news_cache_is_valid($sourceId)
    {
        $cacheFile = news_cache_xml_file($sourceId);
        if (!is_file($cacheFile)) {
            return false;
        }

        return (time() - filemtime($cacheFile)) < NEWS_CACHE_DURATION;
    }
}

if (!function_exists('news_cache_age_seconds')) {
    function news_cache_age_seconds($sourceId)
    {
        $cacheFile = news_cache_xml_file($sourceId);
        if (!is_file($cacheFile)) {
            return null;
        }

        return time() - filemtime($cacheFile);
    }
}

if (!function_exists('news_load_cached_xml')) {
    function news_load_cached_xml($sourceId)
    {
        $cacheFile = news_cache_xml_file($sourceId);
        if (!is_file($cacheFile)) {
            return null;
        }

        return file_get_contents($cacheFile);
    }
}

if (!function_exists('news_load_cached_items')) {
    function news_load_cached_items($sourceId)
    {
        $jsonFile = news_cache_json_file($sourceId);
        if (!is_file($jsonFile)) {
            return [];
        }

        $raw = file_get_contents($jsonFile);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return news_sort_items_newest_first($decoded);
    }
}

if (!function_exists('news_save_cache')) {
    function news_save_cache($sourceId, $rssXml, array $items)
    {
        if (!news_ensure_cache_dir()) {
            throw new Exception('Failed to prepare news cache directory: ' . news_cache_dir());
        }

        if ($rssXml instanceof DOMDocument) {
            $xmlPayload = $rssXml->saveXML();
        } else {
            $xmlPayload = (string)$rssXml;
        }

        $xmlSaved = file_put_contents(news_cache_xml_file($sourceId), $xmlPayload);
        if ($xmlSaved === false) {
            throw new Exception('Failed to write news RSS cache file.');
        }

        $jsonPayload = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            throw new Exception('Failed to encode news JSON cache payload.');
        }

        $jsonSaved = file_put_contents(news_cache_json_file($sourceId), $jsonPayload);
        if ($jsonSaved === false) {
            throw new Exception('Failed to write news JSON cache file.');
        }

        return true;
    }
}

if (!function_exists('news_is_update_locked')) {
    function news_is_update_locked($sourceId)
    {
        $lockFile = news_lock_file($sourceId);
        if (!is_file($lockFile)) {
            return false;
        }

        $lockTime = (int)file_get_contents($lockFile);
        $lockAge = time() - $lockTime;

        if ($lockAge > NEWS_LOCK_TIMEOUT) {
            @unlink($lockFile);
            return false;
        }

        return true;
    }
}

if (!function_exists('news_acquire_lock')) {
    function news_acquire_lock($sourceId)
    {
        if (!news_ensure_cache_dir()) {
            return false;
        }

        if (news_is_update_locked($sourceId)) {
            return false;
        }

        return file_put_contents(news_lock_file($sourceId), (string)time()) !== false;
    }
}

if (!function_exists('news_release_lock')) {
    function news_release_lock($sourceId)
    {
        $lockFile = news_lock_file($sourceId);
        if (is_file($lockFile)) {
            @unlink($lockFile);
        }
    }
}

if (!function_exists('news_update_cache')) {
    function news_update_cache($sourceId, $selfUrl = '')
    {
        $source = news_resolve_feed_source($sourceId);
        $sourceId = (string)$source['id'];

        try {
            news_log_info('Starting cache update for ' . $sourceId);

            if ($source['type'] === 'nvd') {
                $cves = news_fetch_nvd_from_api((string)$source['url'], NEWS_RESULTS_PER_PAGE);
                $items = news_normalize_nvd_items($cves);
                $rssXml = news_generate_nvd_rss_feed($cves, $source, $selfUrl !== '' ? $selfUrl : news_feed_self_url($sourceId));
                news_save_cache($sourceId, $rssXml, $items);
            } else {
                $rssXml = news_fetch_http_body((string)$source['url'], ['Accept: application/rss+xml, application/xml, text/xml']);
                $items = news_parse_rss_items($rssXml, NEWS_RESULTS_PER_PAGE);
                news_save_cache($sourceId, $rssXml, $items);
            }

            news_log_info('Cache updated successfully for ' . $sourceId);

            return true;
        } catch (Exception $e) {
            news_log_error('Cache update failed for ' . $sourceId . ': ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('news_is_new_day')) {
    function news_is_new_day($sourceId)
    {
        $cacheFile = news_cache_xml_file($sourceId);
        if (!is_file($cacheFile)) {
            return true;
        }

        return date('Y-m-d', filemtime($cacheFile)) !== date('Y-m-d');
    }
}

if (!function_exists('news_trigger_background_update')) {
    function news_trigger_background_update($sourceId)
    {
        $phpPath = news_resolve_php_binary();
        $scriptPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'news' . DIRECTORY_SEPARATOR . 'background-update.php';
        $sourceId = news_source_cache_basename($sourceId);
        $escapedSource = escapeshellarg($sourceId);

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen('start /B "" "' . $phpPath . '" "' . $scriptPath . '" ' . $escapedSource . ' > NUL 2>&1', 'r'));
            return;
        }

        exec('"' . $phpPath . '" "' . $scriptPath . '" ' . $escapedSource . ' > /dev/null 2>&1 &');
    }
}

if (!function_exists('news_serve_cached_feed')) {
    function news_serve_cached_feed($sourceId, $status = 'HIT')
    {
        $feed = news_load_cached_xml($sourceId);
        if ($feed === null) {
            return false;
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('X-Cache-Status: ' . (string)$status);
        header('X-Feed-Source: ' . news_source_cache_basename($sourceId));
        $age = news_cache_age_seconds($sourceId);
        if ($age !== null) {
            header('X-Cache-Age: ' . $age . ' seconds');
        }
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . date('r', time() + 3600));

        echo $feed;

        return true;
    }
}

if (!function_exists('news_serve_error_feed')) {
    function news_serve_error_feed(array $source)
    {
        header('Content-Type: application/xml; charset=utf-8');
        http_response_code(503);

        $feedLink = htmlspecialchars((string)($source['site_link'] ?? ''), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars('Error - ' . (string)($source['label'] ?? 'Feed') . ' Unavailable', ENT_QUOTES, 'UTF-8');
        $lastBuild = htmlspecialchars(date('r'), ENT_QUOTES, 'UTF-8');
        $pubDate = htmlspecialchars(date('r'), ENT_QUOTES, 'UTF-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0"><channel>';
        echo '<title>' . $title . '</title>';
        echo '<link>' . $feedLink . '</link>';
        echo '<description>Unable to fetch feed at this time. Please try again later.</description>';
        echo '<lastBuildDate>' . $lastBuild . '</lastBuildDate>';
        echo '<item><title>Feed Temporary Unavailable</title>';
        echo '<link>' . $feedLink . '</link>';
        echo '<description>An error occurred while fetching the latest items. The system will retry automatically on the next request.</description>';
        echo '<pubDate>' . $pubDate . '</pubDate></item>';
        echo '</channel></rss>';
    }
}

if (!function_exists('news_handle_feed_request')) {
    function news_handle_feed_request($sourceId = '')
    {
        $source = news_resolve_feed_source($sourceId);
        $sourceId = (string)$source['id'];
        $selfUrl = news_feed_self_url($sourceId);

        if (news_cache_is_valid($sourceId)) {
            news_serve_cached_feed($sourceId, 'HIT');
            return;
        }

        $staleFeed = news_load_cached_xml($sourceId);

        if ($staleFeed !== null) {
            if (news_is_new_day($sourceId)) {
                news_log_info('New day detected - performing synchronous update for ' . $sourceId);

                if (news_acquire_lock($sourceId)) {
                    try {
                        $updated = news_update_cache($sourceId, $selfUrl);
                        news_release_lock($sourceId);

                        if ($updated) {
                            news_serve_cached_feed($sourceId, 'REFRESHED');
                            return;
                        }
                    } catch (Exception $e) {
                        news_log_error('Synchronous update failed: ' . $e->getMessage());
                        news_release_lock($sourceId);
                    }
                }
            } else {
                if (news_acquire_lock($sourceId)) {
                    news_trigger_background_update($sourceId);
                    news_log_info('Background update triggered for ' . $sourceId);
                } else {
                    news_log_info('Update already in progress for ' . $sourceId);
                }

                header('Content-Type: application/xml; charset=utf-8');
                header('X-Cache-Status: STALE');
                header('X-Feed-Source: ' . $sourceId);
                $age = news_cache_age_seconds($sourceId);
                if ($age !== null) {
                    header('X-Cache-Age: ' . $age . ' seconds');
                }
                header('X-Update-Status: ' . (news_is_update_locked($sourceId) ? 'IN_PROGRESS' : 'NONE'));
                echo $staleFeed;
                return;
            }
        }

        news_log_info('No cache exists - performing initial synchronous update for ' . $sourceId);

        if (news_acquire_lock($sourceId)) {
            try {
                $updated = news_update_cache($sourceId, $selfUrl);
                news_release_lock($sourceId);

                if ($updated) {
                    news_serve_cached_feed($sourceId, 'INITIAL');
                    return;
                }
            } catch (Exception $e) {
                news_log_error('Initial update failed: ' . $e->getMessage());
                news_release_lock($sourceId);
            }
        }

        news_log_error('Unable to generate feed - serving error response for ' . $sourceId);
        news_serve_error_feed($source);
    }
}

if (!function_exists('news_ensure_cache_for_ui')) {
    /**
     * @return array{status:string,items:array<int,array<string,mixed>>,source:array<string,mixed>}
     */
    function news_ensure_cache_for_ui($sourceId, $forceRefresh = false)
    {
        $source = news_resolve_feed_source($sourceId);
        $sourceId = (string)$source['id'];
        $selfUrl = news_feed_self_url($sourceId);

        if ($forceRefresh) {
            if (news_acquire_lock($sourceId)) {
                news_update_cache($sourceId, $selfUrl);
                news_release_lock($sourceId);
            }

            return [
                'status' => 'REFRESHED',
                'items' => news_load_cached_items($sourceId),
                'source' => $source,
            ];
        }

        if (news_cache_is_valid($sourceId)) {
            return [
                'status' => 'HIT',
                'items' => news_load_cached_items($sourceId),
                'source' => $source,
            ];
        }

        $hasStale = news_load_cached_xml($sourceId) !== null;

        if ($hasStale) {
            if (news_is_new_day($sourceId)) {
                if (news_acquire_lock($sourceId)) {
                    $updated = news_update_cache($sourceId, $selfUrl);
                    news_release_lock($sourceId);
                    if ($updated) {
                        return [
                            'status' => 'REFRESHED',
                            'items' => news_load_cached_items($sourceId),
                            'source' => $source,
                        ];
                    }
                }
            } else {
                if (news_acquire_lock($sourceId)) {
                    news_trigger_background_update($sourceId);
                }

                return [
                    'status' => 'STALE',
                    'items' => news_load_cached_items($sourceId),
                    'source' => $source,
                ];
            }
        }

        if (news_acquire_lock($sourceId)) {
            $updated = news_update_cache($sourceId, $selfUrl);
            news_release_lock($sourceId);
            if ($updated) {
                return [
                    'status' => 'INITIAL',
                    'items' => news_load_cached_items($sourceId),
                    'source' => $source,
                ];
            }
        }

        return [
            'status' => 'ERROR',
            'items' => news_load_cached_items($sourceId),
            'source' => $source,
        ];
    }
}

if (!function_exists('news_severity_badge_html')) {
    function news_severity_badge_html($severity)
    {
        $severityText = strtoupper(trim((string)$severity));
        $class = 'badge-secondary';

        if ($severityText === 'CRITICAL' || $severityText === 'HIGH') {
            $class = 'badge-danger';
        } elseif ($severityText === 'MEDIUM') {
            $class = 'badge-warning';
        } elseif ($severityText === 'LOW') {
            $class = 'badge-success';
        }

        $label = $severityText !== '' ? $severityText : 'Unknown';

        return '<span class="badge ' . $class . '">' . sanitize($label) . '</span>';
    }
}
