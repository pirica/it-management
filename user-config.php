<?php
require_once 'config/config.php';
require_once ROOT_PATH . 'includes/itm_vault_master_key.php';
require_once ROOT_PATH . 'includes/employee_profile_photo.php';

/*
SELECT COUNT(assigned_to_employee_id) FROM `events` WHERE `assigned_to_employee_id` = 1 AND `company_id` = 1;
*/
/**
 * user-config.php - Employee Dashboard & Profile System
 *
 * Scoped to the logged-in employee.
 * Provides a dashboard with stat cards linking to modules.
 * Two-column layout:
 *   - Left: Profile Summary, Stats, Progress, Org Chart Path.
 *   - Right: Profile Editing, Security, Sidebar Prefs, Activity, etc.
 */

// Auth check
if (!isset($_SESSION['employee_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = (int)$_SESSION['employee_id'];
$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrfToken = itm_get_csrf_token();

/**
 * Why: employees.theme is light|dark only; anything else falls back to light.
 */
$profile_normalize_theme = static function ($theme) {
    return (strtolower(trim((string)$theme)) === 'dark') ? 'dark' : 'light';
};

// --- 1. GATHER ALL REQUIRED DATA ---

// Fetch current employee profile data
$stmt = mysqli_prepare($conn, '
    SELECT e.*, ep.name AS position_name, d.name AS department_name, es.name AS status_name
    FROM employees e
    LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN employee_statuses es ON e.employment_status_id = es.id
    WHERE e.id = ?
');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$current_user) {
    die('User not found.');
}

// Why: profile self-updates must use the employee home company_id — session
// company_id is the active tenant switcher and often differs for multi-company admins.
$home_company_id = (int)($current_user['company_id'] ?? 0);

// Ensure company_id is set from user record if missing in session
if ($company_id <= 0) {
    $company_id = $home_company_id;
    $_SESSION['company_id'] = $company_id;
}


//echo "User: $user_id<br>";
//echo "Comp $company_id";


// Optimized by Bolt ⚡: Redundant individual queries for events and alerts counts removed.
// They are now extracted from the consolidated $all_stats block below.

$stmt = mysqli_prepare($conn, "SELECT COUNT(file_name) AS total_files FROM explorer WHERE company_id = ? AND employee_id = ? AND file_name LIKE '%.%' AND active = 1");

mysqli_stmt_bind_param($stmt, "ii", $company_id, $user_id); // Fixed order of parameters to match SQL: company_id, employee_id
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$total_files = (int)($row['total_files'] ?? 0);
//echo $total_files;
//print_r($total_files);
//$result = mysqli_stmt_get_result($stmt);
//var_dump(mysqli_fetch_assoc($result));
//echo "$row";


/*
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
*/


/**
 * Count the number of files in a given directory, skipping certain filenames.
 *
 * @param string $dir Path to the directory
 * @param array $skipFiles List of filenames to skip
 * @return int|false Number of files, or false on error
 */
function countFilesInDirectory(string $dir, array $skipFiles = []) {
    if (!is_dir($dir)) {
       // trigger_error("Error: '$dir' is not a valid directory.", E_USER_WARNING);
        return false;
    }

    try {
        // Altera para iteradores recursivos para entrar em todas as subpastas automaticamente
        $directory = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directory);
        $count = 0;

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile()) {
                $filename = $fileinfo->getFilename();
                // Ignora os ficheiros da lista de exclusão
                if (!in_array($filename, $skipFiles, true)) {
                    $count++;
                }
            }
        }

        return $count;
    } catch (Exception $e) {
        trigger_error("Error reading directory: " . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}

$employee_id = $_SESSION['employee_id'];
$username     = $_SESSION['username'];
$company_id_sess   = $_SESSION['company_id'];

$foldername = "{$username}_{$employee_id}";
$dir = __DIR__ . "/files/{$company_id_sess}/Private/{$foldername}/";

$skipList = ['index.html', '.htaccess', 'index.php'];
$fileCount = countFilesInDirectory($dir, $skipList);

if ($fileCount !== false) {
  //  echo "Número de ficheiros na pasta (incluindo subpastas): $fileCount";
}









//var_dump($user_id, $company_id);

// Optimized by Bolt ⚡: Avoid redundant database queries by fetching properties directly from the existing $current_user array.
$workstation_mode_id = (int)($current_user['workstation_mode_id'] ?? 0);
$assignment_type_id = (int)($current_user['assignment_type_id'] ?? 0);

if ($workstation_mode_id > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT mode_name
        FROM workstation_modes
        WHERE id = ?
        AND company_id = ?
    ");

    mysqli_stmt_bind_param($stmt, "ii", $workstation_mode_id, $company_id);
    mysqli_stmt_execute($stmt);

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $mode_name = $row['mode_name'] ?? '';

    mysqli_stmt_close($stmt);

$_SESSION['mode_name'] = $mode_name;
//echo $_SESSION['mode_name'];
}

if ($assignment_type_id > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT name
        FROM assignment_types
        WHERE id = ?
        AND company_id = ?
    ");

    mysqli_stmt_bind_param($stmt, "ii", $assignment_type_id, $company_id);
    mysqli_stmt_execute($stmt);

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $assignment_types = $row['name'] ?? '';

    mysqli_stmt_close($stmt);

$_SESSION['assignment_types'] = $assignment_types;
//echo $_SESSION['assignment_types'];
}




// Stats gathering - All modules using specified fields
// Optimized: consolidated COUNT queries via includes/itm_user_config_stats.php.
require_once ROOT_PATH . 'includes/itm_user_config_stats.php';
$stat_definitions = itm_user_config_stat_definitions();
$all_stats = itm_user_config_fetch_stats_batch($conn, $stat_definitions, $user_id, $company_id);

// Extract variables used in UI from consolidated stats
$alertsEventsCounts = itm_user_config_extract_alerts_events_counts($all_stats);
$total_events_forme = $alertsEventsCounts['total_events_forme'];
$total_events_created = $alertsEventsCounts['total_events_created'];
$total_alerts_forme = $alertsEventsCounts['total_alerts_forme'];
$total_alerts_created = $alertsEventsCounts['total_alerts_created'];
$assigned_assets_count = 0;
$vault_entries_count = 0;

foreach ($all_stats as $s) {
    if ($s['table'] === 'equipment' && $s['field'] === 'assigned_to_employee_id') $assigned_assets_count = $s['count'];
    if ($s['table'] === 'password_entries' && $s['field'] === 'employee_id') $vault_entries_count = $s['count'];
}


$sql = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN ts.is_closed = 0 THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN ts.is_closed = 1 THEN 1 ELSE 0 END) as closed
    FROM tickets t
    JOIN ticket_statuses ts ON t.status_id = ts.id
    WHERE t.assigned_to_employee_id = ?
    AND t.company_id = ?
";


$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "<span style='color:red'>PREPARE FAILED: " . mysqli_error($conn) . "</span><br>";
}


if (!mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id)) {
    echo "<span style='color:red'>BIND FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

if (!mysqli_stmt_execute($stmt)) {
    echo "<span style='color:red'>EXECUTE FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

mysqli_stmt_bind_result($stmt, $total, $open, $closed);

if (!mysqli_stmt_fetch($stmt)) {

    $ticket_summary = ['total' => 0, 'open' => 0, 'closed' => 0];
} else {

    $ticket_summary = [
        'total'  => (int)$total,
        'open'   => (int)$open,
        'closed' => (int)$closed
    ];
}

mysqli_stmt_close($stmt);



$sql = "
    SELECT created_at
    FROM attempts
    WHERE employee_id = ?
      AND attempt_source = 'login'
      AND attempt_type = 'success'
    ORDER BY created_at DESC
    LIMIT 1
";



$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "<span style='color:red'>PREPARE FAILED: " . mysqli_error($conn) . "</span><br>";
}


if (!mysqli_stmt_bind_param($stmt, 'i', $user_id)) {
    echo "<span style='color:red'>BIND FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

if (!mysqli_stmt_execute($stmt)) {
    echo "<span style='color:red'>EXECUTE FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}


mysqli_stmt_bind_result($stmt, $created_at);

if (!mysqli_stmt_fetch($stmt)) {
    $last_login_row = ['created_at' => null];
} else {
    $last_login_row = ['created_at' => $created_at];
}

mysqli_stmt_close($stmt);




/* 1) Descobrir colunas dinâmicas da tabela employee_system_access */
$columns = [];
$system_access_count_0 = 0;
$system_access_count_1 = 0;
//echo "user_id  $user_id - company_id  $company_id";
$res = mysqli_query($conn, "DESCRIBE employee_system_access");
if (!$res) die("DESCRIBE failed: " . mysqli_error($conn));

while ($row = mysqli_fetch_assoc($res)) {
    $columns[] = $row['Field'];
}

/* 2) Excluir colunas meta e montar SELECT apenas com colunas reais (DESCRIBE).
 * Why: never merge invented names like changed_at into SELECT — employee_system_access
 * has created_at/updated_at (and audit soft-delete cols) but not changed_at. */
$fixed = [
    'id', 'company_id', 'employee_id', 'active',
    'changed_at', 'created_at', 'updated_at', 'deleted_at',
    'created_by', 'updated_by', 'deleted_by',
];
$dynamic_columns = array_values(array_diff($columns, $fixed));
// Why: SELECT only columns that exist on the live table.
$all_columns = $columns;
$select_list = implode(', ', array_map(static function ($c) {
    return '`' . str_replace('`', '``', (string)$c) . '`';
}, $all_columns));

$sql = "SELECT $select_list FROM employee_system_access WHERE employee_id = ? AND company_id = ?";

// Lista de controlo para verificar se existem colunas novas comparando com o código antigo
$sql2 = "SELECT network_access, micros_emc, opera_username, micros_card, pms_id, synergy_mms, hu_the_lobby, navision, onq_ri, birchstreet, delphi, digital_rev, omina, vingcard_system, digital_rev, office_key_card FROM employees WHERE `id` = ? AND company_id = ?";

/* 3) COMPARAR E EXIBIR AVISO (Apenas informativo) */
preg_match('/SELECT\s+(.+?)\s+FROM/i', $sql2, $matches);
$fields_sql2_raw = explode(',', $matches[1]);

$fields_sql2 = array_map(fn($c) => trim($c, " `\t\n\r"), $fields_sql2_raw);
$fields_sql2 = array_unique(array_filter($fields_sql2));

$missing_in_sql = array_diff($fields_sql2, $all_columns);

if (!empty($missing_in_sql)) {
  //  echo "<h3>Aviso: Colunas em \$sql2 que não existem na tabela employee_system_access:</h3><ul>";
    foreach ($missing_in_sql as $column) {
    //    echo "<li>`$column`</li>";
    }
  //  echo "</ul>";
}

/* 4) PREPARE & EXECUTE */
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) die("PREPARE FAILED: " . mysqli_error($conn));

mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id);

if (!mysqli_stmt_execute($stmt)) {
    die("EXECUTE FAILED: " . mysqli_stmt_error($stmt));
}

/* 5) FETCH DINÂMICO CORRIGIDO (Extração nativa e segura) */
$result = mysqli_stmt_get_result($stmt);
$system_access_overview = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $system_access_overview[] = $row;
    }
}
mysqli_stmt_close($stmt);

/* 6) CRIAR REGISTO SE ESTIVER VAZIO */
if (empty($system_access_overview)) {
    $insert_fields = array_merge(['employee_id', 'company_id'], $dynamic_columns);
    $escaped_fields = array_map(fn($f) => "`$f`", $insert_fields);

    $insert_values = array_merge([$user_id, $company_id], array_fill(0, count($dynamic_columns), 0));
    $placeholders = implode(', ', array_fill(0, count($insert_values), '?'));

    $insert_sql = "INSERT INTO employee_system_access (" . implode(', ', $escaped_fields) . ") VALUES ($placeholders)";

    $stmt_ins = mysqli_prepare($conn, $insert_sql);
    if ($stmt_ins) {
        $types = str_repeat('i', count($insert_values));
        mysqli_stmt_bind_param($stmt_ins, $types, ...$insert_values);
        mysqli_stmt_execute($stmt_ins);
        mysqli_stmt_close($stmt_ins);

        echo "<script>window.location.reload();</script>";
        exit;
    }
}

/* 7) PROCESSAR CONTAGENS E LISTAGEM VISUAL */
if (!empty($system_access_overview)) {
    $access_row = $system_access_overview[0];

    // Remove meta/audit columns before counting access flags
    foreach ($fixed as $metaCol) {
        unset($access_row[$metaCol]);
    }

    // Converte os dados reais da BD para inteiros (garante 0 ou 1 numéricos)
    $access_row = array_map('intval', $access_row);

    // Faz a contagem real
    $counts = array_count_values($access_row);

    $system_access_count_0 = $counts[0] ?? 0;
    $system_access_count_1 = $counts[1] ?? 0;

   // echo "<p><strong>Sumário (employee_system_access):</strong> Com acesso: $system_access_count_1 | Sem acesso: $system_access_count_0</p>";

    // Exibição visual com ícones atualizados conforme o valor real da BD
   // echo "<div style='font-family: sans-serif; line-height: 2;'>";
    foreach ($access_row as $coluna => $valor) {
        $label = ucwords(str_replace('_', ' ', $coluna));
        if ($valor === 1) {
    //        echo "✅ <strong>$label</strong><br>";
        } else {
    //        echo "❌ $label<br>";
        }
    }
  //  echo "</div>";

} else {
   // echo "<p>Nenhum registo encontrado para os IDs fornecidos (User ID: $user_id | Company ID: $company_id).</p>";
}











$sql = "
    SELECT
        e.*,
        et.name AS type_name,
        m.name AS manufacturer_name,
        ls.name AS status_name
    FROM equipment e
    JOIN equipment_types et ON e.equipment_type_id = et.id
    LEFT JOIN manufacturers m ON e.manufacturer_id = m.id
    LEFT JOIN equipment_statuses ls ON e.status_id = ls.id
    WHERE e.assigned_to_employee_id = ?
      AND e.company_id = ?
      AND et.name = 'Workstation'
    LIMIT 1
";



$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "<span style='color:red'>PREPARE FAILED: " . mysqli_error($conn) . "</span><br>";
}


if (!mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id)) {
    echo "<span style='color:red'>BIND FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

if (!mysqli_stmt_execute($stmt)) {
    echo "<span style='color:red'>EXECUTE FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}



$meta = mysqli_stmt_result_metadata($stmt);
$bindVars = [];
$row = [];



while ($field = mysqli_fetch_field($meta)) {

    $bindVars[] = &$row[$field->name];
}

call_user_func_array([$stmt, 'bind_result'], $bindVars);

$workstation = null;

if (mysqli_stmt_fetch($stmt)) {
    $workstation = $row;
}

mysqli_stmt_close($stmt);




// Direct Reports
$stmt = mysqli_prepare($conn, "
    SELECT e.id, e.display_name, e.first_name, e.last_name, e.photo, ep.name as position
    FROM employees e
    LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id
    WHERE e.reports_to = ? AND e.company_id = ? AND e.employment_status_id IN (SELECT id FROM employee_statuses WHERE name = 'Active' AND company_id = ?)
");
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $company_id, $company_id);
mysqli_stmt_execute($stmt);
$direct_reports = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Reporting Chain
$reporting_chain = [];
$curr_id = $user_id;
while ($curr_id) {
    $stmt = mysqli_prepare($conn, "SELECT id, display_name, first_name, last_name, photo, reports_to, employee_position_id FROM employees WHERE id = ? AND on_orgchart = 1 AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $curr_id, $company_id);
    mysqli_stmt_execute($stmt);
    $member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($member) {
        $reporting_chain[] = $member;
        $curr_id = $member['reports_to'];
        if ($curr_id == $member['id']) break;
    } else break;
}



$sql = "
    SELECT *
    FROM employee_sidebar_preferences
    WHERE employee_id = ?
      AND company_id = ?
";


$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "<span style='color:red'>PREPARE FAILED: " . mysqli_error($conn) . "</span><br>";
}



if (!mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id)) {
    echo "<span style='color:red'>BIND FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

if (!mysqli_stmt_execute($stmt)) {
    echo "<span style='color:red'>EXECUTE FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

/* BIND DINÂMICO DOS RESULTADOS */
$meta = mysqli_stmt_result_metadata($stmt);
$bindVars = [];
$row = [];



while ($field = mysqli_fetch_field($meta)) {

    $bindVars[] = &$row[$field->name];
}

call_user_func_array([$stmt, 'bind_result'], $bindVars);

$sidebar_prefs_raw = [];

while (mysqli_stmt_fetch($stmt)) {

    $sidebar_prefs_raw[] = $row;
}

mysqli_stmt_close($stmt);



/* TRANSFORMAR EM MAPA entry_id => is_visible */
$sidebar_prefs = [];

foreach ($sidebar_prefs_raw as $p) {
    $sidebar_prefs[$p['entry_id']] = (int)$p['is_visible'];
}




$sql = "
    SELECT
        'audit' AS type,
        table_name,
        action,
        created_at
    FROM audit_logs
    WHERE employee_id = ?
      AND company_id = ?
    ORDER BY created_at DESC
    LIMIT 10
";



$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "<span style='color:red'>PREPARE FAILED: " . mysqli_error($conn) . "</span><br>";
}



if (!mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id)) {
    echo "<span style='color:red'>BIND FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

if (!mysqli_stmt_execute($stmt)) {
    echo "<span style='color:red'>EXECUTE FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

/* BIND DINÂMICO DOS RESULTADOS */
$meta = mysqli_stmt_result_metadata($stmt);
$bindVars = [];
$row = [];



while ($field = mysqli_fetch_field($meta)) {
    $bindVars[] = &$row[$field->name];
}

call_user_func_array([$stmt, 'bind_result'], $bindVars);

$activity_list = [];

while (mysqli_stmt_fetch($stmt)) {
    $activity_list[] = $row;
}

mysqli_stmt_close($stmt);




$stmt = mysqli_prepare($conn, "SELECT 'login' as type, attempt_source, attempt_type, created_at FROM attempts WHERE employee_id = ? ORDER BY created_at DESC LIMIT 10");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $r) $activity_list[] = $r;
mysqli_stmt_close($stmt);

usort($activity_list, function($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
$activity_list = array_slice($activity_list, 0, 10);

// --- 2. HANDLE POST UPDATES ---
$message = '';
$message_type = 'info';
// Why: after a successful theme save, sync employees.theme into localStorage for js/theme.js.
$syncThemeToClient = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $theme = $profile_normalize_theme($_POST['theme'] ?? 'light');
        $ec_name = trim($_POST['emergency_contact_name'] ?? '');
        $ec_rel = trim($_POST['emergency_contact_relationship'] ?? '');
        $ec_phone = trim($_POST['emergency_contact_phone'] ?? '');
        // Why: accept type=date (Y-m-d) or dd/mm/yyyy; empty clears birthday.
        $birthday = itm_parse_date_input($_POST['birthday'] ?? '');
        $hide_year = !empty($_POST['hide_year']) ? 1 : 0;

        $sql = 'UPDATE employees SET work_email = ?, mobile_phone = ?, theme = ?, emergency_contact_name = ?, emergency_contact_relationship = ?, emergency_contact_phone = ?, birthday = ?, hide_year = ? WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $message = 'Error preparing profile update.';
            $message_type = 'error';
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                'sssssssiii',
                $email,
                $phone,
                $theme,
                $ec_name,
                $ec_rel,
                $ec_phone,
                $birthday,
                $hide_year,
                $user_id,
                $home_company_id
            );
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Profile updated successfully!';
                $message_type = 'success';
                $syncThemeToClient = true;
                $_SESSION['ui_theme'] = $theme;
                $stmt_refresh = mysqli_prepare($conn, 'SELECT * FROM employees WHERE id = ? AND company_id = ?');
                if ($stmt_refresh) {
                    mysqli_stmt_bind_param($stmt_refresh, 'ii', $user_id, $home_company_id);
                    mysqli_stmt_execute($stmt_refresh);
                    $updated_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_refresh));
                    mysqli_stmt_close($stmt_refresh);
                    if (is_array($updated_user)) {
                        itm_log_audit($conn, 'employees', $user_id, 'UPDATE', $current_user, $updated_user);
                    }
                }
            } else {
                $message = 'Error updating profile.';
                $message_type = 'error';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'change_password') {
        $curr_pw = $_POST['current_password'] ?? '';
        $new_pw = $_POST['new_password'] ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';
        if (password_verify($curr_pw, $current_user['password'])) {
            if ($new_pw === $confirm_pw) {
                $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE employees SET password = ? WHERE id = ? AND company_id = ?");
                mysqli_stmt_bind_param($stmt, 'sii', $hash, $user_id, $home_company_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                itm_log_audit($conn, 'employees', $user_id, 'UPDATE', ['action'=>'password_change'], ['action'=>'password_change_success']);
                $message = 'Password updated!';
                $message_type = 'success';
            } else { $message = 'Passwords do not match.'; $message_type = 'error'; }
        } else { $message = 'Current password incorrect.'; $message_type = 'error'; }
    } elseif ($action === 'vault_key_change') {
        $curr_pw = $_POST['current_password'] ?? '';
        if (password_verify($curr_pw, $current_user['password'])) {
            $new_vk = $_POST['new_master_key'] ?? '';
            $confirm_vk = $_POST['confirm_master_key'] ?? '';
            if ($new_vk === $confirm_vk) {
                $old_vk_verify = $_POST['old_master_key_verify'] ?? '';
                $is_first = empty($current_user['vault_key_hash']);
                if ($is_first || password_verify($old_vk_verify, $current_user['vault_key_hash'])) {
                    mysqli_begin_transaction($conn);
                    try {
                        if (!$is_first) {
                            $res = itm_vault_reencrypt_password_entries($conn, $user_id, hash('sha256', $old_vk_verify), hash('sha256', $new_vk));
                            if (!$res['ok']) throw new Exception($res['message']);
                            $resBkm = itm_vault_reencrypt_bookmark_urls($conn, $user_id, hash('sha256', $old_vk_verify), hash('sha256', $new_vk));
                            if (!$resBkm['ok']) throw new Exception($resBkm['message']);
                        }
                        $vk_hash = password_hash($new_vk, PASSWORD_DEFAULT);
                        $stmt = mysqli_prepare($conn, "UPDATE employees SET vault_key_hash = ? WHERE id = ? AND company_id = ?");
                        mysqli_stmt_bind_param($stmt, 'sii', $vk_hash, $user_id, $home_company_id);
                        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
                        mysqli_commit($conn);
                        itm_log_audit($conn, 'employees', $user_id, 'UPDATE', ['action'=>'vault_key_change'], ['action'=>'vault_key_change_success']);
                        if (isset($_SESSION['vault_key'])) $_SESSION['vault_key'] = hash('sha256', $new_vk);
                        $message = 'Vault key updated!'; $message_type = 'success';
                    } catch (Exception $e) { mysqli_rollback($conn); $message = $e->getMessage(); $message_type = 'error'; }
                } else { $message = 'Old Vault Key incorrect.'; $message_type = 'error'; }
            } else { $message = 'Vault keys do not match.'; $message_type = 'error'; }
        } else { $message = 'System password incorrect.'; $message_type = 'error'; }
    } elseif ($action === 'update_sidebar') {
        $items = $_POST['sidebar_items'] ?? [];
        mysqli_begin_transaction($conn);
        $stmt = mysqli_prepare($conn, "UPDATE employee_sidebar_preferences SET is_visible = 0 WHERE employee_id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        foreach ($items as $item_id) {
            $stmt = mysqli_prepare($conn, "INSERT INTO employee_sidebar_preferences (company_id, employee_id, entry_type, entry_id, is_visible) VALUES (?, ?, 'item', ?, 1) ON DUPLICATE KEY UPDATE is_visible = 1");
            mysqli_stmt_bind_param($stmt, 'iis', $company_id, $user_id, $item_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        mysqli_commit($conn);
        itm_log_audit($conn, 'employee_sidebar_preferences', $user_id, 'UPDATE', ['action'=>'sidebar_preferences_change'], ['action'=>'sidebar_preferences_change_success']);
        $message = 'Sidebar updated!'; $message_type = 'success';
    } elseif ($action === 'upload_photo') {
        if (isset($_FILES['photo'])) {
            $res = emp_profile_photo_store_upload($home_company_id, $current_user, $_FILES['photo']);
            if ($res['ok']) {
                $stmt = mysqli_prepare($conn, "UPDATE employees SET photo = ? WHERE id = ? AND company_id = ?");
                mysqli_stmt_bind_param($stmt, 'sii', $res['filename'], $user_id, $home_company_id);
                mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
                itm_log_audit($conn, 'employees', $user_id, 'UPDATE', ['action'=>'photo_upload'], ['action'=>'photo_upload_success']);
                $message = 'Photo updated!'; $message_type = 'success';
            } else { $message = $res['error']; $message_type = 'error'; }
        }
    }
    // Reload user data
    $stmt = mysqli_prepare($conn, 'SELECT e.*, ep.name AS position_name, d.name AS department_name, es.name AS status_name FROM employees e LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN employee_statuses es ON e.employment_status_id = es.id WHERE e.id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt); $current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
}

// Profile Completeness
$pc_fields = ['photo', 'work_email', 'mobile_phone', 'emergency_contact_name', 'emergency_contact_phone', 'vault_key_hash', 'birthday'];
$pc_filled = 0;
foreach ($pc_fields as $f) if (!empty($current_user[$f])) $pc_filled++;
$pc_percent = round(($pc_filled / count($pc_fields)) * 100);

$messageClass = ($message_type === 'success') ? 'crud_success' : (($message_type === 'error') ? 'crud_error' : '');
$profileTheme = $profile_normalize_theme($current_user['theme'] ?? ($_SESSION['ui_theme'] ?? 'light'));
$_SESSION['ui_theme'] = $profileTheme;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo sanitize($profileTheme); ?>">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?php echo sanitize($current_user['display_name'] ?: $current_user['username']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script>
    // Why: apply employees.theme before paint; theme.js later reads localStorage.
    (function () {
        var theme = <?php echo json_encode($profileTheme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ITM_PREFERRED_THEME = theme;
        try { localStorage.setItem('theme', theme); } catch (e) {}
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
    <style>
        .emp-dashboard { display: flex; flex-direction: column; gap: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
        .stat-card { background: var(--bg-primary); padding: 15px; border-radius: 8px; border: 1px solid var(--border); text-decoration: none; color: inherit; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-3px); border-color: var(--accent); box-shadow: var(--shadow-lg); }
        .stat-val { font-size: 22px; font-weight: 700; display: block; color: var(--text-primary); }
        .stat-lbl { font-size: 12px; color: var(--text-secondary); }
        .layout-2col { display: grid; grid-template-columns: 280px 1fr; gap: 20px; }
        .col-left { display: flex; flex-direction: column; gap: 20px; }
        .col-right { display: flex; flex-direction: column; gap: 20px; }
        /* Why: Fixed 280px sidebar collapses into a single column on phones/tablets. */
        @media (max-width: 768px) {
            .layout-2col { grid-template-columns: 1fr; }
        }
        .profile-pic { width: 120px; height: 120px; border-radius: 50%; border: 4px solid var(--bg-secondary); margin: 0 auto; overflow: hidden; position: relative; cursor: pointer; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; }
        .profile-pic img { width: 100%; height: 100%; object-fit: cover; }
        .profile-pic .overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.4); color: #fff; display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.2s; font-size: 12px; }
        .profile-pic:hover .overlay { opacity: 1; }
        .org-path { position: relative; padding-left: 20px; border-left: 2px solid var(--border); list-style: none; margin: 0; font-size: 13px; }
        .org-path li { margin-bottom: 10px; position: relative; }
        .org-path li::before { content: ''; position: absolute; left: -26px; top: 6px; width: 10px; height: 10px; border-radius: 50%; background: var(--bg-primary); border: 2px solid var(--accent); }
        .progress { height: 6px; background: var(--bg-tertiary); border-radius: 3px; overflow: hidden; margin: 5px 0; }
        .progress-bar { height: 100%; background: var(--success); }
        .access-item { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; margin: 2px; border: 1px solid var(--border); }
        .access-on { background: rgba(46, 160, 67, 0.15); color: var(--success); }
        .access-off { background: rgba(248, 81, 73, 0.12); color: var(--danger); opacity: 0.85; }
        .timeline { list-style: none; padding: 0; font-size: 13px; }
        .timeline-item { padding-bottom: 12px; border-left: 2px solid var(--border); padding-left: 15px; position: relative; color: var(--text-primary); }
        .timeline-item::after { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: var(--accent); }
    </style>
</head>
<body>
<div class="container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        <div class="content">
            <?php if ($message) echo "<div class='$messageClass'>".sanitize($message)."</div>"; ?>
            <div style="margin-top:20px;"><a class="btn" href="dashboard.php">🔙</a></div><br><br>
            <div class="emp-dashboard">
                <!-- 1. DASHBOARD STAT CARDS -->
                <div class="stats-grid">
                    <a href="modules/equipment/index.php" class="stat-card">
                        <span class="stat-val"><?php echo $assigned_assets_count; ?></span>
                        <span class="stat-lbl">💻 My Assets</span>
                    </a>
                    <a href="modules/explorer/index.php" class="stat-card">
                        <span class="stat-val"><?php echo $fileCount ? "1" : "0"; ?></span>
                        <span class="stat-lbl">🌐 My Files</span>
                    </a>
                    <a href="modules/tickets/index.php" class="stat-card">
                        <span class="stat-val"><?php echo (int)$ticket_summary['open']; ?>/<?php echo (int)$ticket_summary['total']; ?></span>
                        <span class="stat-lbl">🎟️ My Tickets (Open/Total)</span>
                    </a>
                    <a href="modules/events/index.php" class="stat-card">
                        <span class="stat-val"><?php echo $total_events_created; ?>/<?php echo $total_events_forme; ?></span>
                        <span class="stat-lbl">📅 My Events (My/For Me)</span>
                    </a>
                    <a href="modules/alerts/index.php" class="stat-card">
                        <span class="stat-val"><?php echo $total_alerts_created; ?>/<?php echo $total_alerts_forme; ?></span>
                        <span class="stat-lbl">📢 My Alerts (My/For Me)</span>
                    </a>
                    <a href="modules/passwords/index.php" class="stat-card">
                        <span class="stat-val"><?php echo $vault_entries_count; ?></span>
                        <span class="stat-lbl">🔐 Vault Entries</span>
                    </a>
                    <div class="stat-card">
                        <span class="stat-val"><?php echo $last_login_row ? date('d/m/y', strtotime($last_login_row['created_at'])) : 'Never'; ?></span>
                        <span class="stat-lbl">🕒 Last Login</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-val"><?php echo date('d/m/y', strtotime($current_user['created_at'])); ?></span>
                        <span class="stat-lbl">📅 Joined Date</span>
                    </div>
                    <a href="modules/equipment/index.php" class="stat-card">
                        <span class="stat-val"><?php echo $workstation ? "1" : "0"; ?></span>
                        <span class="stat-lbl">💻 My Hardware</span>
                    </a>
                    <a href="modules/audit_logs/index.php" class="stat-card">
                        <span class="stat-val"><?php echo count($activity_list); ?></span>
                        <span class="stat-lbl">🕒 My Activity</span>
                    </a>
					<a href="modules/employee_system_access/index.php" class="stat-card">
                        <span class="stat-val"><?php echo $system_access_count_1; ?></span>
                        <span class="stat-lbl">System Access</span>
                    </a>
                    <!-- All other 30 combinations grid -->
                    <?php foreach ($all_stats as $s): if ($s['count'] > 0 && !in_array($s['table'], ['equipment','tickets','password_entries','attempts'])): ?>
                        <a href="modules/<?php echo $s['slug']; ?>/index.php" class="stat-card">
                            <span class="stat-val"><?php echo $s['count']; ?></span>
                            <span class="stat-lbl"><?php echo sanitize($s['label']); ?></span>
                        </a>
                    <?php endif; endforeach; ?>
                </div>

                <div class="layout-2col">
                    <!-- LEFT COLUMN -->
                    <div class="col-left">
                        <div class="card" style="text-align:center;">
                            <div class="profile-pic" onclick="document.getElementById('file-photo').click()">
                                <?php $purl = emp_profile_photo_url($current_user); ?>
                                <?php if ($purl): ?><img src="<?php echo $purl; ?>" alt="Profile"><?php else: ?><h1>📷</h1><?php endif; ?>
                                <div class="overlay">Upload</div>
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="form-photo" style="display:none;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="upload_photo">
                                <input type="file" name="photo" id="file-photo" onchange="this.form.submit()">
                            </form>
                            <h3 style="margin:10px 0 5px;"><?php echo sanitize($current_user['display_name'] ?: $current_user['first_name'].' '.$current_user['last_name']); ?></h3>
                            <div style="font-size:13px; color:#586069; margin-bottom:10px;"><?php echo sanitize($current_user['position_name']); ?></div>
                            <span class="badge badge-success"><?php echo sanitize($current_user['status_name']); ?></span>

                            <div style="margin-top:20px; text-align:left;">
                                <div class="stat-lbl">Profile Completeness</div>
                                <div class="progress"><div class="progress-bar" style="width:<?php echo $pc_percent; ?>%;"></div></div>
                                <div style="font-size:11px; text-align:right;"><?php echo $pc_percent; ?>%</div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>Org Chart Path</strong></div>
                            <ul class="org-path">
                                <?php foreach (array_reverse($reporting_chain) as $m): ?>
                                    <li>
                                        <strong><?php echo sanitize($m['display_name'] ?: $m['first_name']); ?></strong><br>
                                        <small style="color:#586069;"><?php echo sanitize($m['id'] == $user_id ? 'You' : 'Manager'); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="col-right">
                        <div class="card">
                            <div class="card-header"><strong title="Edit Profile">✏️</strong></div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="form-row">
                                    <div class="form-group"><label>Full Name</label><input type="text" value="<?php echo sanitize(trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''))); ?>" readonly title="Name is managed in Employees"></div>
                                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize((string)($current_user['work_email'] ?? '')); ?>" required></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo sanitize((string)($current_user['mobile_phone'] ?? '')); ?>"></div>
                                    <div class="form-group"><label>Theme</label><select name="theme"><option value="light" <?php if ($profileTheme === 'light') echo 'selected'; ?>>Light</option><option value="dark" <?php if ($profileTheme === 'dark') echo 'selected'; ?>>Dark</option></select></div>
                                </div>
                                <div class="form-row">
                                    <?php
                                    $profileBirthday = (string)($current_user['birthday'] ?? '');
                                    if ($profileBirthday === '0000-00-00') {
                                        $profileBirthday = '';
                                    }
                                    $profileHideYear = ((int)($current_user['hide_year'] ?? 0) === 1);
                                    ?>
                                    <div class="form-group">
                                        <label>Birthday</label>
                                        <input type="date" name="birthday" value="<?php echo sanitize($profileBirthday); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Hide Year</label>
                                        <label class="itm-checkbox-control">
                                            <input type="checkbox" name="hide_year" value="1" <?php echo $profileHideYear ? 'checked' : ''; ?>>
                                            <span>Hide Year <span class="itm-check-indicator" aria-hidden="true"><?php echo $profileHideYear ? '✅' : '❌'; ?></span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="card-header" style="background:none; border:none; padding:10px 0;"><strong>Emergency Contact</strong></div>
                                <div class="form-row">
                                    <div class="form-group"><label>Name</label><input type="text" name="emergency_contact_name" value="<?php echo sanitize((string)($current_user['emergency_contact_name'] ?? '')); ?>"></div>
                                    <div class="form-group"><label>Relationship</label><input type="text" name="emergency_contact_relationship" value="<?php echo sanitize((string)($current_user['emergency_contact_relationship'] ?? '')); ?>"></div>
                                    <div class="form-group"><label>Phone</label><input type="text" name="emergency_contact_phone" value="<?php echo sanitize((string)($current_user['emergency_contact_phone'] ?? '')); ?>"></div>
                                </div>
                                <button type="submit" class="btn btn-primary" title="Save">💾</button>
                            </form>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>🌐 System Access Overview</strong></div>
                            <div>
<?php



// Linha única da tabela
$sa = $system_access_overview ? $system_access_overview[0] : [];
//print_r($sa);
// Colunas que NÃO devem aparecer
// Why: hide identity + audit/soft-delete meta; only system flag columns render as ✅/❌.
$exclude = [
    'id', 'company_id', 'employee_id', 'active',
    'changed_at', 'created_at', 'updated_at', 'deleted_at',
    'created_by', 'updated_by', 'deleted_by',
];

// Descobrir automaticamente todos os campos de acesso
$access_fields = array_diff(array_keys($sa), $exclude);

foreach ($access_fields as $f):
    // Label automático: transforma "micros_emc" → "Micros Emc"
    $label = ucwords(str_replace('_', ' ', $f));

    // ON se for 1 ou string não vazia
    $on = (!empty($sa[$f]) && $sa[$f] != "0");
?>
    <span class="access-item <?php echo $on ? 'access-on' : 'access-off'; ?>">
        <?php echo $on ? '✅' : '❌'; ?> <?php echo $label; ?>
    </span>
<?php endforeach; ?>

                            </div>
                        </div>

<?php if (!empty($workstation)): ?>
<div class="card">
    <div class="card-header"><strong>💻 My Hardware Details</strong></div>
    <div style="font-size:13px;">
        <div class="form-row">
            <div class="form-group">
                <label>Model</label>
                <div><strong><?php echo sanitize($workstation['manufacturer_name'].' '.$workstation['model']); ?></strong></div>
            </div>
            <div class="form-group">
                <label>Hostname</label>
                <div><code><?php echo sanitize($workstation['hostname']); ?></code></div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Serial Number</label>
                <div><?php echo sanitize($workstation['serial_number']); ?></div>
            </div>
            <div class="form-group">
                <label>Processor</label>
                <div><?php echo sanitize($workstation['workstation_processor'] ?: '—'); ?></div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Storage</label>
                <div><?php echo sanitize($workstation['workstation_storage'] ?: '—'); ?></div>
            </div>
            <div class="form-group">
                <label>RAM</label>
                <div>
                    <?php
                    if (!empty($workstation['workstation_ram_id'])) {
                        $stmt_ram = mysqli_prepare($conn, "SELECT name FROM workstation_ram WHERE id = ?");
                        mysqli_stmt_bind_param($stmt_ram, "i", $workstation['workstation_ram_id']);
                        mysqli_stmt_execute($stmt_ram);
                        $ram_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ram));
                        echo sanitize($ram_row['name'] ?? '—');
                        mysqli_stmt_close($stmt_ram);
                    } else {
                        echo '—';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>

<?php endif; ?>


<div class="card">
    <div class="card-header"><strong>💻 Workstation mode</strong></div>
    <div style="font-size:13px;">
        <div class="form-row">
            <div class="form-group">
                <label>💻 Workstation Mode:</label>
                <div><strong><?php echo isset($mode_name) ? htmlspecialchars($assignment_types) : ' — '; ?></strong></div>
            </div>
            <div class="form-group">
                <label>👥 Assignment Type: </label>
                <div><strong><?php echo isset($assignment_types) ? htmlspecialchars($assignment_types) : ' — '; ?></strong></div>
            </div>
        </div>
	</div>
</div>


                        <div class="form-row">
                            <div class="card">
                                <div class="card-header"><strong>🔑 Change Password</strong></div>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="change_password">
                                    <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
                                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
                                    <div class="form-group"><label>Confirm</label><input type="password" name="confirm_password" required></div>
                                    <button type="submit" class="btn btn-primary" title="Save">💾</button>
                                </form>
                            </div>
                            <div class="card" id="vault-security">
                                <div class="card-header"><strong>🔐 Vault Security</strong></div>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="vault_key_change">
                                    <div class="form-group"><label>System Password</label><input type="password" name="current_password" required></div>
                                    <?php if($current_user['vault_key_hash']):?><div class="form-group"><label>Current Master Key</label><input type="password" name="old_master_key_verify" required></div><?php endif;?>
                                    <div class="form-group"><label>New Master Key</label><input type="password" name="new_master_key" required></div>
                                    <div class="form-group"><label>Confirm</label><input type="password" name="confirm_master_key" required></div>
                                    <button type="submit" class="btn btn-primary" title="Save">💾</button>
                                </form>
                            </div>
                        </div>

                        <?php if($direct_reports): ?>
                        <div class="card">
                            <div class="card-header"><strong>👥 Direct Reports</strong></div>
                            <div class="stats-grid">
                                <?php foreach($direct_reports as $dr): ?>
                                    <div class="stat-card" style="text-align:center;">
                                        <div style="width:40px;height:40px;border-radius:50%;margin:0 auto 8px;overflow:hidden;background:#eee;">
                                            <?php if($dr['photo']):?><img src="<?php echo emp_profile_photo_url($dr);?>" style="width:100%;height:100%;object-fit:cover;"><?php else:?>👤<?php endif;?>
                                        </div>
                                        <div style="font-size:12px;font-weight:700;"><?php echo sanitize($dr['display_name']?:$dr['first_name']);?></div>
                                        <div style="font-size:10px;color:#586069;"><?php echo sanitize($dr['position']);?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header"><strong>📑 Personalized Sidebar</strong></div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="update_sidebar">
                                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:10px;">
                                    <?php foreach(itm_sidebar_item_catalog() as $id=>$item): if($id==='dashboard_link')continue;
                                        // Why: Open module in a new tab from the prefs grid without underline chrome.
                                        $sidebarItemHref = !empty($item['href']) ? (string)$item['href'] : ('modules/' . $id . '/');
                                    ?>
                                        <label class="itm-checkbox-control">
                                            <input type="checkbox" name="sidebar_items[]" value="<?php echo sanitize($id); ?>" <?php echo ($sidebar_prefs[$id]??1)?'checked':''; ?>>
                                            <span><a class="itm-user-config-sidebar-link" href="<?php echo sanitize($sidebarItemHref); ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($item['label']); ?></a></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn btn-primary" style="margin-top:15px;">💾</button>
                            </form>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>🕒 Recent Activity</strong></div>
                            <ul class="timeline">
                                <?php foreach($activity_list as $act): ?>
                                    <li class="timeline-item">
                                        <?php if($act['type']=='audit'):
                                            // Why: Match Personalized Sidebar — open modules/{table}/ in a new tab without blue underline chrome.
                                            $activityTable = (string)($act['table_name'] ?? '');
                                            $activityModuleHref = '';
                                            if ($activityTable !== '' && function_exists('itm_is_safe_identifier') && itm_is_safe_identifier($activityTable)) {
                                                foreach (itm_sidebar_item_catalog() as $catalogId => $catalogItem) {
                                                    if ($catalogId === $activityTable || (($catalogItem['match_dir'] ?? '') === $activityTable)) {
                                                        if (!empty($catalogItem['href'])) {
                                                            $activityModuleHref = (string)$catalogItem['href'];
                                                        }
                                                        break;
                                                    }
                                                }
                                                if ($activityModuleHref === '') {
                                                    $activityModuleHref = 'modules/' . $activityTable . '/';
                                                }
                                            }
                                        ?>
                                            <strong><?php echo sanitize($act['action']);?></strong> in <?php
                                            if ($activityModuleHref !== ''):
                                            ?><a class="itm-user-config-sidebar-link" href="<?php echo sanitize($activityModuleHref); ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($activityTable); ?></a><?php
                                            else:
                                                echo sanitize($activityTable);
                                            endif;
                                            ?>
                                        <?php else:?><strong>Login</strong> <?php echo sanitize($act['attempt_type']);?> (<?php echo sanitize($act['attempt_source']);?>)<?php endif;?>
                                        <div style="color:#586069; font-size:11px;"><?php echo date('d M Y, H:i', strtotime($act['created_at']));?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;"><a class="btn" href="dashboard.php">🔙</a></div>
        </div>
    </div>
</div>
<script src="js/theme.js"></script>
<script>
(function () {
    // Why: re-assert DB/session theme after theme.js load (header may also load theme.js).
    var theme = window.ITM_PREFERRED_THEME || <?php echo json_encode($profileTheme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    try { localStorage.setItem('theme', theme); } catch (e) {}
    document.documentElement.setAttribute('data-theme', theme);
    if (typeof updateThemeButton === 'function') { updateThemeButton(); }
})();
(function () {
    // Why: keep Hide Year indicator aligned with checkbox without waiting for reload.
    var hideYear = document.querySelector('input[name="hide_year"]');
    if (!hideYear) return;
    hideYear.addEventListener('change', function () {
        var indicator = hideYear.parentNode ? hideYear.parentNode.querySelector('.itm-check-indicator') : null;
        if (indicator) {
            indicator.textContent = hideYear.checked ? '✅' : '❌';
        }
    });
})();
    const pic = document.querySelector('.profile-pic');
    pic.addEventListener('dragover', e=>{ e.preventDefault(); pic.style.borderColor='#0366d6'; });
    pic.addEventListener('dragleave', ()=>{ pic.style.borderColor='#f6f8fa'; });
    pic.addEventListener('drop', e=>{ e.preventDefault();
        if(e.dataTransfer.files.length){
            document.getElementById('file-photo').files = e.dataTransfer.files;
            document.getElementById('form-photo').submit();
        }
    });
</script>
</body>
</html>
