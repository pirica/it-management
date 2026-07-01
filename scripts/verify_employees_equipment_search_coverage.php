<?php
/**
 * Regression: employees and equipment index search coverage (scalar + FK labels).
 *
 * CLI: php scripts/verify_employees_equipment_search_coverage.php
 * Optional env: ITM_TEST_COMPANY_ID (default 1)
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Employees & Equipment Search Coverage');

$nl = itm_script_output_nl();
$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
if ($companyId <= 0) {
    $companyId = 1;
}
$failed = false;

/**
 * @param string $scriptPath
 * @param array<string, mixed> $session
 * @param array<string, mixed> $get
 */
function veesc_run_isolated($scriptPath, array $session, array $get = [])
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
    $tmpFile = tempnam(sys_get_temp_dir(), 'veesc_search');
    file_put_contents($tmpFile, $code);
    $output = [];
    exec(PHP_BINARY . ' -d error_reporting=0 ' . escapeshellarg($tmpFile) . ' 2>&1', $output);
    unlink($tmpFile);

    return implode("\n", $output);
}

/**
 * @param string[] $needles
 */
function veesc_html_matches_probe($html, array $needles)
{
    foreach ($needles as $needle) {
        $needle = (string)$needle;
        if ($needle === '') {
            continue;
        }
        if (strpos($html, $needle) !== false || stripos($html, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @param string[] $needles
 */
function veesc_assert_list_probe($moduleLabel, $searchTerm, $html, array $needles, &$failed)
{
    global $nl;

    if (veesc_html_matches_probe($html, $needles)) {
        echo colorText('[PASS] ' . $moduleLabel . ' search=' . $searchTerm, 'pass') . $nl;
        return;
    }

    echo colorText('[FAIL] ' . $moduleLabel . ' search=' . $searchTerm . ' did not return probe row.', 'fail') . $nl;
    $failed = true;
}

/**
 * @return string
 */
function veesc_lookup_string(mysqli $conn, string $sql, string $types, array $params)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (string)($row['label'] ?? $row['name'] ?? '');
}

/**
 * @param array<string, mixed> $fields
 */
function veesc_update_employee_fields(mysqli $conn, int $employeeId, int $companyId, array $fields)
{
    if ($employeeId <= 0 || empty($fields)) {
        return;
    }

    $sets = [];
    $types = '';
    $values = [];
    foreach ($fields as $column => $value) {
        if (!preg_match('/^[a-z_]+$/', (string)$column)) {
            continue;
        }
        if ($value === null) {
            $sets[] = '`' . $column . '` = NULL';
            continue;
        }
        $sets[] = '`' . $column . '` = ?';
        if (is_int($value)) {
            $types .= 'i';
        } elseif (is_float($value)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $values[] = $value;
    }
    if (empty($sets)) {
        return;
    }

    $sql = 'UPDATE employees SET ' . implode(', ', $sets) . ' WHERE id = ? AND company_id = ?';
    $types .= 'ii';
    $values[] = $employeeId;
    $values[] = $companyId;
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$values);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * @return int
 */
function veesc_lookup_int(mysqli $conn, string $sql, string $types, array $params)
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (int)($row['id'] ?? 0);
}

$session = [
    'employee_id' => 1,
    'company_id' => $companyId,
    'username' => 'Admin',
    'role' => 'Admin',
];

$probeFirstName = 'FkSearchCov';
$probeLastName = 'LastProbe';
$probeFullName = $probeFirstName . ' ' . $probeLastName;

$testUser = itm_script_test_employee_create($conn, $companyId, [
    'script_slug' => 'verify-emp-equip-search-cov',
    'employment_status_id' => 1,
    'first_name' => $probeFirstName,
    'last_name' => $probeLastName,
]);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test employee.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$employeeId = (int)$testUser['id'];
$employeeUsername = (string)($testUser['username'] ?? '');
itm_script_test_employee_register_teardown($conn, $employeeId);

$employeeNeedles = [(string)$employeeId, $probeFirstName, $probeLastName];

// --- Employees: scalar identity fields ---
$employeesIndex = ROOT_PATH . 'modules/employees/index.php';

veesc_assert_list_probe(
    'employees',
    $probeFirstName,
    veesc_run_isolated($employeesIndex, $session, ['search' => $probeFirstName, 'sort' => 'id', 'dir' => 'DESC']),
    $employeeNeedles,
    $failed
);
veesc_assert_list_probe(
    'employees',
    $probeLastName,
    veesc_run_isolated($employeesIndex, $session, ['search' => $probeLastName, 'sort' => 'id', 'dir' => 'DESC']),
    $employeeNeedles,
    $failed
);
if ($employeeUsername !== '') {
    veesc_assert_list_probe(
        'employees',
        $employeeUsername,
        veesc_run_isolated($employeesIndex, $session, ['search' => $employeeUsername, 'sort' => 'id', 'dir' => 'DESC']),
        $employeeNeedles,
        $failed
    );
} else {
    echo colorText('[FAIL] Disposable employee missing username for search probe.', 'fail') . $nl;
    $failed = true;
}

veesc_assert_list_probe(
    'employees',
    $probeFullName,
    veesc_run_isolated($employeesIndex, $session, ['search' => $probeFullName, 'sort' => 'id', 'dir' => 'DESC']),
    $employeeNeedles,
    $failed
);

veesc_assert_list_probe(
    'employees',
    'Active',
    veesc_run_isolated($employeesIndex, $session, ['search' => 'Active', 'sort' => 'id', 'dir' => 'DESC']),
    $employeeNeedles,
    $failed
);

// --- Employees: FK label fields ---
$fnbDeptId = veesc_lookup_int(
    $conn,
    "SELECT id FROM departments WHERE company_id = ? AND code = 'FNB' LIMIT 1",
    'i',
    [$companyId]
);
$locationId = veesc_lookup_int(
    $conn,
    "SELECT id FROM it_locations WHERE company_id = ? AND location_code = 'LOC-NY-01' LIMIT 1",
    'i',
    [$companyId]
);
$positionId = veesc_lookup_int(
    $conn,
    "SELECT id FROM employee_positions WHERE company_id = ? AND description LIKE 'Leads hotel%' LIMIT 1",
    'i',
    [$companyId]
);

if ($fnbDeptId > 0) {
    $stmt = mysqli_prepare($conn, 'UPDATE employees SET department_id = ? WHERE id = ? AND company_id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iii', $fnbDeptId, $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    veesc_assert_list_probe(
        'employees',
        'FNB',
        veesc_run_isolated($employeesIndex, $session, ['search' => 'FNB', 'sort' => 'id', 'dir' => 'DESC']),
        $employeeNeedles,
        $failed
    );
} else {
    echo colorText('[FAIL] departments seed missing FNB for company ' . $companyId . '.', 'fail') . $nl;
    $failed = true;
}

if ($locationId > 0) {
    $stmt = mysqli_prepare($conn, 'UPDATE employees SET location_id = ? WHERE id = ? AND company_id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iii', $locationId, $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    veesc_assert_list_probe(
        'employees',
        'LOC-NY-01',
        veesc_run_isolated($employeesIndex, $session, ['search' => 'LOC-NY-01', 'sort' => 'id', 'dir' => 'DESC']),
        $employeeNeedles,
        $failed
    );
} else {
    echo colorText('[FAIL] it_locations seed missing LOC-NY-01 for company ' . $companyId . '.', 'fail') . $nl;
    $failed = true;
}

if ($positionId > 0) {
    $stmt = mysqli_prepare($conn, 'UPDATE employees SET employee_position_id = ? WHERE id = ? AND company_id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iii', $positionId, $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    veesc_assert_list_probe(
        'employees',
        'Leads hotel',
        veesc_run_isolated($employeesIndex, $session, ['search' => 'Leads hotel', 'sort' => 'id', 'dir' => 'DESC']),
        $employeeNeedles,
        $failed
    );
} else {
    echo colorText('[FAIL] employee_positions seed missing description probe for company ' . $companyId . '.', 'fail') . $nl;
    $failed = true;
}

// Manager username (reports_to = seed Admin id 1).
$adminId = veesc_lookup_int(
    $conn,
    "SELECT id FROM employees WHERE company_id = ? AND username = 'Admin' LIMIT 1",
    'i',
    [$companyId]
);
if ($adminId > 0) {
    $stmt = mysqli_prepare($conn, 'UPDATE employees SET reports_to = ? WHERE id = ? AND company_id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iii', $adminId, $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    veesc_assert_list_probe(
        'employees',
        'Admin',
        veesc_run_isolated($employeesIndex, $session, ['search' => 'Admin', 'sort' => 'id', 'dir' => 'DESC']),
        $employeeNeedles,
        $failed
    );
} else {
    echo colorText('[FAIL] Admin seed user missing for manager username probe.', 'fail') . $nl;
    $failed = true;
}

// --- Employees: remaining FK labels + mobile_phone ---
$roleId = veesc_lookup_int(
    $conn,
    "SELECT id FROM employee_roles WHERE company_id = ? AND name = 'Helpdesk' LIMIT 1",
    'i',
    [$companyId]
);
$accessLevelId = veesc_lookup_int(
    $conn,
    "SELECT id FROM access_levels WHERE company_id = ? AND name = 'Limited' LIMIT 1",
    'i',
    [$companyId]
);
$employeeTypeId = veesc_lookup_int(
    $conn,
    "SELECT id FROM employee_type WHERE company_id = ? AND name_type = 'Team member' LIMIT 1",
    'i',
    [$companyId]
);
$workstationModeId = veesc_lookup_int(
    $conn,
    "SELECT id FROM workstation_modes WHERE company_id = ? AND mode_name = 'Laptop Only' LIMIT 1",
    'i',
    [$companyId]
);
$assignmentTypeId = veesc_lookup_int(
    $conn,
    "SELECT id FROM assignment_types WHERE company_id = ? AND name = 'Individual' LIMIT 1",
    'i',
    [$companyId]
);
$probeMobilePhone = '555-FkSearchCov';

veesc_update_employee_fields($conn, $employeeId, $companyId, array_filter([
    'role_id' => $roleId > 0 ? $roleId : null,
    'access_level_id' => $accessLevelId > 0 ? $accessLevelId : null,
    'employee_type_id' => $employeeTypeId > 0 ? $employeeTypeId : null,
    'workstation_mode_id' => $workstationModeId > 0 ? $workstationModeId : null,
    'assignment_type_id' => $assignmentTypeId > 0 ? $assignmentTypeId : null,
    'office_key_card_department_id' => $fnbDeptId > 0 ? $fnbDeptId : null,
    'mobile_phone' => $probeMobilePhone,
], static function ($value) {
    return $value !== null;
}));

$employeeFkProbes = [
    ['term' => 'Helpdesk', 'ok' => $roleId > 0],
    ['term' => 'Limited', 'ok' => $accessLevelId > 0],
    ['term' => 'Team member', 'ok' => $employeeTypeId > 0],
    ['term' => 'Laptop Only', 'ok' => $workstationModeId > 0],
    ['term' => 'Individual', 'ok' => $assignmentTypeId > 0],
    ['term' => 'IT Manager', 'ok' => $positionId > 0],
    ['term' => $probeMobilePhone, 'ok' => true],
];
if ($fnbDeptId > 0) {
    $employeeFkProbes[] = ['term' => 'FNB', 'ok' => true];
}

foreach ($employeeFkProbes as $probe) {
    if (empty($probe['ok'])) {
        echo colorText('[FAIL] employees seed missing lookup for search=' . $probe['term'] . '.', 'fail') . $nl;
        $failed = true;
        continue;
    }
    veesc_assert_list_probe(
        'employees',
        (string)$probe['term'],
        veesc_run_isolated($employeesIndex, $session, ['search' => (string)$probe['term'], 'sort' => 'id', 'dir' => 'DESC']),
        $employeeNeedles,
        $failed
    );
}

// --- Equipment: FK + assignee identity ---
$supplierId = veesc_lookup_int(
    $conn,
    "SELECT id FROM suppliers WHERE company_id = ? AND supplier_code = 'SUP-001' LIMIT 1",
    'i',
    [$companyId]
);
$rackId = veesc_lookup_int(
    $conn,
    "SELECT id FROM racks WHERE company_id = ? AND rack_code = 'RACK-A' LIMIT 1",
    'i',
    [$companyId]
);
$equipmentTypeId = veesc_lookup_int(
    $conn,
    "SELECT id FROM equipment_types WHERE company_id = ? AND name = 'Switch' LIMIT 1",
    'i',
    [$companyId]
);
$manufacturerId = veesc_lookup_int(
    $conn,
    "SELECT id FROM manufacturers WHERE company_id = ? AND name = 'Cisco Systems' LIMIT 1",
    'i',
    [$companyId]
);
$equipmentStatusId = veesc_lookup_int(
    $conn,
    'SELECT id FROM equipment_statuses WHERE company_id = ? ORDER BY id ASC LIMIT 1',
    'i',
    [$companyId]
);
$equipmentStatusName = veesc_lookup_string(
    $conn,
    'SELECT name FROM equipment_statuses WHERE id = ? AND company_id = ? LIMIT 1',
    'ii',
    [$equipmentStatusId, $companyId]
);

$equipmentProbeId = 0;
$equipmentProbeName = 'FkSearchEquipCov-' . substr(md5((string)microtime(true)), 0, 8);
$equipmentSerial = 'SN-FKSEARCHCOV';
$equipmentHostname = 'fksearchcov-host';
$equipmentModel = 'FkSearchModelX';
$equipmentNotes = 'FkSearchNotesProbe';
$equipmentPurchaseCost = 9876.54;
$equipmentNeedles = [];

if ($equipmentTypeId > 0 && $equipmentStatusId > 0) {
    $stmtEquip = mysqli_prepare(
        $conn,
        'INSERT INTO equipment (
            company_id, equipment_type_id, manufacturer_id, department_id, supplier_id, location_id, rack_id,
            assigned_to_employee_id, status_id, name, serial_number, hostname, model, notes, purchase_cost, active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );
    if ($stmtEquip) {
        $deptParam = $fnbDeptId > 0 ? $fnbDeptId : null;
        $supplierParam = $supplierId > 0 ? $supplierId : null;
        $locationParam = $locationId > 0 ? $locationId : null;
        $rackParam = $rackId > 0 ? $rackId : null;
        $manufacturerParam = $manufacturerId > 0 ? $manufacturerId : null;
        mysqli_stmt_bind_param(
            $stmtEquip,
            'iiiiiiiiisssssd',
            $companyId,
            $equipmentTypeId,
            $manufacturerParam,
            $deptParam,
            $supplierParam,
            $locationParam,
            $rackParam,
            $employeeId,
            $equipmentStatusId,
            $equipmentProbeName,
            $equipmentSerial,
            $equipmentHostname,
            $equipmentModel,
            $equipmentNotes,
            $equipmentPurchaseCost
        );
        if (mysqli_stmt_execute($stmtEquip)) {
            $equipmentProbeId = (int)mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmtEquip);
    }
}

if ($equipmentProbeId > 0) {
    $equipmentNeedles = [(string)$equipmentProbeId, $equipmentProbeName];
    $equipmentIndex = ROOT_PATH . 'modules/equipment/index.php';

    $equipmentProbes = [
        ['term' => $equipmentProbeName, 'ok' => true],
        ['term' => $equipmentSerial, 'ok' => true],
        ['term' => $equipmentHostname, 'ok' => true],
        ['term' => $equipmentModel, 'ok' => true],
        ['term' => $equipmentNotes, 'ok' => true],
        ['term' => '9876.54', 'ok' => true],
        ['term' => 'Switch', 'ok' => $equipmentTypeId > 0],
        ['term' => 'Cisco', 'ok' => $manufacturerId > 0],
        ['term' => $equipmentStatusName, 'ok' => $equipmentStatusName !== ''],
    ];
    if ($fnbDeptId > 0) {
        $equipmentProbes[] = ['term' => 'FNB', 'ok' => true];
    }
    if ($supplierId > 0) {
        $equipmentProbes[] = ['term' => 'SUP-001', 'ok' => true];
    } else {
        echo colorText('[FAIL] suppliers seed missing SUP-001 for company ' . $companyId . '.', 'fail') . $nl;
        $failed = true;
    }
    if ($locationId > 0) {
        $equipmentProbes[] = ['term' => 'LOC-NY-01', 'ok' => true];
    }
    if ($rackId > 0) {
        $equipmentProbes[] = ['term' => 'RACK-A', 'ok' => true];
    } else {
        echo colorText('[FAIL] racks seed missing RACK-A for company ' . $companyId . '.', 'fail') . $nl;
        $failed = true;
    }
    $equipmentProbes[] = ['term' => $probeFirstName, 'ok' => true];
    $equipmentProbes[] = ['term' => $probeLastName, 'ok' => true];
    if ($employeeUsername !== '') {
        $equipmentProbes[] = ['term' => $employeeUsername, 'ok' => true];
    }
    $equipmentProbes[] = ['term' => $probeFullName, 'ok' => true];

    foreach ($equipmentProbes as $probe) {
        if (empty($probe['ok']) || (string)$probe['term'] === '') {
            echo colorText('[FAIL] equipment seed missing lookup for search=' . ($probe['term'] ?? ''), 'fail') . $nl;
            $failed = true;
            continue;
        }
        veesc_assert_list_probe(
            'equipment',
            (string)$probe['term'],
            veesc_run_isolated($equipmentIndex, $session, ['search' => (string)$probe['term'], 'sort' => 'id', 'dir' => 'DESC']),
            $equipmentNeedles,
            $failed
        );
    }

    mysqli_query($conn, 'DELETE FROM equipment WHERE id = ' . (int)$equipmentProbeId . ' AND company_id = ' . (int)$companyId);
} else {
    echo colorText('[FAIL] Unable to seed disposable equipment row for search probes.', 'fail') . $nl;
    $failed = true;
}

itm_script_output_end();
exit($failed ? 1 : 0);
