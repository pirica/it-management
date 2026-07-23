<?php
/**
 * NVD CVE feed cache, RSS generation, and refresh helpers (no cron).
 */

if (!defined('CVE_NVD_API_URL')) {
    define('CVE_NVD_API_URL', 'https://services.nvd.nist.gov/rest/json/cves/2.0');
}
if (!defined('CVE_RESULTS_PER_PAGE')) {
    define('CVE_RESULTS_PER_PAGE', 50);
}
if (!defined('CVE_CACHE_DURATION')) {
    define('CVE_CACHE_DURATION', 86400);
}
if (!defined('CVE_LOCK_TIMEOUT')) {
    define('CVE_LOCK_TIMEOUT', 300);
}
if (!defined('CVE_FEED_TITLE')) {
    define('CVE_FEED_TITLE', 'Latest CVE Security Advisories');
}
if (!defined('CVE_FEED_DESCRIPTION')) {
    define('CVE_FEED_DESCRIPTION', 'Recent Common Vulnerabilities and Exposures from the National Vulnerability Database');
}
if (!defined('CVE_FEED_LINK')) {
    define('CVE_FEED_LINK', 'https://nvd.nist.gov/');
}

if (!function_exists('cve_cache_dir')) {
    function cve_cache_dir()
    {
        if (defined('ROOT_PATH')) {
            return rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'cve' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
        }

        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'cve' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('cve_cache_xml_file')) {
    function cve_cache_xml_file()
    {
        return cve_cache_dir() . 'cve-feed.xml';
    }
}

if (!function_exists('cve_cache_json_file')) {
    function cve_cache_json_file()
    {
        return cve_cache_dir() . 'cve-feed.json';
    }
}

if (!function_exists('cve_lock_file')) {
    function cve_lock_file()
    {
        return cve_cache_dir() . 'update.lock';
    }
}

if (!function_exists('cve_feed_user_agent')) {
    function cve_feed_user_agent()
    {
        $base = defined('BASE_URL') ? (string)BASE_URL : '';
        if ($base === '' && isset($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/';
        }

        return 'IT-Management-CVE-Feed/1.0 (' . $base . ')';
    }
}

if (!function_exists('cve_resolve_php_binary')) {
    function cve_resolve_php_binary()
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

if (!function_exists('cve_ensure_cache_dir')) {
    function cve_ensure_cache_dir()
    {
        $dir = cve_cache_dir();
        if (function_exists('itm_ensure_upload_directory')) {
            return itm_ensure_upload_directory($dir, 'upload');
        }

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return false;
        }

        return is_dir($dir) && is_writable($dir);
    }
}

if (!function_exists('cve_log_info')) {
    function cve_log_info($message)
    {
        error_log('[CVE feed] INFO: ' . (string)$message);
    }
}

if (!function_exists('cve_log_error')) {
    function cve_log_error($message)
    {
        error_log('[CVE feed] ERROR: ' . (string)$message);
    }
}

if (!function_exists('cve_fetch_from_api')) {
    function cve_fetch_from_api($url, $limit)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?resultsPerPage=' . (int)$limit . '&startIndex=0');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_USERAGENT, cve_feed_user_agent());
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
            throw new Exception('NVD API returned HTTP code: ' . $httpCode);
        }

        $data = json_decode((string)$response, true);
        if (!isset($data['vulnerabilities']) || !is_array($data['vulnerabilities'])) {
            throw new Exception('Invalid response format from NVD API');
        }

        return $data['vulnerabilities'];
    }
}

if (!function_exists('cve_extract_cvss')) {
    function cve_extract_cvss(array $cve)
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

if (!function_exists('cve_normalize_items_from_api')) {
    function cve_normalize_items_from_api(array $cves)
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

            $cvss = cve_extract_cvss($cve);

            $items[] = [
                'id' => $cveId,
                'link' => 'https://nvd.nist.gov/vuln/detail/' . rawurlencode($cveId),
                'description' => $description,
                'published' => (string)($cve['published'] ?? ''),
                'last_modified' => (string)($cve['lastModified'] ?? ''),
                'base_score' => $cvss['base_score'],
                'severity' => $cvss['severity'],
                'cvss_label' => $cvss['label'],
            ];
        }

        return $items;
    }
}

if (!function_exists('cve_current_request_url')) {
    function cve_current_request_url()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');

        return $protocol . '://' . $host . $uri;
    }
}

if (!function_exists('cve_generate_rss_feed')) {
    function cve_generate_rss_feed(array $cves, $selfUrl = '')
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

        $channel->appendChild($xml->createElement('title', CVE_FEED_TITLE));
        $channel->appendChild($xml->createElement('link', CVE_FEED_LINK));
        $channel->appendChild($xml->createElement('description', CVE_FEED_DESCRIPTION));
        $channel->appendChild($xml->createElement('language', 'en-us'));
        $channel->appendChild($xml->createElement('lastBuildDate', date('r')));
        $channel->appendChild($xml->createElement('generator', 'IT Management CVE Feed'));

        if ($selfUrl === '') {
            $selfUrl = cve_current_request_url();
        }

        $selfLink = $xml->createElement('atom:link');
        $selfLink->setAttribute('href', $selfUrl);
        $selfLink->setAttribute('rel', 'self');
        $selfLink->setAttribute('type', 'application/rss+xml');
        $channel->appendChild($selfLink);

        foreach ($cves as $cveData) {
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

            $link = 'https://nvd.nist.gov/vuln/detail/' . $cveId;
            $item->appendChild($xml->createElement('link', $link));

            $description = '';
            if (isset($cve['descriptions'][0]['value'])) {
                $description = htmlspecialchars((string)$cve['descriptions'][0]['value'], ENT_QUOTES, 'UTF-8');
            }

            $cvss = cve_extract_cvss($cve);
            if ($cvss['label'] !== '') {
                $description .= PHP_EOL . PHP_EOL . 'CVSS Score: ' . $cvss['label'];
            }

            $item->appendChild($xml->createElement('description', $description));

            if (!empty($cve['published'])) {
                $item->appendChild($xml->createElement('pubDate', date('r', strtotime((string)$cve['published']))));
            }

            if (!empty($cve['lastModified'])) {
                $item->appendChild($xml->createElement('dc:date', (string)$cve['lastModified']));
            }

            $guid = $xml->createElement('guid', $cveId);
            $guid->setAttribute('isPermaLink', 'false');
            $item->appendChild($guid);
            $item->appendChild($xml->createElement('source', 'NVD'));

            $channel->appendChild($item);
        }

        return $xml;
    }
}

if (!function_exists('cve_cache_is_valid')) {
    function cve_cache_is_valid()
    {
        $cacheFile = cve_cache_xml_file();
        if (!is_file($cacheFile)) {
            return false;
        }

        return (time() - filemtime($cacheFile)) < CVE_CACHE_DURATION;
    }
}

if (!function_exists('cve_cache_age_seconds')) {
    function cve_cache_age_seconds()
    {
        $cacheFile = cve_cache_xml_file();
        if (!is_file($cacheFile)) {
            return null;
        }

        return time() - filemtime($cacheFile);
    }
}

if (!function_exists('cve_load_cached_xml')) {
    function cve_load_cached_xml()
    {
        $cacheFile = cve_cache_xml_file();
        if (!is_file($cacheFile)) {
            return null;
        }

        return file_get_contents($cacheFile);
    }
}

if (!function_exists('cve_load_cached_items')) {
    function cve_load_cached_items()
    {
        $jsonFile = cve_cache_json_file();
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

        return $decoded;
    }
}

if (!function_exists('cve_save_cache')) {
    function cve_save_cache(DOMDocument $rssXml, array $items)
    {
        if (!cve_ensure_cache_dir()) {
            throw new Exception('Failed to prepare CVE cache directory: ' . cve_cache_dir());
        }

        $xmlSaved = file_put_contents(cve_cache_xml_file(), $rssXml->saveXML());
        if ($xmlSaved === false) {
            throw new Exception('Failed to write CVE RSS cache file.');
        }

        $jsonPayload = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            throw new Exception('Failed to encode CVE JSON cache payload.');
        }

        $jsonSaved = file_put_contents(cve_cache_json_file(), $jsonPayload);
        if ($jsonSaved === false) {
            throw new Exception('Failed to write CVE JSON cache file.');
        }

        return true;
    }
}

if (!function_exists('cve_is_update_locked')) {
    function cve_is_update_locked()
    {
        $lockFile = cve_lock_file();
        if (!is_file($lockFile)) {
            return false;
        }

        $lockTime = (int)file_get_contents($lockFile);
        $lockAge = time() - $lockTime;

        if ($lockAge > CVE_LOCK_TIMEOUT) {
            @unlink($lockFile);
            return false;
        }

        return true;
    }
}

if (!function_exists('cve_acquire_lock')) {
    function cve_acquire_lock()
    {
        if (!cve_ensure_cache_dir()) {
            return false;
        }

        if (cve_is_update_locked()) {
            return false;
        }

        return file_put_contents(cve_lock_file(), (string)time()) !== false;
    }
}

if (!function_exists('cve_release_lock')) {
    function cve_release_lock()
    {
        $lockFile = cve_lock_file();
        if (is_file($lockFile)) {
            @unlink($lockFile);
        }
    }
}

if (!function_exists('cve_update_cache')) {
    function cve_update_cache($selfUrl = '')
    {
        try {
            cve_log_info('Starting cache update...');

            $cves = cve_fetch_from_api(CVE_NVD_API_URL, CVE_RESULTS_PER_PAGE);
            cve_log_info('Fetched ' . count($cves) . ' CVEs from NVD API');

            $items = cve_normalize_items_from_api($cves);
            $rssXml = cve_generate_rss_feed($cves, $selfUrl);
            cve_save_cache($rssXml, $items);

            cve_log_info('Cache updated successfully');

            return true;
        } catch (Exception $e) {
            cve_log_error('Cache update failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('cve_is_new_day')) {
    function cve_is_new_day()
    {
        $cacheFile = cve_cache_xml_file();
        if (!is_file($cacheFile)) {
            return true;
        }

        return date('Y-m-d', filemtime($cacheFile)) !== date('Y-m-d');
    }
}

if (!function_exists('cve_trigger_background_update')) {
    function cve_trigger_background_update()
    {
        $phpPath = cve_resolve_php_binary();
        $scriptPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'cve' . DIRECTORY_SEPARATOR . 'background-update.php';

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            pclose(popen('start /B "" "' . $phpPath . '" "' . $scriptPath . '" > NUL 2>&1', 'r'));
            return;
        }

        exec('"' . $phpPath . '" "' . $scriptPath . '" > /dev/null 2>&1 &');
    }
}

if (!function_exists('cve_serve_cached_feed')) {
    function cve_serve_cached_feed($status = 'HIT')
    {
        $feed = cve_load_cached_xml();
        if ($feed === null) {
            return false;
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('X-Cache-Status: ' . (string)$status);
        $age = cve_cache_age_seconds();
        if ($age !== null) {
            header('X-Cache-Age: ' . $age . ' seconds');
        }
        header('Cache-Control: public, max-age=3600');
        header('Expires: ' . date('r', time() + 3600));

        echo $feed;

        return true;
    }
}

if (!function_exists('cve_serve_error_feed')) {
    function cve_serve_error_feed()
    {
        header('Content-Type: application/xml; charset=utf-8');
        http_response_code(503);

        $feedLink = htmlspecialchars(CVE_FEED_LINK, ENT_QUOTES, 'UTF-8');
        $lastBuild = htmlspecialchars(date('r'), ENT_QUOTES, 'UTF-8');
        $pubDate = htmlspecialchars(date('r'), ENT_QUOTES, 'UTF-8');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0"><channel>';
        echo '<title>Error - CVE Feed Unavailable</title>';
        echo '<link>' . $feedLink . '</link>';
        echo '<description>Unable to fetch CVEs at this time. Please try again later.</description>';
        echo '<lastBuildDate>' . $lastBuild . '</lastBuildDate>';
        echo '<item><title>Feed Temporary Unavailable</title>';
        echo '<link>' . $feedLink . '</link>';
        echo '<description>An error occurred while fetching the latest CVEs. The system will retry automatically on the next request.</description>';
        echo '<pubDate>' . $pubDate . '</pubDate></item>';
        echo '</channel></rss>';
    }
}

if (!function_exists('cve_handle_feed_request')) {
  /**
   * RSS endpoint handler (ported from standalone CVE feed index.php).
   */
    function cve_handle_feed_request()
    {
        $selfUrl = cve_current_request_url();

        if (cve_cache_is_valid()) {
            cve_serve_cached_feed('HIT');
            return;
        }

        $staleFeed = cve_load_cached_xml();

        if ($staleFeed !== null) {
            if (cve_is_new_day()) {
                cve_log_info('New day detected - performing synchronous update');

                if (cve_acquire_lock()) {
                    try {
                        $updated = cve_update_cache($selfUrl);
                        cve_release_lock();

                        if ($updated) {
                            cve_serve_cached_feed('REFRESHED');
                            return;
                        }
                    } catch (Exception $e) {
                        cve_log_error('Synchronous update failed: ' . $e->getMessage());
                        cve_release_lock();
                    }
                }
            } else {
                if (cve_acquire_lock()) {
                    cve_trigger_background_update();
                    cve_log_info('Background update triggered');
                } else {
                    cve_log_info('Update already in progress');
                }

                header('Content-Type: application/xml; charset=utf-8');
                header('X-Cache-Status: STALE');
                $age = cve_cache_age_seconds();
                if ($age !== null) {
                    header('X-Cache-Age: ' . $age . ' seconds');
                }
                header('X-Update-Status: ' . (cve_is_update_locked() ? 'IN_PROGRESS' : 'NONE'));
                echo $staleFeed;
                return;
            }
        }

        cve_log_info('No cache exists - performing initial synchronous update');

        if (cve_acquire_lock()) {
            try {
                $updated = cve_update_cache($selfUrl);
                cve_release_lock();

                if ($updated) {
                    cve_serve_cached_feed('INITIAL');
                    return;
                }
            } catch (Exception $e) {
                cve_log_error('Initial update failed: ' . $e->getMessage());
                cve_release_lock();
            }
        }

        cve_log_error('Unable to generate feed - serving error response');
        cve_serve_error_feed();
    }
}

if (!function_exists('cve_ensure_cache_for_ui')) {
  /**
   * Ensure cache is populated for HTML UI without emitting RSS headers.
   *
   * @return array{status:string,items:array<int,array<string,mixed>>}
   */
    function cve_ensure_cache_for_ui($forceRefresh = false)
    {
        $selfUrl = '';
        if (defined('BASE_URL')) {
            $selfUrl = rtrim((string)BASE_URL, '/') . '/modules/cve/feed.php';
        }

        if ($forceRefresh) {
            if (cve_acquire_lock()) {
                cve_update_cache($selfUrl);
                cve_release_lock();
            }

            return [
                'status' => 'REFRESHED',
                'items' => cve_load_cached_items(),
            ];
        }

        if (cve_cache_is_valid()) {
            return [
                'status' => 'HIT',
                'items' => cve_load_cached_items(),
            ];
        }

        $hasStale = cve_load_cached_xml() !== null;

        if ($hasStale) {
            if (cve_is_new_day()) {
                if (cve_acquire_lock()) {
                    $updated = cve_update_cache($selfUrl);
                    cve_release_lock();
                    if ($updated) {
                        return [
                            'status' => 'REFRESHED',
                            'items' => cve_load_cached_items(),
                        ];
                    }
                }
            } else {
                if (cve_acquire_lock()) {
                    cve_trigger_background_update();
                }

                return [
                    'status' => 'STALE',
                    'items' => cve_load_cached_items(),
                ];
            }
        }

        if (cve_acquire_lock()) {
            $updated = cve_update_cache($selfUrl);
            cve_release_lock();
            if ($updated) {
                return [
                    'status' => 'INITIAL',
                    'items' => cve_load_cached_items(),
                ];
            }
        }

        return [
            'status' => 'ERROR',
            'items' => cve_load_cached_items(),
        ];
    }
}

if (!function_exists('cve_severity_badge_html')) {
    function cve_severity_badge_html($severity)
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
