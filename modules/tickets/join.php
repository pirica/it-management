<?php
/**
 * Public join page for temporary tickets share sessions.
 */
define('ITM_QR_SHARE_PUBLIC', true);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_crud_record_share.php';
itm_crud_record_share_render_join_page($conn, 'tickets');
