<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();


$isCli = (php_sapi_name() === 'cli');
$nl = itm_script_output_nl();
$failures = 0;

if (!$isCli) {
    itm_script_browser_nav_echo();
    echo '<h1>Verify Company Module Access</h1>';
}

if (!$conn instanceof mysqli) {
    echo '[FAIL] Database connection is required.' . $nl;
    exit(1);
}

if (!itm_module_access_table_exists($conn, 'modules_registry') || !itm_module_access_table_exists($conn, 'company_module_access')) {
    echo '[FAIL] Required tables modules_registry and company_module_access are missing.' . $nl;
    exit(1);
}

itm_sync_modules_registry_from_filesystem($conn);
$registryCount = count(itm_list_all_modules_registry($conn));
$discovered = count(itm_discover_module_slugs_for_registry());

if ($registryCount < $discovered) {
    echo '[FAIL] Registry row count (' . $registryCount . ') is lower than discovered modules (' . $discovered . ').' . $nl;
    $failures++;
} else {
    echo '[PASS] Registry contains ' . $registryCount . ' module rows (discovered ' . $discovered . ').' . $nl;
}

if (!has_module_access($conn, 1, 'settings')) {
    echo '[FAIL] settings should remain accessible for company 1.' . $nl;
    $failures++;
} else {
    echo '[PASS] settings access allowed for company 1.' . $nl;
}

$expectedAccessRows = $registryCount * 5;
$accessCountRes = mysqli_query($conn, 'SELECT COUNT(*) AS count FROM company_module_access');
$accessCountRow = $accessCountRes ? mysqli_fetch_assoc($accessCountRes) : null;
$accessCount = (int)($accessCountRow['count'] ?? 0);
if ($accessCount < $expectedAccessRows) {
    echo '[FAIL] company_module_access row count (' . $accessCount . ') is lower than expected company x module seeds (' . $expectedAccessRows . ').' . $nl;
    $failures++;
} else {
    echo '[PASS] company_module_access contains ' . $accessCount . ' seeded rows (expected at least ' . $expectedAccessRows . ').' . $nl;
}

$suppliersId = 0;
$stmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? LIMIT 1');
if ($stmt) {
    $slug = 'suppliers';
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $suppliersId = (int)($row['id'] ?? 0);
    mysqli_stmt_close($stmt);
}

if ($suppliersId > 0) {
    itm_set_company_module_access($conn, 1, $suppliersId, 0);
    if (has_module_access($conn, 1, 'suppliers', true)) {
        echo '[FAIL] suppliers should be denied after explicit enabled=0 for company 1.' . $nl;
        $failures++;
    } else {
        echo '[PASS] suppliers denied after explicit enabled=0 for company 1.' . $nl;
    }
    $stmtDelete = mysqli_prepare($conn, 'DELETE FROM company_module_access WHERE company_id = 1 AND module_id = ? LIMIT 1');
    if ($stmtDelete) {
        mysqli_stmt_bind_param($stmtDelete, 'i', $suppliersId);
        mysqli_stmt_execute($stmtDelete);
        mysqli_stmt_close($stmtDelete);
    }
    if (has_module_access($conn, 1, 'suppliers', true)) {
        echo '[FAIL] suppliers should be denied when company_module_access row is missing (strict opt-in).' . $nl;
        $failures++;
    } else {
        echo '[PASS] suppliers denied when company_module_access row is missing.' . $nl;
    }
    itm_set_company_module_access($conn, 1, $suppliersId, 1);
    has_module_access($conn, 1, 'suppliers', true); // restore cache
} else {
    echo '[FAIL] suppliers registry row not found.' . $nl;
    $failures++;
}

$allRows = itm_list_all_modules_registry($conn);
$hasExcluded = false;
foreach (['password_entries', 'floor_plan_tags'] as $excludedSlug) {
    foreach ($allRows as $registryRow) {
        if ((string)($registryRow['module_slug'] ?? '') === $excludedSlug) {
            $hasExcluded = true;
            break 2;
        }
    }
}
if (!$hasExcluded) {
    echo '[FAIL] Registry is missing sidebar-excluded module slugs used by the admin matrix.' . $nl;
    $failures++;
} else {
    echo '[PASS] Registry includes sidebar-excluded slugs for admin matrix visibility.' . $nl;
}

$ticketsSlug = 'tickets';
$customCompanyIcon = '🎫';
$resolvedBefore = itm_resolve_module_sidebar_icon($conn, 1, 1, $ticketsSlug);
$stmtTickets = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? LIMIT 1');
$ticketsModuleId = 0;
if ($stmtTickets) {
    mysqli_stmt_bind_param($stmtTickets, 's', $ticketsSlug);
    mysqli_stmt_execute($stmtTickets);
    $ticketsRes = mysqli_stmt_get_result($stmtTickets);
    $ticketsRow = $ticketsRes ? mysqli_fetch_assoc($ticketsRes) : null;
    $ticketsModuleId = (int)($ticketsRow['id'] ?? 0);
    mysqli_stmt_close($stmtTickets);
}
if ($ticketsModuleId > 0 && itm_set_company_module_icon($conn, 1, $ticketsModuleId, $customCompanyIcon)) {
    $resolvedCompany = itm_resolve_module_sidebar_icon($conn, 1, 0, $ticketsSlug);
    if ($resolvedCompany !== $customCompanyIcon) {
        echo '[FAIL] Company module icon override was not resolved for tickets.' . $nl;
        $failures++;
    } else {
        echo '[PASS] Company module icon override resolves for tickets.' . $nl;
    }
    itm_set_company_module_icon($conn, 1, $ticketsModuleId, '');
} else {
    echo '[FAIL] Could not set company module icon for tickets verification.' . $nl;
    $failures++;
}

$probeSlug = 'mbqa_sidebar_discovery_probe';
itm_sidebar_discovery_probe_cleanup($conn, $probeSlug);

// Registry-only: active modules_registry row without modules/{slug}/index.php.
$probeModuleId = 0;
$probeName = 'MBQA Sidebar Probe';
$stmtProbeInsert = mysqli_prepare(
    $conn,
    'INSERT INTO modules_registry (module_slug, module_name, icon, is_system_module, active) VALUES (?, ?, ?, 0, 1)'
);
if ($stmtProbeInsert) {
    $probeIcon = '';
    mysqli_stmt_bind_param($stmtProbeInsert, 'sss', $probeSlug, $probeName, $probeIcon);
    if (mysqli_stmt_execute($stmtProbeInsert)) {
        $probeModuleId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmtProbeInsert);
}
if ($probeModuleId > 0) {
    itm_seed_company_module_access_for_module($conn, $probeModuleId);
    $probeIndex = ROOT_PATH . 'modules/' . $probeSlug . '/index.php';
    if (is_file($probeIndex)) {
        echo '[FAIL] Registry-only probe should not require modules/' . $probeSlug . '/index.php.' . $nl;
        $failures++;
    } elseif (!itm_sidebar_structure_contains_slug($conn, $probeSlug, true)) {
        echo '[FAIL] Registry-only probe missing from sidebar structure.' . $nl;
        $failures++;
    } elseif (!has_module_access($conn, 1, $probeSlug, true)) {
        echo '[FAIL] Registry-only probe denied by has_module_access after CMA seed.' . $nl;
        $failures++;
    } else {
        echo '[PASS] Registry-only probe appears in sidebar structure without module folder.' . $nl;
    }
} else {
    echo '[FAIL] Could not insert registry-only sidebar probe row.' . $nl;
    $failures++;
}
itm_sidebar_discovery_probe_cleanup($conn, $probeSlug);

// MySQL table only: SHOW TABLES discovery auto-scaffolds module folder and registry row.
$probeTableSql = 'CREATE TABLE `' . $probeSlug . '` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL,
    `active` TINYINT DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
if (!mysqli_query($conn, $probeTableSql)) {
    echo '[FAIL] Could not create sidebar probe table.' . $nl;
    $failures++;
} else {
    itm_sidebar_structure($conn, true);
    $probeIndex = ROOT_PATH . 'modules/' . $probeSlug . '/index.php';
    if (!is_file($probeIndex)) {
        echo '[FAIL] Table-only probe did not auto-scaffold modules/' . $probeSlug . '/index.php.' . $nl;
        $failures++;
    } elseif (!itm_sidebar_structure_contains_slug($conn, $probeSlug, true)) {
        echo '[FAIL] Table-only probe missing from sidebar structure.' . $nl;
        $failures++;
    } elseif (!has_module_access($conn, 1, $probeSlug, true)) {
        echo '[FAIL] Table-only probe denied by has_module_access after auto registry ensure.' . $nl;
        $failures++;
    } else {
        echo '[PASS] New MySQL table probe auto-scaffolds module folder and sidebar access.' . $nl;
    }
}
itm_sidebar_discovery_probe_cleanup($conn, $probeSlug);

// Folder only: modules/{slug}/index.php without a pre-existing registry row.
$probeModuleDir = ROOT_PATH . 'modules/' . $probeSlug;
if (!is_dir($probeModuleDir) && !mkdir($probeModuleDir, 0775, true) && !is_dir($probeModuleDir)) {
    echo '[FAIL] Could not create folder-only sidebar probe module directory.' . $nl;
    $failures++;
} else {
    $probeStub = "<?php\n"
        . '$crud_table = ' . var_export($probeSlug, true) . ";\n"
        . '$crud_title = ' . var_export('MBQA Sidebar Probe', true) . ";\n"
        . '$crud_action = ' . var_export('index', true) . ";\n"
        . "require __DIR__ . '/../manufacturers/index.php';\n";
    if (file_put_contents($probeModuleDir . '/index.php', $probeStub) === false) {
        echo '[FAIL] Could not write folder-only sidebar probe index.php.' . $nl;
        $failures++;
    } else {
        itm_sidebar_structure($conn, true);
        if (!itm_sidebar_structure_contains_slug($conn, $probeSlug, true)) {
            echo '[FAIL] Folder-only probe missing from sidebar structure.' . $nl;
            $failures++;
        } elseif (!has_module_access($conn, 1, $probeSlug, true)) {
            echo '[FAIL] Folder-only probe denied by has_module_access after auto registry ensure.' . $nl;
            $failures++;
        } else {
            echo '[PASS] Folder-only probe appears in sidebar after auto registry ensure.' . $nl;
        }
    }
}
itm_sidebar_discovery_probe_cleanup($conn, $probeSlug);

// Both registry row and modules/{slug}/index.php: single sidebar entry with access.
$probeModuleId = 0;
$probeName = 'MBQA Sidebar Probe';
$stmtBothRegistry = mysqli_prepare(
    $conn,
    'INSERT INTO modules_registry (module_slug, module_name, icon, is_system_module, active) VALUES (?, ?, ?, 0, 1)'
);
if ($stmtBothRegistry) {
    $probeIcon = '';
    mysqli_stmt_bind_param($stmtBothRegistry, 'sss', $probeSlug, $probeName, $probeIcon);
    if (mysqli_stmt_execute($stmtBothRegistry)) {
        $probeModuleId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmtBothRegistry);
}
$probeModuleDir = ROOT_PATH . 'modules/' . $probeSlug;
if ($probeModuleId <= 0) {
    echo '[FAIL] Could not insert registry row for both-path sidebar probe.' . $nl;
    $failures++;
} elseif (!is_dir($probeModuleDir) && !mkdir($probeModuleDir, 0775, true) && !is_dir($probeModuleDir)) {
    echo '[FAIL] Could not create module folder for both-path sidebar probe.' . $nl;
    $failures++;
} else {
    $probeStub = "<?php\n"
        . '$crud_table = ' . var_export($probeSlug, true) . ";\n"
        . '$crud_title = ' . var_export('MBQA Sidebar Probe', true) . ";\n"
        . '$crud_action = ' . var_export('index', true) . ";\n"
        . "require __DIR__ . '/../manufacturers/index.php';\n";
    if (file_put_contents($probeModuleDir . '/index.php', $probeStub) === false) {
        echo '[FAIL] Could not write index.php for both-path sidebar probe.' . $nl;
        $failures++;
    } else {
        itm_seed_company_module_access_for_module($conn, $probeModuleId);
        itm_sidebar_structure($conn, true);
        $probeCount = itm_sidebar_structure_slug_count($conn, $probeSlug, true);
        if ($probeCount !== 1) {
            echo '[FAIL] Both-path probe should appear exactly once in sidebar (found ' . $probeCount . ').' . $nl;
            $failures++;
        } elseif (!has_module_access($conn, 1, $probeSlug, true)) {
            echo '[FAIL] Both-path probe denied by has_module_access.' . $nl;
            $failures++;
        } else {
            echo '[PASS] Registry + folder probe appears once in sidebar with access.' . $nl;
        }
    }
}
itm_sidebar_discovery_probe_cleanup($conn, $probeSlug);

// Neither registry nor folder/table: absent from sidebar and denied.
itm_sidebar_discovery_probe_cleanup($conn, $probeSlug);
if (itm_sidebar_structure_contains_slug($conn, $probeSlug, true)) {
    echo '[FAIL] Neither-path probe should be absent from sidebar structure.' . $nl;
    $failures++;
} elseif (has_module_access($conn, 1, $probeSlug, true)) {
    echo '[FAIL] Neither-path probe should be denied by has_module_access.' . $nl;
    $failures++;
} else {
    echo '[PASS] Neither-path probe absent from sidebar and denied.' . $nl;
}

if ($failures > 0) {
    echo '[FAIL] Verification finished with ' . $failures . ' failure(s).' . $nl;
    exit(1);
}

echo '[PASS] Company module access verification succeeded.' . $nl;
exit(0);

itm_script_output_end();
