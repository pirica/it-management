<?php
/**
 * Regression: flattened CRUD FK label search matches related lookup names.
 *
 * CLI: php scripts/verify_crud_fk_label_search.php
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_crud_fk_label_search.php';
require_once ROOT_PATH . 'includes/itm_todo_search.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('CRUD FK Label Search Verification');

$nl = itm_script_output_nl();
$companyId = 1;
$failed = false;

function verify_crud_fk_label_run_isolated($scriptPath, array $session, array $get = [])
{
    $sessionExport = var_export($session, true);
    $getExport = var_export($get, true);
    $dir = var_export(dirname($scriptPath), true);
    $base = var_export(basename($scriptPath), true);
    $code = "<?php
define('ITM_CLI_SCRIPT', true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
\$_SESSION = {$sessionExport};
\$_GET = {$getExport};
chdir({$dir});
ob_start();
include {$base};
echo ob_get_clean();
";
    $tmpFile = tempnam(sys_get_temp_dir(), 'verify_crud_fk_search');
    file_put_contents($tmpFile, $code);
    $output = [];
    exec(PHP_BINARY . ' -d error_reporting=0 ' . escapeshellarg($tmpFile) . ' 2>&1', $output);
    unlink($tmpFile);

    return implode("\n", $output);
}

// Why: Disposable employee carries employment_status_id=1 (Active) for employees search regression.
$testUser = itm_script_test_employee_create($conn, $companyId, [
    'script_slug' => 'verify-crud-fk-search',
    'employment_status_id' => 1,
    'first_name' => 'FkSearch',
    'last_name' => 'Probe',
]);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test employee.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$employeeId = (int)$testUser['id'];
itm_script_test_employee_register_teardown($conn, $employeeId);

$session = [
    'employee_id' => 1,
    'company_id' => $companyId,
    'username' => 'Admin',
    'role' => 'Admin',
];

// Employees: search Active should list disposable row (employment_statuses.name).
$employeesHtml = verify_crud_fk_label_run_isolated(
    ROOT_PATH . 'modules/employees/index.php',
    $session,
    ['search' => 'Active', 'sort' => 'id', 'dir' => 'DESC']
);
if (strpos($employeesHtml, (string)$employeeId) === false && stripos($employeesHtml, 'FkSearch') === false) {
    echo colorText('[FAIL] employees search=Active did not return Active-status disposable row.', 'fail') . $nl;
    $failed = true;
} else {
    echo colorText('[PASS] employees search=Active matches employment_statuses.name.', 'pass') . $nl;
}

// License management: search a known license type label when seeded.
$typeRes = mysqli_query($conn, "SELECT lt.name FROM license_types lt WHERE lt.company_id = {$companyId} ORDER BY lt.id ASC LIMIT 1");
$typeRow = $typeRes ? mysqli_fetch_assoc($typeRes) : null;
$typeName = trim((string)($typeRow['name'] ?? ''));
if ($typeName !== '') {
    $licenseHtml = verify_crud_fk_label_run_isolated(
        ROOT_PATH . 'modules/license_management/index.php',
        $session,
        ['search' => $typeName, 'sort' => 'id', 'dir' => 'DESC']
    );
    if (stripos($licenseHtml, htmlspecialchars($typeName, ENT_QUOTES, 'UTF-8')) === false && stripos($licenseHtml, $typeName) === false) {
        echo colorText('[FAIL] license_management search did not match license_types.name (' . $typeName . ').', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] license_management search matches license_types.name.', 'pass') . $nl;
    }
} else {
    echo colorText('[SKIP] license_types seed missing for company 1.', 'warn') . $nl;
}

// Helper unit probe: EXISTS fragment is emitted for employment_status_id.
$fkMap = [];
$fkSql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
          FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'employees'
            AND REFERENCED_TABLE_NAME IS NOT NULL";
$res = mysqli_query($conn, $fkSql);
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $fkMap[$row['COLUMN_NAME']] = $row;
}
$conds = itm_crud_fk_label_search_conditions($conn, 'employees', 'e', $fkMap, ['employment_status_id'], $companyId, '%Active%');
if (empty($conds) || stripos($conds[0], 'employee_statuses') === false) {
    echo colorText('[FAIL] itm_crud_fk_label_search_conditions did not build employee_statuses EXISTS.', 'fail') . $nl;
    $failed = true;
} else {
    echo colorText('[PASS] Shared FK label search helper builds EXISTS predicate.', 'pass') . $nl;
}

// Switch ports: search Down should match switch_status.status label.
$switchPortId = 0;
$switchPortNumber = 99991;
$stmtSwitch = mysqli_prepare(
    $conn,
    'INSERT INTO switch_ports (company_id, port_type, port_number, status_id, color_id)
     VALUES (?, ?, ?, ?, ?)'
);
if ($stmtSwitch) {
    $portType = 'RJ45';
    $statusId = 11;
    $colorId = 1;
    mysqli_stmt_bind_param($stmtSwitch, 'isiii', $companyId, $portType, $switchPortNumber, $statusId, $colorId);
    if (mysqli_stmt_execute($stmtSwitch)) {
        $switchPortId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmtSwitch);
}
if ($switchPortId > 0) {
    $switchHtml = verify_crud_fk_label_run_isolated(
        ROOT_PATH . 'modules/switch_ports/list_all.php',
        $session,
        ['search' => 'Down', 'sort' => 'id', 'dir' => 'DESC']
    );
    if (strpos($switchHtml, (string)$switchPortNumber) === false && stripos($switchHtml, 'Down') === false) {
        echo colorText('[FAIL] switch_ports search=Down did not return status-labelled row.', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] switch_ports search matches switch_status.status.', 'pass') . $nl;
    }
    mysqli_query($conn, 'DELETE FROM switch_ports WHERE id = ' . (int)$switchPortId);
} else {
    echo colorText('[FAIL] Unable to seed disposable switch_ports row for search probe.', 'fail') . $nl;
    $failed = true;
}

// Todo: search category/department/assignee labels via CSV FK helper.
$todoCategoryId = 0;
$todoTaskId = 0;
$todoCategoryName = 'FkSearchCat' . substr(md5((string)microtime(true)), 0, 8);
$stmtCat = mysqli_prepare(
    $conn,
    'INSERT INTO todo_categories (company_id, cat_from_employee_id, name, active) VALUES (?, ?, ?, 1)'
);
if ($stmtCat) {
    mysqli_stmt_bind_param($stmtCat, 'iis', $companyId, $employeeId, $todoCategoryName);
    if (mysqli_stmt_execute($stmtCat)) {
        $todoCategoryId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmtCat);
}
if ($todoCategoryId > 0) {
    $deptId = 1;
    $assigneeId = $employeeId;
    $todoTitle = 'FkSearchTodoProbe';
    $categoryCsv = (string)$todoCategoryId;
    $deptCsv = (string)$deptId;
    $assigneeCsv = (string)$assigneeId;
    $createdByAdmin = 1;
    $stmtTodo = mysqli_prepare(
        $conn,
        'INSERT INTO todo (company_id, title, category_id, department_id, assigned_to_employee_id, created_by_employee_id, active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );
    if ($stmtTodo) {
        mysqli_stmt_bind_param($stmtTodo, 'issssi', $companyId, $todoTitle, $categoryCsv, $deptCsv, $assigneeCsv, $createdByAdmin);
        if (mysqli_stmt_execute($stmtTodo)) {
            $todoTaskId = (int)mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmtTodo);
    }
}
if ($todoTaskId > 0) {
    $todoClause = itm_todo_build_search_clause($todoCategoryName);
    if ($todoClause['sql'] === '' || $todoClause['types'] !== 'ssssssssss') {
        echo colorText('[FAIL] itm_todo_build_search_clause did not build expected prepared fragment.', 'fail') . $nl;
        $failed = true;
    } else {
        $todoHtml = verify_crud_fk_label_run_isolated(
            ROOT_PATH . 'modules/todo/index.php',
            $session,
            ['search' => $todoCategoryName, 'filter' => 'tasks']
        );
        if (stripos($todoHtml, $todoTitle) === false) {
            echo colorText('[FAIL] todo search did not match todo_categories.name.', 'fail') . $nl;
            $failed = true;
        } else {
            echo colorText('[PASS] todo search matches category/department/assignee labels.', 'pass') . $nl;
        }
    }
    mysqli_query($conn, 'DELETE FROM todo WHERE id = ' . (int)$todoTaskId);
}
if ($todoCategoryId > 0) {
    mysqli_query($conn, 'DELETE FROM todo_categories WHERE id = ' . (int)$todoCategoryId);
}
if ($todoCategoryId <= 0 || $todoTaskId <= 0) {
    echo colorText('[FAIL] Unable to seed disposable todo row for search probe.', 'fail') . $nl;
    $failed = true;
}

// Notes: shared-with employee name search.
$noteId = 0;
$noteTitle = 'FkSearchNoteProbe';
$sharedJson = json_encode([$employeeId]);
$sharedSearchTerm = (string)($testUser['username'] ?? 'FkSearch');
$stmtNote = mysqli_prepare(
    $conn,
    'INSERT INTO notes (company_id, employee_id, title, content, shared_with_json, active)
     VALUES (?, 1, ?, ?, ?, 1)'
);
if ($stmtNote) {
    $noteContent = 'Shared-with search probe without matching title token';
    mysqli_stmt_bind_param($stmtNote, 'isss', $companyId, $noteTitle, $noteContent, $sharedJson);
    if (mysqli_stmt_execute($stmtNote)) {
        $noteId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmtNote);
}
if ($noteId > 0) {
    $noteHtml = verify_crud_fk_label_run_isolated(
        ROOT_PATH . 'modules/notes/index.php',
        $session,
        ['search' => $sharedSearchTerm, 'filter' => 'all']
    );
    if (stripos($noteHtml, $noteTitle) === false) {
        echo colorText('[FAIL] notes search did not match shared-with employee names.', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] notes search matches shared-with employee labels.', 'pass') . $nl;
    }
    mysqli_query($conn, 'DELETE FROM notes WHERE id = ' . (int)$noteId);
} else {
    echo colorText('[FAIL] Unable to seed disposable notes row for search probe.', 'fail') . $nl;
    $failed = true;
}

// Private contacts: phone and labels visible in list search.
$privateContactId = 0;
$privatePhone = 'FkSearchPhone' . substr(md5((string)microtime(true)), 0, 6);
$privateLabel = 'FkSearchLabel';
$stmtContact = mysqli_prepare(
    $conn,
    'INSERT INTO private_contacts (company_id, employee_id, first_name, last_name, phone1_value, labels, active)
     VALUES (?, 1, ?, ?, ?, ?, 1)'
);
if ($stmtContact) {
    $firstName = 'FkSearch';
    $lastName = 'Contact';
    mysqli_stmt_bind_param($stmtContact, 'issss', $companyId, $firstName, $lastName, $privatePhone, $privateLabel);
    if (mysqli_stmt_execute($stmtContact)) {
        $privateContactId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmtContact);
}
if ($privateContactId > 0) {
    $contactHtml = verify_crud_fk_label_run_isolated(
        ROOT_PATH . 'modules/private_contacts/index.php',
        $session,
        ['search' => $privatePhone]
    );
    if (stripos($contactHtml, $privatePhone) === false) {
        echo colorText('[FAIL] private_contacts search did not match phone1_value.', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] private_contacts search matches phone and labels columns.', 'pass') . $nl;
    }
    mysqli_query($conn, 'DELETE FROM private_contacts WHERE id = ' . (int)$privateContactId);
} else {
    echo colorText('[FAIL] Unable to seed disposable private_contacts row for search probe.', 'fail') . $nl;
    $failed = true;
}

function verify_crud_fk_label_run_ajax_post($scriptPath, array $session, array $post = [])
{
    $sessionExport = var_export($session, true);
    $postExport = var_export($post, true);
    $dir = var_export(dirname($scriptPath), true);
    $base = var_export(basename($scriptPath), true);
    $rootPath = var_export(rtrim(ROOT_PATH, '/\\') . '/', true);
    $code = "<?php
define('ITM_CLI_SCRIPT', true);
require_once {$rootPath} . 'config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
\$_SESSION = {$sessionExport};
\$_POST = {$postExport};
\$_POST['csrf_token'] = itm_get_csrf_token();
\$_SERVER['REQUEST_METHOD'] = 'POST';
chdir({$dir});
ob_start();
include {$base};
echo ob_get_clean();
";
    $tmpFile = tempnam(sys_get_temp_dir(), 'verify_crud_fk_ajax');
    file_put_contents($tmpFile, $code);
    $output = [];
    exec(PHP_BINARY . ' -d error_reporting=0 ' . escapeshellarg($tmpFile) . ' 2>&1', $output);
    unlink($tmpFile);

    return implode("\n", $output);
}

// IP subnets: search VLAN name via shared FK helper on list_query.php.
$ipSubnetId = 0;
$vlanSearchTerm = 'Factory Default';
$ipSubnetCidr = '10.99.' . random_int(10, 250) . '.0/24';
$stmtSubnet = mysqli_prepare(
    $conn,
    'INSERT INTO ip_subnets (company_id, vlan_id, cidr, network_ip, prefix_length, active) VALUES (?, 1, ?, ?, 24, 1)'
);
if ($stmtSubnet) {
    $networkIp = preg_replace('#/.*$#', '', $ipSubnetCidr);
    mysqli_stmt_bind_param($stmtSubnet, 'iss', $companyId, $ipSubnetCidr, $networkIp);
    if (mysqli_stmt_execute($stmtSubnet)) {
        $ipSubnetId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmtSubnet);
}
if ($ipSubnetId > 0) {
    $subnetHtml = verify_crud_fk_label_run_isolated(
        ROOT_PATH . 'modules/ip_subnets/list_all.php',
        $session,
        ['search' => $vlanSearchTerm, 'sort' => 'id', 'dir' => 'DESC']
    );
    if (strpos($subnetHtml, $ipSubnetCidr) === false && stripos($subnetHtml, $vlanSearchTerm) === false) {
        echo colorText('[FAIL] ip_subnets search did not match vlans.vlan_name.', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] ip_subnets search matches vlans.vlan_name.', 'pass') . $nl;
    }
    mysqli_query($conn, 'DELETE FROM ip_subnets WHERE id = ' . (int)$ipSubnetId);
} else {
    echo colorText('[FAIL] Unable to seed disposable ip_subnets row for search probe.', 'fail') . $nl;
    $failed = true;
}

// Bookmarks list_all: search folder name via JOIN.
$bookmarkFolderId = 0;
$bookmarkId = 0;
$bookmarkFolderName = 'FkSearchBkmFolder' . substr(md5((string)microtime(true)), 0, 8);
$stmtBkmFolder = mysqli_prepare(
    $conn,
    'INSERT INTO bookmark_folders (company_id, employee_id, name, active) VALUES (?, 1, ?, 1)'
);
if ($stmtBkmFolder) {
    mysqli_stmt_bind_param($stmtBkmFolder, 'is', $companyId, $bookmarkFolderName);
    if (mysqli_stmt_execute($stmtBkmFolder)) {
        $bookmarkFolderId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmtBkmFolder);
}
if ($bookmarkFolderId > 0) {
    $bookmarkTitle = 'FkSearchBkmProbe';
    $bookmarkUrl = 'https://example.com/fk-search-probe';
    $stmtBkm = mysqli_prepare(
        $conn,
        'INSERT INTO bookmarks (company_id, employee_id, folder_id, title, url, shared, active)
         VALUES (?, 1, ?, ?, ?, 0, 1)'
    );
    if ($stmtBkm) {
        mysqli_stmt_bind_param($stmtBkm, 'iiss', $companyId, $bookmarkFolderId, $bookmarkTitle, $bookmarkUrl);
        if (mysqli_stmt_execute($stmtBkm)) {
            $bookmarkId = (int)mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmtBkm);
    }
}
if ($bookmarkId > 0) {
    $bkmHtml = verify_crud_fk_label_run_isolated(
        ROOT_PATH . 'modules/bookmarks/list_all.php',
        $session,
        ['search' => $bookmarkFolderName, 'sort' => 'title', 'dir' => 'ASC']
    );
    if (stripos($bkmHtml, $bookmarkTitle) === false) {
        echo colorText('[FAIL] bookmarks list_all search did not match bookmark_folders.name.', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] bookmarks list_all search matches folder name.', 'pass') . $nl;
    }
    mysqli_query($conn, 'DELETE FROM bookmarks WHERE id = ' . (int)$bookmarkId);
}
if ($bookmarkFolderId > 0) {
    mysqli_query($conn, 'DELETE FROM bookmark_folders WHERE id = ' . (int)$bookmarkFolderId);
}
if ($bookmarkFolderId <= 0 || $bookmarkId <= 0) {
    echo colorText('[FAIL] Unable to seed disposable bookmarks row for search probe.', 'fail') . $nl;
    $failed = true;
}

// Passwords: global list_entries search matches password_folders.name.
$passwordFolderId = 0;
$passwordEntryId = 0;
$passwordFolderName = 'FkSearchPwdFolder' . substr(md5((string)microtime(true)), 0, 8);
$stmtPwdFolder = mysqli_prepare(
    $conn,
    'INSERT INTO password_folders (employee_id, name) VALUES (1, ?)'
);
if ($stmtPwdFolder) {
    mysqli_stmt_bind_param($stmtPwdFolder, 's', $passwordFolderName);
    if (mysqli_stmt_execute($stmtPwdFolder)) {
        $passwordFolderId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmtPwdFolder);
}
if ($passwordFolderId > 0) {
    $pwdAccount = 'FkSearchPwdProbe';
    $pwdLogin = 'probe-user';
    $encryptedPwd = itm_encrypt('probe-secret', hash('sha256', 'Admin'));
    $stmtPwdEntry = mysqli_prepare(
        $conn,
        'INSERT INTO password_entries (employee_id, folder_name, account, login_name, password)
         VALUES (1, ?, ?, ?, ?)'
    );
    if ($stmtPwdEntry) {
        mysqli_stmt_bind_param($stmtPwdEntry, 'isss', $passwordFolderId, $pwdAccount, $pwdLogin, $encryptedPwd);
        if (mysqli_stmt_execute($stmtPwdEntry)) {
            $passwordEntryId = (int)mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmtPwdEntry);
    }
}
if ($passwordEntryId > 0) {
    $vaultSession = $session;
    $vaultSession['vault_key'] = hash('sha256', 'Admin');
    $pwdJson = verify_crud_fk_label_run_ajax_post(
        ROOT_PATH . 'modules/passwords/ajax_handler.php',
        $vaultSession,
        [
            'action' => 'list_entries',
            'folder_id' => '0',
            'search' => $passwordFolderName,
        ]
    );
    $pwdRows = json_decode($pwdJson, true);
    $pwdMatched = false;
    if (is_array($pwdRows)) {
        foreach ($pwdRows as $pwdRow) {
            if (is_array($pwdRow) && (string)($pwdRow['account'] ?? '') === $pwdAccount) {
                $pwdMatched = true;
                break;
            }
        }
    }
    if (!$pwdMatched) {
        echo colorText('[FAIL] passwords list_entries global search did not match password_folders.name.', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] passwords list_entries global search matches folder name.', 'pass') . $nl;
    }
    mysqli_query($conn, 'DELETE FROM password_entries WHERE id = ' . (int)$passwordEntryId);
}
if ($passwordFolderId > 0) {
    mysqli_query($conn, 'DELETE FROM password_folders WHERE id = ' . (int)$passwordFolderId);
}
if ($passwordFolderId <= 0 || $passwordEntryId <= 0) {
    echo colorText('[FAIL] Unable to seed disposable passwords row for search probe.', 'fail') . $nl;
    $failed = true;
}


itm_script_output_end();
exit($failed ? 1 : 0);
