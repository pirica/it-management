<?php
require_once '../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    return;
}

if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token']);
    return;
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'list_folders':
        $res = mysqli_query($conn, "SELECT id, name, parent_id FROM password_folders WHERE user_id = $user_id ORDER BY name ASC");
        echo json_encode(mysqli_fetch_all($res, MYSQLI_ASSOC));
        break;

    case 'save_folder':
        $id = (int)($_POST['id'] ?? 0);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : 'NULL';
        
        if ($id) {
            $sql = "UPDATE password_folders SET name = '$name', parent_id = $parent_id WHERE id = $id AND user_id = $user_id";
        } else {
            $sql = "INSERT INTO password_folders (user_id, name, parent_id) VALUES ($user_id, '$name', $parent_id)";
        }
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'message' => mysqli_error($conn)]);
        }
        break;

    case 'delete_folder':
        $id = (int)$_POST['id'];
        if (mysqli_query($conn, "DELETE FROM password_folders WHERE id = $id AND user_id = $user_id")) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'message' => mysqli_error($conn)]);
        }
        break;

    case 'list_entries':
        if (empty($_SESSION['vault_key'])) {
            echo json_encode([]);
            break;
        }
        $folder_id = (int)($_POST['folder_id'] ?? 0);
        $search = mysqli_real_escape_string($conn, $_POST['search'] ?? '');
        
        $sql = "SELECT * FROM password_entries WHERE user_id = $user_id";
        if ($folder_id > 0) {
            $sql .= " AND folder_id = $folder_id";
        }
        if (!empty($search)) {
            $sql .= " AND (account LIKE '%$search%' OR login_name LIKE '%$search%' OR website LIKE '%$search%' OR comments LIKE '%$search%')";
        }
        $sql .= " ORDER BY account ASC";
        
        $res = mysqli_query($conn, $sql);
        $entries = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $row['password'] = itm_decrypt($row['password'], $_SESSION['vault_key']);
            $entries[] = $row;
        }
        echo json_encode($entries);
        break;

    case 'get_entry':
        $id = (int)$_POST['id'];
        $res = mysqli_query($conn, "SELECT * FROM password_entries WHERE id = $id AND user_id = $user_id");
        $entry = mysqli_fetch_assoc($res);
        if ($entry && !empty($_SESSION['vault_key'])) {
            $entry['password'] = itm_decrypt($entry['password'], $_SESSION['vault_key']);
        }
        echo json_encode($entry);
        break;

    case 'save_entry':
        if (empty($_SESSION['vault_key'])) {
            echo json_encode(['ok' => false, 'message' => 'Vault locked']);
            break;
        }
        $id = (int)($_POST['id'] ?? 0);
        $folder_id = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : 'NULL';
        $account = mysqli_real_escape_string($conn, $_POST['account']);
        $login_name = mysqli_real_escape_string($conn, $_POST['login_name']);
        $password = itm_encrypt($_POST['password'], $_SESSION['vault_key']);
        $password = mysqli_real_escape_string($conn, $password);
        $website = mysqli_real_escape_string($conn, $_POST['website']);
        $comments = mysqli_real_escape_string($conn, $_POST['comments']);
        
        if ($id) {
            $sql = "UPDATE password_entries SET folder_id = $folder_id, account = '$account', login_name = '$login_name', password = '$password', website = '$website', comments = '$comments' WHERE id = $id AND user_id = $user_id";
        } else {
            $sql = "INSERT INTO password_entries (user_id, folder_id, account, login_name, password, website, comments) VALUES ($user_id, $folder_id, '$account', '$login_name', '$password', '$website', '$comments')";
        }
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'message' => mysqli_error($conn)]);
        }
        break;

    case 'delete_entry':
        $id = (int)$_POST['id'];
        if (mysqli_query($conn, "DELETE FROM password_entries WHERE id = $id AND user_id = $user_id")) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'message' => mysqli_error($conn)]);
        }
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
        
        $folder_id = !empty($_POST['target_folder_id']) ? (int)$_POST['target_folder_id'] : 'NULL';
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        
        $format = '';
        if ($header[0] === 'name' && $header[1] === 'url') $format = 'edge';
        else if ($header[0] === 'Account' && $header[1] === 'Login Name') $format = 'keepass';
        
        if (!$format) {
            echo json_encode(['ok' => false, 'message' => 'Unknown CSV format']);
            fclose($handle);
            break;
        }
        
        $total = 0; $imported = 0; $failed = 0; $skipped = 0;
        while (($row = fgetcsv($handle)) !== FALSE) {
            $total++;
            $data = [];
            if ($format === 'edge') {
                $data = [
                    'account' => $row[0],
                    'website' => $row[1],
                    'login_name' => $row[2],
                    'password' => $row[3],
                    'comments' => $row[4]
                ];
            } else {
                $data = [
                    'account' => $row[0],
                    'login_name' => $row[1],
                    'password' => $row[2],
                    'website' => $row[3],
                    'comments' => $row[4]
                ];
            }
            
            if (empty($data['account'])) { $skipped++; continue; }
            
            $acc = mysqli_real_escape_string($conn, $data['account']);
            $url = mysqli_real_escape_string($conn, $data['website']);
            $log = mysqli_real_escape_string($conn, $data['login_name']);
            $pwd = itm_encrypt($data['password'], $_SESSION['vault_key']);
            $pwd = mysqli_real_escape_string($conn, $pwd);
            $com = mysqli_real_escape_string($conn, $data['comments']);
            
            $sql = "INSERT INTO password_entries (user_id, folder_id, account, login_name, password, website, comments) VALUES ($user_id, $folder_id, '$acc', '$log', '$pwd', '$url', '$com')";
            if (mysqli_query($conn, $sql)) $imported++; else $failed++;
        }
        fclose($handle);
        echo json_encode(['ok' => true, 'total' => $total, 'imported' => $imported, 'failed' => $failed, 'skipped' => $skipped]);
        break;

    default:
        echo json_encode(['ok' => false, 'message' => 'Invalid action']);
        break;
}
