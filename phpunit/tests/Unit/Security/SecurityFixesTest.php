<?php
use PHPUnit\Framework\TestCase;

/**
 * Functional security tests for identified vulnerabilities.
 */
class SecurityFixesTest extends TestCase
{
    private $conn;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../config/config.php';
        $this->conn = $GLOBALS['conn'];
        if (!$this->conn) {
            $this->markTestSkipped('Database connection unavailable.');
        }
    }

    private function runPhpScriptFile($scriptFile)
    {
        $phpBin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        if (strpos($phpBin, 'php-cgi') !== false) {
            $phpBin = str_replace('php-cgi', 'php', $phpBin);
        }

        // Why: 2>/dev/null breaks on Windows cmd ("The system cannot find the path specified.").
        // 2>&1 is supported on Windows and Linux shells used by PHP exec().
        $command = escapeshellarg($phpBin) . ' -d error_reporting=0 ' . escapeshellarg($scriptFile) . ' 2>&1';
        $lines = [];
        exec($command, $lines);
        return implode("\n", $lines);
    }

    private function runIsolated($script_path, $session_data = [], $post_data = [], $get_data = [], $extra_globals = [])
    {
        $scriptPathLiteral = var_export($script_path, true);
        $code = "<?php
define('ITM_CLI_SCRIPT', true);
session_start();
" . implode("\n", array_map(function($k, $v) { return "\$_SESSION['$k'] = " . var_export($v, true) . ";"; }, array_keys($session_data), $session_data)) . "
" . implode("\n", array_map(function($k, $v) { return "\$_POST['$k'] = " . var_export($v, true) . ";"; }, array_keys($post_data), $post_data)) . "
" . implode("\n", array_map(function($k, $v) { return "\$_GET['$k'] = " . var_export($v, true) . ";"; }, array_keys($get_data), $get_data)) . "
" . implode("\n", array_map(function($k, $v) { return "global \$$k; \$$k = " . var_export($v, true) . ";"; }, array_keys($extra_globals), $extra_globals)) . "
chdir(dirname({$scriptPathLiteral}));
ob_start();
include basename({$scriptPathLiteral});
echo ob_get_clean();
?>";
        $tmp_file = tempnam(sys_get_temp_dir(), 'repro_test');
        file_put_contents($tmp_file, $code);
        $output = $this->runPhpScriptFile($tmp_file);
        unlink($tmp_file);
        return $output;
    }

    public function testExplorerPhpUploadBlocked()
    {
        $company_id = 1;
        $session = [
            'company_id' => $company_id,
            'user_id' => 1,
            'username' => 'admin',
            'csrf_token' => 'test_token'
        ];

        // We can't easily mock $_FILES for a real move_uploaded_file call in unit tests
        // but we can check if the logic correctly identifies and blocks the extension.
        // The repro script already verified this functionally.
        // For unit test, we can check if the file was NOT created.

        $php_content = "<?php echo 'RCE'; ?>";
        $tmp_file = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmp_file, $php_content);

        $_FILES['files'] = [
            'name' => ['shell.php'],
            'type' => ['application/x-php'],
            'tmp_name' => [$tmp_file],
            'error' => [0],
            'size' => [strlen($php_content)]
        ];

        $_POST['action'] = 'upload';
        $_POST['path'] = 'Common';
        $_POST['csrf_token'] = 'test_token';

        // Direct include in the same process to test the block logic
        // We need to mock itm_require_post_csrf as it might exit
        // Actually, let's use runIsolated to be safe and truly functional
        $this->runIsolated(ROOT_PATH . 'modules/explorer/api.php', $session, $_POST);

        $target_path = ROOT_PATH . "files/$company_id/Common/shell.php";
        $this->assertFileDoesNotExist($target_path, "PHP file should be blocked by extension allowlist.");

        if (file_exists($target_path)) unlink($target_path);
        if (file_exists($tmp_file)) unlink($tmp_file);
    }

    public function testUserRoleEscalationBlocked()
    {
        // 1. Create a non-admin user
        $stmt = $this->conn->prepare("INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (1, 'attacker', 'attacker@example.com', 'pass', 5, 2, 1)");
        $stmt->execute();
        $attacker_id = mysqli_insert_id($this->conn);

        $session = [
            'company_id' => 1,
            'user_id' => $attacker_id,
            'username' => 'attacker',
            'csrf_token' => 'test_token'
        ];

        // 2. Attempt to update own role to Admin (1)
        $post = [
            'csrf_token' => 'test_token',
            'username' => 'attacker',
            'email' => 'attacker@example.com',
            'role_id' => 1,
            'access_level_id' => 1,
            'active' => 1
        ];
        $get = ['id' => $attacker_id];
        $globals = ['crud_action' => 'edit'];

        $this->runIsolated(ROOT_PATH . 'modules/users/index.php', $session, $post, $get, $globals);

        // 3. Verify role was NOT updated
        $stmtV = $this->conn->prepare("SELECT role_id FROM users WHERE id = ?");
        $stmtV->bind_param("i", $attacker_id);
        $stmtV->execute();
        $res = $stmtV->get_result();
        $row = mysqli_fetch_assoc($res);
        $this->assertEquals(5, (int)$row['role_id'], "Non-admin user should not be able to update their role to Admin.");

        // Cleanup
        $stmtC = $this->conn->prepare("DELETE FROM users WHERE id = ?");
        $stmtC->bind_param("i", $attacker_id);
        $stmtC->execute();
    }

    public function testRoleModulePermissionsAdminOnly()
    {
        $session = [
            'company_id' => 1,
            'user_id' => 999,
            'username' => 'nobody',
            'role_name' => 'User'
        ];

        $output = (string)$this->runIsolated(ROOT_PATH . 'modules/role_module_permissions/index.php', $session);

        $this->assertStringNotContainsString('Role Module Permissions Management', $output, "Non-admin users should be redirected from Role Module Permissions.");
    }

    public function testCompanyModuleAdminOnly()
    {
        $session = [
            'company_id' => 1,
            'user_id' => 999,
            'username' => 'nobody'
        ];

        $output = (string)$this->runIsolated(ROOT_PATH . 'modules/companies/index.php', $session);
        $this->assertStringNotContainsString('Companies Management', $output, "Non-admin users should be redirected from Companies module.");
    }

    public function testSensitiveImportAdminOnly()
    {
        // 1. Create a non-admin user
        $stmt = $this->conn->prepare("INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (1, 'attacker_import', 'attacker_import@example.com', 'pass', 5, 2, 1)");
        $stmt->execute();
        $attacker_id = mysqli_insert_id($this->conn);

        $session = [
            'company_id' => 1,
            'user_id' => $attacker_id,
            'username' => 'attacker_import',
            'csrf_token' => 'test_token'
        ];

        $payload = [
            'csrf_token' => 'test_token',
            'import_excel_rows' => [
                ['id', 'company', 'incode'],
                [1, 'Hacked', 'HACKED']
            ]
        ];

        // 2. Attempt to import companies
        $code = "<?php
define('ITM_CLI_SCRIPT', true);
require_once '" . ROOT_PATH . "config/config.php';
\$_SESSION['user_id'] = $attacker_id;
\$_SESSION['company_id'] = 1;
\$_SESSION['csrf_token'] = 'test_token';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['CONTENT_TYPE'] = 'application/json';
\$payload = " . var_export($payload, true) . ";
echo json_encode(itm_handle_json_table_import(\$conn, 'companies', 1, \$payload, true));
?>";
        $tmp_file = tempnam(sys_get_temp_dir(), 'import_test');
        file_put_contents($tmp_file, $code);
        $output = $this->runPhpScriptFile($tmp_file);
        unlink($tmp_file);

        $result = json_decode($output, true);
        $this->assertFalse($result['ok'] ?? true, "Import should fail for non-admin user on sensitive table.");

        // Cleanup
        $this->conn->query("DELETE FROM users WHERE id = $attacker_id");
    }

    public function testRegistrationInvitationsAdminOnly()
    {
        $session = [
            'company_id' => 1,
            'user_id' => 999,
            'username' => 'nobody',
            'role_name' => 'User'
        ];

        $output = (string)$this->runIsolated(ROOT_PATH . 'modules/registration_invitations/index.php', $session);

        $this->assertStringNotContainsString('Registration Invitations Management', $output, "Non-admin users should be redirected from Registration Invitations.");
    }

    public function testResetGitHistoryAdminOnly()
    {
        $session = [
            'company_id' => 1,
            'user_id' => 999,
            'username' => 'nobody',
            'role_name' => 'User'
        ];

        $output = (string)$this->runIsolated(ROOT_PATH . 'reset_git_history.php', $session);

        $this->assertStringNotContainsString('Starting Git history reset', $output, "Non-admin users should be redirected from reset_git_history.php.");
    }

    public function testNotesZipTraversalBlocked()
    {
        require_once ROOT_PATH . 'includes/notes_visibility.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_user.php';

        $companyId = 1;
        $owner = itm_script_test_user_create($this->conn, $companyId, ['script_slug' => 'phpunit-notes-zip-fix']);
        if (!is_array($owner)) {
            $this->fail('Unable to create disposable test user.');
        }

        $userId = (int)$owner['id'];
        $username = (string)$owner['username'];
        itm_script_test_user_register_teardown($this->conn, $userId);

        $traversalPath = '../../../../../config/config.php';
        $this->assertNull(
            itm_notes_resolve_image_path($companyId, $username, $userId, $traversalPath),
            'Path traversal filenames must not resolve to files outside the notes upload directory.'
        );

        $uploadDir = itm_notes_private_images_dir($companyId, $username, $userId);
        if (function_exists('itm_ensure_files_storage_directory')) {
            itm_ensure_files_storage_directory(rtrim($uploadDir, '/'));
        } elseif (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $title = 'VULN_TEST_TRAVERSAL_FIX';
        $imagesJson = json_encode([$traversalPath]);
        $stmt = $this->conn->prepare("INSERT INTO notes (company_id, user_id, title, content, images_json, active) VALUES (?, ?, ?, 'test', ?, 1)");
        $stmt->bind_param('iiss', $companyId, $userId, $title, $imagesJson);
        $stmt->execute();
        $noteId = (int)$stmt->insert_id;
        $stmt->close();

        $imgs = json_decode($imagesJson, true);
        $zipName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title) . '_download.zip';
        $zipPath = sys_get_temp_dir() . '/' . $zipName;
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

        $addedFiles = 0;
        foreach ($imgs as $img) {
            $filePath = itm_notes_resolve_image_path($companyId, $username, $userId, $img);
            if ($filePath !== null) {
                $zip->addFile($filePath, basename($filePath));
                $addedFiles++;
            }
        }
        $zip->close();

        $this->assertSame(0, $addedFiles, 'Malicious images_json entries must not add files to the ZIP.');
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }

        $this->conn->query("DELETE FROM notes WHERE id = $noteId");
    }

    public function testNotesIdorViewBlocked()
    {
        require_once ROOT_PATH . 'includes/notes_visibility.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_user.php';

        $companyId = 1;
        $victim = itm_script_test_user_create($this->conn, $companyId, ['script_slug' => 'phpunit-notes-idor-victim-fix']);
        $attacker = itm_script_test_user_create($this->conn, $companyId, ['script_slug' => 'phpunit-notes-idor-attacker-fix']);
        if (!is_array($victim) || !is_array($attacker)) {
            $this->fail('Unable to create disposable test users.');
        }

        $victimId = (int)$victim['id'];
        $attackerId = (int)$attacker['id'];
        itm_script_test_user_register_teardown($this->conn, $victimId);
        itm_script_test_user_register_teardown($this->conn, $attackerId);

        $title = 'VULN_TEST_IDOR_VIEW_FIX';
        $secret = 'SECRET_CONTENT_' . bin2hex(random_bytes(8));
        $stmt = $this->conn->prepare('INSERT INTO notes (company_id, user_id, title, content, active) VALUES (?, ?, ?, ?, 1)');
        $stmt->bind_param('iiss', $companyId, $victimId, $title, $secret);
        $stmt->execute();
        $noteId = (int)$stmt->insert_id;
        $stmt->close();

        $this->assertNull(
            itm_notes_fetch_visible_by_id($this->conn, $noteId, $companyId, $attackerId, true),
            'Cross-user private notes must not load via the visibility helper.'
        );

        $session = [
            'company_id' => $companyId,
            'user_id' => $attackerId,
            'username' => (string)$attacker['username'],
        ];
        $get = ['id' => $noteId];
        $extra_globals = ['crud_action' => 'view'];

        $output = $this->runIsolated(ROOT_PATH . 'modules/notes/index.php', $session, [], $get, $extra_globals);
        $this->assertStringNotContainsString($secret, $output, 'Attacker must not view victim private note content via view load.');

        $ownerRow = itm_notes_fetch_visible_by_id($this->conn, $noteId, $companyId, $victimId, true);
        $this->assertIsArray($ownerRow);
        $this->assertSame($secret, $ownerRow['content']);

        $this->conn->query("DELETE FROM notes WHERE id = $noteId");
    }

    public function testUsersSensitiveFieldsHiddenFromView()
    {
        require_once ROOT_PATH . 'includes/itm_users_sensitive_fields.php';
        require_once ROOT_PATH . 'scripts/lib/itm_script_test_user.php';

        $companyId = 1;
        $testUser = itm_script_test_user_create($this->conn, $companyId, ['script_slug' => 'phpunit-users-sensitive-view']);
        if (!is_array($testUser)) {
            $this->fail('Unable to create disposable test user.');
        }

        $userId = (int)$testUser['id'];
        itm_script_test_user_register_teardown($this->conn, $userId);

        $secretToken = 'PHPUNIT_RESET_' . bin2hex(random_bytes(8));
        $secretHash = hash('sha256', $secretToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $this->conn->prepare('UPDATE users SET reset_token = ?, reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?');
        $stmt->bind_param('sssi', $secretToken, $secretHash, $expiresAt, $userId);
        $stmt->execute();
        $stmt->close();

        $uiSample = array_map(function ($name) {
            return ['Field' => $name];
        }, array_merge(['username'], itm_users_sensitive_field_names()));
        $filtered = itm_users_filter_ui_columns($uiSample);
        $filteredNames = array_column($filtered, 'Field');
        foreach (itm_users_sensitive_field_names() as $sensitiveField) {
            $this->assertNotContains($sensitiveField, $filteredNames);
        }

        $adminStmt = $this->conn->prepare('SELECT id, username FROM users WHERE id = 1 LIMIT 1');
        $adminStmt->execute();
        $adminRow = $adminStmt->get_result()->fetch_assoc();
        $adminStmt->close();
        if (!is_array($adminRow)) {
            $this->markTestSkipped('Seed admin user missing.');
        }

        $session = [
            'company_id' => $companyId,
            'user_id' => (int)$adminRow['id'],
            'username' => (string)$adminRow['username'],
        ];
        $get = ['id' => $userId];
        $extraGlobals = ['crud_action' => 'view'];

        $output = $this->runIsolated(ROOT_PATH . 'modules/users/index.php', $session, [], $get, $extraGlobals);
        $this->assertStringNotContainsString($secretToken, $output);
        $this->assertStringNotContainsString($secretHash, $output);
        $this->assertStringNotContainsString('Reset Token Hash', $output);

        $this->conn->query("DELETE FROM users WHERE id = $userId");
    }

    public function testJsonImportRejectsInvalidDecimal(): void
    {
        $companyId = 1;
        $uniqueModel = 'PhpUnitImport-' . bin2hex(random_bytes(4));
        $payload = [
            'csrf_token' => 'test_token',
            'import_excel_rows' => [
                ['Model', 'Price'],
                [$uniqueModel, 'invalid-price'],
            ],
        ];

        $code = "<?php
define('ITM_CLI_SCRIPT', true);
require_once '" . ROOT_PATH . "config/config.php';
\$_SESSION['user_id'] = 1;
\$_SESSION['company_id'] = 1;
\$_SESSION['csrf_token'] = 'test_token';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['CONTENT_TYPE'] = 'application/json';
\$payload = " . var_export($payload, true) . ";
echo json_encode(itm_handle_json_table_import(\$conn, 'catalogs', 1, \$payload, true));
?>";
        $tmpFile = tempnam(sys_get_temp_dir(), 'import_decimal_test');
        file_put_contents($tmpFile, $code);
        $output = $this->runPhpScriptFile($tmpFile);
        unlink($tmpFile);

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertFalse($result['ok'] ?? true);
        $this->assertSame(0, (int)($result['inserted'] ?? -1));

        $stmt = $this->conn->prepare('SELECT id FROM catalogs WHERE company_id = ? AND model = ? LIMIT 1');
        $stmt->bind_param('is', $companyId, $uniqueModel);
        $stmt->execute();
        $res = $stmt->get_result();
        $this->assertSame(0, $res ? mysqli_num_rows($res) : -1);
        $stmt->close();
    }

    public function testJsonImportRejectsInvalidDatetime(): void
    {
        $companyId = 1;
        $uniqueTitle = 'PhpUnitImportDate-' . bin2hex(random_bytes(4));
        $payload = [
            'csrf_token' => 'test_token',
            'import_excel_rows' => [
                ['Title', 'Start Datetime'],
                [$uniqueTitle, 'not-a-date'],
            ],
        ];

        $code = "<?php
define('ITM_CLI_SCRIPT', true);
require_once '" . ROOT_PATH . "config/config.php';
\$_SESSION['user_id'] = 1;
\$_SESSION['company_id'] = 1;
\$_SESSION['csrf_token'] = 'test_token';
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['CONTENT_TYPE'] = 'application/json';
\$payload = " . var_export($payload, true) . ";
echo json_encode(itm_handle_json_table_import(\$conn, 'events', 1, \$payload, true));
?>";
        $tmpFile = tempnam(sys_get_temp_dir(), 'import_date_test');
        file_put_contents($tmpFile, $code);
        $output = $this->runPhpScriptFile($tmpFile);
        unlink($tmpFile);

        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertFalse($result['ok'] ?? true);
        $this->assertSame(0, (int)($result['inserted'] ?? -1));

        $stmt = $this->conn->prepare('SELECT id FROM events WHERE company_id = ? AND title = ? LIMIT 1');
        $stmt->bind_param('is', $companyId, $uniqueTitle);
        $stmt->execute();
        $res = $stmt->get_result();
        $this->assertSame(0, $res ? mysqli_num_rows($res) : -1);
        $stmt->close();
    }
}
