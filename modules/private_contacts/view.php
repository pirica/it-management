<?php
require_once '../../config/config.php';

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$employeeId = $_SESSION['employee_id'];

$stmt = $conn->prepare("SELECT * FROM private_contacts WHERE id = ? AND employee_id = ?");
$stmt->bind_param("ii", $id, $employeeId);
$stmt->execute();
$contact = $stmt->get_result()->fetch_assoc();

if (!$contact) {
    header("Location: index.php");
    exit();
}

$pageTitle = "View Contact - " . $contact['first_name'] . ' ' . $contact['last_name'];
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
                <a href="index.php" class="btn btn-outline-secondary">🔙</a>
                <div style="display:flex; gap:8px;">
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary">✏️</a>
                    <form method="POST" action="index_logic.php" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                        <button type="submit" class="btn btn-danger">🗑️</button>
                    </form>
                </div>
            </div>

            <?php if (!empty($_GET['photo_error'])): ?>
                <div class="alert alert-danger" role="alert"><?= sanitize((string)$_GET['photo_error']) ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <?php if ($contact['photo']): ?>
                            <img src="<?= itm_files_serve_url('Private/' . $_SESSION['username'] . '_' . $_SESSION['employee_id'] . '/private_contacts/' . $contact['photo']) ?>" class="rounded-circle border" width="120" height="120" style="object-fit: cover;" onerror="this.onerror=null; this.src='../../images/5x5-pixel.png';">
                        <?php else: ?>
                            <div class="rounded-circle border d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                <i class="fas fa-user text-muted fa-4x"></i>
                            </div>
                        <?php endif; ?>
                        <div class="ml-4">
                            <h1 class="mb-1">
                                <?php echo htmlspecialchars(($contact['name_prefix'] ? $contact['name_prefix'] . ' ' : '') . $contact['first_name'] . ' ' . $contact['last_name'] . ($contact['name_suffix'] ? ' ' . $contact['name_suffix'] : '')); ?>
                                <?php if ($contact['is_favorite']): ?>
                                    <i class="fas fa-star text-warning small align-middle"></i>
                                <?php endif; ?>
                            </h1>
                            <p class="lead text-muted mb-2">
                                <?php echo htmlspecialchars(($contact['organization_title'] ? $contact['organization_title'] . ' at ' : '') . ($contact['organization_name'] ?? '')); ?>
                            </p>
                            <div class="labels">
                                <?php
                                if ($contact['labels']) {
                                    foreach (explode(',', $contact['labels']) as $label) {
                                        echo '<span class="badge badge-secondary p-2 px-3 mr-1">' . htmlspecialchars(strtoupper(trim($label))) . '</span>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header"><h5>Contact Information</h5></div>
                                <div class="card-body">
                                    <div class="info-row mb-3">
                                        <label class="small text-muted d-block"><?php echo htmlspecialchars($contact['email1_label'] ?? 'Email'); ?></label>
                                        <a href="mailto:<?php echo htmlspecialchars($contact['email1_value']); ?>"><?php echo htmlspecialchars($contact['email1_value']); ?></a>
                                    </div>
                                    <div class="info-row mb-3">
                                        <label class="small text-muted d-block"><?php echo htmlspecialchars($contact['phone1_label'] ?? 'Phone'); ?></label>
                                        <span><?php echo htmlspecialchars($contact['phone1_value']); ?></span>
                                    </div>
                                    <?php if ($contact['website1_value']): ?>
                                        <div class="info-row mb-3">
                                            <label class="small text-muted d-block"><?php echo htmlspecialchars($contact['website1_label'] ?: 'Website'); ?></label>
                                            <a href="<?php echo htmlspecialchars($contact['website1_value']); ?>" target="_blank"><?php echo htmlspecialchars($contact['website1_value']); ?></a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header"><h5>Address</h5></div>
                                <div class="card-body">
                                    <label class="small text-muted d-block"><?php echo htmlspecialchars($contact['address1_label'] ?: 'Main'); ?></label>
                                    <p class="mb-0">
                                        <?php echo nl2br(htmlspecialchars($contact['address1_street'] ?? '')); ?><br>
                                        <?php echo htmlspecialchars($contact['address1_city'] ?? ''); ?><?php echo ($contact['address1_region'] ? ', ' . htmlspecialchars($contact['address1_region']) : ''); ?> <?php echo htmlspecialchars($contact['address1_postcode'] ?? ''); ?><br>
                                        <?php echo htmlspecialchars($contact['address1_country'] ?? ''); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header"><h5>About</h5></div>
                                <div class="card-body">
                                    <?php if ($contact['birthday']): ?>
                                        <div class="info-row mb-3">
                                            <label class="small text-muted d-block">Birthday</label>
                                            <span><?php echo date('j F Y', strtotime($contact['birthday'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($contact['event1_value']): ?>
                                        <div class="info-row mb-3">
                                            <label class="small text-muted d-block"><?php echo htmlspecialchars($contact['event1_label'] ?: 'Event'); ?></label>
                                            <span><?php echo date('j F Y', strtotime($contact['event1_value'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($contact['relation1_value']): ?>
                                        <div class="info-row mb-3">
                                            <label class="small text-muted d-block"><?php echo htmlspecialchars($contact['relation1_label'] ?: 'Relation'); ?></label>
                                            <span><?php echo htmlspecialchars($contact['relation1_value']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header"><h5>Notes</h5></div>
                                <div class="card-body">
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($contact['notes'] ?? 'No notes available.')); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <?php if ($contact['nickname'] || $contact['phonetic_first_name']): ?>
                    <div class="card mb-4">
                        <div class="card-header"><h5>Names Detail</h5></div>
                        <div class="card-body">
                            <?php if ($contact['nickname']): ?>
                                <div class="mb-2"><label class="small text-muted mr-2">Nickname:</label> <?php echo htmlspecialchars($contact['nickname']); ?></div>
                            <?php endif; ?>
                            <?php if ($contact['phonetic_first_name']): ?>
                                <div class="mb-2"><label class="small text-muted mr-2">Phonetic First:</label> <?php echo htmlspecialchars($contact['phonetic_first_name']); ?></div>
                            <?php endif; ?>
                            <?php if ($contact['phonetic_middle_name']): ?>
                                <div class="mb-2"><label class="small text-muted mr-2">Phonetic Middle:</label> <?php echo htmlspecialchars($contact['phonetic_middle_name']); ?></div>
                            <?php endif; ?>
                            <?php if ($contact['phonetic_last_name']): ?>
                                <div class="mb-2"><label class="small text-muted mr-2">Phonetic Last:</label> <?php echo htmlspecialchars($contact['phonetic_last_name']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($contact['custom_field1_value']): ?>
                    <div class="card mb-4">
                        <div class="card-header"><h5>Other</h5></div>
                        <div class="card-body">
                            <div><label class="small text-muted mr-2"><?php echo htmlspecialchars($contact['custom_field1_label'] ?: 'Field'); ?>:</label> <?php echo htmlspecialchars($contact['custom_field1_value']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
