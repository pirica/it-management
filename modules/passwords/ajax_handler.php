<?php
require_once '../../config/config.php';

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    return;
}

if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token']);
    return;
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    echo json_encode(['ok' => false, 'message' => 'Connection failed: ' . mysqli_connect_error()]);
    return;
}

$user_id = (int)$_SESSION['employee_id'];
$action = (string)($_POST['action'] ?? '');

switch ($action) {
    case 'list_folders':
        $stmt = mysqli_prepare($conn, "SELECT id, name, parent_id FROM password_folders WHERE employee_id = ? ORDER BY name ASC");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        echo json_encode(mysqli_fetch_all($res, MYSQLI_ASSOC));
        mysqli_stmt_close($stmt);
        break;

    case 'save_folder':
        $id = (int)($_POST['id'] ?? 0);
        $name = (string)($_POST['name'] ?? '');
        $parent_id = (!empty($_POST['parent_id']) && $_POST['parent_id'] != '0') ? (int)$_POST['parent_id'] : null;
        
        if ($id) {
            $stmt = mysqli_prepare($conn, "UPDATE password_folders SET name = ?, parent_id = ? WHERE id = ? AND employee_id = ?");
            mysqli_stmt_bind_param($stmt, 'siii', $name, $parent_id, $id, $user_id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO password_folders (user_id, name, parent_id) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'isi', $user_id, $name, $parent_id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'message' => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'delete_folder':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM password_folders WHERE id = ? AND employee_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'message' => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'list_entries':
        if (empty($_SESSION['vault_key'])) {
            echo json_encode([]);
            break;
        }
        $folder_id = (int)($_POST['folder_id'] ?? 0);
        $search = (string)($_POST['search'] ?? '');
        
        $sql = "SELECT * FROM password_entries WHERE employee_id = ?";
        if ($folder_id > 0) {
            $sql .= " AND folder_name = ?";
        }
        if ($search !== '') {
            $sql .= " AND (account LIKE ? OR login_name LIKE ? OR website LIKE ? OR comments LIKE ?)";
        }
        $sql .= " ORDER BY account ASC";
        
        $stmt = mysqli_prepare($conn, $sql);
        if ($folder_id > 0 && $search !== '') {
            $search_param = "%$search%";
            mysqli_stmt_bind_param($stmt, 'iissss', $user_id, $folder_id, $search_param, $search_param, $search_param, $search_param);
        } elseif ($folder_id > 0) {
            mysqli_stmt_bind_param($stmt, 'ii', $user_id, $folder_id);
        } elseif ($search !== '') {
            $search_param = "%$search%";
            mysqli_stmt_bind_param($stmt, 'issss', $user_id, $search_param, $search_param, $search_param, $search_param);
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
        }

        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $entries = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $row['password'] = itm_decrypt($row['password'], $_SESSION['vault_key']);
            $entries[] = $row;
        }
        echo json_encode($entries);
        mysqli_stmt_close($stmt);
        break;

    case 'get_entry':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "SELECT * FROM password_entries WHERE id = ? AND employee_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $entry = mysqli_fetch_assoc($res);
        if ($entry && !empty($_SESSION['vault_key'])) {
            $entry['password'] = itm_decrypt($entry['password'], $_SESSION['vault_key']);
        }
        echo json_encode($entry);
        mysqli_stmt_close($stmt);
        break;

    case 'save_entry':
        if (empty($_SESSION['vault_key'])) {
            echo json_encode(['ok' => false, 'message' => 'Vault locked']);
            break;
        }
        $id = (int)($_POST['id'] ?? 0);
        $folder_id = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
        $account = (string)($_POST['account'] ?? '');
        $login_name = (string)($_POST['login_name'] ?? '');
        $password = itm_encrypt((string)($_POST['password'] ?? ''), $_SESSION['vault_key']);
        $website = (string)($_POST['website'] ?? '');
        $comments = (string)($_POST['comments'] ?? '');
        
        if ($id) {
            $stmt = mysqli_prepare($conn, "UPDATE password_entries SET folder_name = ?, account = ?, login_name = ?, password = ?, website = ?, comments = ? WHERE id = ? AND employee_id = ?");
            mysqli_stmt_bind_param($stmt, 'isssssii', $folder_id, $account, $login_name, $password, $website, $comments, $id, $user_id);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO password_entries (user_id, folder_name, account, login_name, password, website, comments) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iisssss', $user_id, $folder_id, $account, $login_name, $password, $website, $comments);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'message' => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'delete_entry':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM password_entries WHERE id = ? AND employee_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'message' => mysqli_error($conn)]);
        }
        mysqli_stmt_close($stmt);
        break;

    case 'import_rows':
        if (empty($_SESSION['vault_key'])) {
            echo json_encode(['ok' => false, 'message' => 'Vault locked']);
            break;
        }
        $folder_id = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
        $rows = json_decode($_POST['rows'] ?? '[]', true);
        if (empty($rows) || count($rows) < 2) {
            echo json_encode(['ok' => false, 'message' => 'No data']);
            break;
        }
        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        $map = array_flip($headers);

        $imported = 0; $failed = 0;
        $stmt = mysqli_prepare($conn, "INSERT INTO password_entries (user_id, folder_name, account, login_name, password, website, comments) VALUES (?, ?, ?, ?, ?, ?, ?)");

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $account = (string)($row[$map['account'] ?? -1] ?? '');
            if (empty($account)) continue;

            $login_name = (string)($row[$map['login name'] ?? $map['login'] ?? -1] ?? '');
            $password = (string)($row[$map['password'] ?? -1] ?? '');
            $website = (string)($row[$map['website'] ?? $map['url'] ?? -1] ?? '');
            $comments = (string)($row[$map['comments'] ?? $map['notes'] ?? -1] ?? '');

            $encrypted_pwd = itm_encrypt($password, $_SESSION['vault_key']);
            mysqli_stmt_bind_param($stmt, 'iisssss', $user_id, $folder_id, $account, $login_name, $encrypted_pwd, $website, $comments);
            if (mysqli_stmt_execute($stmt)) $imported++; else $failed++;
        }
        mysqli_stmt_close($stmt);
        echo json_encode(['ok' => true, 'imported' => $imported, 'failed' => $failed]);
        break;

    case 'import_csv':
        if (empty($_SESSION['vault_key'])) {
            echo json_encode(['ok' => false, 'message' => 'Vault locked']);
            break;
        }
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => false, 'message' => 'File upload error']);
            break;
        }
        
        $folder_id = !empty($_POST['target_folder_id']) ? (int)$_POST['target_folder_id'] : null;
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            echo json_encode(['ok' => false, 'message' => 'Could not open file']);
            break;
        }
        $header = fgetcsv($handle);
        
        $format = '';
        if ($header && isset($header[0], $header[1])) {
            if ($header[0] === 'name' && $header[1] === 'url') $format = 'edge';
            else if ($header[0] === 'Account' && $header[1] === 'Login Name') $format = 'keepass';
        }
        
        if (!$format) {
            echo json_encode(['ok' => false, 'message' => 'Unknown CSV format']);
            fclose($handle);
            break;
        }
        
        $total = 0; $imported = 0; $failed = 0; $skipped = 0;
        $stmt = mysqli_prepare($conn, "INSERT INTO password_entries (user_id, folder_name, account, login_name, password, website, comments) VALUES (?, ?, ?, ?, ?, ?, ?)");

        while (($row = fgetcsv($handle)) !== FALSE) {
            $total++;
            $data = [];
            if ($format === 'edge') {
                $data = [
                    'account' => $row[0] ?? '',
                    'website' => $row[1] ?? '',
                    'login_name' => $row[2] ?? '',
                    'password' => $row[3] ?? '',
                    'comments' => $row[4] ?? ''
                ];
            } else {
                $data = [
                    'account' => $row[0] ?? '',
                    'login_name' => $row[1] ?? '',
                    'password' => $row[2] ?? '',
                    'website' => $row[3] ?? '',
                    'comments' => $row[4] ?? ''
                ];
            }
            
            if (empty($data['account'])) { $skipped++; continue; }
            
            $pwd = itm_encrypt($data['password'], $_SESSION['vault_key']);
            mysqli_stmt_bind_param($stmt, 'iisssss', $user_id, $folder_id, $data['account'], $data['login_name'], $pwd, $data['website'], $data['comments']);
            if (mysqli_stmt_execute($stmt)) $imported++; else $failed++;
        }
        mysqli_stmt_close($stmt);
        fclose($handle);
        echo json_encode(['ok' => true, 'total' => $total, 'imported' => $imported, 'failed' => $failed, 'skipped' => $skipped]);
        break;

    default:
        echo json_encode(['ok' => false, 'message' => 'Invalid action']);
        break;
}
mysqli_close($conn);
