<?php
$crud_table = 'private_contacts';
$crud_title = 'Private Contacts';
$crud_action = 'index';
require_once 'index_logic.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Private Contacts';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Private Contacts</h1>
                <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Contact</a>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label>Search contacts...</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card" style="overflow:auto;">
                <table class="table table-hover mb-0" data-itm-db-import-endpoint="modules/private_contacts/index.php">
                    <thead>
                        <tr>
                            <th width="50"></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Organization</th>
                            <th>Labels</th>
                            <th width="120" class="text-right itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No contacts found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td>
                                        <span class="favorite-star cursor-pointer" data-id="<?php echo $contact['id']; ?>" data-favorite="<?php echo $contact['is_favorite']; ?>">
                                            <i class="<?php echo $contact['is_favorite'] ? 'fas' : 'far'; ?> fa-star text-warning"></i>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($contact['photo']): ?>
                                                <img src="<?= itm_files_serve_url('Private/' . $_SESSION['username'] . '_' . $_SESSION['employee_id'] . '/private_contacts/' . $contact['photo']) ?>" class="rounded-circle mr-2" width="30" height="30" alt="" style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle border mr-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                                    <i class="fas fa-user text-muted" style="font-size: 14px;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <a href="view.php?id=<?php echo $contact['id']; ?>" class="font-weight-bold">
                                                <?php echo htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($contact['email1_value']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['phone1_value']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['organization_name']); ?></td>
                                    <td>
                                        <?php
                                        if ($contact['labels']) {
                                            foreach (explode(',', $contact['labels']) as $label) {
                                                echo '<span class="badge badge-secondary mr-1">' . htmlspecialchars(trim($label)) . '</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td class="text-right itm-actions-cell" data-itm-actions-origin="1">
                                        <div class="itm-actions-wrap" style="display:flex;justify-content:flex-end;gap:4px;">
                                            <a class="btn btn-sm" href="view.php?id=<?php echo (int)$contact['id']; ?>">🔎</a>
                                            <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$contact['id']; ?>">✏️</a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $contact['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../../js/theme.js"></script>
<script>
$(document).ready(function() {
    $('.favorite-star').click(function() {
        var $star = $(this);
        var id = $star.data('id');
        var current = $star.data('favorite');
        var newVal = current ? 0 : 1;

        $.post('index_logic.php', {
            csrf_token: $('input[name="csrf_token"]').val(),
            action: 'toggle_favorite',
            id: id,
            is_favorite: newVal
        }, function() {
            $star.data('favorite', newVal);
            $star.find('i').toggleClass('fas far');
        });
    });
});
</script>

</body>
</html>
