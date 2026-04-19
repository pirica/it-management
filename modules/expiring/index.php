<?php
$expiring_action = $expiring_action ?? 'index';

require_once '../../config/config.php';

if (!function_exists('expiring_format_duration')) {
    function expiring_format_duration(DateTimeImmutable $fromDate, DateTimeImmutable $toDate) {
        $invert = ($fromDate > $toDate);
        $diff = $fromDate->diff($toDate);
        $parts = [];

        if ((int)$diff->y > 0) {
            $parts[] = (int)$diff->y . 'y';
        }
        if ((int)$diff->m > 0) {
            $parts[] = (int)$diff->m . 'm';
        }
        if ((int)$diff->d > 0 || empty($parts)) {
            $parts[] = (int)$diff->d . 'd';
        }

        return [
            'text' => implode(' ', $parts),
            'invert' => $invert,
        ];
    }
}

if (!function_exists('expiring_days_left_badge')) {
    function expiring_days_left_badge($daysLeft) {
        if ($daysLeft < 0) {
            return '<span class="badge badge-danger">Expired ' . sanitize(abs((string)$daysLeft)) . 'd ago</span>';
        }
        if ($daysLeft <= 30) {
            return '<span class="badge badge-danger">' . sanitize((string)$daysLeft) . ' days left</span>';
        }
        if ($daysLeft <= 90) {
            return '<span class="badge badge-warning">' . sanitize((string)$daysLeft) . ' days left</span>';
        }

        return '<span class="badge badge-success">' . sanitize((string)$daysLeft) . ' days left</span>';
    }
}

$company_id = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
$uiConfig = function_exists('itm_get_ui_configuration') ? itm_get_ui_configuration($conn, $company_id, isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null) : [];

if (($uiConfig['enable_all_error_reporting'] ?? 0) == 1) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
}

$recordsPerPage = max(5, (int)($uiConfig['records_per_page'] ?? 10));

$certificateRows = [];
$warrantyRows = [];
$fetchError = '';

if ($company_id > 0) {
    $baseSql = "
        SELECT
            e.id,
            e.name,
            e.hostname,
            e.model,
            e.serial_number,
            e.purchase_date,
            e.%s AS expiry_date,
            et.name AS equipment_type
        FROM equipment e
        LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
        WHERE e.company_id = ?
          AND e.%s IS NOT NULL
          AND e.%s <> ''
          AND e.%s <> '0000-00-00'
        ORDER BY e.%s ASC, e.name ASC
    ";

    $datasets = [
        'certificate_expiry' => &$certificateRows,
        'warranty_expiry' => &$warrantyRows,
    ];

    foreach ($datasets as $field => &$targetRows) {
        $sql = sprintf($baseSql, $field, $field, $field, $field, $field);
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $fetchError = 'Unable to prepare expiring equipment query.';
            continue;
        }

        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            $today = new DateTimeImmutable('today');
            while ($row = mysqli_fetch_assoc($result)) {
                $expiryRaw = trim((string)($row['expiry_date'] ?? ''));
                if ($expiryRaw === '') {
                    continue;
                }

                $purchaseRaw = trim((string)($row['purchase_date'] ?? ''));
                $expiryDate = DateTimeImmutable::createFromFormat('Y-m-d', $expiryRaw);
                $purchaseDate = ($purchaseRaw !== '') ? DateTimeImmutable::createFromFormat('Y-m-d', $purchaseRaw) : null;
                if (!$expiryDate) {
                    continue;
                }

                $todayDuration = expiring_format_duration($today, $expiryDate);
                $termDuration = null;
                if ($purchaseDate instanceof DateTimeImmutable) {
                    $termDuration = expiring_format_duration($purchaseDate, $expiryDate);
                }

                $equipmentTitle = trim((string)($row['name'] ?? ''));
                if ($equipmentTitle === '') {
                    $equipmentTitle = trim((string)($row['hostname'] ?? ''));
                }
                if ($equipmentTitle === '') {
                    $equipmentTitle = trim((string)($row['model'] ?? ''));
                }
                if ($equipmentTitle === '') {
                    $equipmentTitle = 'Equipment #' . (int)($row['id'] ?? 0);
                }

                $daysLeft = (int)$today->diff($expiryDate)->format('%r%a');

                $targetRows[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'equipment_title' => $equipmentTitle,
                    'equipment_type' => (string)($row['equipment_type'] ?? ''),
                    'serial_number' => (string)($row['serial_number'] ?? ''),
                    'purchase_date' => $purchaseRaw,
                    'expiry_date' => $expiryRaw,
                    'days_left' => $daysLeft,
                    'countdown_text' => $todayDuration['invert'] ? ('Expired ' . $todayDuration['text'] . ' ago') : ('In ' . $todayDuration['text']),
                    'term_text' => $termDuration ? $termDuration['text'] : '—',
                ];
            }
        }

        mysqli_stmt_close($stmt);
    }
    unset($targetRows);
}

$moduleTitle = itm_sidebar_label_for_module('expiring');
if ($moduleTitle === '') {
    $moduleTitle = '⏳ Expiring Equipment';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($moduleTitle); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="card">
                <h2>⏳ Expiring Equipment Center</h2>
                <p style="margin-top: 4px; opacity: 0.86;">Only equipment with filled expiry dates is listed. Empty / NULL expiry fields are automatically hidden.</p>
            </div>

            <?php if ($company_id <= 0): ?>
                <div class="card"><p>Please select a company to view expiration timelines.</p></div>
            <?php elseif ($fetchError !== ''): ?>
                <div class="card"><p class="alert alert-error"><?php echo sanitize($fetchError); ?></p></div>
            <?php endif; ?>

            <?php
            $sections = [
                [
                    'emoji' => '📜',
                    'title' => 'Certificate Expiry',
                    'rows' => $certificateRows,
                    'empty' => 'No certificate expiration dates were found for this company.',
                ],
                [
                    'emoji' => '🛡️',
                    'title' => 'Warranty Expiry',
                    'rows' => $warrantyRows,
                    'empty' => 'No warranty expiration dates were found for this company.',
                ],
            ];
            ?>

            <?php foreach ($sections as $section): ?>
                <div class="card" style="margin-top: 16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
                        <h3 style="margin:0;"><?php echo sanitize($section['emoji'] . ' ' . $section['title']); ?></h3>
                        <span class="badge badge-info"><?php echo (int)count($section['rows']); ?> records</span>
                    </div>

                    <?php if (empty($section['rows'])): ?>
                        <p style="margin-top: 14px;"><?php echo sanitize($section['empty']); ?></p>
                    <?php else: ?>
                        <div class="table-responsive" style="margin-top: 12px;">
                            <table class="table" data-itm-db-import-endpoint="index.php">
                                <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Type</th>
                                    <th>Serial</th>
                                    <th>Purchase Date</th>
                                    <th>Expiry Date</th>
                                    <th>Coverage Term<br><small>(purchase ➜ expiry)</small></th>
                                    <th>Time Left<br><small>(today ➜ expiry)</small></th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($section['rows'] as $row): ?>
                                    <tr>
                                        <td><a class="btn-link" href="../equipment/view.php?id=<?php echo (int)$row['id']; ?>"><?php echo sanitize($row['equipment_title']); ?></a></td>
                                        <td><?php echo sanitize($row['equipment_type'] !== '' ? $row['equipment_type'] : '—'); ?></td>
                                        <td><?php echo sanitize($row['serial_number'] !== '' ? $row['serial_number'] : '—'); ?></td>
                                        <td><?php echo sanitize($row['purchase_date'] !== '' ? $row['purchase_date'] : '—'); ?></td>
                                        <td><strong><?php echo sanitize($row['expiry_date']); ?></strong></td>
                                        <td><?php echo sanitize($row['term_text']); ?></td>
                                        <td><?php echo sanitize($row['countdown_text']); ?></td>
                                        <td><?php echo expiring_days_left_badge((int)$row['days_left']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($certificateRows) || !empty($warrantyRows)): ?>
                <div class="card" style="margin-top: 16px;">
                    <h4 style="margin-bottom:8px;">✨ Quick Insights</h4>
                    <ul style="margin:0;padding-left:18px;">
                        <li>Showing up to <?php echo (int)$recordsPerPage; ?> records per page setting as a visual threshold reference.</li>
                        <li>Red badge = urgent (expired or under 30 days), yellow = medium urgency, green = healthy timeline.</li>
                        <li>Coverage term uses <code>purchase_date</code> as requested for year/month/day lifecycle context.</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
