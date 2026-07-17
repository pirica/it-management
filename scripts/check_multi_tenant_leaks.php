<?php
/**
 * Multi-Tenant Leak Checker (Optimized v4)
 * Scans modules/ for SQL queries and UI elements that might leak data across companies.
 * Supports CLI and Browser.
 *
 * Report model:
 * - Likely leak: no tenant predicate and no strong scope signal in the query.
 * - Needs review (context-validated?): no direct tenant predicate, but nearby/context hints suggest another guard.
 */

$modules_dir = __DIR__ . '/../modules';
$database_sql = __DIR__ . '/../database.sql';
$project_root = realpath(__DIR__ . '/..');
$allowlist_file = __DIR__ . '/data/multi_tenant_leak_allowlist.json';

function get_table_exceptions() {
    return [
        'companies' => 'No company_id column (global / system table).',
        'audit_logs' => 'Append-only log table; tenant scope UNIQUE not required.'
    ];
}

/**
 * Parse table definitions from database.sql and separate scoped/non-scoped tables.
 */
function parse_database_tables($sql_file) {
    if (!file_exists($sql_file)) {
        return [
            'tables' => [],
            'total' => 0,
            'scoped' => [],
            'non_scoped' => [],
            'raw_create_count' => 0
        ];
    }

    $sql = file_get_contents($sql_file);
    $raw_create_count = preg_match_all('/CREATE TABLE\s+`/i', $sql, $throwaway);

    $matches = [];
    preg_match_all('/CREATE TABLE\s+`([^`]+)`\s*\((.*?)\)\s*ENGINE\b/si', $sql, $matches, PREG_SET_ORDER);

    // Fallback if table options do not include ENGINE in some CREATE statements.
    if (empty($matches) && $raw_create_count > 0) {
        preg_match_all('/CREATE TABLE\s+`([^`]+)`\s*\((.*?)\)\s*;/si', $sql, $matches, PREG_SET_ORDER);
    }

    $tables = [];
    $scoped = [];
    $non_scoped = [];

    foreach ($matches as $match) {
        $table_name = $match[1];
        $table_def = $match[2];
        $has_company_id = (stripos($table_def, '`company_id`') !== false);

        $tables[$table_name] = [
            'has_company_id' => $has_company_id
        ];

        if ($has_company_id) {
            $scoped[] = $table_name;
        } else {
            $non_scoped[] = $table_name;
        }
    }

    $scoped = array_values(array_unique($scoped));
    $non_scoped = array_values(array_unique($non_scoped));
    sort($scoped);
    sort($non_scoped);

    return [
        'tables' => $tables,
        'total' => count($tables),
        'scoped' => $scoped,
        'non_scoped' => $non_scoped,
        'raw_create_count' => (int) $raw_create_count
    ];
}

function build_table_regex($tables) {
    if (empty($tables)) {
        return null;
    }

    $escaped = [];
    foreach ($tables as $table) {
        $escaped[] = preg_quote($table, '/');
    }

    return '/\b(' . implode('|', $escaped) . ')\b/i';
}

function normalize_relative_path($root, $path) {
    $root_norm = str_replace('\\', '/', rtrim((string) $root, '\\/'));
    $path_norm = str_replace('\\', '/', (string) $path);

    if ($root_norm !== '' && strpos($path_norm, $root_norm) === 0) {
        $path_norm = substr($path_norm, strlen($root_norm));
    }

    return ltrim($path_norm, '/');
}

function offset_to_line_number($content, $offset) {
    return substr_count(substr($content, 0, $offset), "\n") + 1;
}

function extract_line_at_offset($content, $offset) {
    $start = strrpos(substr($content, 0, $offset), "\n");
    $start = ($start === false) ? 0 : $start + 1;

    $end = strpos($content, "\n", $offset);
    $end = ($end === false) ? strlen($content) : $end;

    return substr($content, $start, $end - $start);
}

function split_content_lines($content) {
    return preg_split('/\r\n|\r|\n/', $content);
}

/**
 * Detect real tenant predicate, not just the company_id token.
 */
function query_has_company_predicate($sql_fragment) {
    if (stripos($sql_fragment, 'company_id') === false) {
        return false;
    }

    $patterns = [
        '/\b(?:where|and|or|on)\b[\s\S]{0,500}?\b(?:[a-z_][a-z0-9_]*\.)?company_id\b\s*(?:=|<=>|IN\s*\(|IS\s+NULL|IS\s+NOT\s+NULL)/i',
        '/\b(?:where|and|or|on)\b[\s\S]{0,500}?(?:\?|\$[a-z_][a-z0-9_]*|:[a-z_][a-z0-9_]*|\(int\)\s*\$[a-z_][a-z0-9_]*|\d+)\s*=\s*(?:[a-z_][a-z0-9_]*\.)?company_id\b/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $sql_fragment)) {
            return true;
        }
    }

    return false;
}

function expression_implies_company_scope($expression) {
    if (stripos($expression, 'itm_get_company_where') !== false) {
        return true;
    }

    if (preg_match('/[\'"]company_id[\'"]\s*=>\s*(?:\$company_id|\(int\)\s*\$company_id|\?)/i', $expression)) {
        return true;
    }

    return query_has_company_predicate($expression);
}

/**
 * Track scope state of variables over file lines.
 */
function collect_variable_scope_history($content) {
    $history = [];
    $current_scope = [];

    if (!preg_match_all('/\$(\w+)\s*(=|\.=)\s*(.*?);/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
        return $history;
    }

    $total = count($matches[0]);
    for ($i = 0; $i < $total; $i++) {
        $var_name = $matches[1][$i][0];
        $operator = $matches[2][$i][0];
        $expression = $matches[3][$i][0];
        $offset = $matches[0][$i][1];
        $line = offset_to_line_number($content, $offset);

        $expr_scoped = expression_implies_company_scope($expression);
        $prev_scoped = isset($current_scope[$var_name]) ? (bool) $current_scope[$var_name] : false;

        if ($operator === '.=') {
            $new_scoped = ($prev_scoped || $expr_scoped);
        } else {
            $new_scoped = $expr_scoped;
        }

        $current_scope[$var_name] = $new_scoped;

        if (!isset($history[$var_name])) {
            $history[$var_name] = [];
        }

        $history[$var_name][] = [
            'line' => $line,
            'scoped' => $new_scoped,
            'operator' => $operator
        ];
    }

    return $history;
}

function file_has_scope_signal($variable_scope_history) {
    foreach ($variable_scope_history as $events) {
        foreach ($events as $entry) {
            if (!empty($entry['scoped'])) {
                return true;
            }
        }
    }
    return false;
}

function extract_variable_names($text) {
    if (!preg_match_all('/\$(\w+)/', $text, $matches)) {
        return [];
    }
    return array_values(array_unique($matches[1]));
}

function variable_scope_state_at_line($history, $var_name, $line) {
    if (!isset($history[$var_name])) {
        return null;
    }

    $state = null;
    foreach ($history[$var_name] as $entry) {
        if ($entry['line'] > $line) {
            break;
        }
        $state = (bool) $entry['scoped'];
    }

    return $state;
}

function query_scoped_variables($query_fragment, $line, $variable_scope_history) {
    $vars = extract_variable_names($query_fragment);
    $scoped_vars = [];

    foreach ($vars as $var_name) {
        $state = variable_scope_state_at_line($variable_scope_history, $var_name, $line);
        if ($state === true) {
            $scoped_vars[] = '$' . $var_name;
        }
    }

    return $scoped_vars;
}

function query_uses_scope_function($fragment_or_line) {
    return (stripos($fragment_or_line, 'itm_get_company_where') !== false);
}

function extract_id_lookup_variable($query_fragment) {
    if (preg_match('/\bid\b\s*=\s*(?:\?\s*|\(int\)\s*\$([a-z_][a-z0-9_]*)|\$([a-z_][a-z0-9_]*)|\d+)/i', $query_fragment, $m)) {
        if (!empty($m[1])) {
            return $m[1];
        }
        if (!empty($m[2])) {
            return $m[2];
        }
    }

    if (preg_match('/\bid\b\s*=\s*\{\s*\$([a-z_][a-z0-9_]*)\s*\}/i', $query_fragment, $m)) {
        return $m[1];
    }

    if (preg_match('/\bid\b\s*=\s*[\'"]\s*\.\s*(?:\(\s*int\s*\)\s*)?\$([a-z_][a-z0-9_]*)/i', $query_fragment, $m)) {
        return $m[1];
    }

    return null;
}

function query_is_single_id_lookup($query_fragment) {
    $where_has_id = preg_match('/\bWHERE\b[\s\S]{0,260}?\bid\b\s*=/i', $query_fragment);
    if (!$where_has_id) {
        return false;
    }

    return extract_id_lookup_variable($query_fragment) !== null
        || preg_match('/\bWHERE\b[\s\S]{0,260}?\bid\b\s*=\s*\?/i', $query_fragment);
}

function id_variable_from_scoped_context($file_lines, $line, $query_fragment, $window_lines) {
    $id_var = extract_id_lookup_variable($query_fragment);
    if (empty($id_var) || empty($file_lines)) {
        return false;
    }

    $total = count($file_lines);
    $start = max(1, $line - max(20, (int)$window_lines));
    $end = max(1, min($total, $line - 1));
    if ($start > $end) {
        return false;
    }

    $var_pattern = '/\$' . preg_quote($id_var, '/') . '\s*=\s*\(int\)\s*\(\s*\$[a-z_][a-z0-9_]*\s*\[\s*[\'"]id[\'"]\s*\]/i';
    $assignment_line = null;
    for ($i = $end; $i >= $start; $i--) {
        $text = (string)$file_lines[$i - 1];
        if (preg_match($var_pattern, $text)) {
            $assignment_line = $i;
            break;
        }
    }

    if ($assignment_line === null) {
        return false;
    }

    $probe_start = max(1, $assignment_line - 80);
    $probe_end = min($total, $assignment_line + 5);
    $found_company_scope_signal = false;
    $found_id_row_signal = false;

    for ($i = $probe_start; $i <= $probe_end; $i++) {
        $text = (string)$file_lines[$i - 1];
        if (query_has_company_predicate($text) || query_uses_scope_function($text)) {
            $found_company_scope_signal = true;
        }
        if (preg_match('/\bSELECT\b/i', $text) && preg_match('/\bid\b/i', $text)) {
            $found_id_row_signal = true;
        }
        if (preg_match('/mysqli_stmt_get_result|mysqli_fetch_assoc/i', $text)) {
            $found_id_row_signal = true;
        }
    }

    return $found_company_scope_signal && $found_id_row_signal;
}

function nearby_company_signal($lines, $line, $radius) {
    $total = count($lines);
    if ($total === 0) {
        return false;
    }

    $start = max(1, $line - $radius);
    $end = min($total, $line + $radius);

    for ($i = $start; $i <= $end; $i++) {
        $text = $lines[$i - 1];
        if (query_has_company_predicate($text) || query_uses_scope_function($text)) {
            return true;
        }
    }

    return false;
}

function compact_snippet($text, $limit) {
    $single_line = preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $text));
    $single_line = trim($single_line);
    if (strlen($single_line) <= $limit) {
        return $single_line;
    }
    return substr($single_line, 0, $limit) . '...';
}

function detect_query_type($query_fragment) {
    if (preg_match('/\b(SELECT|UPDATE|DELETE|INSERT|FROM|JOIN|INTO)\b/i', $query_fragment, $m)) {
        return strtoupper($m[1]);
    }
    return 'UNKNOWN';
}

function query_fragment_has_sql_shape($query_fragment, $query_type) {
    $fragment = (string)$query_fragment;
    $type = strtoupper((string)$query_type);

    switch ($type) {
        case 'SELECT':
            return preg_match('/\bSELECT\b[\s\S]{0,1600}\bFROM\b/i', $fragment) === 1;
        case 'UPDATE':
            return preg_match('/\bUPDATE\b[\s\S]{0,1600}\bSET\b/i', $fragment) === 1;
        case 'DELETE':
            return preg_match('/\bDELETE\b[\s\S]{0,1600}\bFROM\b/i', $fragment) === 1;
        case 'INSERT':
            return preg_match('/\bINSERT\b[\s\S]{0,1600}\bINTO\b/i', $fragment) === 1;
        case 'FROM':
            return preg_match('/^\s*[\'"]?\s*FROM\b/i', $fragment) === 1;
        case 'JOIN':
            return preg_match('/^\s*[\'"]?\s*(?:LEFT|RIGHT|INNER|OUTER|CROSS|STRAIGHT)?\s*JOIN\b/i', $fragment) === 1;
        case 'INTO':
            return preg_match('/^\s*[\'"]?\s*INTO\b/i', $fragment) === 1;
        default:
            return false;
    }
}

function read_balanced_parentheses_span($content, $open_paren_pos) {
    $length = strlen($content);
    if ($open_paren_pos < 0 || $open_paren_pos >= $length || $content[$open_paren_pos] !== '(') {
        return null;
    }

    $depth = 0;
    $quote = '';
    $escaped = false;

    for ($i = $open_paren_pos; $i < $length; $i++) {
        $char = $content[$i];

        if ($quote !== '') {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === $quote) {
                $quote = '';
            }
            continue;
        }

        if ($char === "'" || $char === '"') {
            $quote = $char;
            continue;
        }

        if ($char === '(') {
            $depth++;
            continue;
        }

        if ($char === ')') {
            $depth--;
            if ($depth === 0) {
                return [
                    'inner' => substr($content, $open_paren_pos + 1, $i - $open_paren_pos - 1),
                    'close' => $i
                ];
            }
        }
    }

    return null;
}

function split_top_level_arguments_with_offsets($text) {
    $args = [];
    $length = strlen($text);
    $arg_start = 0;
    $paren_depth = 0;
    $bracket_depth = 0;
    $brace_depth = 0;
    $quote = '';
    $escaped = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $text[$i];

        if ($quote !== '') {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                continue;
            }
            if ($char === $quote) {
                $quote = '';
            }
            continue;
        }

        if ($char === "'" || $char === '"') {
            $quote = $char;
            continue;
        }

        if ($char === '(') {
            $paren_depth++;
            continue;
        }
        if ($char === ')' && $paren_depth > 0) {
            $paren_depth--;
            continue;
        }
        if ($char === '[') {
            $bracket_depth++;
            continue;
        }
        if ($char === ']' && $bracket_depth > 0) {
            $bracket_depth--;
            continue;
        }
        if ($char === '{') {
            $brace_depth++;
            continue;
        }
        if ($char === '}' && $brace_depth > 0) {
            $brace_depth--;
            continue;
        }

        if ($char === ',' && $paren_depth === 0 && $bracket_depth === 0 && $brace_depth === 0) {
            $raw = substr($text, $arg_start, $i - $arg_start);
            $trimmed = trim($raw);
            if ($trimmed !== '') {
                $leading_ws = strspn($raw, " \t\r\n");
                $trimmed_raw = rtrim($raw);
                $arg_end = $arg_start + strlen($trimmed_raw);
                $args[] = [
                    'text' => $trimmed,
                    'start' => $arg_start + $leading_ws,
                    'end' => $arg_end
                ];
            }
            $arg_start = $i + 1;
        }
    }

    if ($arg_start <= $length) {
        $raw = substr($text, $arg_start);
        $trimmed = trim($raw);
        if ($trimmed !== '') {
            $leading_ws = strspn($raw, " \t\r\n");
            $trimmed_raw = rtrim($raw);
            $arg_end = $arg_start + strlen($trimmed_raw);
            $args[] = [
                'text' => $trimmed,
                'start' => $arg_start + $leading_ws,
                'end' => $arg_end
            ];
        }
    }

    return $args;
}

function extract_mysqli_query_sql_candidates($content) {
    $candidates = [];

    if (!preg_match_all('/\bmysqli_query\s*\(/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
        return $candidates;
    }

    foreach ($matches[0] as $match) {
        $call_text = $match[0];
        $call_start = $match[1];
        $paren_rel = strrpos($call_text, '(');
        if ($paren_rel === false) {
            continue;
        }
        $open_paren_pos = $call_start + $paren_rel;

        $span = read_balanced_parentheses_span($content, $open_paren_pos);
        if ($span === null || !isset($span['inner'], $span['close'])) {
            continue;
        }

        $args = split_top_level_arguments_with_offsets((string) $span['inner']);
        if (count($args) < 2 || empty($args[1]['text'])) {
            continue;
        }

        $sql_arg = $args[1];
        $arg_start_abs = $open_paren_pos + 1 + (int) $sql_arg['start'];
        $arg_end_abs = $open_paren_pos + 1 + (int) $sql_arg['end'];

        $candidates[] = [
            'fragment' => (string) $sql_arg['text'],
            'offset' => $arg_start_abs,
            'arg_start' => $arg_start_abs,
            'arg_end' => $arg_end_abs
        ];
    }

    return $candidates;
}

function offset_within_sql_arg_ranges($offset, $sql_candidates) {
    foreach ($sql_candidates as $candidate) {
        if (!isset($candidate['arg_start'], $candidate['arg_end'])) {
            continue;
        }
        if ($offset >= (int) $candidate['arg_start'] && $offset <= (int) $candidate['arg_end']) {
            return true;
        }
    }
    return false;
}

function sql_expression_starts_with_dml($expression) {
    return preg_match('/^\s*\(*\s*[\'"]?\s*(SELECT|UPDATE|DELETE|INSERT)\b/i', (string)$expression) === 1;
}

function collect_query_candidates($content) {
    $candidates = [];
    $mysqli_query_candidates = extract_mysqli_query_sql_candidates($content);

    foreach ($mysqli_query_candidates as $candidate) {
        $fragment = (string) $candidate['fragment'];
        if (!sql_expression_starts_with_dml($fragment)) {
            continue;
        }
        $candidates[] = [
            'fragment' => $fragment,
            'offset' => (int) $candidate['offset']
        ];
    }

    if (preg_match_all('/([\'"])(SELECT|UPDATE|DELETE|INSERT|FROM|JOIN|INTO)\b.*?\\1/si', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $fragment = $match[0];
            $offset = $match[1];
            if (offset_within_sql_arg_ranges($offset, $mysqli_query_candidates)) {
                continue;
            }
            $candidates[] = [
                'fragment' => $fragment,
                'offset' => $offset
            ];
        }
    }

    usort($candidates, function ($a, $b) {
        return ((int) $a['offset']) <=> ((int) $b['offset']);
    });

    return $candidates;
}

function join_or_dash($items) {
    if (empty($items)) {
        return '-';
    }
    return implode(', ', $items);
}

function load_allowlist_rules($json_file) {
    if (!file_exists($json_file)) {
        return [];
    }

    $raw = file_get_contents($json_file);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['rules']) || !is_array($decoded['rules'])) {
        return [];
    }

    $rules = [];
    foreach ($decoded['rules'] as $rule) {
        if (!is_array($rule)) {
            continue;
        }
        if (isset($rule['enabled']) && !$rule['enabled']) {
            continue;
        }
        if (empty($rule['id'])) {
            continue;
        }
        $rules[] = $rule;
    }

    return $rules;
}

function bool_context_flag($flags, $name) {
    return !empty($flags[$name]);
}

function context_flags_match_all($flags, $required) {
    if (empty($required)) {
        return true;
    }
    foreach ($required as $flag) {
        if (!bool_context_flag($flags, $flag)) {
            return false;
        }
    }
    return true;
}

function context_flags_match_any($flags, $required_any) {
    if (empty($required_any)) {
        return true;
    }
    foreach ($required_any as $flag) {
        if (bool_context_flag($flags, $flag)) {
            return true;
        }
    }
    return false;
}

function allowlist_rule_matches_issue($rule, $issue) {
    if (!empty($rule['issue_type']) && (string)$rule['issue_type'] !== (string)$issue['issue_type']) {
        return false;
    }

    if (!empty($rule['query_types']) && is_array($rule['query_types'])) {
        $ok = false;
        foreach ($rule['query_types'] as $q) {
            if (strcasecmp((string)$q, (string)$issue['query_type']) === 0) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            return false;
        }
    }

    if (!empty($rule['file_regex']) && !preg_match('/' . $rule['file_regex'] . '/i', (string)$issue['file'])) {
        return false;
    }

    if (!empty($rule['table_regex']) && !preg_match('/' . $rule['table_regex'] . '/i', (string)$issue['table'])) {
        return false;
    }

    $flags = isset($issue['context_flags']) && is_array($issue['context_flags']) ? $issue['context_flags'] : [];
    if (!context_flags_match_all($flags, isset($rule['require_context_flags_all']) ? $rule['require_context_flags_all'] : [])) {
        return false;
    }
    if (!context_flags_match_any($flags, isset($rule['require_context_flags_any']) ? $rule['require_context_flags_any'] : [])) {
        return false;
    }

    if (!empty($rule['snippet_regex']) && !preg_match('/' . $rule['snippet_regex'] . '/i', (string)$issue['snippet'])) {
        return false;
    }

    return true;
}

function apply_allowlist_rules_to_issue($issue, $rules) {
    if (!isset($issue['allowlist_rules']) || !is_array($issue['allowlist_rules'])) {
        $issue['allowlist_rules'] = [];
    }

    foreach ($rules as $rule) {
        if (!allowlist_rule_matches_issue($rule, $issue)) {
            continue;
        }

        $rule_id = (string)$rule['id'];
        if (!in_array($rule_id, $issue['allowlist_rules'], true)) {
            $issue['allowlist_rules'][] = $rule_id;
        }

        $downgrade = isset($rule['downgrade_classification_to']) ? trim((string)$rule['downgrade_classification_to']) : '';
        if ($downgrade !== '' && stripos((string)$issue['classification'], 'Likely leak') === 0) {
            $issue['classification'] = $downgrade;
        }
    }

    return $issue;
}

function detect_query_assignment_variable_from_line($line_content) {
    if (preg_match('/\$(\w+)\s*=\s*[\'"]/i', $line_content, $m)) {
        return $m[1];
    }
    return null;
}

function line_has_dynamic_company_append_for_variable($line_text, $var_name) {
    $quoted_var = preg_quote((string)$var_name, '/');
    if (preg_match('/\$' . $quoted_var . '\s*\.=\s*[\'"][^\'"]*company_id/i', $line_text)) {
        return true;
    }
    if (preg_match('/\$' . $quoted_var . '\s*=\s*\$' . $quoted_var . '\s*\.\s*[\'"][^\'"]*company_id/i', $line_text)) {
        return true;
    }
    return false;
}

function detect_dynamic_company_append_same_var($lines, $line, $var_name, $window_lines) {
    if (empty($var_name) || empty($lines)) {
        return false;
    }

    $total = count($lines);
    $forward = max(0, (int)$window_lines);
    $backward = max(8, (int) floor($forward / 2));
    $start = max(1, $line - $backward);
    $end = min($total, $line + $forward);

    for ($i = $start; $i <= $end; $i++) {
        $text = (string)$lines[$i - 1];
        if (line_has_dynamic_company_append_for_variable($text, $var_name)) {
            return true;
        }
    }

    return false;
}

function detect_company_column_gated_dynamic_scope($lines, $line, $var_name, $table, $window_lines) {
    if (empty($var_name) || empty($table) || empty($lines)) {
        return false;
    }

    $total = count($lines);
    $forward = max(0, (int)$window_lines);
    $backward = max(8, (int) floor($forward / 2));
    $start = max(1, $line - $backward);
    $end = min($total, $line + $forward);
    $quoted_table = preg_quote((string)$table, '/');

    $gate_vars = [];
    $append_lines = [];

    for ($i = $start; $i <= $end; $i++) {
        $text = (string)$lines[$i - 1];

        if (line_has_dynamic_company_append_for_variable($text, $var_name)) {
            $append_lines[] = $i;
        }

        if (preg_match('/\$(\w+)\s*=\s*[a-z_][a-z0-9_]*table_has_column\s*\([^;]*[\'"]' . $quoted_table . '[\'"][^;]*[\'"]company_id[\'"]/i', $text, $m)) {
            $gate_vars[] = $m[1];
        }
    }

    if (empty($append_lines) || empty($gate_vars)) {
        return false;
    }

    $gate_vars = array_values(array_unique($gate_vars));
    foreach ($append_lines as $append_line) {
        $probe_start = max($start, $append_line - 14);
        $probe_end = min($end, $append_line + 4);

        for ($i = $probe_start; $i <= $probe_end; $i++) {
            $text = (string)$lines[$i - 1];
            foreach ($gate_vars as $gate_var) {
                $quoted_gate = preg_quote((string)$gate_var, '/');
                if (preg_match('/\bif\s*\([^)]*\$' . $quoted_gate . '\b/i', $text)) {
                    return true;
                }
            }
        }
    }

    return false;
}

function detect_adjacent_company_column_gate_safe_pattern($lines, $line, $var_name, $table, $window_lines) {
    if (empty($var_name) || empty($table) || empty($lines) || $line <= 1) {
        return false;
    }

    $total = count($lines);
    $prev_line_text = (string)$lines[$line - 2];
    $quoted_table = preg_quote((string)$table, '/');

    if (!preg_match('/\$(\w+)\s*=\s*[a-z_][a-z0-9_]*table_has_column\s*\([^;]*[\'"]' . $quoted_table . '[\'"][^;]*[\'"]company_id[\'"]/i', $prev_line_text, $m)) {
        return false;
    }
    $gate_var = $m[1];

    $start = max(1, $line);
    $end = min($total, $line + max(8, (int)$window_lines));
    $found_gate_if = false;
    $found_sql_append = false;
    $quoted_gate_var = preg_quote((string)$gate_var, '/');

    for ($i = $start; $i <= $end; $i++) {
        $text = (string)$lines[$i - 1];
        if (preg_match('/\bif\s*\([^)]*\$' . $quoted_gate_var . '\b/i', $text)) {
            $found_gate_if = true;
        }
        if (line_has_dynamic_company_append_for_variable($text, $var_name)) {
            $found_sql_append = true;
        }
        if ($found_gate_if && $found_sql_append) {
            return true;
        }
    }

    return false;
}

function detect_idf_position_delete_after_scoped_ports_cleanup($lines, $line, $query_fragment, $table, $window_lines) {
    if (empty($lines) || empty($query_fragment) || empty($table)) {
        return false;
    }

    if (strcasecmp((string)$table, 'idf_positions') !== 0) {
        return false;
    }

    if (!preg_match('/\bDELETE\s+FROM\s+[`"]?idf_positions[`"]?\s+WHERE\s+id\s*=\s*\?/i', (string)$query_fragment)) {
        return false;
    }

    $total = count($lines);
    $start = max(1, $line - max(10, (int)$window_lines));
    $end = max(1, min($total, $line - 1));
    if ($start > $end) {
        return false;
    }

    for ($i = $start; $i <= $end; $i++) {
        $text = (string)$lines[$i - 1];
        if (stripos($text, 'DELETE FROM idf_ports') === false) {
            continue;
        }

        $block_start = max(1, $i - 1);
        $block_end = min($total, $i + 10);
        $block = implode("\n", array_slice($lines, $block_start - 1, $block_end - $block_start + 1));
        if (preg_match('/\bDELETE\s+FROM\s+idf_ports\b[\s\S]{0,320}\bcompany_id\b[\s\S]{0,320}\bposition_id\b/i', $block)) {
            return true;
        }
    }

    return false;
}

function detect_prevalidated_company_scoped_delete_by_id($lines, $line, $query_fragment, $table, $window_lines) {
    if (empty($lines) || empty($query_fragment) || empty($table)) {
        return false;
    }

    $quoted_table = preg_quote((string)$table, '/');
    if (!preg_match('/\bDELETE\s+FROM\s+[`"]?' . $quoted_table . '[`"]?\s+WHERE\s+id\s*=\s*\?/i', (string)$query_fragment)) {
        return false;
    }

    $total = count($lines);
    $delete_bind_start = max(1, $line);
    $delete_bind_end = min($total, $line + 8);
    $delete_bind_block = implode("\n", array_slice($lines, $delete_bind_start - 1, $delete_bind_end - $delete_bind_start + 1));
    if (!preg_match('/mysqli_stmt_bind_param\s*\(\s*\$[a-z_][a-z0-9_]*\s*,\s*[\'"]i[\'"]\s*,\s*\$([a-z_][a-z0-9_]*)\s*\)/is', $delete_bind_block, $mDelete)) {
        return false;
    }
    $delete_id_var = $mDelete[1];

    $start = max(1, $line - max(12, (int)$window_lines));
    $end = max(1, min($total, $line - 1));
    if ($start > $end) {
        return false;
    }

    $company_var_pattern = '/\$company[_a-z0-9]*/i';
    $id_var_pattern = '/\$' . preg_quote($delete_id_var, '/') . '\b/i';

    for ($i = $start; $i <= $end; $i++) {
        $bind_block_start = $i;
        $bind_block_end = min($end, $i + 6);
        $bind_block = implode("\n", array_slice($lines, $bind_block_start - 1, $bind_block_end - $bind_block_start + 1));

        if (!preg_match('/mysqli_stmt_bind_param\s*\(\s*\$([a-z_][a-z0-9_]*)\s*,\s*[\'"]([^\'"]*)[\'"]\s*,\s*([^)]*)\)/is', $bind_block, $mBind)) {
            continue;
        }

        $stmt_var = $mBind[1];
        $bind_types = $mBind[2];
        $bind_args = $mBind[3];

        if (stripos($bind_types, 'ii') === false) {
            continue;
        }
        if (!preg_match($company_var_pattern, $bind_args) || !preg_match($id_var_pattern, $bind_args)) {
            continue;
        }

        $prepare_search_start = max($start, $i - 40);
        $prepare_search_end = $i;
        $quoted_stmt_var = preg_quote((string)$stmt_var, '/');
        for ($j = $prepare_search_end; $j >= $prepare_search_start; $j--) {
            $prepare_line = (string)$lines[$j - 1];
            if (!preg_match('/\$' . $quoted_stmt_var . '\s*=\s*mysqli_prepare\s*\(/i', $prepare_line)) {
                continue;
            }

            $prepare_block_start = $j;
            $prepare_block_end = min($end, $j + 22);
            $prepare_block = implode("\n", array_slice($lines, $prepare_block_start - 1, $prepare_block_end - $prepare_block_start + 1));
            if (!preg_match('/\bSELECT\b[\s\S]{0,1400}\bFROM\s+[`"]?' . $quoted_table . '[`"]?\b/i', $prepare_block)) {
                continue;
            }
            if (!query_has_company_predicate($prepare_block)) {
                continue;
            }

            return true;
        }
    }

    return false;
}

function detect_fallback_select_after_company_scoped_lookup($lines, $line, $query_fragment, $table, $window_lines) {
    if (empty($lines) || empty($query_fragment) || empty($table)) {
        return false;
    }

    $fragment = (string)$query_fragment;
    $quoted_table = preg_quote((string)$table, '/');
    if (!preg_match('/\bSELECT\b[\s\S]{0,240}\bFROM\s+[`"]?' . $quoted_table . '[`"]?\b/i', $fragment)) {
        return false;
    }
    if (!preg_match('/\bSELECT\b[\s\S]{0,120}\bid\b/i', $fragment)) {
        return false;
    }
    if (query_has_company_predicate($fragment)) {
        return false;
    }
    if (stripos($fragment, 'limit 1') === false) {
        return false;
    }

    $total = count($lines);
    $start = max(1, $line - max(12, (int)$window_lines));
    $end = max(1, min($total, $line - 1));
    if ($start > $end) {
        return false;
    }

    $company_var_pattern = '/\$company[_a-z0-9]*/i';
    for ($i = $start; $i <= $end; $i++) {
        $bind_block_start = $i;
        $bind_block_end = min($end, $i + 6);
        $bind_block = implode("\n", array_slice($lines, $bind_block_start - 1, $bind_block_end - $bind_block_start + 1));

        if (!preg_match('/mysqli_stmt_bind_param\s*\(\s*\$([a-z_][a-z0-9_]*)\s*,\s*[\'"]([^\'"]*)[\'"]\s*,\s*([^)]*)\)/is', $bind_block, $mBind)) {
            continue;
        }

        $stmt_var = $mBind[1];
        $bind_types = (string)$mBind[2];
        $bind_args = (string)$mBind[3];

        if (strpos($bind_types, 'i') === false) {
            continue;
        }
        if (!preg_match($company_var_pattern, $bind_args)) {
            continue;
        }

        $prepare_search_start = max($start, $i - 40);
        $prepare_search_end = $i;
        $quoted_stmt_var = preg_quote((string)$stmt_var, '/');
        for ($j = $prepare_search_end; $j >= $prepare_search_start; $j--) {
            $prepare_line = (string)$lines[$j - 1];
            if (!preg_match('/\$' . $quoted_stmt_var . '\s*=\s*mysqli_prepare\s*\(/i', $prepare_line)) {
                continue;
            }

            $prepare_block_start = $j;
            $prepare_block_end = min($end, $j + 22);
            $prepare_block = implode("\n", array_slice($lines, $prepare_block_start - 1, $prepare_block_end - $prepare_block_start + 1));
            if (!preg_match('/\bSELECT\b[\s\S]{0,1400}\bFROM\s+[`"]?' . $quoted_table . '[`"]?\b/i', $prepare_block)) {
                continue;
            }
            if (!query_has_company_predicate($prepare_block)) {
                continue;
            }

            return true;
        }
    }

    return false;
}

function check_ui_leaks($content, $relative_path, &$issues) {
    if (stripos($content, 'Company ID') === false) {
        return;
    }

    if (!preg_match_all('/<label[^>]*>\s*Company ID\s*<\/label>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
        return;
    }

    foreach ($matches[0] as $match) {
        $line = offset_to_line_number($content, $match[1]);
        $issues[] = [
            'file' => $relative_path,
            'line' => $line,
            'table' => 'N/A (UI)',
            'query_type' => 'UI',
            'issue_type' => 'Visible "Company ID" label',
            'classification' => 'Likely leak',
            'file_scope_signal' => '-',
            'scope_signals' => '-',
            'context_hints' => '-',
            'allowlist_rules' => [],
            'context_flags' => [],
            'snippet' => compact_snippet($match[0], 180)
        ];
    }
}

$table_info = parse_database_tables($database_sql);
$total_tables = (int) $table_info['total'];
$scoped_tables = $table_info['scoped'];
$non_scoped_tables = $table_info['non_scoped'];
$raw_create_count = (int) $table_info['raw_create_count'];
$table_exceptions = get_table_exceptions();
$display_non_scoped_tables = $non_scoped_tables;
if (!in_array('audit_logs', $display_non_scoped_tables, true)) {
    $display_non_scoped_tables[] = 'audit_logs';
}

if (empty($scoped_tables)) {
    die("Error: No scoped tables found in $database_sql\n");
}

$table_regex = build_table_regex($scoped_tables);
if ($table_regex === null) {
    die("Error: Could not build scoped table matcher.\n");
}

$allowlist_rules = load_allowlist_rules($allowlist_file);

require_once __DIR__ . '/lib/script_cli_output.php';

$is_cli = (PHP_SAPI === 'cli');

if (!$is_cli) {
    require_once __DIR__ . '/../config/config.php';
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('Multi-Tenant Leak Audit');
$nl = itm_script_output_nl();

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modules_dir));
$issues = [];

foreach ($files as $file) {
    if ($file->isDir() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $filepath = $file->getPathname();
    $real_file = realpath($filepath);
    if ($real_file === false) {
        continue;
    }

    $relative_path = normalize_relative_path($project_root, $real_file);
    $content = file_get_contents($real_file);
    $file_lines = split_content_lines($content);
    $variable_scope_history = collect_variable_scope_history($content);
    $has_file_scope_signal = file_has_scope_signal($variable_scope_history);

    if (!preg_match($table_regex, $content)) {
        check_ui_leaks($content, $relative_path, $issues);
        continue;
    }

    $query_candidates = collect_query_candidates($content);
    foreach ($query_candidates as $candidate) {
            $query_fragment = $candidate['fragment'];
            $offset = $candidate['offset'];

            if (!preg_match($table_regex, $query_fragment, $table_match)) {
                continue;
            }

            $table = $table_match[1];
            $table_key = strtolower((string)$table);

            if (isset($table_exceptions[$table_key])) {
                continue;
            }

            if (preg_match('/\b(DESCRIBE|SHOW|INFORMATION_SCHEMA|ALTER TABLE|DROP TABLE|CREATE TABLE|CREATE TRIGGER|DROP TRIGGER)\b/i', $query_fragment)) {
                continue;
            }

            $line = offset_to_line_number($content, $offset);
            $line_content = extract_line_at_offset($content, $offset);
            $query_type = detect_query_type($query_fragment);
            $is_insert_query = (strcasecmp($query_type, 'INSERT') === 0);

            if (!query_fragment_has_sql_shape($query_fragment, $query_type)) {
                continue;
            }

            $has_fragment_predicate = query_has_company_predicate($query_fragment);
            $has_line_predicate = query_has_company_predicate($line_content);
            $uses_scope_function = query_uses_scope_function($query_fragment) || query_uses_scope_function($line_content);
            $scoped_vars = query_scoped_variables($query_fragment, $line, $variable_scope_history);
            $line_scoped_vars = query_scoped_variables($line_content, $line, $variable_scope_history);
            $has_scoped_variable = !empty($scoped_vars);
            $has_line_scoped_variable = !empty($line_scoped_vars);

            $assigned_var = detect_query_assignment_variable_from_line($line_content);
            $has_dynamic_company_append_same_var = detect_dynamic_company_append_same_var($file_lines, $line, $assigned_var, 25);
            $has_company_column_gated_dynamic_scope = detect_company_column_gated_dynamic_scope($file_lines, $line, $assigned_var, $table, 40);
            $has_adjacent_company_column_gate_safe_pattern = detect_adjacent_company_column_gate_safe_pattern($file_lines, $line, $assigned_var, $table, 24);
            $has_idf_position_cleanup_delete_safe_pattern = detect_idf_position_delete_after_scoped_ports_cleanup($file_lines, $line, $query_fragment, $table, 40);
            $has_prevalidated_company_scoped_delete_by_id = detect_prevalidated_company_scoped_delete_by_id($file_lines, $line, $query_fragment, $table, 60);
            $has_fallback_select_after_company_scoped_lookup = detect_fallback_select_after_company_scoped_lookup($file_lines, $line, $query_fragment, $table, 60);

            $scope_signals = [];
            if ($has_fragment_predicate) {
                $scope_signals[] = 'fragment_predicate';
            }
            if ($has_line_predicate) {
                $scope_signals[] = 'line_predicate';
            }
            if ($uses_scope_function) {
                $scope_signals[] = 'scope_function';
            }
            if ($has_scoped_variable) {
                $scope_signals[] = 'scoped_vars:' . implode('|', $scoped_vars);
            }
            if ($has_line_scoped_variable) {
                $scope_signals[] = 'line_scoped_vars:' . implode('|', $line_scoped_vars);
            }
            if ($has_dynamic_company_append_same_var) {
                $scope_signals[] = 'dynamic_company_append_same_var';
            }
            if ($has_company_column_gated_dynamic_scope) {
                $scope_signals[] = 'company_column_gated_dynamic_scope';
            }
            if ($has_adjacent_company_column_gate_safe_pattern) {
                $scope_signals[] = 'adjacent_company_column_gate_safe_pattern';
            }
            if ($has_idf_position_cleanup_delete_safe_pattern) {
                $scope_signals[] = 'idf_position_cleanup_delete_safe_pattern';
            }
            if ($has_prevalidated_company_scoped_delete_by_id) {
                $scope_signals[] = 'prevalidated_company_scoped_delete_by_id';
            }
            if ($has_fallback_select_after_company_scoped_lookup) {
                $scope_signals[] = 'fallback_select_after_company_scoped_lookup';
            }

            // Strict leak gate: only raise issue when query itself has no strong tenant scope signal.
            $is_missing_direct_scope = (!$has_fragment_predicate && !$has_line_predicate && !$uses_scope_function && !$has_scoped_variable);

            if (!$is_insert_query && $is_missing_direct_scope) {
                if ($has_adjacent_company_column_gate_safe_pattern || $has_idf_position_cleanup_delete_safe_pattern || $has_prevalidated_company_scoped_delete_by_id || $has_fallback_select_after_company_scoped_lookup) {
                    continue;
                }

                $context_hints = [];
                $is_id_lookup = query_is_single_id_lookup($query_fragment);
                $has_limit_1 = (stripos($query_fragment, 'limit 1') !== false);
                $nearby_scope = nearby_company_signal($file_lines, $line, 8);
                $has_id_var_scoped_origin = id_variable_from_scoped_context($file_lines, $line, $query_fragment, 140);

                if ($is_id_lookup) {
                    $context_hints[] = 'id_lookup';
                }
                if ($has_limit_1) {
                    $context_hints[] = 'limit_1';
                }
                if ($nearby_scope) {
                    $context_hints[] = 'nearby_company_signal';
                }
                if ($has_file_scope_signal) {
                    $context_hints[] = 'file_scope_signal';
                }
                if ($has_line_scoped_variable) {
                    $context_hints[] = 'line_scoped_variable';
                }
                if ($has_dynamic_company_append_same_var) {
                    $context_hints[] = 'dynamic_company_append_same_var';
                }
                if ($has_company_column_gated_dynamic_scope) {
                    $context_hints[] = 'company_column_gated_dynamic_scope';
                }
                if ($has_adjacent_company_column_gate_safe_pattern) {
                    $context_hints[] = 'adjacent_company_column_gate_safe_pattern';
                }
                if ($has_idf_position_cleanup_delete_safe_pattern) {
                    $context_hints[] = 'idf_position_cleanup_delete_safe_pattern';
                }
                if ($has_prevalidated_company_scoped_delete_by_id) {
                    $context_hints[] = 'prevalidated_company_scoped_delete_by_id';
                }
                if ($has_fallback_select_after_company_scoped_lookup) {
                    $context_hints[] = 'fallback_select_after_company_scoped_lookup';
                }
                if ($has_id_var_scoped_origin) {
                    $context_hints[] = 'id_var_scoped_origin';
                }

                $classification = 'Likely leak';
                if (($is_id_lookup && ($nearby_scope || $has_file_scope_signal)) || $has_id_var_scoped_origin) {
                    $classification = 'Needs review (context-validated?)';
                }

                $issue = [
                    'file' => $relative_path,
                    'line' => $line,
                    'table' => $table,
                    'query_type' => $query_type,
                    'issue_type' => 'Missing company_id filter',
                    'classification' => $classification,
                    'file_scope_signal' => $has_file_scope_signal ? 'yes' : 'no',
                    'scope_signals' => join_or_dash($scope_signals),
                    'context_hints' => join_or_dash($context_hints),
                    'allowlist_rules' => [],
                    'context_flags' => [
                        'id_lookup' => $is_id_lookup,
                        'limit_1' => $has_limit_1,
                        'nearby_company_signal' => $nearby_scope,
                        'file_scope_signal' => $has_file_scope_signal,
                        'line_scoped_variable' => $has_line_scoped_variable,
                        'dynamic_company_append_same_var' => $has_dynamic_company_append_same_var,
                        'company_column_gated_dynamic_scope' => $has_company_column_gated_dynamic_scope,
                        'adjacent_company_column_gate_safe_pattern' => $has_adjacent_company_column_gate_safe_pattern,
                        'idf_position_cleanup_delete_safe_pattern' => $has_idf_position_cleanup_delete_safe_pattern,
                        'prevalidated_company_scoped_delete_by_id' => $has_prevalidated_company_scoped_delete_by_id,
                        'fallback_select_after_company_scoped_lookup' => $has_fallback_select_after_company_scoped_lookup,
                        'id_var_scoped_origin' => $has_id_var_scoped_origin
                    ],
                    'snippet' => compact_snippet($query_fragment, 180)
                ];

                $issue = apply_allowlist_rules_to_issue($issue, $allowlist_rules);
                $issues[] = $issue;
            }

            // INSERT checks
            if (preg_match('/INSERT\s+INTO\s+[`"]?' . preg_quote($table, '/') . '[`"]?\s*\(([^)]+)\)/i', $query_fragment, $cols)) {
                $normalized_cols = strtolower(str_replace(['`', '"', "'", ' ', "\t", "\r", "\n"], '', $cols[1]));
                if (strpos($normalized_cols, 'company_id') === false) {
                    $issue = [
                        'file' => $relative_path,
                        'line' => $line,
                        'table' => $table,
                        'query_type' => 'INSERT',
                        'issue_type' => 'INSERT missing company_id',
                        'classification' => 'Likely leak',
                        'file_scope_signal' => $has_file_scope_signal ? 'yes' : 'no',
                        'scope_signals' => join_or_dash($scope_signals),
                        'context_hints' => '-',
                        'allowlist_rules' => [],
                        'context_flags' => [
                            'id_lookup' => false,
                            'limit_1' => false,
                            'nearby_company_signal' => false,
                            'file_scope_signal' => $has_file_scope_signal,
                            'line_scoped_variable' => $has_line_scoped_variable,
                            'dynamic_company_append_same_var' => $has_dynamic_company_append_same_var,
                            'company_column_gated_dynamic_scope' => $has_company_column_gated_dynamic_scope,
                            'adjacent_company_column_gate_safe_pattern' => $has_adjacent_company_column_gate_safe_pattern,
                            'idf_position_cleanup_delete_safe_pattern' => $has_idf_position_cleanup_delete_safe_pattern,
                            'prevalidated_company_scoped_delete_by_id' => $has_prevalidated_company_scoped_delete_by_id,
                            'fallback_select_after_company_scoped_lookup' => $has_fallback_select_after_company_scoped_lookup,
                            'id_var_scoped_origin' => false
                        ],
                        'snippet' => compact_snippet($query_fragment, 180)
                    ];
                    $issue = apply_allowlist_rules_to_issue($issue, $allowlist_rules);
                    $issues[] = $issue;
                }
            } elseif (preg_match('/INSERT\s+INTO\s+[`"]?' . preg_quote($table, '/') . '[`"]?\s+(VALUES|SELECT)\b/i', $query_fragment)) {
                $issue = [
                    'file' => $relative_path,
                    'line' => $line,
                    'table' => $table,
                    'query_type' => 'INSERT',
                    'issue_type' => 'INSERT without explicit column list',
                    'classification' => 'Needs review (context-validated?)',
                    'file_scope_signal' => $has_file_scope_signal ? 'yes' : 'no',
                    'scope_signals' => join_or_dash($scope_signals),
                    'context_hints' => 'implicit_column_order',
                    'allowlist_rules' => [],
                    'context_flags' => [
                        'id_lookup' => false,
                        'limit_1' => false,
                            'nearby_company_signal' => false,
                            'file_scope_signal' => $has_file_scope_signal,
                            'line_scoped_variable' => $has_line_scoped_variable,
                            'dynamic_company_append_same_var' => $has_dynamic_company_append_same_var,
                            'company_column_gated_dynamic_scope' => $has_company_column_gated_dynamic_scope,
                            'adjacent_company_column_gate_safe_pattern' => $has_adjacent_company_column_gate_safe_pattern,
                            'idf_position_cleanup_delete_safe_pattern' => $has_idf_position_cleanup_delete_safe_pattern,
                            'prevalidated_company_scoped_delete_by_id' => $has_prevalidated_company_scoped_delete_by_id,
                            'fallback_select_after_company_scoped_lookup' => $has_fallback_select_after_company_scoped_lookup,
                            'id_var_scoped_origin' => false
                        ],
                        'snippet' => compact_snippet($query_fragment, 180)
                ];
                $issue = apply_allowlist_rules_to_issue($issue, $allowlist_rules);
                $issues[] = $issue;
            }
    }

    check_ui_leaks($content, $relative_path, $issues);
}

$issue_counts = [];
$class_counts = [];
$allowlist_tag_counts = [];
foreach ($issues as $issue) {
    $type = $issue['issue_type'];
    $class = $issue['classification'];

    if (!isset($issue_counts[$type])) {
        $issue_counts[$type] = 0;
    }
    if (!isset($class_counts[$class])) {
        $class_counts[$class] = 0;
    }

    $issue_counts[$type]++;
    $class_counts[$class]++;

    if (!empty($issue['allowlist_rules']) && is_array($issue['allowlist_rules'])) {
        foreach ($issue['allowlist_rules'] as $rule_id) {
            if (!isset($allowlist_tag_counts[$rule_id])) {
                $allowlist_tag_counts[$rule_id] = 0;
            }
            $allowlist_tag_counts[$rule_id]++;
        }
    }
}

if (!$is_cli) {
    itm_script_output_close_pre();
    echo "<style>table{width:100%;border-collapse:collapse;background:white;} th,td{padding:8px 10px;border:1px solid #ddd;text-align:left;vertical-align:top;font-size:13px;} th{background:#eee;} th.sortable{cursor:pointer;user-select:none;position:relative;padding-right:20px;} th.sortable::after{content:'\\2195';position:absolute;right:7px;color:#888;font-size:11px;} th.sortable.sorted-asc::after{content:'\\25B2';color:#333;} th.sortable.sorted-desc::after{content:'\\25BC';color:#333;} .type-err{color:#b00020;font-weight:bold;} .class-review{color:#8a6d3b;font-weight:600;} code{background:#fffbe6;padding:2px 4px;border:1px solid #ffe58f;} .summary{background:#fff;margin-bottom:16px;border:1px solid #ddd;padding:12px 14px;} ul{margin:8px 0 0 20px;} .mono{font-family:monospace;}</style>";
    echo "<h1>Multi-Tenant Leak Audit Result</h1>";
    echo "<div class='summary'>";
    echo "<p><strong>CREATE TABLE entries found:</strong> {$total_tables}</p>";
    echo "<p><strong>Scoped tables with <span class='mono'>company_id</span>:</strong> " . count($scoped_tables) . "</p>";
    echo "<p><strong>Non-scoped tables:</strong> " . count($non_scoped_tables) . " (" . htmlspecialchars(join_or_dash($display_non_scoped_tables)) . ")</p>";
    echo "<p><strong>Allowlist rules loaded:</strong> " . count($allowlist_rules) . "</p>";
    if ($raw_create_count !== $total_tables) {
        echo "<p><strong>Parse note:</strong> raw <span class='mono'>CREATE TABLE</span> count is {$raw_create_count}, parsed count is {$total_tables}. Please inspect unusual table DDL if these diverge.</p>";
    }
    echo "<p><strong>Total issues found:</strong> " . count($issues) . "</p>";

    if (!empty($class_counts)) {
        echo "<p><strong>By classification:</strong></p><ul>";
        foreach ($class_counts as $class => $count) {
            echo "<li><strong>" . htmlspecialchars($class) . ":</strong> " . (int) $count . "</li>";
        }
        echo "</ul>";
    }

    if (!empty($issue_counts)) {
        echo "<p><strong>By issue type:</strong></p><ul>";
        foreach ($issue_counts as $type => $count) {
            echo "<li><strong>" . htmlspecialchars($type) . ":</strong> " . (int) $count . "</li>";
        }
        echo "</ul>";
    }

    if (!empty($allowlist_tag_counts)) {
        echo "<p><strong>Allowlist rule matches:</strong></p><ul>";
        foreach ($allowlist_tag_counts as $rule_id => $count) {
            echo "<li><strong>" . htmlspecialchars($rule_id) . ":</strong> " . (int) $count . "</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
    echo "<script>
function itmSortNormalize(text) {
    return String(text || '').replace(/\\s+/g, ' ').trim();
}

function itmSortIssuesTable(columnIndex, valueType, header) {
    var table = document.getElementById('issues-table');
    if (!table) {
        return;
    }
    var body = table.tBodies[0];
    if (!body) {
        return;
    }

    var headers = table.querySelectorAll('th.sortable');
    var currentDirection = header.getAttribute('data-sort-dir') || 'none';
    var nextDirection = (currentDirection === 'asc') ? 'desc' : 'asc';

    headers.forEach(function (th) {
        th.classList.remove('sorted-asc', 'sorted-desc');
        th.setAttribute('data-sort-dir', 'none');
    });
    header.setAttribute('data-sort-dir', nextDirection);
    header.classList.add(nextDirection === 'asc' ? 'sorted-asc' : 'sorted-desc');

    var rows = Array.prototype.slice.call(body.rows);
    rows.sort(function (rowA, rowB) {
        var textA = itmSortNormalize(rowA.cells[columnIndex] ? rowA.cells[columnIndex].textContent : '');
        var textB = itmSortNormalize(rowB.cells[columnIndex] ? rowB.cells[columnIndex].textContent : '');
        var cmp = 0;

        if (valueType === 'number') {
            var numA = parseInt(textA, 10);
            var numB = parseInt(textB, 10);
            var aValid = !isNaN(numA);
            var bValid = !isNaN(numB);
            if (!aValid && !bValid) {
                cmp = 0;
            } else if (!aValid) {
                cmp = -1;
            } else if (!bValid) {
                cmp = 1;
            } else {
                cmp = numA - numB;
            }
        } else {
            cmp = textA.localeCompare(textB, undefined, { sensitivity: 'base', numeric: true });
        }

        return nextDirection === 'asc' ? cmp : -cmp;
    });

    rows.forEach(function (row) {
        body.appendChild(row);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('issues-table');
    if (!table) {
        return;
    }
    var headers = table.querySelectorAll('th.sortable');
    headers.forEach(function (header) {
        header.addEventListener('click', function () {
            var columnIndex = parseInt(header.getAttribute('data-col-index') || '0', 10);
            var valueType = header.getAttribute('data-sort-type') || 'text';
            itmSortIssuesTable(columnIndex, valueType, header);
        });
    });
});
</script>";
} else {
    echo "Multi-Tenant Leak Audit" . $nl;
    echo "========================" . $nl;
    echo "CREATE TABLE entries found: {$total_tables}" . $nl;
    echo "Scoped tables with company_id: " . count($scoped_tables) . "" . $nl;
    echo "Non-scoped tables (" . count($non_scoped_tables) . "): " . join_or_dash($display_non_scoped_tables) . "" . $nl;
    echo "Allowlist rules loaded: " . count($allowlist_rules) . "" . $nl;
    if ($raw_create_count !== $total_tables) {
        echo "Parse note: raw CREATE TABLE count is {$raw_create_count}, parsed count is {$total_tables}." . $nl;
    }
    echo "Total issues found: " . count($issues) . "" . $nl;
    if (!empty($class_counts)) {
        echo "By classification:" . $nl;
        foreach ($class_counts as $class => $count) {
            echo "- {$class}: {$count}" . $nl;
        }
    }
    if (!empty($issue_counts)) {
        echo "By issue type:" . $nl;
        foreach ($issue_counts as $type => $count) {
            echo "- {$type}: {$count}" . $nl;
        }
    }
    if (!empty($allowlist_tag_counts)) {
        echo "Allowlist rule matches:" . $nl;
        foreach ($allowlist_tag_counts as $rule_id => $count) {
            echo "- {$rule_id}: {$count}" . $nl;
        }
    }
    echo "" . $nl;
}

if (empty($issues)) {
    echo "No leaks detected! (Based on current heuristics)" . $nl;
} else {
    if (!$is_cli) {
        echo "<table id='issues-table'><thead><tr>";
        echo "<th class='sortable' data-col-index='0' data-sort-type='text' data-sort-dir='none'>File</th>";
        echo "<th class='sortable' data-col-index='1' data-sort-type='number' data-sort-dir='none'>Line</th>";
        echo "<th class='sortable' data-col-index='2' data-sort-type='text' data-sort-dir='none'>Table</th>";
        echo "<th class='sortable' data-col-index='3' data-sort-type='text' data-sort-dir='none'>Query</th>";
        echo "<th class='sortable' data-col-index='4' data-sort-type='text' data-sort-dir='none'>Issue Type</th>";
        echo "<th class='sortable' data-col-index='5' data-sort-type='text' data-sort-dir='none'>Classification</th>";
        echo "<th class='sortable' data-col-index='6' data-sort-type='text' data-sort-dir='none'>Allowlist Rules</th>";
        echo "<th class='sortable' data-col-index='7' data-sort-type='text' data-sort-dir='none'>File Scope Signal</th>";
        echo "<th class='sortable' data-col-index='8' data-sort-type='text' data-sort-dir='none'>Scope Signals</th>";
        echo "<th class='sortable' data-col-index='9' data-sort-type='text' data-sort-dir='none'>Context Hints</th>";
        echo "<th>Snippet</th>";
        echo "</tr></thead><tbody>";
        foreach ($issues as $issue) {
            $class_css = ($issue['classification'] === 'Likely leak') ? 'type-err' : 'class-review';
            $allowlist_rules = (isset($issue['allowlist_rules']) && is_array($issue['allowlist_rules'])) ? join_or_dash($issue['allowlist_rules']) : '-';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($issue['file']) . "</td>";
            echo "<td>" . (int) $issue['line'] . "</td>";
            echo "<td>" . htmlspecialchars($issue['table']) . "</td>";
            echo "<td>" . htmlspecialchars($issue['query_type']) . "</td>";
            echo "<td>" . htmlspecialchars($issue['issue_type']) . "</td>";
            echo "<td class='" . $class_css . "'>" . htmlspecialchars($issue['classification']) . "</td>";
            echo "<td>" . htmlspecialchars($allowlist_rules) . "</td>";
            echo "<td>" . htmlspecialchars($issue['file_scope_signal']) . "</td>";
            echo "<td>" . htmlspecialchars($issue['scope_signals']) . "</td>";
            echo "<td>" . htmlspecialchars($issue['context_hints']) . "</td>";
            echo "<td><code>" . htmlspecialchars($issue['snippet']) . "</code></td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        foreach ($issues as $issue) {
            $allowlist_rules_list = (isset($issue['allowlist_rules']) && is_array($issue['allowlist_rules'])) ? join_or_dash($issue['allowlist_rules']) : '-';
            echo "[!] {$issue['classification']} | {$issue['file']}:{$issue['line']} | {$issue['query_type']} | {$issue['issue_type']} | table={$issue['table']}" . $nl;
            echo "    allowlist_rules: {$allowlist_rules_list}" . $nl;
            echo "    file_scope_signal: {$issue['file_scope_signal']}" . $nl;
            echo "    scope_signals: {$issue['scope_signals']}" . $nl;
            echo "    context_hints: {$issue['context_hints']}" . $nl;
            $snippet_html = "<pre style='display:inline-block; margin:0; vertical-align:top; white-space:pre-wrap; font-family:monospace; background:#fffbe6; border:1px solid #ffe58f; padding:2px 4px;'>" . htmlspecialchars($issue['snippet']) . "</pre>";
            echo "    snippet: " . ($is_cli ? $issue['snippet'] : $snippet_html) . PHP_EOL . $nl;
        }
    }
}

itm_script_output_end();
