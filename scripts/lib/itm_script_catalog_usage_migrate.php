<?php
/**
 * Helpers for moving scripts.php catalog "How to use" into PHP entry scripts.
 */

if (!function_exists('itm_script_catalog_usage_extract_fifth_td')) {
  /**
   * @return string HTML inner of 5th <td> or empty
   */
  function itm_script_catalog_usage_extract_fifth_td(string $rowHtml): string
  {
    if (!preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $rowHtml, $matches)) {
      return '';
    }
    $cells = $matches[1];
    if (count($cells) < 5) {
      return '';
    }

    return trim((string)$cells[4]);
  }
}

if (!function_exists('itm_script_catalog_usage_row_is_php_href')) {
  function itm_script_catalog_usage_row_is_php_href(string $slug, string $href): bool
  {
    $href = trim($href);
    if ($href !== '' && preg_match('/\.php$/i', $href)) {
      return true;
    }

    return preg_match('/\.php$/i', $slug) === 1;
  }
}

if (!function_exists('itm_script_catalog_usage_row_is_browser_capable')) {
  function itm_script_catalog_usage_row_is_browser_capable(string $rowHtml): bool
  {
    if (strpos($rowHtml, 'scripts-badge-web') === false) {
      return false;
    }
    if (strpos($rowHtml, 'scripts-badge-cli-only') !== false
      && strpos($rowHtml, 'scripts-badge-web') === false) {
      return false;
    }

    return true;
  }
}

if (!function_exists('itm_script_catalog_usage_html_to_usage_body')) {
  /**
   * Keep safe inline markup from catalog cells; normalize whitespace.
   */
  function itm_script_catalog_usage_html_to_usage_body(string $html): string
  {
    $html = trim($html);
    if ($html === '') {
      return '';
    }
  $html = preg_replace('/\s+/u', ' ', $html) ?? $html;
    $html = str_replace(['> <', '>  <'], '><', $html);

    return trim($html);
  }
}

if (!function_exists('itm_script_catalog_usage_build_function_snippet')) {
  function itm_script_catalog_usage_build_function_snippet(string $usageBody): string
  {
    $usageBody = trim($usageBody);
    if ($usageBody === '') {
      return '';
    }

    $delimiter = 'ITM_SCRIPT_BROWSER_HOW_TO_USE';
    $attempts = 0;
    while (strpos($usageBody, $delimiter) !== false && $attempts < 8) {
      $delimiter = 'ITM_SCRIPT_BROWSER_HOW_TO_USE_' . substr(md5($delimiter . (string)$attempts), 0, 8);
      $attempts++;
    }

    $snippet = "\n/**\n * Browser catalog: How to use (shown on landing before run=1).\n */\n";
    $snippet .= "function itm_script_browser_how_to_use(): string\n";
    $snippet .= "{\n";
    $snippet .= "    return <<<'" . $delimiter . "'\n";
    $snippet .= $usageBody . "\n";
    $snippet .= $delimiter . ";\n";
    $snippet .= "}\n";

    return $snippet;
  }
}

if (!function_exists('itm_script_catalog_usage_inject_into_php')) {
  /**
   * @return array{changed:bool,reason:string}
   */
  function itm_script_catalog_usage_inject_into_php(string $phpSource, string $usageBody): array
  {
    if (strpos($phpSource, 'function itm_script_browser_how_to_use') !== false) {
      return ['changed' => false, 'reason' => 'already_has_usage_function'];
    }

    $snippet = itm_script_catalog_usage_build_function_snippet($usageBody);
    if ($snippet === '') {
      return ['changed' => false, 'reason' => 'empty_usage'];
    }

    if (!preg_match('/\A(\s*<\?php\s*)(.*)\z/s', $phpSource, $openMatch)) {
      return ['changed' => false, 'reason' => 'no_php_open'];
    }

    $afterOpen = $openMatch[2];
    $insertAt = 0;
    if (preg_match('/\A(\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*)?(\s*(?:\/\*\*.*?\*\/\s*)?)/s', $afterOpen, $leadMatch)) {
      $insertAt = strlen($leadMatch[0]);
    } elseif (preg_match('/\A(\s*(?:\/\*\*.*?\*\/\s*)?)/s', $afterOpen, $leadMatch)) {
      $insertAt = strlen($leadMatch[1]);
    }

    $newSource = $openMatch[1] . substr($afterOpen, 0, $insertAt) . $snippet . substr($afterOpen, $insertAt);

    return ['changed' => true, 'reason' => 'injected', 'source' => $newSource];
  }
}

if (!function_exists('itm_script_catalog_usage_stub_how_td')) {
  function itm_script_catalog_usage_stub_how_td(): string
  {
    return '<td class="scripts-catalog-how-stub">Open in browser for usage.</td>';
  }
}

if (!function_exists('itm_script_catalog_usage_patch_row_how_cell')) {
  /**
   * Replace 5th catalog <td> with stub for PHP rows.
   */
  function itm_script_catalog_usage_patch_row_how_cell(string $rowInner): string
  {
    $count = 0;
    $patched = preg_replace_callback(
      '/<td\b[^>]*>.*?<\/td>/is',
      static function (array $match) use (&$count) {
        $count++;
        if ($count === 5) {
          return itm_script_catalog_usage_stub_how_td();
        }

        return $match[0];
      },
      $rowInner,
      5
    );

    return is_string($patched) ? $patched : $rowInner;
  }
}

if (!function_exists('itm_script_catalog_usage_script_supports_apply')) {
  function itm_script_catalog_usage_script_supports_apply(string $scriptPath, string $howHtml): bool
  {
    if (strpos($howHtml, 'apply=1') !== false || strpos($howHtml, '--apply') !== false) {
      return true;
    }
    if (!is_file($scriptPath)) {
      return false;
    }
    $src = file_get_contents($scriptPath);
    if (!is_string($src)) {
      return false;
    }

    return strpos($src, 'itm_apply_script_bootstrap') !== false;
  }
}

if (!function_exists('itm_script_catalog_usage_needs_explicit_gate')) {
  function itm_script_catalog_usage_needs_explicit_gate(string $phpSource): bool
  {
    if (strpos($phpSource, 'itm_script_browser_usage_maybe_gate') !== false) {
      return false;
    }
    if (strpos($phpSource, 'itm_apply_script_bootstrap') !== false) {
      return false;
    }
    if (strpos($phpSource, 'itm_script_regression_entry.php') !== false) {
      return false;
    }
    if (strpos($phpSource, 'itm_check_script_begin_browser_admin') !== false) {
      return false;
    }
    if (strpos($phpSource, 'itm_script_output_begin') !== false) {
      return false;
    }

    return true;
  }
}

if (!function_exists('itm_script_catalog_usage_gate_snippet')) {
  function itm_script_catalog_usage_gate_snippet(): string
  {
    return "\nif (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {\n"
      . "    require_once __DIR__ . '/lib/itm_script_browser_usage.php';\n"
      . "    itm_script_browser_usage_maybe_gate([]);\n"
      . "}\n";
  }
}

if (!function_exists('itm_script_catalog_usage_inject_gate_after_config')) {
  /**
   * @return array{changed:bool,reason:string,source?:string}
   */
  function itm_script_catalog_usage_inject_gate_after_config(string $phpSource): array
  {
    if (!itm_script_catalog_usage_needs_explicit_gate($phpSource)) {
      return ['changed' => false, 'reason' => 'gate_not_needed'];
    }
    if (strpos($phpSource, 'function itm_script_browser_how_to_use') === false) {
      return ['changed' => false, 'reason' => 'no_usage_function'];
    }

    $pattern = '/(require_once\s+[^;]*config\.php\s*;)/i';
    if (!preg_match($pattern, $phpSource, $match, PREG_OFFSET_CAPTURE)) {
      return ['changed' => false, 'reason' => 'no_config_require'];
    }

    $insertPos = $match[0][1] + strlen($match[0][0]);
    $snippet = itm_script_catalog_usage_gate_snippet();
    if (strpos(substr($phpSource, 0, $insertPos + 200), 'itm_script_browser_usage_maybe_gate') !== false) {
      return ['changed' => false, 'reason' => 'gate_present'];
    }

    $newSource = substr($phpSource, 0, $insertPos) . $snippet . substr($phpSource, $insertPos);

    return ['changed' => true, 'reason' => 'gate_injected', 'source' => $newSource];
  }
}

if (!function_exists('itm_script_catalog_usage_inject_gate_before_output_begin')) {
  /**
   * @return array{changed:bool,reason:string,source?:string}
   */
  function itm_script_catalog_usage_inject_gate_before_output_begin(string $phpSource): array
  {
    if (!itm_script_catalog_usage_needs_explicit_gate($phpSource)) {
      return ['changed' => false, 'reason' => 'gate_not_needed'];
    }
    if (strpos($phpSource, 'function itm_script_browser_how_to_use') === false) {
      return ['changed' => false, 'reason' => 'no_usage_function'];
    }
    if (!preg_match('/\bitm_script_output_begin\s*\(/', $phpSource, $match, PREG_OFFSET_CAPTURE)) {
      return ['changed' => false, 'reason' => 'no_output_begin'];
    }

    $insertPos = $match[0][1];
    $snippet = itm_script_catalog_usage_gate_snippet();
    $before = substr($phpSource, max(0, $insertPos - 120), 120);
    if (strpos($before, 'itm_script_browser_usage_maybe_gate') !== false) {
      return ['changed' => false, 'reason' => 'gate_present'];
    }

    $newSource = substr($phpSource, 0, $insertPos) . $snippet . substr($phpSource, $insertPos);

    return ['changed' => true, 'reason' => 'gate_before_output_begin', 'source' => $newSource];
  }
}
