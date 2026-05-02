<?php
/**
 * Rack Planner Module
 *
 * Loads a local mirrored copy of rack-planner.patchbox.com sandbox.
 */

require '../../config/config.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itm_content_type = isset($_SERVER['CONTENT_TYPE']) ? strtolower(trim((string) $_SERVER['CONTENT_TYPE'])) : '';
    if (strpos($itm_content_type, 'application/json') === 0) {
        $itm_raw_body = file_get_contents('php://input');
        $itm_payload = json_decode($itm_raw_body, true);

        if (is_array($itm_payload) && isset($itm_payload['action']) && $itm_payload['action'] === 'db_import') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo json_encode([
                'success' => false,
                'message' => 'Database import is not available for Rack Planner.',
            ]);
            exit;
        }
    }
}

$mirrorUrl = BASE_URL . 'modules/rack_planner/mirror/index-local.html';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rack Planner</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .rack-planner-frame {
            width: 100%;
            min-height: 78vh;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
        }
        .rack-planner-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="rack-planner-toolbar">
                <h1>Rack Planner</h1>
                <a class="btn" href="<?php echo sanitize($mirrorUrl); ?>" target="_blank" rel="noopener noreferrer">Open Mirror</a>
            </div>
            <iframe class="rack-planner-frame" src="<?php echo sanitize($mirrorUrl); ?>" title="Rack Planner Mirror"></iframe>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
