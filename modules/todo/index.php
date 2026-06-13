<?php
/**
 * Todo Module - Index - manages tasks with Microsoft To-Do style UI.
 */

require_once "../../config/config.php";
require_once ROOT_PATH . "includes/todo_visibility.php";

// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, 'todo', (int)($company_id ?? 0));
    }
}

$crud_table = "todo";
$crud_title = "Todo";
$crud_action = $crud_action ?? "index";
$logged_user_id = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$company_id = isset($_SESSION["company_id"]) ? (int)$_SESSION["company_id"] : 0;

// AJAX Handlers
if (isset($_GET["ajax_action"])) {
    if (!itm_validate_csrf_token($_POST["csrf_token"] ?? "")) {
        header("Content-Type: application/json");
        echo json_encode(["ok" => false, "message" => "CSRF token mismatch"]);
        die();
    }
    header("Content-Type: application/json");
    $id = (int)($_POST["id"] ?? 0);

    if ($_GET["ajax_action"] === "toggle_completed") {
        if ($id <= 0) { echo json_encode(["ok" => false]); die(); }
        $val = (int)($_POST["completed"] ?? 0);
        $stmt = $conn->prepare("UPDATE todo SET completed = ? WHERE id = ? AND company_id = ?");
        $stmt->bind_param("iii", $val, $id, $company_id);
        echo json_encode(["ok" => $stmt->execute()]);
        die();
    }

    if ($_GET["ajax_action"] === "toggle_importance") {
        if ($id <= 0) { echo json_encode(["ok" => false]); die(); }
        $val = (int)($_POST["importance"] ?? 0);
        $stmt = $conn->prepare("UPDATE todo SET importance = ? WHERE id = ? AND company_id = ?");
        $stmt->bind_param("iii", $val, $id, $company_id);
        echo json_encode(["ok" => $stmt->execute()]);
        die();
    }

    if ($_GET["ajax_action"] === "quick_add") {
        $title = trim((string)($_POST["title"] ?? ""));
        if ($title === "") { echo json_encode(["ok" => false]); die(); }
        $due_date = !empty($_POST["due_date"]) ? $_POST["due_date"] : null;
        $reminder_at = !empty($_POST["reminder_at"]) ? $_POST["reminder_at"] : null;
        $repeat_pattern = !empty($_POST["repeat_pattern"]) ? $_POST["repeat_pattern"] : null;
        
        $category_ids = $_POST["category_id"] ?? [];
        $category_id = is_array($category_ids) ? implode(",", array_filter(array_map("intval", $category_ids))) : null;
        
        $department_ids = $_POST["department_id"] ?? [];
        $department_id = is_array($department_ids) ? implode(",", array_filter(array_map("intval", $department_ids))) : null;
        
        $assigned_to_user_ids = $_POST["assigned_to_user_id"] ?? [];
        $assigned_to_user_id = is_array($assigned_to_user_ids) ? implode(",", array_filter(array_map("intval", $assigned_to_user_ids))) : ($logged_user_id ?: null);

        $stmt = $conn->prepare("INSERT INTO todo (company_id, title, due_date, reminder_at, repeat_pattern, category_id, department_id, assigned_to_user_id, created_by_user_id, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("isssssssi", $company_id, $title, $due_date, $reminder_at, $repeat_pattern, $category_id, $department_id, $assigned_to_user_id, $logged_user_id);
        if ($stmt->execute()) { echo json_encode(["ok" => true, "id" => $conn->insert_id]); } else { echo json_encode(["ok" => false, "error" => $conn->error]); }
        die();
    }
}


// Standard CRUD processing
$editId = (int)($_GET["id"] ?? 0);
$csrfToken = itm_get_csrf_token();

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_GET["ajax_action"])) {
    if (!itm_validate_csrf_token($_POST["csrf_token"] ?? "")) {
        die("CSRF token mismatch");
    }

    $action = $_POST["bulk_action"] ?? "";
    if ($action === "delete" && !empty($_POST["ids"])) {
        $ids = array_map("intval", $_POST["ids"]);
        foreach ($ids as $id) {
            $stmt = $conn->prepare("UPDATE todo SET active = 0 WHERE id = ? AND company_id = ?");
            $stmt->bind_param("ii", $id, $company_id);
            $stmt->execute();
        }
        header("Location: index.php?msg=deleted");
        die();
    }

    if ($action === "single_delete" && $editId > 0) {
        $stmt = $conn->prepare("UPDATE todo SET active = 0 WHERE id = ? AND company_id = ?");
        $stmt->bind_param("ii", $editId, $company_id);
        $stmt->execute();
        header("Location: index.php?msg=deleted");
        die();
    }

    if (in_array($crud_action, ["create", "edit"], true)) {
        $title = $_POST["title"] ?? "";
        $description = $_POST["description"] ?? "";
        $due_date = !empty($_POST["due_date"]) ? $_POST["due_date"] : null;
        $reminder_at = !empty($_POST["reminder_at"]) ? $_POST["reminder_at"] : null;
        $repeat_pattern = !empty($_POST["repeat_pattern"]) ? $_POST["repeat_pattern"] : null;
        
        $category_ids = $_POST["category_id"] ?? [];
        $category_id = is_array($category_ids) ? implode(",", array_filter(array_map("intval", $category_ids))) : null;

        $department_ids = $_POST["department_id"] ?? [];
        $department_id = is_array($department_ids) ? implode(",", array_filter(array_map("intval", $department_ids))) : null;

        $assigned_to_user_ids = $_POST["assigned_to_user_id"] ?? [];
        $assigned_to_user_id = is_array($assigned_to_user_ids) ? implode(",", array_filter(array_map("intval", $assigned_to_user_ids))) : null;

        $importance = isset($_POST["importance"]) ? 1 : 0;
        $completed = isset($_POST["completed"]) ? 1 : 0;

        if ($crud_action === "edit" && $editId > 0) {
            $stmt = $conn->prepare("UPDATE todo SET title=?, description=?, due_date=?, reminder_at=?, repeat_pattern=?, category_id=?, department_id=?, assigned_to_user_id=?, importance=?, completed=? WHERE id=? AND company_id=?");
            $stmt->bind_param("ssssssssiiii", $title, $description, $due_date, $reminder_at, $repeat_pattern, $category_id, $department_id, $assigned_to_user_id, $importance, $completed, $editId, $company_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO todo (company_id, title, description, due_date, reminder_at, repeat_pattern, category_id, department_id, assigned_to_user_id, created_by_user_id, importance, completed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssssiii", $company_id, $title, $description, $due_date, $reminder_at, $repeat_pattern, $category_id, $department_id, $assigned_to_user_id, $logged_user_id, $importance, $completed);
        }

        if ($stmt->execute()) {
            header("Location: index.php?msg=saved");
            die();
        }
    }
}

// Data fetching
$filter = $_GET["filter"] ?? "tasks";
$search = $_GET["search"] ?? "";

if ($crud_action === "index") {
    $sql = "SELECT t.* FROM todo t WHERE t.company_id = ? AND t.active = 1";

    $params = [$company_id];
    $types = "i";

    $visibilitySql = itm_todo_visibility_sql("t");
    $sql .= " AND ($visibilitySql)";
    $types .= "ii";
    $params[] = $logged_user_id;
    $params[] = $logged_user_id;

    if ($filter === "my_day") {
        $sql .= " AND DATE(t.due_date) = CURDATE()";
    } elseif ($filter === "important") {
        $sql .= " AND t.importance = 1";
    } elseif ($filter === "planned") {
        $sql .= " AND t.due_date IS NOT NULL";
    } elseif ($filter === "assigned") {
        $sql .= " AND t.assigned_to_user_id = ?";
        $types .= "i";
        $params[] = $logged_user_id;
    }

    if ($search !== "") {
        $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
        $types .= "ss";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $sql .= " ORDER BY t.completed ASC, t.importance DESC, t.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif ($crud_action === "edit" || $crud_action === "view") {
    $stmt = $conn->prepare("SELECT * FROM todo WHERE id = ? AND company_id = ? AND active = 1");
    $stmt->bind_param("ii", $editId, $company_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    if (!$data) {
        header("Location: index.php");
        die();
    }
} elseif ($crud_action === "create") {
    $data = [];
    if ($filter === "my_day") {
        $data["due_date"] = date("Y-m-d H:i:s");
    } elseif ($filter === "important") {
        $data["importance"] = 1;
    } elseif ($filter === "planned") {
        $data["due_date"] = date("Y-m-d H:i:s");
    }
}

// Metadata
$categories = [];
$resCat = mysqli_query($conn, "SELECT id, name FROM todo_categories WHERE company_id = $company_id OR company_id IS NULL");
if ($resCat) { while ($row = mysqli_fetch_assoc($resCat)) { $categories[$row['id']] = $row; } }

$users = [];
$resUser = mysqli_query($conn, "SELECT id, username FROM users");
if ($resUser) { while ($row = mysqli_fetch_assoc($resUser)) { $users[$row['id']] = $row; } }

$departments = [];
$resDept = mysqli_query($conn, "SELECT id, name, code FROM departments WHERE company_id = $company_id OR company_id IS NULL");
if ($resDept) { while ($row = mysqli_fetch_assoc($resDept)) { $departments[$row['id']] = $row; } }



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Management - Todo</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .todo-container { display: flex; height: calc(100vh - 120px); background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .todo-sidebar { width: 280px; background: var(--bg-secondary); border-right: 1px solid var(--border); padding: 20px 0; display: flex; flex-direction: column; }
        .todo-sidebar-item { padding: 10px 25px; display: flex; align-items: center; cursor: pointer; color: var(--text-primary); text-decoration: none; transition: background 0.2s; }
        .todo-sidebar-item:hover { background: var(--bg-tertiary); }
        .todo-sidebar-item.active { background: #e7f3ff; color: var(--accent); font-weight: 500; }
        .todo-header .date-subtitle { color: var(--text-secondary); font-size: 14px; }
        .todo-content { flex: 1; padding: 30px 50px; overflow-y: auto; position: relative; }
        .todo-header { margin-bottom: 30px; }
        .quick-add { background: var(--bg-secondary); border-radius: 4px; padding: 15px 20px; display: flex; align-items: center; margin-bottom: 20px; box-shadow: var(--shadow); border: 1px solid var(--border); }
        .quick-add-icon { color: var(--accent); margin-right: 15px; font-size: 20px; cursor: pointer; }
        .quick-add input { background: transparent; border: 1px solid var(--border); border-radius: 4px; padding: 8px 12px; flex: 1; font-size: 16px; color: var(--text-primary); outline: none !important; transition: border-color 0.2s; margin-right: 10px; }
        .quick-add input:focus { border-color: var(--accent); }
        .task-item { background: var(--bg-primary); border-radius: 4px; padding: 12px 15px; margin-bottom: 8px; display: flex; align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        .task-item:hover { background-color: var(--bg-secondary); }
        .task-checkbox { width: 22px; height: 22px; border: 2px solid var(--text-tertiary); border-radius: 50%; margin-right: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; flex-shrink: 0; }
        .task-checkbox.completed { background-color: var(--success); border-color: var(--success); }
        .task-checkbox.completed::after { content: "✓"; color: white; font-size: 14px; }
        .task-main { flex: 1; cursor: pointer; min-width: 0; }
        .task-title { font-size: 15px; font-weight: 400; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .task-item.completed .task-title { text-decoration: line-through; color: var(--text-tertiary); }
        .task-meta { font-size: 12px; color: var(--text-secondary); display: flex; gap: 10px; margin-top: 2px; }
        .task-star { cursor: pointer; font-size: 20px; color: var(--text-tertiary); margin-left: 10px; flex-shrink: 0; }
        .task-star.active { color: var(--accent); }
        .empty-state { text-align: center; padding: 100px 50px; color: var(--text-tertiary); }
        .todo-sidebar-footer { margin-top: auto; padding-top: 20px; border-top: 1px solid var(--border); }
        .quick-add-actions { display: flex; gap: 10px; margin-top: 10px; padding-left: 35px; }
        .quick-add-btn { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 4px; padding: 5px 10px; font-size: 13px; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; gap: 8px; position: relative; }
        .quick-add-btn i { font-style: normal; }
        .quick-add-btn:hover { background: var(--bg-tertiary); }
        .quick-add-dropdown { position: absolute; top: 100%; left: 0; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 4px; box-shadow: var(--shadow-lg); z-index: 1000; min-width: 240px; display: none; margin-top: 5px; }
        .quick-add-dropdown.show { display: block; }
        .quick-add-dropdown-header { padding: 8px 15px; border-bottom: 1px solid var(--border); font-weight: 600; text-align: center; font-size: 12px; color: var(--text-secondary); }
        .quick-add-dropdown-item { padding: 10px 15px; display: flex; align-items: center; gap: 12px; cursor: pointer; color: var(--text-primary); transition: background 0.2s; }
        .quick-add-dropdown-item:hover { background: var(--bg-tertiary); }
        .quick-add-dropdown-item.active { background: #e7f3ff; color: var(--accent); }
        .quick-add-dropdown-item i { width: 16px; text-align: center; font-style: normal; }
        .quick-add-dropdown-item .item-label { flex: 1; }
        .quick-add-dropdown-item .item-suffix { color: var(--text-tertiary); font-size: 12px; }
        .quick-add-dropdown-item.danger { color: var(--danger); border-top: 1px solid var(--border); margin-top: 5px; }

    </style>
</head>
<body>
<?php include ROOT_PATH . "includes/header.php"; ?>
<div class="container">
    <?php include ROOT_PATH . "includes/sidebar.php"; ?>
    <div class="main-content">
        <div class="content">
            <div class="todo-container">
                <div class="todo-sidebar">
                    <a href="index.php" class="todo-sidebar-item">
                        📝 To Do
                    </a>
                    <a href="?filter=my_day" class="todo-sidebar-item <?php echo $filter === "my_day" ? "active" : ""; ?>">
                        ☀️ My Day
                    </a>
                    <a href="?filter=important" class="todo-sidebar-item <?php echo $filter === "important" ? "active" : ""; ?>">
                        ⭐ Important
                    </a>
                    <a href="?filter=planned" class="todo-sidebar-item <?php echo $filter === "planned" ? "active" : ""; ?>">
                        📅 Planned
                    </a>
                    <a href="?filter=assigned" class="todo-sidebar-item <?php echo $filter === "assigned" ? "active" : ""; ?>">
                        👤 Attributed to me
                    </a>
                    <a href="?filter=tasks" class="todo-sidebar-item <?php echo ($filter === "tasks" || $filter === "") ? "active" : ""; ?>">
                        🏠 Tasks
                    </a>

                </div>
                <div class="todo-content">
                    <?php if ($crud_action === "index"): ?>
                        <div class="todo-header">
                            <h1>
                                                    <div>
                        <a href="create.php?filter=<?php echo urlencode($filter); ?>" class="todo-sidebar-item" style="color: var(--accent);">
                            ➕ New Task
                        </a>
                    </div>
                                <?php
                                    if ($filter === "my_day") echo "☀️ My Day";
                                    elseif ($filter === "important") echo "⭐ Important";
                                    elseif ($filter === "planned") echo "📅 Planned";
                                    elseif ($filter === "assigned") echo "👤 Attributed to me";
                                    else echo "🏠 Tasks";
                                ?>
                            </h1>
                            <div class="date-subtitle"><?php echo date("l, F j"); ?></div>
                        </div>

                        <!-- SEARCH BAR -->
                        <div class="card" style="margin-bottom:16px;">
                            <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
                                <input type="hidden" name="filter" value="<?php echo sanitize($filter); ?>">
                                <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                                    <label for="moduleSearch">Search (all fields)</label>
                                    <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($search); ?>" placeholder="Type to search records...">
                                </div>
                                <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    <a href="index.php?filter=<?php echo urlencode($filter); ?>" class="btn">Clear</a>
                                </div>
                            </form>
                            <table data-itm-db-import-endpoint="index.php" style="display:none;">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Due Date</th>
                                        <th>Reminder</th>
                                        <th>Repeat</th>
                                        <th>Importance</th>
                                        <th>Completed</th>
                                        <th>Category</th>
                                        <th>Department</th>
                                        <th>Assigned To User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $t): ?>
                                    <tr>
                                        <td><?php echo (int)$t['id']; ?></td>
                                        <td><?php echo sanitize($t['title']); ?></td>
                                        <td><?php echo sanitize($t['description']); ?></td>
                                        <td><?php echo sanitize($t['due_date']); ?></td>
                                        <td><?php echo sanitize($t['reminder_at']); ?></td>
                                        <td><?php echo sanitize($t['repeat_pattern']); ?></td>
                                        <td><?php echo (int)$t['importance']; ?></td>
                                        <td><?php echo (int)$t['completed']; ?></td>
                                        <td><?php echo sanitize($t['category_name'] ?? ''); ?></td>
                                        <td><?php echo sanitize($t['department_name'] ?? ''); ?></td>
                                        <td><?php echo sanitize($t['assigned_username'] ?? ''); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                       <div class="quick-add" style="display: block;">
                            <div style="display: flex; align-items: center;">
                                <div class="quick-add-icon" onclick="quickAdd()">➕</div>
                                <input type="text" id="quickAddInput" placeholder="Add a task" onkeypress="if(event.key==='Enter') quickAdd()">
                                <button class="btn btn-sm btn-primary" onclick="quickAdd()">Add</button>
                            </div>
  <div class="quick-add-actions">

    <!-- DEADLINE -->
    <div class="quick-add-btn" id="deadlineBtn" onclick="toggleQuickDropdown(event, 'deadlineDropdown')">
        <i id="deadlineIcon">📅</i>
        <span id="deadlineLabel">Deadline</span>

        <div class="quick-add-dropdown" id="deadlineDropdown">
            <div class="quick-add-dropdown-header">Deadline</div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('deadline', 'today', event)">
                <i>📅</i>
                <span class="item-label">Today</span>
                <span class="item-suffix"><?php echo date("D"); ?></span>
            </div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('deadline', 'tomorrow', event)">
                <i>📅</i>
                <span class="item-label">Tomorrow</span>
                <span class="item-suffix"><?php echo date("D", strtotime('+1 day')); ?></span>
            </div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('deadline', 'next_week', event)">
                <i>📅</i>
                <span class="item-label">Next week</span>
                <span class="item-suffix">Mon</span>
            </div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('deadline', 'choose', event)">
                <i>📅</i>
                <span class="item-label">Choose a date</span>
            </div>

            <div class="quick-add-dropdown-item danger" onclick="setQuickValue('deadline', 'remove', event)">
                <i>🗑️</i>
                <span class="item-label">Remove deadline</span>
            </div>
        </div>
    </div>

    <!-- REMINDER -->
    <div class="quick-add-btn" id="reminderBtn" onclick="toggleQuickDropdown(event, 'reminderDropdown')">
        <i id="reminderIcon">🔔</i>
        <span id="reminderLabel">Reminder</span>

        <div class="quick-add-dropdown" id="reminderDropdown">
            <div class="quick-add-dropdown-header">Reminder</div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('reminder', 'later', event)">
                <i>🕒</i>
                <span class="item-label">Later today</span>
                <span class="item-suffix"><?php echo date("H:i", strtotime('+3 hours')); ?></span>
            </div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('reminder', 'tomorrow', event)">
                <i>🕒</i>
                <span class="item-label">Tomorrow</span>
                <span class="item-suffix"><?php echo date("D 09:00", strtotime('+1 day')); ?></span>
            </div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('reminder', 'next_week', event)">
                <i>🕒</i>
                <span class="item-label">Next week</span>
                <span class="item-suffix">Mon 09:00</span>
            </div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('reminder', 'choose', event)">
                <i>🕒</i>
                <span class="item-label">Choose a date</span>
            </div>

            <div class="quick-add-dropdown-item danger" onclick="setQuickValue('reminder', 'remove', event)">
                <i>🗑️</i>
                <span class="item-label">Remove reminder</span>
            </div>
        </div>
    </div>

    <!-- REPEAT -->
    <div class="quick-add-btn" id="repeatBtn" onclick="toggleQuickDropdown(event, 'repeatDropdown')">
        <i id="repeatIcon">🔄</i>
        <span id="repeatLabel">Repeat</span>

        <div class="quick-add-dropdown" id="repeatDropdown">
            <div class="quick-add-dropdown-header">Repeat</div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('repeat', 'daily', event)">
                <i>📅</i>
                <span class="item-label">Daily</span>
            </div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('repeat', 'weekdays', event)">
                <i>📅</i>
                <span class="item-label">Weekdays</span>
            </div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('repeat', 'weekly', event)">
                <i>📅</i>
                <span class="item-label">Weekly</span>
            </div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('repeat', 'monthly', event)">
                <i>📅</i>
                <span class="item-label">Monthly</span>
            </div>

            <div class="quick-add-dropdown-item" onclick="setQuickValue('repeat', 'annually', event)">
                <i>📅</i>
                <span class="item-label">Annually</span>
            </div>

            <div class="quick-add-dropdown-item danger" onclick="setQuickValue('repeat', 'remove', event)">
                <i>🗑️</i>
                <span class="item-label">Never repeat</span>
            </div>
        </div>
    </div>

    <!-- DEPARTMENT -->
    <div class="quick-add-btn" id="depBtn" onclick="toggleQuickDropdown(event, 'depDropdown')">
        <i id="depIcon">🏢</i>
        <span id="depLabel">Department</span>

        <div class="quick-add-dropdown" id="depDropdown">
            <div class="quick-add-dropdown-header">Department</div>
            <?php foreach ($departments as $dept): ?>
                <div class="quick-add-dropdown-item" onclick="setQuickValue('department', '<?php echo $dept['id']; ?>', event)" data-id="<?php echo $dept['id']; ?>">
                    <i>🏢</i>
                    <span class="item-label"><?php echo sanitize(!empty($dept['code']) ? $dept['code'] : $dept['name']); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="quick-add-dropdown-item danger" onclick="setQuickValue('department', 'remove', event)">
                <i>🗑️</i>
                <span class="item-label">Remove</span>
            </div>
        </div>
    </div>

    <!-- CATEGORY -->
    <div class="quick-add-btn" id="catBtn" onclick="toggleQuickDropdown(event, 'catDropdown')">
        <i id="catIcon">🏷️</i>
        <span id="catLabel">Category</span>

        <div class="quick-add-dropdown" id="catDropdown">
            <div class="quick-add-dropdown-header">Category</div>
            <?php foreach ($categories as $cat): ?>
                <div class="quick-add-dropdown-item" onclick="setQuickValue('category', '<?php echo $cat['id']; ?>', event)" data-id="<?php echo $cat['id']; ?>">
                    <i>🏷️</i>
                    <span class="item-label"><?php echo sanitize($cat['name']); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="quick-add-dropdown-item danger" onclick="setQuickValue('category', 'remove', event)">
                <i>🗑️</i>
                <span class="item-label">Remove</span>
            </div>
        </div>
    </div>

    <!-- ASSIGN TO -->
    <div class="quick-add-btn" id="assignBtn" onclick="toggleQuickDropdown(event, 'assignDropdown')">
        <i id="assignIcon">👤</i>
        <span id="assignLabel">Assign To</span>

        <div class="quick-add-dropdown" id="assignDropdown">
            <div class="quick-add-dropdown-header">Assign To</div>
            <?php foreach ($users as $user): ?>
                <div class="quick-add-dropdown-item" onclick="setQuickValue('assign', '<?php echo $user['id']; ?>', event)" data-id="<?php echo $user['id']; ?>">
                    <i>👤</i>
                    <span class="item-label"><?php echo sanitize($user['username']); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="quick-add-dropdown-item danger" onclick="setQuickValue('assign', 'remove', event)">
                <i>🗑️</i>
                <span class="item-label">Remove</span>
            </div>
        </div>
    </div>
</div>

                            <input type="hidden" id="quickAddDueDate">
                            <input type="hidden" id="quickAddReminderAt">
                            <input type="hidden" id="quickAddRepeatPattern">
                            <input type="hidden" id="quickAddDept">
                            <input type="hidden" id="quickAddCat">
                            <input type="hidden" id="quickAddUser">
                        </div>

                        <div class="todo-list">
                            <?php if (empty($tasks)): ?>
                                <div class="empty-state">
                                    <i style="font-size: 48px; display: block; margin-bottom: 20px; opacity: 0.5;">📋
                                    <p>No tasks found. Try adding one!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasks as $task): ?>
                                    <div class="task-item <?php echo $task["completed"] ? "completed" : ""; ?>">
                                        <div class="task-checkbox <?php echo $task["completed"] ? "completed" : ""; ?>" onclick="toggleCompleted(<?php echo $task["id"]; ?>, this)"></div>
                                        <div class="task-main" onclick="location.href='view.php?id=<?php echo $task["id"]; ?>'">
                                            <div class="task-title"><?php echo sanitize($task["title"]); ?></div>
                                            <div class="task-meta">
                                                <span>Tasks</span>
                                                <?php
                                                $catIds = array_filter(explode(',', (string)($task['category_id'] ?? '')));
                                                if (!empty($catIds)):
                                                    $catNames = [];
                                                    foreach ($catIds as $cid) { if (isset($categories[$cid])) $catNames[] = $categories[$cid]['name']; }
                                                    if (!empty($catNames)): ?>
                                                        <span>• 🏷️ <?php echo sanitize(implode(' - ', $catNames)); ?></span>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php
                                                $deptIds = array_filter(explode(',', (string)($task['department_id'] ?? '')));
                                                if (!empty($deptIds)):
                                                    $deptCodes = [];
                                                    foreach ($deptIds as $did) {
                                                        if (isset($departments[$did])) {
                                                            $deptCodes[] = !empty($departments[$did]['code']) ? $departments[$did]['code'] : $departments[$did]['name'];
                                                        }
                                                    }
                                                    if (!empty($deptCodes)): ?>
                                                        <span>• 🏢 <?php echo sanitize(implode(' - ', $deptCodes)); ?></span>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php
                                                $assignedIds = array_filter(explode(',', (string)($task['assigned_to_user_id'] ?? '')));
                                                if (!empty($assignedIds)):
                                                    $userNames = [];
                                                    foreach ($assignedIds as $uid) { if (isset($users[$uid])) $userNames[] = $users[$uid]['username']; }
                                                    if (!empty($userNames)): ?>
                                                        <span>• 👥 <?php echo sanitize(implode(' - ', $userNames)); ?></span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($task["due_date"]): ?>
                                                    <span>• 📅 <?php echo date("M j", strtotime($task["due_date"])); ?></span>
                                                <?php endif; ?>
                                                <?php if ($task["reminder_at"]): ?>
                                                    <span title="Reminder set">• 🔔</span>
                                                <?php endif; ?>
                                                <?php if ($task["repeat_pattern"]): ?>
                                                    <span title="Repeat: <?php echo sanitize($task['repeat_pattern']); ?>">• 🔄</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="task-star <?php echo $task["importance"] ? "active" : ""; ?>" onclick="toggleImportance(<?php echo $task["id"]; ?>, this)">
                                            <?php echo $task["importance"] ? "★" : "☆"; ?>
                                        </div>
                                        <a href="edit.php?id=<?php echo $task["id"]; ?>" style="margin-left:15px; text-decoration:none;" title="Edit">✏️</a>
                                        <form method="POST" action="index.php?id=<?php echo $task["id"]; ?>" style="display:inline;" onsubmit="return confirm('Delete this task?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="bulk_action" value="single_delete">
                                            <button class="btn btn-sm" type="submit" style="background:none;border:none;padding:0;margin-left:5px;" title="Delete">🗑️</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($crud_action === "edit" || $crud_action === "create"): ?>
                        <h1><?php echo $crud_action === "edit" ? "Edit Task" : "New Task"; ?></h1>
                        <form method="POST" class="form-grid" style="max-width: 800px;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" name="title" value="<?php echo sanitize($data["title"] ?? ""); ?>" required autofocus>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" rows="5"><?php echo sanitize($data["description"] ?? ""); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="datetime-local" name="due_date" value="<?php echo isset($data["due_date"]) ? str_replace(" ", "T", substr($data["due_date"], 0, 16)) : ""; ?>">
                            </div>
                            <div class="form-group">
                                <label>Reminder</label>
                                <input type="datetime-local" name="reminder_at" value="<?php echo isset($data["reminder_at"]) ? str_replace(" ", "T", substr($data["reminder_at"], 0, 16)) : ""; ?>">
                            </div>
                            <div class="form-group">
                                <label>Repeat Pattern</label>
                                <select name="repeat_pattern">
                                    <option value="">-- None --</option>
                                    <option value="daily" <?php echo (isset($data["repeat_pattern"]) && $data["repeat_pattern"] === 'daily') ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekdays" <?php echo (isset($data["repeat_pattern"]) && $data["repeat_pattern"] === 'weekdays') ? 'selected' : ''; ?>>On weekdays</option>
                                    <option value="weekly" <?php echo (isset($data["repeat_pattern"]) && $data["repeat_pattern"] === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo (isset($data["repeat_pattern"]) && $data["repeat_pattern"] === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="annually" <?php echo (isset($data["repeat_pattern"]) && $data["repeat_pattern"] === 'annually') ? 'selected' : ''; ?>>Annually</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Department</label>
                                <select name="department_id[]" multiple size="5" data-addable-select="1" data-add-table="departments" data-add-friendly="department" data-add-company-scoped="1">
                                    <option value="">-- None --</option>
                                    <option value="__add_new__">➕</option>
                                    <?php 
                                    $selectedDepts = explode(',', (string)($data['department_id'] ?? ''));
                                    foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept["id"]; ?>" <?php echo in_array($dept["id"], $selectedDepts) ? "selected" : ""; ?>><?php echo sanitize($dept["name"]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category_id[]" multiple size="5" data-addable-select="1" data-add-table="todo_categories" data-add-friendly="category" data-add-company-scoped="1">
                                    <option value="">-- None --</option>
                                    <option value="__add_new__">➕</option>
                                    <?php 
                                    $selectedCats = explode(',', (string)($data['category_id'] ?? ''));
                                    foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat["id"]; ?>" <?php echo in_array($cat["id"], $selectedCats) ? "selected" : ""; ?>><?php echo sanitize($cat["name"]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Assign To</label>
                                <select name="assigned_to_user_id[]" multiple size="5" data-addable-select="1" data-add-table="users" data-add-friendly="user">
                                    <option value="">-- Unassigned --</option>
                                    <option value="__add_new__">➕</option>
                                    <?php 
                                    $selectedUsers = explode(',', (string)($data['assigned_to_user_id'] ?? ''));
                                    if ($crud_action === 'create' && empty($selectedUsers) && !isset($data['assigned_to_user_id'])) {
                                        $selectedUsers = [(string)$logged_user_id];
                                    }
                                    foreach ($users as $user): ?>
                                        <option value="<?php echo $user["id"]; ?>" <?php echo in_array($user["id"], $selectedUsers) ? "selected" : ""; ?>><?php echo sanitize($user["username"]); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display: flex; gap: 30px; margin-top: 10px;">
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="importance" value="1" <?php echo !empty($data["importance"]) ? "checked" : ""; ?>>
                                    <span>Important ⭐</span>
                                </label>
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="completed" value="1" <?php echo !empty($data["completed"]) ? "checked" : ""; ?>>
                                    <span>Completed ✅</span>
                                </label>
                            </div>
                            <div class="form-actions" style="margin-top: 30px;">
                                <button class="btn btn-primary" type="submit">💾 Save</button>
                                <a href="index.php" class="btn">🔙 Cancel</a>
                                <?php if ($crud_action === "edit"): ?>
                                    <button type="submit" name="bulk_action" value="single_delete" class="btn btn-danger" style="margin-left: auto;" onclick="return confirm('Delete this task?')">🗑️ Delete</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php elseif ($crud_action === "view"): ?>
                        <h1>Task Details</h1>
                        <div class="card" style="max-width: 800px;">
                            <div style="display: flex; align-items: flex-start; margin-bottom: 20px;">
                                <div class="task-checkbox <?php echo $data["completed"] ? "completed" : ""; ?>" style="margin-top: 5px;"></div>
                                <div style="flex: 1;">
                                    <h2 style="margin: 0; text-decoration: <?php echo $data["completed"] ? "line-through" : "none"; ?>;"><?php echo sanitize($data["title"]); ?></h2>
                                    <div style="color: var(--text-secondary); margin-top: 5px;">Created on <?php echo date("M j, Y", strtotime($data["created_at"])); ?></div>
                                </div>
                            </div>

                            <?php if ($data["description"]): ?>
                                <div style="margin-bottom: 20px; white-space: pre-wrap;"><?php echo sanitize($data["description"]); ?></div>
                            <?php endif; ?>

                            <table class="table" style="width: auto;">
                                <?php if ($data["due_date"]): ?>
                                <tr>
                                    <th style="text-align: left; padding-right: 20px;">Due Date</th>
                                    <td>📅 <?php echo date("M j, Y H:i", strtotime($data["due_date"])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($data["reminder_at"]): ?>
                                <tr>
                                    <th style="text-align: left; padding-right: 20px;">Reminder</th>
                                    <td>🔔 <?php echo date("M j, Y H:i", strtotime($data["reminder_at"])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($data["repeat_pattern"]): ?>
                                <tr>
                                    <th style="text-align: left; padding-right: 20px;">Repeat</th>
                                    <td>🔄 <?php echo sanitize(ucfirst($data["repeat_pattern"])); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th style="text-align: left; padding-right: 20px;">Department</th>
                                    <td><?php
                                        $deptIds = array_filter(explode(',', (string)($data['department_id'] ?? '')));
                                        if (empty($deptIds)) {
                                            echo "None";
                                        } else {
                                            $names = [];
                                            foreach ($deptIds as $did) { if (isset($departments[$did])) $names[] = $departments[$did]['name']; }
                                            echo sanitize(implode(' - ', $names));
                                        }
                                    ?></td>
                                </tr>
                                <tr>
                                    <th style="text-align: left; padding-right: 20px;">Category</th>
                                    <td><?php
                                        $catIds = array_filter(explode(',', (string)($data['category_id'] ?? '')));
                                        if (empty($catIds)) {
                                            echo "None";
                                        } else {
                                            $names = [];
                                            foreach ($catIds as $cid) { if (isset($categories[$cid])) $names[] = $categories[$cid]['name']; }
                                            echo sanitize(implode(' - ', $names));
                                        }
                                    ?></td>
                                </tr>
                                <tr>
                                    <th style="text-align: left; padding-right: 20px;">Assigned To</th>
                                    <td><?php
                                        $uIds = array_filter(explode(',', (string)($data['assigned_to_user_id'] ?? '')));
                                        if (empty($uIds)) {
                                            echo "Unassigned";
                                        } else {
                                            $names = [];
                                            foreach ($uIds as $uid) { if (isset($users[$uid])) $names[] = $users[$uid]['username']; }
                                            echo sanitize(implode(' - ', $names));
                                        }
                                    ?></td>
                                </tr>
                            </table>

                            <div class="form-actions" style="margin-top: 30px;">
                                <a href="edit.php?id=<?php echo $data["id"]; ?>" class="btn btn-primary">✏️ Edit</a>
                                <a href="index.php" class="btn">🔙 Back</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
    function toggleQuickDropdown(event, id) {
        event.stopPropagation();
        const el = document.getElementById(id);
        const isShown = el.classList.contains('show');
        document.querySelectorAll('.quick-add-dropdown').forEach(d => d.classList.remove('show'));
        if (!isShown) el.classList.add('show');
    }

    document.addEventListener('click', () => {
        document.querySelectorAll('.quick-add-dropdown').forEach(d => d.classList.remove('show'));
    });

    function setQuickValue(type, value, event) {
        event.stopPropagation();
        const inputMap = {
            'deadline': 'quickAddDueDate',
            'reminder': 'quickAddReminderAt',
            'repeat': 'quickAddRepeatPattern',
            'department': 'quickAddDept',
            'category': 'quickAddCat',
            'assign': 'quickAddUser'
        };
        const labelMap = {
            'deadline': 'deadlineLabel',
            'reminder': 'reminderLabel',
            'repeat': 'repeatLabel',
            'department': 'depLabel',
            'category': 'catLabel',
            'assign': 'assignLabel'
        };
        const btnMap = {
            'deadline': 'deadlineBtn',
            'reminder': 'reminderBtn',
            'repeat': 'repeatBtn',
            'department': 'depBtn',
            'category': 'catBtn',
            'assign': 'assignBtn'
        };

        const isMulti = ['department', 'category', 'assign'].includes(type);
        let displayValue = '';
        let dbValue = '';

        if (value === 'remove') {
            dbValue = '';
            displayValue = type.charAt(0).toUpperCase() + type.slice(1);
            if (type === 'assign') displayValue = 'Assign To';
            document.getElementById(btnMap[type]).style.color = '';
            if (isMulti) {
                document.querySelectorAll(`#${type}Dropdown .quick-add-dropdown-item`).forEach(i => i.classList.remove('active'));
            }
        } else if (value === 'choose') {
            const dateStr = prompt("Enter date (YYYY-MM-DD HH:MM):");
            if (!dateStr) return;
            dbValue = dateStr;
            displayValue = dateStr;
            document.getElementById(btnMap[type]).style.color = 'var(--accent)';
        } else if (isMulti) {
            const input = document.getElementById(inputMap[type]);
            let currentIds = input.value ? input.value.split(',').filter(x => x) : [];
            const valStr = value.toString();

            if (currentIds.includes(valStr)) {
                currentIds = currentIds.filter(id => id !== valStr);
                event.currentTarget.classList.remove('active');
            } else {
                currentIds.push(valStr);
                event.currentTarget.classList.add('active');
            }

            dbValue = currentIds.join(',');
            const count = currentIds.length;
            if (count === 0) {
                displayValue = type === 'assign' ? 'Assign To' : (type.charAt(0).toUpperCase() + type.slice(1));
                document.getElementById(btnMap[type]).style.color = '';
            } else {
                displayValue = count + ' selected';
                document.getElementById(btnMap[type]).style.color = 'var(--accent)';
            }
            // Don't close dropdown for multi-select
            input.value = dbValue;
            document.getElementById(labelMap[type]).textContent = displayValue;
            return;
        } else {
            const now = new Date();
            if (type === 'deadline') {
                if (value === 'today') {
                    dbValue = now.toISOString().split('T')[0] + ' 23:59:59';
                    displayValue = 'Today';
                } else if (value === 'tomorrow') {
                    const tomorrow = new Date(now);
                    tomorrow.setDate(now.getDate() + 1);
                    dbValue = tomorrow.toISOString().split('T')[0] + ' 23:59:59';
                    displayValue = 'Tomorrow';
                } else if (value === 'next_week') {
                    const nextMonday = new Date(now);
                    nextMonday.setDate(now.getDate() + ((1 + 7 - now.getDay()) % 7 || 7));
                    dbValue = nextMonday.toISOString().split('T')[0] + ' 09:00:00';
                    displayValue = 'Next week';
                }
            } else if (type === 'reminder') {
                 if (value === 'later') {
                    const later = new Date(now.getTime() + 3 * 60 * 60 * 1000);
                    dbValue = later.toISOString().slice(0, 19).replace('T', ' ');
                    displayValue = 'Later today';
                } else if (value === 'tomorrow') {
                    const tomorrow = new Date(now);
                    tomorrow.setDate(now.getDate() + 1);
                    dbValue = tomorrow.toISOString().split('T')[0] + ' 09:00:00';
                    displayValue = 'Tomorrow';
                } else if (value === 'next_week') {
                    const nextMonday = new Date(now);
                    nextMonday.setDate(now.getDate() + ((1 + 7 - now.getDay()) % 7 || 7));
                    dbValue = nextMonday.toISOString().split('T')[0] + ' 09:00:00';
                    displayValue = 'Next week';
                }
            } else if (type === 'repeat') {
                dbValue = value;
                displayValue = value.charAt(0).toUpperCase() + value.slice(1);
            }
            document.getElementById(btnMap[type]).style.color = 'var(--accent)';
        }

        document.getElementById(inputMap[type]).value = dbValue;
        document.getElementById(labelMap[type]).textContent = displayValue;
        document.querySelectorAll('.quick-add-dropdown').forEach(d => d.classList.remove('show'));
    }


    function quickAdd() {
        const input = document.getElementById("quickAddInput");
        const title = input.value.trim();
        if (!title) {
            alert("Please add a title, cannot be empty.");
            return;
        }

        const formData = new FormData();
        formData.append("csrf_token", CSRF_TOKEN);
        formData.append("title", title);
        formData.append("due_date", document.getElementById("quickAddDueDate").value);
        formData.append("reminder_at", document.getElementById("quickAddReminderAt").value);
        formData.append("repeat_pattern", document.getElementById("quickAddRepeatPattern").value);

        const deptIds = document.getElementById("quickAddDept").value.split(',').filter(x => x);
        deptIds.forEach(id => formData.append("department_id[]", id));

        const catIds = document.getElementById("quickAddCat").value.split(',').filter(x => x);
        catIds.forEach(id => formData.append("category_id[]", id));

        const userIds = document.getElementById("quickAddUser").value.split(',').filter(x => x);
        userIds.forEach(id => formData.append("assigned_to_user_id[]", id));

        fetch("index.php?ajax_action=quick_add", {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                location.reload();
            } else {
                alert("Error adding task: " + (data.error || "Unknown error"));
            }
        });
    }

    function toggleCompleted(id, el) {
        const isCompleted = el.classList.contains("completed");
        const newVal = isCompleted ? 0 : 1;

        const formData = new FormData();
        formData.append("csrf_token", CSRF_TOKEN);
        formData.append("id", id);
        formData.append("completed", newVal);

        fetch("index.php?ajax_action=toggle_completed", {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                el.classList.toggle("completed");
                el.closest(".task-item").classList.toggle("completed");
            }
        });
    }

    function toggleImportance(id, el) {
        const isImportant = el.classList.contains("active");
        const newVal = isImportant ? 0 : 1;

        const formData = new FormData();
        formData.append("csrf_token", CSRF_TOKEN);
        formData.append("id", id);
        formData.append("importance", newVal);

        fetch("index.php?ajax_action=toggle_importance", {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                el.classList.toggle("active");
                el.textContent = newVal ? "★" : "☆";
            }
        });
    }
</script>
<script src="../../js/select-add-option.js"></script>
<script>
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
</script>
<script src="../../js/vendor/xlsx.full.min.js"></script>
<script src="../../js/table-tools.js"></script>
</body>
</html>
