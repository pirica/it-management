<?php
/**
 * UI Rendering for Visitors Access Log Module
 */

include ROOT_PATH . 'includes/header.php';
?>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><?= sanitize($crud_title) ?></h4>
                        </div>
                        <div class="card-body">
                            <?php if (isset($_SESSION['crud_success'])): ?>
                                <div class="alert alert-success"><?= sanitize($_SESSION['crud_success']) ?><?php unset($_SESSION['crud_success']); ?></div>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['crud_error'])): ?>
                                <div class="alert alert-danger"><?= sanitize($_SESSION['crud_error']) ?><?php unset($_SESSION['crud_error']); ?></div>
                            <?php endif; ?>

                            <!-- Search and Tools -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <form action="index.php" method="GET" class="form-inline">
                                        <input type="text" name="search" class="form-control form-control-sm mr-2" placeholder="Search..." value="<?= sanitize($search) ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">Search</button>
                                        <?php if ($search !== ''): ?>
                                            <a href="index.php" class="btn btn-sm btn-secondary ml-2">Clear</a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                                <div class="col-md-6 text-right">
                                    <?php if ($showBulkActions): ?>
                                        <form id="bulk-delete-form" method="POST" action="delete.php" style="display:inline-block;" data-itm-bulk-delete-bound="1">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                                            <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
                                            <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover" data-itm-no-import-excel="1">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"></th>
                                            <?php foreach ($uiColumns as $field => $label): ?>
                                                <th>
                                                    <a href="?search=<?= urlencode($search) ?>&sort=<?= $field ?>&dir=<?= ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC' ?>">
                                                        <?= sanitize($label) ?>
                                                        <?php if ($sort === $field): ?>
                                                            <?= $dir === 'ASC' ? '▲' : '▼' ?>
                                                        <?php endif; ?>
                                                    </a>
                                                </th>
                                            <?php endforeach; ?>
                                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- 1st Row: Ready to fill -->
                                        <tr class="table-primary">
                                        <td>
                                            <form id="quick-add-form" action="index.php" method="POST" style="display:none;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="action_quick_add" value="1">
                                            </form>
                                        </td>
                                        <td><input type="text" name="visitor_name" form="quick-add-form" class="form-control form-control-sm" placeholder="Visitor Name" required></td>
                                        <td><input type="text" name="company_department" form="quick-add-form" class="form-control form-control-sm" placeholder="Company / Dept"></td>
                                        <td><input type="text" name="reason_for_visit" form="quick-add-form" class="form-control form-control-sm" placeholder="Reason"></td>
                                        <td><input type="text" name="pre_approved_by" form="quick-add-form" class="form-control form-control-sm" placeholder="Approved By"></td>
                                        <td><input type="text" name="room_opened_by" form="quick-add-form" class="form-control form-control-sm" placeholder="Opened By"></td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="date_time_in" form="quick-add-form" class="form-control" placeholder="Now" readonly>
                                                <div class="input-group-append">
                                                    <button type="submit" form="quick-add-form" class="btn btn-success">IN</button>
                                                    </div>
                                            </div>
                                        </td>
                                        <td>—</td>
                                        <td><button type="submit" form="quick-add-form" class="btn btn-sm btn-primary">Save</button></td>
                                        </tr>

                                        <!-- Data Rows -->
                                        <?php if (empty($logs)): ?>
                                            <tr>
                                                <td colspan="<?= count($uiColumns) + 2 ?>" class="text-center">No logs found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($logs as $log):
                                                $isToday = val_is_today($log['date_time_in']);
                                                $rowClass = $isToday ? '' : 'text-muted';
                                            ?>
                                                <tr class="<?= $rowClass ?>" data-id="<?= $log['id'] ?>">
                                                    <td>
                                                        <?php if ($showBulkActions): ?>
                                                            <input type="checkbox" name="ids[]" value="<?= $log['id'] ?>" form="bulk-delete-form" style="display:none;">
                                                        <?php endif; ?>
                                                    </td>

                                                    <?php foreach ($uiColumns as $field => $label): ?>
                                                        <td class="inline-editable" data-field="<?= $field ?>" data-original="<?= sanitize($log[$field]) ?>">
                                                            <?php if ($field === 'date_time_in' || $field === 'date_time_out'): ?>
                                                                <span class="display-val"><?= val_format_datetime($log[$field]) ?></span>
                                                                <?php if ($isToday): ?>
                                                                    <button class="btn btn-xs btn-outline-info btn-timestamp ml-1" data-type="<?= ($field === 'date_time_in' ? 'in' : 'out') ?>" title="Set current time">⏱️</button>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <?php if ($isToday): ?>
                                                                    <span class="display-val"><?= sanitize($log[$field]) ?: '—' ?></span>
                                                                    <input type="text" class="form-control form-control-sm edit-input" style="display:none;" value="<?= sanitize($log[$field]) ?>">
                                                                <?php else: ?>
                                                                    <?= sanitize($log[$field]) ?: '—' ?>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>

                                                    <td class="itm-actions-cell">
                                                        <div class="itm-actions-wrap">
                                                            <a href="view.php?id=<?= $log['id'] ?>" class="btn btn-sm">🔎</a>
                                                            <a href="edit.php?id=<?= $log['id'] ?>" class="btn btn-sm">✏️</a>
                                                            <form action="delete.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this entry?');">
                                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                                <input type="hidden" name="id" value="<?= $log['id'] ?>">
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

                            <!-- Pagination -->
                            <?php if ($totalRows > $perPage): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item"><a class="page-link" href="?search=<?= urlencode($search) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>&page=<?= $page - 1 ?>" title="◀️ Previous">Previous</a></li>
                                        <?php endif; ?>

                                        <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= ceil($totalRows / $perPage) ?></span></li>

                                        <?php if ($page < ceil($totalRows / $perPage)): ?>
                                            <li class="page-item"><a class="page-link" href="?search=<?= urlencode($search) ?>&sort=<?= $sort ?>&dir=<?= $dir ?>&page=<?= $page + 1 ?>" title="▶️ Next">Next</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title"><?= $crud_action === 'create' ? 'New' : 'Edit' ?> Visitor Log</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="form-grid">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="id" value="<?= (int)($data['id'] ?? 0) ?>">

                                <div class="form-group">
                                    <label>Visitor Name</label>
                                    <input type="text" name="visitor_name" value="<?= sanitize($data['visitor_name'] ?? '') ?>" required>
                                </div>

                                <div class="form-group">
                                    <label>Company / Department</label>
                                    <input type="text" name="company_department" value="<?= sanitize($data['company_department'] ?? '') ?>">
                                </div>

                                <div class="form-group">
                                    <label>Reason for Visit</label>
                                    <textarea name="reason_for_visit"><?= sanitize($data['reason_for_visit'] ?? '') ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Pre-Approved by</label>
                                    <input type="text" name="pre_approved_by" value="<?= sanitize($data['pre_approved_by'] ?? '') ?>">
                                </div>

                                <div class="form-group">
                                    <label>Computer Room Opened By</label>
                                    <input type="text" name="room_opened_by" value="<?= sanitize($data['room_opened_by'] ?? '') ?>">
                                </div>

                                <div class="form-group">
                                    <label>Date & Time IN</label>
                                    <input type="datetime-local" name="date_time_in" value="<?= !empty($data['date_time_in']) ? date('Y-m-d\TH:i', strtotime($data['date_time_in'])) : '' ?>">
                                </div>

                                <div class="form-group">
                                    <label>Date & Time OUT</label>
                                    <input type="datetime-local" name="date_time_out" value="<?= !empty($data['date_time_out']) ? date('Y-m-d\TH:i', strtotime($data['date_time_out'])) : '' ?>">
                                </div>

                                <div class="form-actions">
                                    <button class="btn btn-primary" type="submit">Save</button>
                                    <a href="index.php" class="btn">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($crud_action === 'view'): ?>
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">View Visitor Log</h4>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <?php foreach ($uiColumns as $field => $label): ?>
                                    <tr>
                                        <th style="width: 250px;"><?= sanitize($label) ?></th>
                                        <td>
                                            <?php if ($field === 'date_time_in' || $field === 'date_time_out'): ?>
                                                <?= val_format_datetime($data[$field] ?? null) ?>
                                            <?php else: ?>
                                                <?= sanitize($data[$field] ?? '') ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                            <div class="mt-3">
                                <a href="edit.php?id=<?= (int)$data['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="index.php" class="btn btn-sm">Back</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inline editing for text fields
    document.querySelectorAll('.inline-editable').forEach(cell => {
        const field = cell.dataset.field;
        if (field === 'date_time_in' || field === 'date_time_out') return;

        const display = cell.querySelector('.display-val');
        const input = cell.querySelector('.edit-input');
        if (!display || !input) return;

        display.addEventListener('click', () => {
            display.style.display = 'none';
            input.style.display = 'block';
            input.focus();
        });

        input.addEventListener('blur', () => saveEdit(cell, input, display));
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') input.blur();
        });
    });

    function saveEdit(cell, input, display) {
        const id = cell.closest('tr').dataset.id;
        const field = cell.dataset.field;
        const value = input.value.trim();
        const original = cell.dataset.original;

        if (value === original) {
            display.style.display = 'block';
            input.style.display = 'none';
            return;
        }

        const formData = new FormData();
        formData.append('ajax_inline_edit', '1');
        formData.append('csrf_token', '<?= $csrf_token ?>');
        formData.append('id', id);
        formData.append('field', field);
        formData.append('value', value);

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                display.textContent = value || '—';
                cell.dataset.original = value;
            } else {
                alert(data.message || 'Update failed');
                input.value = original;
            }
            display.style.display = 'block';
            input.style.display = 'none';
        });
    }

    // Timestamp buttons
    document.querySelectorAll('.btn-timestamp').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const cell = this.closest('td');
            const id = this.closest('tr').dataset.id;
            const type = this.dataset.type;

            const formData = new FormData();
            formData.append('action_timestamp', '1');
            formData.append('csrf_token', '<?= $csrf_token ?>');
            formData.append('id', id);
            formData.append('type', type);

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cell.querySelector('.display-val').textContent = data.formatted;
                } else {
                    alert(data.message || 'Update failed');
                }
            });
        });
    });
});
</script>

<?php include ROOT_PATH . 'includes/footer.php'; ?>
