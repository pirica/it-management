<?php
require_once 'index_logic.php';
$pageTitle = "Private Contacts";
include '../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>👤 Private Contacts</h1>
        <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Contact</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="form-inline">
                <input type="text" name="search" class="form-control mr-sm-2" placeholder="Search contacts..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-outline-primary">Search</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0" data-itm-db-import-endpoint="modules/private_contacts/index.php">
                <thead>
                    <tr>
                        <th width="50"></th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Organization</th>
                        <th>Labels</th>
                        <th width="120" class="text-right" data-itm-actions-origin="1">Actions</th>
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
                                            <img src="../../files/contacts/<?php echo htmlspecialchars($contact['photo']); ?>" class="rounded-circle mr-2" width="30" height="30" alt="">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-light border mr-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
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
                                    <a href="edit.php?id=<?php echo $contact['id']; ?>" class="btn btn-sm btn-outline-secondary mr-1"><i class="fas fa-edit"></i></a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                        <?php itm_csrf_token_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $contact['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

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

<?php include '../../templates/footer.php'; ?>
