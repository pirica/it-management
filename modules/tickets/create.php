<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';

$data = [
    'ticket_code' => '',
    'title' => '',
    'description' => '',
    'category_id' => '',
    'status_id' => '',
    'priority_id' => '',
    'assigned_to_user_id' => '',
    'asset_id' => '',
    'created_at' => date('Y-m-d\TH:i')
];

if ($is_edit) {
    $q = mysqli_query($conn, "SELECT * FROM tickets WHERE id=$id AND company_id=$company_id LIMIT 1");
    if ($q && mysqli_num_rows($q) === 1) {
        $data = mysqli_fetch_assoc($q);
        $data['created_at'] = date('Y-m-d\TH:i', strtotime($data['created_at']));
    } else {
        $error = 'Ticket not found.';
        $is_edit = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_code = escape_sql($_POST['ticket_code'] ?? '', $conn);
    $title = escape_sql($_POST['title'] ?? '', $conn);
    $description = escape_sql($_POST['description'] ?? '', $conn);
    $category_id = (int)($_POST['category_id'] ?? 0) ?: 'NULL';
    $status_id = (int)($_POST['status_id'] ?? 0) ?: 'NULL';
    $priority_id = (int)($_POST['priority_id'] ?? 0) ?: 'NULL';
    $assigned_to_user_id = (int)($_POST['assigned_to_user_id'] ?? 0) ?: 'NULL';
    $asset_id = (int)($_POST['asset_id'] ?? 0) ?: 'NULL';

    $created_at_raw = $_POST['created_at'] ?? '';
    $created_at = $created_at_raw
        ? "'" . escape_sql(str_replace('T', ' ', $created_at_raw) . ':00', $conn) . "'"
        : 'CURRENT_TIMESTAMP';

    if (!$title) {
        $error = 'Ticket title is required.';
    } else {
        if ($is_edit) {
            $sql = "UPDATE tickets SET
                        ticket_code='$ticket_code',
                        title='$title',
                        description='$description',
                        category_id=$category_id,
                        status_id=$status_id,
                        priority_id=$priority_id,
                        assigned_to_user_id=$assigned_to_user_id,
                        asset_id=$asset_id,
                        created_at=$created_at
                    WHERE id=$id AND company_id=$company_id";
        } else {
            $created_by_user_id = 0;
            $creator_result = mysqli_query($conn, "SELECT id FROM users WHERE company_id=$company_id AND active=1 ORDER BY id ASC LIMIT 1");
            if ($creator_result && mysqli_num_rows($creator_result) === 1) {
                $created_by_user_id = (int)mysqli_fetch_assoc($creator_result)['id'];
            }

            if ($created_by_user_id <= 0) {
                $error = 'Please create at least one active user before creating tickets.';
            } else {
                $sql = "INSERT INTO tickets
                        (company_id, ticket_code, title, description, category_id, status_id, priority_id, created_by_user_id, assigned_to_user_id, asset_id, created_at)
                        VALUES
                        ($company_id, '$ticket_code', '$title', '$description', $category_id, $status_id, $priority_id, $created_by_user_id, $assigned_to_user_id, $asset_id, $created_at)";
            }
        }

        if (!$error) {
            if (mysqli_query($conn, $sql)) {
                header('Location: index.php');
                exit;
            }
            $error = 'Database error: ' . mysqli_error($conn);
        }
    }
}

$categories = mysqli_query($conn, "SELECT id,name FROM ticket_categories WHERE active=1 ORDER BY name");
$statuses = mysqli_query($conn, "SELECT id,name,color FROM ticket_statuses WHERE active=1 ORDER BY name");
$priorities = mysqli_query($conn, "SELECT id,name,color FROM ticket_priorities WHERE active=1 ORDER BY level");
$users = mysqli_query($conn, "SELECT id,username FROM users WHERE company_id=$company_id AND active=1 ORDER BY username");
$assets = mysqli_query($conn, "SELECT id,name FROM equipment WHERE company_id=$company_id AND active=1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Ticket</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1><?php echo $is_edit ? '✏️ Edit' : '➕ Create'; ?> Ticket</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Title *</label>
                            <input required name="title" value="<?php echo sanitize($data['title']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Ticket Code</label>
                            <input name="ticket_code" value="<?php echo sanitize($data['ticket_code']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description"><?php echo sanitize($data['description']); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id">
                                <option value="">-- Select --</option>
                                <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (string)$data['category_id'] === (string)$c['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($c['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status_id">
                                <option value="">-- Select --</option>
                                <?php while ($s = mysqli_fetch_assoc($statuses)): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo (string)$data['status_id'] === (string)$s['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($s['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority_id">
                                <option value="">-- Select --</option>
                                <?php while ($p = mysqli_fetch_assoc($priorities)): ?>
                                    <option value="<?php echo (int)$p['id']; ?>" <?php echo (string)$data['priority_id'] === (string)$p['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($p['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assigned To</label>
                            <select name="assigned_to_user_id">
                                <option value="">-- Unassigned --</option>
                                <?php while ($u = mysqli_fetch_assoc($users)): ?>
                                    <option value="<?php echo (int)$u['id']; ?>" <?php echo (string)$data['assigned_to_user_id'] === (string)$u['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($u['username']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Related Asset</label>
                            <select name="asset_id">
                                <option value="">-- None --</option>
                                <?php while ($a = mysqli_fetch_assoc($assets)): ?>
                                    <option value="<?php echo (int)$a['id']; ?>" <?php echo (string)$data['asset_id'] === (string)$a['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($a['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Created Date</label>
                            <input type="datetime-local" name="created_at" value="<?php echo sanitize($data['created_at']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Quick Color Tag (UI)</label>
                        <input type="color" name="ui_color" value="#0969da">
                        <div class="form-hint">Color picker for fast visual tagging while creating tickets.</div>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-primary" type="submit">Save</button>
                        <a class="btn" href="index.php">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
