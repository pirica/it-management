<?php
/**
 * Tickets Module - View
 * 
 * Provides a detailed overview of a single support ticket.
 * Displays all metadata, including linked assets, assignees, and 
 * a gallery of attached photos.
 */

require '../../config/config.php';

/**
 * Parses JSON photo filename list
 */
function ticket_parse_photo_filenames($rawValue): array
{
    if (!is_string($rawValue) || trim($rawValue) === '') { return []; }
    $decoded = json_decode($rawValue, true);
    if (!is_array($decoded)) { return []; }
    return array_values(array_filter(array_map('strval', $decoded), static function ($value) { return $value !== ''; }));
}

/**
 * Maps filename to public URL
 */
function ticket_photo_public_path(string $filename): string
{
    return TICKET_UPLOAD_URL . rawurlencode($filename);
}

/**
 * Renders a lookup label badge tinted by ticket_statuses/ticket_priorities hex color.
 */
function ticket_render_lookup_badge(string $label, string $color, string $fallbackLabel = '-'): string
{
    $name = trim($label);
    if ($name === '') {
        $name = $fallbackLabel;
    }

    $hex = trim($color);
    if ($hex === '' || !preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
        $hex = '#9aa4b2';
    }

    return '<span class="badge" style="background-color:' . sanitize($hex) . '33;color:' . sanitize($hex) . ';">' . sanitize($name) . '</span>';
}

// Fetch ticket context
$id = (int)($_GET['id'] ?? 0);
$item = null;
if ($id > 0) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT t.*, tc.name AS category_name, ts.name AS status_name, ts.color AS status_color,
            tp.name AS priority_name, tp.color AS priority_color,
            assigned_user.username AS assigned_to_username, created_user.username AS created_by_username,
            e.name AS asset_name
        FROM tickets t
        LEFT JOIN ticket_categories tc ON tc.id = t.category_id
        LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
        LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
        LEFT JOIN employees assigned_user ON assigned_user.id = t.assigned_to_employee_id
        LEFT JOIN employees created_user ON created_user.id = t.created_by_employee_id
        LEFT JOIN equipment e ON e.id = t.asset_id
        WHERE t.id = ? AND t.company_id = ? LIMIT 1'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $query = mysqli_stmt_get_result($stmt);
        if ($query && mysqli_num_rows($query) === 1) { $item = mysqli_fetch_assoc($query); }
        mysqli_stmt_close($stmt);
    }
}
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
    $crud_title = 'View Ticket';
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
            <h1 title="View ticket details">🔎</h1>
            <div class="card">
                <?php if (!$item): ?>
                    <div class="alert alert-danger">Record not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <!-- RENDER FIELDS DYNAMICALLY -->
                        <?php
                        $fieldLabels = [
                            'id' => 'ID', 'ticket_external_code' => 'External Code', 'title' => 'Title',
                            'description' => 'Description', 'category_id' => 'Category', 'status_id' => 'Status',
                            'priority_id' => 'Priority', 'created_by_employee_id' => 'Created By',
                            'assigned_to_employee_id' => 'Assigned To', 'asset_id' => 'Related Asset',
                            'due_date' => 'Due Date', 'is_archived' => 'Archived', 'tickets_photos' => 'Photos', 'created_at' => 'Created At',
                        ];

                        $fieldDisplayValues = [
                            'category_id' => $item['category_name'] ?? '', 'status_id' => $item['status_name'] ?? '',
                            'priority_id' => $item['priority_name'] ?? '', 'created_by_employee_id' => $item['created_by_username'] ?? '',
                            'assigned_to_employee_id' => $item['assigned_to_username'] ?? '', 'asset_id' => $item['asset_name'] ?? '',
                        ];
                        ?>
                        <?php foreach ($fieldLabels as $field => $label): ?>
                            <?php $value = $item[$field] ?? null; ?>
                            <?php if ($field === 'tickets_photos'): ?>
                                <!-- SPECIAL PHOTO ROW -->
                                <?php $ticketPhotos = ticket_parse_photo_filenames((string)$value); ?>
                                <tr>
                                    <th style="width:220px;"><?php echo sanitize($label); ?></th>
                                    <td>
                                        <?php if (empty($ticketPhotos)): ?><span>—</span><?php else: ?>
                                            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                <?php foreach ($ticketPhotos as $tp): ?>
                                                    <a href="<?php echo sanitize(ticket_photo_public_path($tp)); ?>" target="_blank">
                                                        <img src="<?php echo sanitize(ticket_photo_public_path($tp)); ?>" style="width:96px;height:96px;object-fit:cover;border-radius:6px;">
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php continue; ?>
                            <?php endif; ?>
                            <?php if ($field === 'is_archived'): ?>
                                <tr>
                                    <th style="width:220px;"><?php echo sanitize($label); ?></th>
                                    <td>
                                        <?php if ((int)($item['is_archived'] ?? 0) === 1): ?>
                                            <span class="badge badge-danger">Archived</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php continue; ?>
                            <?php endif; ?>
                            <?php if ($field === 'status_id'): ?>
                                <tr>
                                    <th style="width:220px;"><?php echo sanitize($label); ?></th>
                                    <td><?php echo ticket_render_lookup_badge((string)($item['status_name'] ?? ''), (string)($item['status_color'] ?? ''), 'Open'); ?></td>
                                </tr>
                                <?php continue; ?>
                            <?php endif; ?>
                            <?php if ($field === 'priority_id'): ?>
                                <tr>
                                    <th style="width:220px;"><?php echo sanitize($label); ?></th>
                                    <td><?php echo ticket_render_lookup_badge((string)($item['priority_name'] ?? ''), (string)($item['priority_color'] ?? '')); ?></td>
                                </tr>
                                <?php continue; ?>
                            <?php endif; ?>
                            
                            <?php 
                            if ($field === 'is_archived') { $value = (int)$value === 1 ? 'Yes' : 'No'; }
                            elseif (array_key_exists($field, $fieldDisplayValues) && (string)$fieldDisplayValues[$field] !== '') { $value = $fieldDisplayValues[$field]; }
                            elseif ($value === null || $value === '') { $value = '—'; }
                            ?>
                            <tr><th style="width:220px;"><?php echo sanitize($label); ?></th><td><?php echo sanitize((string)$value); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn">🔙</a>
                    <?php if ($item): ?><a href="edit.php?id=<?php echo (int)$item['id']; ?>" class="btn btn-primary">✏️</a><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
